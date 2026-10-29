import 'dart:async';

import 'package:flutter/material.dart';

import '../../config/app_config.dart';
import '../../models/dashboard_snapshot.dart';
import '../../models/oil_surrender.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/dashboard_service.dart';
import '../../services/monitoring_service.dart';
import '../../services/notification_permission_service.dart';
import '../../services/alerts_service.dart';
import '../../services/oil_surrender_service.dart';
import '../../services/incentives_service.dart';
import '../../theme/app_theme.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/owner_brand.dart';
import '../../widgets/state_panel.dart';
import 'dashboard_content.dart';
import '../monitoring/monitoring_screen.dart';
import '../alerts/alerts_screen.dart';
import '../alerts/alert_detail_screen.dart';
import '../oil_surrender/oil_surrender_screen.dart';
import '../incentives/incentives_screen.dart';

class HomeScreen extends StatefulWidget {
  final AuthService auth;
  final Future<void> Function() onLogout;
  final MonitoringRepository? monitoringRepository;
  final AlertsRepository? alertsRepository;
  final OilSurrenderRepository? oilSurrenderRepository;
  final IncentivesRepository? incentivesRepository;
  final EvidencePicker? evidencePicker;
  final NotificationPermissionRequester notificationPermissions;

  const HomeScreen({
    super.key,
    required this.auth,
    required this.onLogout,
    this.monitoringRepository,
    this.alertsRepository,
    this.oilSurrenderRepository,
    this.incentivesRepository,
    this.evidencePicker,
    this.notificationPermissions = const MobileNotificationPermissionService(),
  });

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _Destination {
  final String label;
  final IconData icon;

  const _Destination(this.label, this.icon);
}

const _destinations = [
  _Destination('Home', Icons.home_outlined),
  _Destination('Monitoring', Icons.monitor_heart_outlined),
  _Destination('Alerts', Icons.notifications_outlined),
  _Destination('Oil Surrender', Icons.oil_barrel_outlined),
  _Destination('Incentives', Icons.card_giftcard_outlined),
  _Destination('Profile', Icons.person_outline),
];

class _HomeScreenState extends State<HomeScreen> {
  int selectedIndex = 0;
  final List<int> _destinationHistory = [0];
  DashboardSnapshot? snapshot;
  String? initialError;
  String? refreshError;
  bool loading = false;
  bool refreshing = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(widget.notificationPermissions.requestAfterSignIn());
    });
    loadDashboard();
  }

  Future<void> loadDashboard() async {
    if (loading || refreshing) return;
    final hasData = snapshot != null;
    setState(() {
      if (hasData) {
        refreshing = true;
      } else {
        loading = true;
      }
      initialError = null;
      refreshError = null;
    });
    try {
      final value = await DashboardService(widget.auth).load();
      if (mounted) {
        setState(() {
          snapshot = value;
          initialError = null;
          refreshError = null;
        });
      }
    } on ApiException catch (exception) {
      if (mounted && exception.type != ApiErrorType.unauthorized) {
        setState(() {
          if (hasData) {
            refreshError = exception.message;
          } else {
            initialError = exception.message;
          }
        });
      }
    } finally {
      if (mounted) {
        setState(() {
          loading = false;
          refreshing = false;
        });
      }
    }
  }

  void selectDestination(int index) {
    if (index == selectedIndex || index < 0 || index >= _destinations.length) {
      return;
    }
    setState(() {
      selectedIndex = index;
      _destinationHistory.add(index);
    });
  }

  void _selectPreviousDestination() {
    if (_destinationHistory.length <= 1) return;
    setState(() {
      _destinationHistory.removeLast();
      selectedIndex = _destinationHistory.last;
    });
  }

  @override
  Widget build(BuildContext context) => PopScope<void>(
    canPop: _destinationHistory.length == 1,
    onPopInvokedWithResult: (didPop, result) {
      if (!didPop) _selectPreviousDestination();
    },
    child: LayoutBuilder(
      builder: (context, constraints) {
        final wide =
            constraints.maxWidth >= 760 && constraints.maxHeight >= 600;
        final extended = constraints.maxWidth >= 1120;
        return Scaffold(
          appBar: AppBar(
            title: Row(
              children: [
                if (wide) ...[
                  const OwnerBrand(compact: true),
                  const SizedBox(width: 18),
                  const SizedBox(height: 24, child: VerticalDivider()),
                  const SizedBox(width: 18),
                ],
                Flexible(
                  child: Text(
                    _destinations[selectedIndex].label,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: forest,
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                if (constraints.maxWidth >= 620) ...[
                  const SizedBox(width: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: mintSurface,
                      border: Border.all(color: line),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Text(
                      'Owner workspace',
                      style: TextStyle(
                        color: emerald,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
            actions: [
              IconButton(
                tooltip: 'About',
                onPressed: () => showAboutDialog(
                  context: context,
                  applicationName: '$appName Owner App',
                  applicationVersion: appVersion,
                  children: const [
                    Text(
                      'Karinderya owner portal for monitoring, alerts, oil surrender, and read-only rice incentives.',
                    ),
                  ],
                ),
                icon: const Icon(Icons.info_outline),
              ),
              IconButton(
                tooltip: 'Sign out',
                onPressed: widget.onLogout,
                icon: const Icon(Icons.logout),
              ),
            ],
          ),
          drawer: wide
              ? null
              : NavigationDrawer(
                  selectedIndex: selectedIndex,
                  onDestinationSelected: (index) {
                    selectDestination(index);
                    Navigator.of(context).pop();
                  },
                  children: [
                    const Padding(
                      padding: EdgeInsets.fromLTRB(24, 24, 16, 18),
                      child: OwnerBrand(showPortalLabel: true),
                    ),
                    Padding(
                      padding: EdgeInsets.fromLTRB(28, 4, 20, 12),
                      child: _OwnerWorkspaceLabel(
                        fullName: widget.auth.user!.fullName,
                        email: widget.auth.user!.email,
                      ),
                    ),
                    const Divider(),
                    for (final item in _destinations)
                      NavigationDrawerDestination(
                        icon: Icon(item.icon),
                        label: Text(item.label),
                      ),
                  ],
                ),
          body: SafeArea(
            child: Row(
              children: [
                if (wide)
                  NavigationRail(
                    selectedIndex: selectedIndex,
                    onDestinationSelected: selectDestination,
                    extended: extended,
                    minExtendedWidth: 230,
                    labelType: extended
                        ? NavigationRailLabelType.none
                        : NavigationRailLabelType.selected,
                    leading: Padding(
                      padding: const EdgeInsets.fromLTRB(12, 20, 12, 22),
                      child: extended
                          ? _OwnerWorkspaceLabel(
                              fullName: widget.auth.user!.fullName,
                              email: widget.auth.user!.email,
                            )
                          : _WorkspaceMark(
                              fullName: widget.auth.user!.fullName,
                            ),
                    ),
                    destinations: [
                      for (final item in _destinations)
                        NavigationRailDestination(
                          icon: Icon(item.icon),
                          label: Text(item.label),
                        ),
                    ],
                  ),
                if (wide) const VerticalDivider(width: 1),
                Expanded(child: _selectedPage()),
              ],
            ),
          ),
        );
      },
    ),
  );

  Widget _selectedPage() {
    if (selectedIndex == 0) return _dashboardPage();
    if (selectedIndex == 1) {
      return MonitoringScreen(
        auth: widget.auth,
        repository: widget.monitoringRepository,
        alertsRepository: widget.alertsRepository,
      );
    }
    if (selectedIndex == 2) {
      return AlertsScreen(
        auth: widget.auth,
        repository: widget.alertsRepository,
      );
    }
    if (selectedIndex == 3) {
      final establishments = snapshot?.dashboard.establishments ?? const [];
      return OilSurrenderScreen(
        auth: widget.auth,
        repository: widget.oilSurrenderRepository,
        incentivesRepository: widget.incentivesRepository,
        evidencePicker: widget.evidencePicker,
        establishmentCount: establishments.length,
        traps: [
          for (final establishment in establishments)
            for (final trap in establishment.traps)
              SurrenderTrapOption(
                id: trap.id,
                name: trap.name,
                businessName: establishment.businessName,
              ),
        ],
        onSubmissionSuccess: loadDashboard,
      );
    }
    if (selectedIndex == 4) {
      return IncentivesScreen(
        auth: widget.auth,
        repository: widget.incentivesRepository,
      );
    }
    if (selectedIndex == 5) return _profilePage();
    return _profilePage();
  }

  Widget _dashboardPage() {
    if (loading && snapshot == null) {
      return const LoadingWidget(label: 'Loading owner dashboard…');
    }
    if (initialError != null && snapshot == null) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: StatePanel(
          icon: Icons.cloud_off_outlined,
          title: 'Unable to load dashboard',
          message: initialError!,
          actionLabel: 'Retry',
          onAction: loadDashboard,
        ),
      );
    }
    return DashboardContent(
      snapshot: snapshot!,
      refreshing: refreshing,
      refreshError: refreshError,
      onRefresh: loadDashboard,
      onNavigate: selectDestination,
      onOpenAlert: (id) => Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => AlertDetailScreen(
            alertId: id,
            repository: widget.alertsRepository ?? AlertsService(widget.auth),
          ),
        ),
      ),
    );
  }

  Widget _profilePage() {
    final owner = snapshot?.dashboard.user ?? widget.auth.user!;
    final establishments = snapshot?.dashboard.establishments;
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 680),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Icon(Icons.account_circle_outlined, size: 64),
                    const SizedBox(height: 16),
                    Text(
                      owner.fullName,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 8),
                    Text(owner.email, textAlign: TextAlign.center),
                    const Divider(height: 40),
                    Text(
                      establishments == null || establishments.isEmpty
                          ? 'No linked establishment'
                          : establishments
                                .map((item) => item.businessName)
                                .join(', '),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 24),
                    const Text(
                      'AQUASENSE+ Owner App v$appVersion',
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 24),
                    OutlinedButton.icon(
                      onPressed: widget.onLogout,
                      icon: const Icon(Icons.logout),
                      label: const Text('Sign out'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _WorkspaceMark extends StatelessWidget {
  final String fullName;

  const _WorkspaceMark({required this.fullName});

  String get initials {
    final parts = fullName
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();
    if (parts.isEmpty) return 'KO';
    final first = parts.first.substring(0, 1);
    final last = parts.length > 1 ? parts.last.substring(0, 1) : '';
    return '$first$last'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) => Container(
    width: 34,
    height: 34,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: const Color(0xFFF7F8F5),
      border: Border.all(color: line),
      borderRadius: BorderRadius.circular(7),
    ),
    child: Text(
      initials,
      style: const TextStyle(
        color: forest,
        fontSize: 10,
        fontWeight: FontWeight.w800,
        letterSpacing: .5,
      ),
    ),
  );
}

class _OwnerWorkspaceLabel extends StatelessWidget {
  final String fullName;
  final String email;

  const _OwnerWorkspaceLabel({required this.fullName, required this.email});

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 198,
    child: Row(
      children: [
        _WorkspaceMark(fullName: fullName),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                fullName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: ink,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
              Text(
                email,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: muted, fontSize: 9),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

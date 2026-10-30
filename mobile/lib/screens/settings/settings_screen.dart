import 'package:flutter/material.dart';

import '../../services/notification_permission_service.dart';
import '../../theme/app_theme.dart';

class SettingsScreen extends StatefulWidget {
  final AppPermissionManager permissionManager;

  const SettingsScreen({
    super.key,
    this.permissionManager = const MobileNotificationPermissionService(),
  });

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen>
    with WidgetsBindingObserver {
  AppPermissionState? _notificationState;
  AppPermissionState? _overlayState;
  bool _loading = true;
  bool _requestingNotification = false;
  bool _requestingOverlay = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _refresh();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _refresh();
  }

  Future<void> _refresh() async {
    final states = await Future.wait([
      widget.permissionManager.notificationStatus(),
      widget.permissionManager.overlayStatus(),
    ]);
    if (!mounted) return;
    setState(() {
      _notificationState = states[0];
      _overlayState = states[1];
      _loading = false;
    });
  }

  Future<void> _requestNotifications() async {
    setState(() => _requestingNotification = true);
    final state = await widget.permissionManager
        .requestNotificationPermission();
    if (!mounted) return;
    setState(() {
      _notificationState = state;
      _requestingNotification = false;
    });
  }

  Future<void> _requestOverlay() async {
    setState(() => _requestingOverlay = true);
    final state = await widget.permissionManager.requestOverlayPermission();
    if (!mounted) return;
    setState(() {
      _overlayState = state;
      _requestingOverlay = false;
    });
  }

  Future<void> _openSettings() async {
    await widget.permissionManager.openApplicationSettings();
  }

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: _refresh,
    child: ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.all(24),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 760),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'App permissions',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: forest,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Control the Android permissions AQUASENSE+ can use for owner alerts.',
                  style: TextStyle(color: muted),
                ),
                const SizedBox(height: 20),
                if (_loading)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(24),
                      child: Center(child: CircularProgressIndicator()),
                    ),
                  )
                else ...[
                  _PermissionCard(
                    icon: Icons.notifications_active_outlined,
                    title: 'Notifications',
                    description:
                        'Allows AQUASENSE+ to show alert notifications when Android notification delivery is available.',
                    state: _notificationState!,
                    busy: _requestingNotification,
                    enableLabel: 'Enable notifications',
                    onEnable: _requestNotifications,
                    onOpenSettings: _openSettings,
                  ),
                  const SizedBox(height: 14),
                  _PermissionCard(
                    icon: Icons.picture_in_picture_alt_outlined,
                    title: 'Display over other apps',
                    description:
                        'Optional Android access for urgent AQUASENSE+ pop-up surfaces above another app. Enable only if your device permits it.',
                    state: _overlayState!,
                    busy: _requestingOverlay,
                    enableLabel: 'Allow display over other apps',
                    blockedUsesAppSettings: false,
                    onEnable: _requestOverlay,
                    onOpenSettings: _openSettings,
                  ),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: mintSurface,
                      border: Border.all(color: line),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.info_outline, color: emerald),
                        SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Display-over-other-apps access is optional and does not replace notification permission. Android may restrict pop-ups to protect battery life and privacy.',
                            style: TextStyle(color: forest, height: 1.4),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

class _PermissionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String description;
  final AppPermissionState state;
  final bool busy;
  final String enableLabel;
  final bool blockedUsesAppSettings;
  final Future<void> Function() onEnable;
  final Future<void> Function() onOpenSettings;

  const _PermissionCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.state,
    required this.busy,
    required this.enableLabel,
    this.blockedUsesAppSettings = true,
    required this.onEnable,
    required this.onOpenSettings,
  });

  @override
  Widget build(BuildContext context) {
    final enabled = state == AppPermissionState.enabled;
    final unavailable = state == AppPermissionState.notAvailable;
    final blocked = state == AppPermissionState.blocked;
    final statusLabel = switch (state) {
      AppPermissionState.enabled => 'Enabled',
      AppPermissionState.disabled => 'Disabled',
      AppPermissionState.blocked => 'Blocked in Android settings',
      AppPermissionState.notAvailable => 'Android app only',
    };

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: mintSurface,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: emerald),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: forest,
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(description, style: const TextStyle(color: muted)),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Icon(
                  enabled ? Icons.check_circle : Icons.info_outline,
                  size: 18,
                  color: enabled ? emerald : muted,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    statusLabel,
                    style: TextStyle(
                      color: enabled ? emerald : forest,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            if (!enabled && !unavailable) ...[
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: busy
                    ? null
                    : (blocked && blockedUsesAppSettings
                          ? onOpenSettings
                          : onEnable),
                icon: busy
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : Icon(
                        blocked && blockedUsesAppSettings
                            ? Icons.settings_outlined
                            : Icons.lock_open,
                      ),
                label: Text(
                  blocked && blockedUsesAppSettings
                      ? 'Open Android settings'
                      : enableLabel,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

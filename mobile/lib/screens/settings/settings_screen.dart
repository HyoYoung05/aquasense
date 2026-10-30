import 'package:flutter/material.dart';

import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../services/notification_permission_service.dart';
import '../../theme/app_theme.dart';

class SettingsScreen extends StatefulWidget {
  final AuthService auth;
  final AppPermissionManager permissionManager;

  const SettingsScreen({
    super.key,
    required this.auth,
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
  final _emailController = TextEditingController();
  final _emailPasswordController = TextEditingController();
  final _currentPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _passwordConfirmationController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _refresh();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _emailController.dispose();
    _emailPasswordController.dispose();
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _passwordConfirmationController.dispose();
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

  Future<void> _showEmailDialog() async {
    final formKey = GlobalKey<FormState>();
    _emailController.text = widget.auth.user?.email ?? '';
    _emailPasswordController.clear();
    var busy = false;
    String? error;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Change email address'),
          content: Form(
            key: formKey,
            child: SizedBox(
              width: 420,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Use the email address you will enter the next time you sign in.',
                    style: TextStyle(color: muted),
                  ),
                  const SizedBox(height: 18),
                  TextFormField(
                    key: const ValueKey('settings-email'),
                    controller: _emailController,
                    enabled: !busy,
                    keyboardType: TextInputType.emailAddress,
                    autofillHints: const [AutofillHints.email],
                    decoration: const InputDecoration(
                      labelText: 'New email address',
                      prefixIcon: Icon(Icons.email_outlined),
                    ),
                    validator: (value) {
                      final text = value?.trim() ?? '';
                      return RegExp(
                            r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                          ).hasMatch(text)
                          ? null
                          : 'Enter a valid email address.';
                    },
                  ),
                  const SizedBox(height: 14),
                  TextFormField(
                    key: const ValueKey('settings-email-current-password'),
                    controller: _emailPasswordController,
                    enabled: !busy,
                    obscureText: true,
                    autofillHints: const [AutofillHints.password],
                    decoration: const InputDecoration(
                      labelText: 'Current password',
                      prefixIcon: Icon(Icons.lock_outline),
                    ),
                    validator: (value) => (value ?? '').isEmpty
                        ? 'Enter your current password.'
                        : null,
                  ),
                  if (error != null) ...[
                    const SizedBox(height: 12),
                    Text(error!, style: const TextStyle(color: Colors.red)),
                  ],
                ],
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: busy ? null : () => Navigator.pop(dialogContext),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: busy
                  ? null
                  : () async {
                      if (!formKey.currentState!.validate()) return;
                      setDialogState(() {
                        busy = true;
                        error = null;
                      });
                      try {
                        await widget.auth.changeEmail(
                          email: _emailController.text,
                          currentPassword: _emailPasswordController.text,
                        );
                        if (!dialogContext.mounted) return;
                        Navigator.pop(dialogContext);
                        if (mounted) {
                          setState(() {});
                          ScaffoldMessenger.of(this.context).showSnackBar(
                            const SnackBar(
                              content: Text('Email address updated.'),
                            ),
                          );
                        }
                      } on ApiException catch (exception) {
                        if (dialogContext.mounted) {
                          setDialogState(() {
                            busy = false;
                            error = exception.message;
                          });
                        }
                      }
                    },
              child: busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Update email'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _showPasswordDialog() async {
    final formKey = GlobalKey<FormState>();
    _currentPasswordController.clear();
    _newPasswordController.clear();
    _passwordConfirmationController.clear();
    var busy = false;
    String? error;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Change password'),
          content: Form(
            key: formKey,
            child: SizedBox(
              width: 420,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Replace the temporary password provided by the Barangay administrator. You will sign in again afterward.',
                      style: TextStyle(color: muted),
                    ),
                    const SizedBox(height: 18),
                    _PasswordField(
                      fieldKey: const ValueKey('settings-password-current'),
                      controller: _currentPasswordController,
                      label: 'Current or temporary password',
                      enabled: !busy,
                    ),
                    const SizedBox(height: 14),
                    _PasswordField(
                      fieldKey: const ValueKey('settings-password-new'),
                      controller: _newPasswordController,
                      label: 'New password',
                      enabled: !busy,
                      validator: (value) {
                        final text = value ?? '';
                        if (text.length < 12 || text.length > 128) {
                          return 'Use 12 to 128 characters.';
                        }
                        if (!RegExp(r'[a-z]').hasMatch(text) ||
                            !RegExp(r'[A-Z]').hasMatch(text) ||
                            !RegExp(r'[0-9]').hasMatch(text) ||
                            !RegExp(r'[^A-Za-z0-9]').hasMatch(text)) {
                          return 'Include uppercase, lowercase, number, and symbol.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    _PasswordField(
                      fieldKey: const ValueKey(
                        'settings-password-confirmation',
                      ),
                      controller: _passwordConfirmationController,
                      label: 'Confirm new password',
                      enabled: !busy,
                      validator: (value) => value == _newPasswordController.text
                          ? null
                          : 'Password confirmation does not match.',
                    ),
                    if (error != null) ...[
                      const SizedBox(height: 12),
                      Text(error!, style: const TextStyle(color: Colors.red)),
                    ],
                  ],
                ),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: busy ? null : () => Navigator.pop(dialogContext),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: busy
                  ? null
                  : () async {
                      if (!formKey.currentState!.validate()) return;
                      setDialogState(() {
                        busy = true;
                        error = null;
                      });
                      try {
                        await widget.auth.changePassword(
                          currentPassword: _currentPasswordController.text,
                          newPassword: _newPasswordController.text,
                          confirmation: _passwordConfirmationController.text,
                        );
                        if (dialogContext.mounted) {
                          Navigator.pop(dialogContext);
                        }
                      } on ApiException catch (exception) {
                        if (dialogContext.mounted) {
                          setDialogState(() {
                            busy = false;
                            error = exception.message;
                          });
                        }
                      }
                    },
              child: busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Update password'),
            ),
          ],
        ),
      ),
    );
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
                  'Settings',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: forest,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Manage your owner account, password, and application permissions.',
                  style: TextStyle(color: muted),
                ),
                const SizedBox(height: 20),
                const _SettingsSectionHeading(
                  title: 'Account',
                  description: 'Sign-in identity for this Karinderya account.',
                ),
                const SizedBox(height: 10),
                _SettingsActionCard(
                  icon: Icons.alternate_email,
                  title: 'Email address',
                  value: widget.auth.user?.email ?? 'Unavailable',
                  description:
                      'Change the email address used to sign in to the owner app.',
                  actionLabel: 'Change email',
                  onPressed: _showEmailDialog,
                ),
                const SizedBox(height: 22),
                const _SettingsSectionHeading(
                  title: 'Security',
                  description:
                      'Replace an administrator-issued temporary password.',
                ),
                const SizedBox(height: 10),
                _SettingsActionCard(
                  icon: Icons.password_outlined,
                  title: 'Password',
                  value: 'Protected',
                  description:
                      'Use your current or temporary password to create a new private password.',
                  actionLabel: 'Change password',
                  onPressed: _showPasswordDialog,
                ),
                const SizedBox(height: 22),
                const _SettingsSectionHeading(
                  title: 'App permissions',
                  description:
                      'Android access used for owner alert notifications.',
                ),
                const SizedBox(height: 10),
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

class _SettingsSectionHeading extends StatelessWidget {
  final String title;
  final String description;

  const _SettingsSectionHeading({
    required this.title,
    required this.description,
  });

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(
          color: forest,
          fontSize: 18,
          fontWeight: FontWeight.w800,
        ),
      ),
      const SizedBox(height: 3),
      Text(description, style: const TextStyle(color: muted)),
    ],
  );
}

class _SettingsActionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String value;
  final String description;
  final String actionLabel;
  final VoidCallback onPressed;

  const _SettingsActionCard({
    required this.icon,
    required this.title,
    required this.value,
    required this.description,
    required this.actionLabel,
    required this.onPressed,
  });

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Row(
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
                const SizedBox(height: 3),
                Text(
                  value,
                  style: const TextStyle(
                    color: emerald,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 6),
                Text(description, style: const TextStyle(color: muted)),
                const SizedBox(height: 14),
                OutlinedButton(onPressed: onPressed, child: Text(actionLabel)),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _PasswordField extends StatefulWidget {
  final Key fieldKey;
  final TextEditingController controller;
  final String label;
  final bool enabled;
  final String? Function(String?)? validator;

  const _PasswordField({
    required this.fieldKey,
    required this.controller,
    required this.label,
    required this.enabled,
    this.validator,
  });

  @override
  State<_PasswordField> createState() => _PasswordFieldState();
}

class _PasswordFieldState extends State<_PasswordField> {
  bool obscured = true;

  @override
  Widget build(BuildContext context) => TextFormField(
    key: widget.fieldKey,
    controller: widget.controller,
    enabled: widget.enabled,
    obscureText: obscured,
    autofillHints: const [AutofillHints.password],
    decoration: InputDecoration(
      labelText: widget.label,
      prefixIcon: const Icon(Icons.lock_outline),
      suffixIcon: IconButton(
        tooltip: obscured ? 'Show password' : 'Hide password',
        onPressed: () => setState(() => obscured = !obscured),
        icon: Icon(obscured ? Icons.visibility_outlined : Icons.visibility_off),
      ),
    ),
    validator:
        widget.validator ??
        (value) => (value ?? '').isEmpty ? 'Enter your password.' : null,
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

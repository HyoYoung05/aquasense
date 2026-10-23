import 'package:flutter/material.dart';

import '../../config/app_config.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../theme/app_theme.dart';
import '../../utils/validators.dart';
import '../../widgets/owner_brand.dart';

class LoginScreen extends StatefulWidget {
  final AuthService auth;

  const LoginScreen({super.key, required this.auth});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final form = GlobalKey<FormState>();
  final email = TextEditingController();
  final password = TextEditingController();
  bool hidden = true;
  bool busy = false;
  String? error;

  @override
  void dispose() {
    email.dispose();
    password.clear();
    password.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (busy || !form.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.login(email.text, password.text);
    } on ApiException catch (exception) {
      if (mounted) setState(() => error = exception.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: forest,
    body: LayoutBuilder(
      builder: (context, constraints) {
        final landscape = constraints.maxWidth >= 760;
        final story = _StoryPanel(
          compact: !landscape || constraints.maxHeight < 650,
        );
        final formPanel = _FormPanel(
          form: form,
          email: email,
          password: password,
          hidden: hidden,
          busy: busy,
          error: error,
          notice: widget.auth.notice,
          onTogglePassword: () => setState(() => hidden = !hidden),
          onSubmit: submit,
        );

        if (landscape) {
          return SafeArea(
            child: Row(
              children: [
                Expanded(flex: 46, child: story),
                Expanded(flex: 54, child: formPanel),
              ],
            ),
          );
        }

        return SafeArea(
          child: SingleChildScrollView(
            child: Column(children: [story, formPanel]),
          ),
        );
      },
    ),
  );
}

class _StoryPanel extends StatelessWidget {
  final bool compact;

  const _StoryPanel({required this.compact});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    constraints: BoxConstraints(minHeight: compact ? 270 : double.infinity),
    padding: EdgeInsets.fromLTRB(
      compact ? 24 : 44,
      compact ? 24 : 40,
      compact ? 24 : 44,
      compact ? 24 : 36,
    ),
    decoration: const BoxDecoration(
      color: forest,
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF103F3B), Color(0xFF164D44)],
      ),
    ),
    child: Stack(
      children: [
        Positioned(
          right: compact ? -24 : -54,
          bottom: compact ? -70 : 54,
          child: Opacity(
            opacity: compact ? .22 : .38,
            child: Image.asset(
              'assets/images/water-drop-splash.png',
              width: compact ? 220 : 420,
              excludeFromSemantics: true,
            ),
          ),
        ),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const OwnerBrand(light: true, showPortalLabel: true),
            SizedBox(height: compact ? 42 : 88),
            const Text(
              'CLEANER WATER. STRONGER COMMUNITIES.',
              style: TextStyle(
                color: mint,
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.7,
              ),
            ),
            const SizedBox(height: 16),
            Text.rich(
              const TextSpan(
                children: [
                  TextSpan(text: 'A cleaner kitchen.\n'),
                  TextSpan(
                    text: 'A healthier community.',
                    style: TextStyle(color: mint),
                  ),
                ],
              ),
              style: TextStyle(
                color: Colors.white,
                fontSize: compact ? 28 : 42,
                height: 1.12,
                fontWeight: FontWeight.w700,
                letterSpacing: -1.4,
              ),
            ),
            if (!compact) ...[
              const SizedBox(height: 18),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 390),
                child: const Text(
                  'Monitor your grease trap, review alerts, and track oil surrenders from your owner workspace.',
                  style: TextStyle(
                    color: Color(0xFFBDD0C8),
                    height: 1.6,
                    fontSize: 14,
                  ),
                ),
              ),
            ],
            if (!compact) const Spacer(),
            if (!compact)
              const Row(
                children: [
                  Icon(Icons.verified_user_outlined, color: mint, size: 20),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'For registered karinderya owners',
                      style: TextStyle(color: Color(0xFFACC6BD), fontSize: 12),
                    ),
                  ),
                ],
              ),
          ],
        ),
      ],
    ),
  );
}

class _FormPanel extends StatelessWidget {
  final GlobalKey<FormState> form;
  final TextEditingController email;
  final TextEditingController password;
  final bool hidden;
  final bool busy;
  final String? error;
  final String? notice;
  final VoidCallback onTogglePassword;
  final Future<void> Function() onSubmit;

  const _FormPanel({
    required this.form,
    required this.email,
    required this.password,
    required this.hidden,
    required this.busy,
    required this.error,
    required this.notice,
    required this.onTogglePassword,
    required this.onSubmit,
  });

  @override
  Widget build(BuildContext context) => Container(
    color: const Color(0xFFFDFEFD),
    constraints: const BoxConstraints(minHeight: 520),
    padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 32),
    child: Center(
      child: SingleChildScrollView(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: AutofillGroup(
            child: Form(
              key: form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    width: 47,
                    height: 47,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: mintSurface,
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: const Icon(Icons.login_rounded, color: emerald),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Welcome back',
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: forest,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Sign in to access your karinderya owner workspace.',
                    style: TextStyle(color: muted, fontSize: 15),
                  ),
                  const SizedBox(height: 30),
                  TextFormField(
                    controller: email,
                    enabled: !busy,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autocorrect: false,
                    autofillHints: const [AutofillHints.username],
                    validator: validateEmail,
                    decoration: const InputDecoration(
                      labelText: 'Email address',
                      prefixIcon: Icon(Icons.mail_outline),
                    ),
                  ),
                  const SizedBox(height: 18),
                  TextFormField(
                    controller: password,
                    enabled: !busy,
                    obscureText: hidden,
                    enableSuggestions: false,
                    autocorrect: false,
                    autofillHints: const [AutofillHints.password],
                    textInputAction: TextInputAction.done,
                    onFieldSubmitted: (_) => onSubmit(),
                    validator: validatePassword,
                    decoration: InputDecoration(
                      labelText: 'Password',
                      prefixIcon: const Icon(Icons.lock_outline),
                      suffixIcon: IconButton(
                        tooltip: hidden ? 'Show password' : 'Hide password',
                        onPressed: onTogglePassword,
                        icon: Icon(
                          hidden
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                        ),
                      ),
                    ),
                  ),
                  if (notice != null && error == null)
                    _Message(
                      icon: Icons.info_outline,
                      message: notice!,
                      color: forest,
                      background: mintSurface,
                    ),
                  if (error != null)
                    _Message(
                      icon: Icons.error_outline,
                      message: error!,
                      color: critical,
                      background: const Color(0xFFFFF2EF),
                    ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: busy ? null : onSubmit,
                    child: busy
                        ? const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  color: Colors.white,
                                  strokeWidth: 2,
                                ),
                              ),
                              SizedBox(width: 12),
                              Text('Signing in…'),
                            ],
                          )
                        : const Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Sign in'),
                              Icon(Icons.arrow_forward, size: 19),
                            ],
                          ),
                  ),
                  const SizedBox(height: 22),
                  const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.lock_outline, color: muted, size: 18),
                      SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Use the owner account registered by your Barangay. Administrative accounts cannot access this app.',
                          style: TextStyle(
                            color: muted,
                            fontSize: 12,
                            height: 1.55,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 26),
                  const Divider(),
                  const SizedBox(height: 12),
                  const Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Barangay San Antonio',
                          style: TextStyle(color: muted, fontSize: 11),
                        ),
                      ),
                      Expanded(
                        child: Text(
                          'Owner App v$appVersion',
                          textAlign: TextAlign.right,
                          style: TextStyle(color: muted, fontSize: 11),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class _Message extends StatelessWidget {
  final IconData icon;
  final String message;
  final Color color;
  final Color background;

  const _Message({
    required this.icon,
    required this.message,
    required this.color,
    required this.background,
  });

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 16),
    child: Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(message, style: TextStyle(color: color)),
            ),
          ],
        ),
      ),
    ),
  );
}

import 'package:flutter/material.dart';
import 'config/app_config.dart';
import 'screens/home/home_screen.dart';
import 'screens/login/login_screen.dart';
import 'services/auth_service.dart';
import 'theme/app_theme.dart';
import 'widgets/loading_widget.dart';

class ConfigurationErrorApp extends StatelessWidget {
  final String message;
  const ConfigurationErrorApp({super.key, required this.message});

  ThemeData _theme(Brightness brightness) {
    final scheme = ColorScheme.fromSeed(
      seedColor: emerald,
      brightness: brightness,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: scheme.surface,
    );
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: '$appName Owner',
    debugShowCheckedModeBanner: false,
    theme: _theme(Brightness.light),
    darkTheme: _theme(Brightness.dark),
    themeMode: ThemeMode.system,
    home: Builder(
      builder: (context) => Scaffold(
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 520),
                child: Card(
                  child: Padding(
                    padding: const EdgeInsets.all(28),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          appName,
                          style: Theme.of(context).textTheme.headlineSmall
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 24),
                        Icon(
                          Icons.settings_ethernet_rounded,
                          size: 52,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                        const SizedBox(height: 16),
                        Text(
                          'Server configuration error',
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(height: 12),
                        Text(message, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        const Text(
                          'Rebuild the application with a valid HTTPS API_BASE_URL.',
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 24),
                        Text(
                          'Owner App v$appVersion',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class OwnerApp extends StatefulWidget {
  final AuthService auth;
  const OwnerApp({super.key, required this.auth});
  @override
  State<OwnerApp> createState() => _OwnerAppState();
}

class _OwnerAppState extends State<OwnerApp> {
  final messenger = GlobalKey<ScaffoldMessengerState>();
  @override
  void initState() {
    super.initState();
    widget.auth.restore();
  }

  Future<void> signOut() async {
    final warning = await widget.auth.logout();
    if (mounted && warning != null) {
      messenger.currentState?.showSnackBar(SnackBar(content: Text(warning)));
    }
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: '$appName Owner',
    debugShowCheckedModeBanner: false,
    scaffoldMessengerKey: messenger,
    theme: ownerTheme(),
    // Replacing the root on auth changes leaves no protected route in the back stack.
    home: ListenableBuilder(
      listenable: widget.auth,
      builder: (context, _) {
        switch (widget.auth.state) {
          case AuthState.checking:
            return const Scaffold(
              body: LoadingWidget(label: 'Restoring your session…'),
            );
          case AuthState.signedOut:
            return LoginScreen(auth: widget.auth);
          case AuthState.signedIn:
            return HomeScreen(
              key: ValueKey(widget.auth.user!.id),
              auth: widget.auth,
              onLogout: signOut,
            );
          case AuthState.unavailable:
            return Scaffold(
              body: SafeArea(
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.cloud_off_outlined, size: 48),
                        const SizedBox(height: 16),
                        Text(
                          widget.auth.error ??
                              'Unable to connect to the AQUASENSE+ server.',
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 16),
                        FilledButton(
                          onPressed: widget.auth.restore,
                          child: const Text('Retry'),
                        ),
                        TextButton(
                          onPressed: signOut,
                          child: const Text('Sign out'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
        }
      },
    ),
  );
}

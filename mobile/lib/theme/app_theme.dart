import 'package:flutter/material.dart';

// Shared with the administrative website in assets/css/style.css.
const ink = Color(0xFF203B38);
const muted = Color(0xFF71807D);
const emerald = Color(0xFF197B66);
const forest = Color(0xFF103F3B);
const mint = Color(0xFFACD8BA);
const mintSurface = Color(0xFFE9F3EE);
const canvas = Color(0xFFF5F7F5);
const line = Color(0xFFE4EAE6);
const warning = Color(0xFF805600);
const critical = Color(0xFF9B2C22);

const _radius = 12.0;

ThemeData ownerTheme() {
  const scheme = ColorScheme.light(
    primary: emerald,
    onPrimary: Colors.white,
    primaryContainer: mintSurface,
    onPrimaryContainer: forest,
    secondary: forest,
    onSecondary: Colors.white,
    surface: Colors.white,
    onSurface: ink,
    error: critical,
    outline: line,
    outlineVariant: line,
  );

  final base = ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: canvas,
    fontFamily: 'Segoe UI',
  );

  return base.copyWith(
    textTheme: base.textTheme
        .apply(bodyColor: ink, displayColor: ink)
        .copyWith(
          headlineLarge: base.textTheme.headlineLarge?.copyWith(
            fontWeight: FontWeight.w700,
            letterSpacing: -1.2,
          ),
          headlineMedium: base.textTheme.headlineMedium?.copyWith(
            fontWeight: FontWeight.w700,
            letterSpacing: -.8,
          ),
          headlineSmall: base.textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.w700,
            letterSpacing: -.5,
          ),
          titleLarge: base.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w600,
            letterSpacing: -.25,
          ),
          bodySmall: base.textTheme.bodySmall?.copyWith(color: muted),
        ),
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: ink,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      toolbarHeight: 72,
      shape: Border(bottom: BorderSide(color: line)),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(_radius),
        side: const BorderSide(color: line),
      ),
    ),
    dividerTheme: const DividerThemeData(color: line, thickness: 1),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      labelStyle: const TextStyle(color: muted),
      prefixIconColor: muted,
      suffixIconColor: muted,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: emerald, width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: critical),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: emerald,
        foregroundColor: Colors.white,
        minimumSize: const Size(48, 48),
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 13),
        textStyle: const TextStyle(fontWeight: FontWeight.w700),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: emerald,
        minimumSize: const Size(44, 44),
        side: const BorderSide(color: line),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: emerald,
        textStyle: const TextStyle(fontWeight: FontWeight.w700),
      ),
    ),
    chipTheme: base.chipTheme.copyWith(
      backgroundColor: mintSurface,
      selectedColor: emerald,
      side: const BorderSide(color: line),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
      labelStyle: const TextStyle(fontWeight: FontWeight.w600),
    ),
    navigationRailTheme: const NavigationRailThemeData(
      backgroundColor: Colors.white,
      indicatorColor: mintSurface,
      selectedIconTheme: IconThemeData(color: emerald),
      selectedLabelTextStyle: TextStyle(
        color: emerald,
        fontWeight: FontWeight.w700,
      ),
      unselectedIconTheme: IconThemeData(color: muted),
      unselectedLabelTextStyle: TextStyle(color: muted),
    ),
    navigationDrawerTheme: const NavigationDrawerThemeData(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      indicatorColor: mintSurface,
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(color: emerald),
    snackBarTheme: const SnackBarThemeData(
      backgroundColor: forest,
      contentTextStyle: TextStyle(color: Colors.white),
      behavior: SnackBarBehavior.floating,
    ),
  );
}

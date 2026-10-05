import 'package:flutter/material.dart';

const forest = Color(0xFF103F3B);
const emerald = Color(0xFF197B66);
const mint = Color(0xFFACD8BA);

ThemeData ownerTheme() => ThemeData(
  useMaterial3: true,
  colorScheme: ColorScheme.fromSeed(
    seedColor: emerald,
    primary: emerald,
    secondary: forest,
  ),
  scaffoldBackgroundColor: const Color(0xFFF2F7F3),
  appBarTheme: const AppBarTheme(
    backgroundColor: Color(0xFFF2F7F3),
    foregroundColor: forest,
    centerTitle: false,
  ),
  inputDecorationTheme: InputDecorationTheme(
    filled: true,
    fillColor: Colors.white,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
    contentPadding: const EdgeInsets.all(18),
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      minimumSize: const Size(48, 54),
      textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    ),
  ),
);

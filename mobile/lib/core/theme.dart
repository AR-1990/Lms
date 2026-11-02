import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// The same palette as the web portal (public/css/dashboard.css): orange brand on a gray canvas, Figtree type.
class AppColors {
  static const brand50 = Color(0xFFFFF7ED);
  static const brand100 = Color(0xFFFFEDD5);
  static const brand400 = Color(0xFFFB923C);
  static const brand500 = Color(0xFFF97316);
  static const brand600 = Color(0xFFEA580C);
  static const brand700 = Color(0xFFC2410C);

  static const gray50 = Color(0xFFF9FAFB);
  static const gray100 = Color(0xFFF3F4F6);
  static const gray200 = Color(0xFFE5E7EB);
  static const gray300 = Color(0xFFD1D5DB);
  static const gray400 = Color(0xFF9CA3AF);
  static const gray500 = Color(0xFF6B7280);
  static const gray600 = Color(0xFF4B5563);
  static const gray700 = Color(0xFF374151);
  static const gray800 = Color(0xFF1F2937);
  static const gray900 = Color(0xFF111827);

  static const green50 = Color(0xFFF0FDF4);
  static const green600 = Color(0xFF16A34A);
  static const amber50 = Color(0xFFFFFBEB);
  static const amber700 = Color(0xFFB45309);
  static const red50 = Color(0xFFFEF2F2);
  static const red600 = Color(0xFFDC2626);
  static const sky50 = Color(0xFFF0F9FF);
  static const sky600 = Color(0xFF0284C7);
  static const violet50 = Color(0xFFF5F3FF);
  static const violet600 = Color(0xFF7C3AED);
}

ThemeData buildTheme() {
  final base = ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(seedColor: AppColors.brand500, primary: AppColors.brand600, surface: Colors.white),
    scaffoldBackgroundColor: AppColors.gray100,
  );
  final text = GoogleFonts.figtreeTextTheme(base.textTheme).apply(bodyColor: AppColors.gray800, displayColor: AppColors.gray900);
  final rounded = RoundedRectangleBorder(borderRadius: BorderRadius.circular(8));

  return base.copyWith(
    textTheme: text,
    appBarTheme: AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: AppColors.gray900,
      elevation: 0,
      scrolledUnderElevation: 1,
      surfaceTintColor: Colors.white,
      titleTextStyle: text.titleMedium?.copyWith(fontWeight: FontWeight.w700),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      margin: EdgeInsets.zero,
      surfaceTintColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: AppColors.gray200)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(backgroundColor: AppColors.brand500, foregroundColor: Colors.white, minimumSize: const Size(64, 48), shape: rounded, textStyle: const TextStyle(fontWeight: FontWeight.w600)),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(foregroundColor: AppColors.gray700, minimumSize: const Size(64, 44), shape: rounded, side: const BorderSide(color: AppColors.gray300)),
    ),
    textButtonTheme: TextButtonThemeData(style: TextButton.styleFrom(foregroundColor: AppColors.brand600)),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppColors.gray300)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppColors.gray300)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AppColors.brand500, width: 2)),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      indicatorColor: AppColors.brand100,
      surfaceTintColor: Colors.white,
      iconTheme: WidgetStateProperty.resolveWith((states) => IconThemeData(color: states.contains(WidgetState.selected) ? AppColors.brand700 : AppColors.gray500)),
      labelTextStyle: WidgetStateProperty.resolveWith((states) => TextStyle(fontSize: 12, fontWeight: states.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500, color: states.contains(WidgetState.selected) ? AppColors.brand700 : AppColors.gray600)),
    ),
    chipTheme: base.chipTheme.copyWith(backgroundColor: AppColors.gray100, selectedColor: AppColors.brand100, side: BorderSide.none, shape: rounded),
    dividerTheme: const DividerThemeData(color: AppColors.gray200, space: 1),
    snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating, backgroundColor: AppColors.gray900),
  );
}

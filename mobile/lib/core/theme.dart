import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Haske tokens, the same as the web: Kano indigo accent, LCD-green token panel.
class HaskeColors extends ThemeExtension<HaskeColors> {
  const HaskeColors({
    required this.bg,
    required this.surface,
    required this.surface2,
    required this.ink,
    required this.muted,
    required this.line,
    required this.accent,
    required this.accentInk,
    required this.accentSoft,
    required this.lcd,
    required this.lcdInk,
    required this.lcdDim,
    required this.good,
    required this.goodSoft,
    required this.warn,
    required this.warnSoft,
    required this.bad,
    required this.badSoft,
    required this.navy,
    required this.navy2,
    required this.orangeSoft,
    required this.blueSoft,
    required this.heading,
  });

  final Color bg, surface, surface2, ink, muted, line, accent, accentInk, accentSoft;
  final Color lcd, lcdInk, lcdDim, good, goodSoft, warn, warnSoft, bad, badSoft;
  final Color navy, navy2, orangeSoft, blueSoft, heading;

  static const light = HaskeColors(
    bg: Color(0xFFF4F7FB), surface: Color(0xFFFFFFFF), surface2: Color(0xFFF6F9FC), ink: Color(0xFF172B45),
    muted: Color(0xFF718096), line: Color(0xFFE4EAF1), accent: Color(0xFF07945F), accentInk: Color(0xFFFFFFFF),
    accentSoft: Color(0xFFE7F7EF), lcd: Color(0xFFE7F7EF), lcdInk: Color(0xFF0B4D33), lcdDim: Color(0xFF3F7A60),
    good: Color(0xFF07814F), goodSoft: Color(0xFFE5F8EE), warn: Color(0xFF8C5A05), warnSoft: Color(0xFFFFF5DF),
    bad: Color(0xFFB82E2E), badSoft: Color(0xFFFFEBEB),
    navy: Color(0xFF102B4E), navy2: Color(0xFF1D527D), orangeSoft: Color(0xFFFFF2D9), blueSoft: Color(0xFFE8EFFF), heading: Color(0xFF142F50),
  );

  static const dark = HaskeColors(
    bg: Color(0xFF0A1626), surface: Color(0xFF102036), surface2: Color(0xFF142841), ink: Color(0xFFE3EBF5),
    muted: Color(0xFF93A3B8), line: Color(0xFF22374F), accent: Color(0xFF16B979), accentInk: Color(0xFF04150D),
    accentSoft: Color(0xFF0F3527), lcd: Color(0xFF0F3527), lcdInk: Color(0xFFBFF2D9), lcdDim: Color(0xFF6FB896),
    good: Color(0xFF3DD598), goodSoft: Color(0xFF0F3527), warn: Color(0xFFF1B54E), warnSoft: Color(0xFF3A2D12),
    bad: Color(0xFFFF8E84), badSoft: Color(0xFF40201E),
    navy: Color(0xFF081525), navy2: Color(0xFF12385A), orangeSoft: Color(0xFF3A2B12), blueSoft: Color(0xFF18284A), heading: Color(0xFFF1F5FA),
  );

  @override
  HaskeColors copyWith() => this;

  @override
  HaskeColors lerp(ThemeExtension<HaskeColors>? other, double t) => t < .5 ? this : (other as HaskeColors? ?? this);
}

extension HaskeTheme on BuildContext {
  HaskeColors get hc => Theme.of(this).extension<HaskeColors>()!;
}

TextStyle display(double size, {FontWeight weight = FontWeight.w700, Color? color}) =>
    GoogleFonts.inter(fontSize: size, fontWeight: weight == FontWeight.w700 ? FontWeight.w800 : weight, color: color, letterSpacing: -size * .025, height: 1.15);

TextStyle mono(double size, {FontWeight weight = FontWeight.w500, Color? color, double spacing = .5}) =>
    GoogleFonts.inter(fontSize: size, fontWeight: weight == FontWeight.w500 ? FontWeight.w600 : weight, color: color, letterSpacing: spacing, fontFeatures: const [FontFeature.tabularFigures()]);

ThemeData buildTheme(Brightness b) {
  final c = b == Brightness.dark ? HaskeColors.dark : HaskeColors.light;
  final base = ThemeData(brightness: b, useMaterial3: true);
  final text = GoogleFonts.interTextTheme(base.textTheme).apply(bodyColor: c.ink, displayColor: c.ink);
  final radius = BorderRadius.circular(10);

  return base.copyWith(
    scaffoldBackgroundColor: c.bg,
    colorScheme: ColorScheme.fromSeed(seedColor: c.accent, brightness: b, primary: c.accent, onPrimary: c.accentInk, surface: c.surface, error: c.bad),
    textTheme: text,
    extensions: [c],
    appBarTheme: AppBarTheme(backgroundColor: c.bg, foregroundColor: c.ink, elevation: 0, scrolledUnderElevation: 0, titleTextStyle: display(22, color: c.ink)),
    dividerColor: c.line,
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: c.surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      hintStyle: TextStyle(color: c.muted),
      border: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: c.line)),
      enabledBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: c.line)),
      focusedBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: c.accent, width: 2)),
      errorBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: c.bad)),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: c.accent,
        foregroundColor: c.accentInk,
        disabledBackgroundColor: c.accent.withValues(alpha: .4),
        disabledForegroundColor: c.accentInk,
        minimumSize: const Size.fromHeight(52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: c.ink,
        backgroundColor: c.surface,
        side: BorderSide(color: c.line),
        minimumSize: const Size.fromHeight(50),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: c.surface,
      indicatorColor: c.accentSoft,
      labelTextStyle: WidgetStatePropertyAll(TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: c.ink)),
    ),
    snackBarTheme: const SnackBarThemeData(backgroundColor: Color(0xFF102B4E), contentTextStyle: TextStyle(color: Colors.white), behavior: SnackBarBehavior.floating),
  );
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/format.dart';
import '../core/scope.dart';
import '../core/theme.dart';

class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key});
  final String status;

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    final (bg, fg) = switch (status) {
      'success' => (c.goodSoft, c.good),
      'pending' => (c.warnSoft, c.warn),
      _ => (c.badSoft, c.bad),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(99)),
      child: Text(context.t(status), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: fg)),
    );
  }
}

class Card2 extends StatelessWidget {
  const Card2({super.key, required this.child, this.padding = const EdgeInsets.all(16), this.color});
  final Widget child;
  final EdgeInsets padding;
  final Color? color;

  @override
  Widget build(BuildContext context) => Container(
        padding: padding,
        decoration: BoxDecoration(
          color: color ?? context.hc.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: context.hc.line),
        ),
        child: child,
      );
}

class Banner2 extends StatelessWidget {
  const Banner2({super.key, required this.tone, required this.child});
  final String tone; // pending | refunded | info
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    final (bg, fg) = switch (tone) {
      'pending' => (c.warnSoft, c.warn),
      'refunded' => (c.badSoft, c.bad),
      _ => (c.accentSoft, c.accent),
    };
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(14)),
      child: DefaultTextStyle.merge(style: TextStyle(color: fg, fontSize: 13.5, height: 1.4), child: child),
    );
  }
}

class FieldLabel extends StatelessWidget {
  const FieldLabel(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Text(text, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: context.hc.muted)),
      );
}

class Pill extends StatelessWidget {
  const Pill({super.key, required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    return Material(
      color: selected ? c.accentSoft : c.surface,
      shape: StadiumBorder(side: BorderSide(color: selected ? c.accent : c.line)),
      child: InkWell(
        customBorder: const StadiumBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
          child: Text(label, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: selected ? c.accent : c.ink)),
        ),
      ),
    );
  }
}

class Segmented extends StatelessWidget {
  const Segmented({super.key, required this.options, required this.value, required this.onChanged});
  final Map<String, String> options;
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    return Container(
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(12), border: Border.all(color: c.line)),
      child: Row(
        children: options.entries.map((e) {
          final on = e.key == value;
          return Expanded(
            child: GestureDetector(
              onTap: () => onChanged(e.key),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                padding: const EdgeInsets.symmetric(vertical: 9),
                decoration: BoxDecoration(
                  color: on ? c.surface : Colors.transparent,
                  borderRadius: BorderRadius.circular(9),
                  boxShadow: on ? [const BoxShadow(color: Color(0x1F000000), blurRadius: 3, offset: Offset(0, 1))] : null,
                ),
                child: Text(e.value, textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w600, color: on ? c.ink : c.muted)),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

/// The prepaid meter LCD: the one bold moment in the app.
class LcdToken extends StatelessWidget {
  const LcdToken({super.key, required this.token, this.short, this.units, this.meter});
  final String token;
  final String? short, units, meter;

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    final small = mono(10, weight: FontWeight.w800, color: c.lcdDim, spacing: 1.4);
    final strong = mono(11, weight: FontWeight.w700, color: c.lcdInk, spacing: .3);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
          decoration: BoxDecoration(
            color: c.lcd,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Column(children: [
            Row(mainAxisAlignment: MainAxisAlignment.center, children: [Text('${short != null ? '$short · ' : ''}ELECTRICITY TOKEN', style: small)]),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: FittedBox(child: SelectableText(tokenGroups(token), style: mono(23, weight: FontWeight.w800, color: c.lcdInk, spacing: 2))),
            ),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text.rich(TextSpan(text: 'Units ', style: small, children: [TextSpan(text: units ?? '—', style: strong)])),
              if (meter != null) Text.rich(TextSpan(text: 'Meter ', style: small, children: [TextSpan(text: meter, style: strong)])),
            ]),
          ]),
        ),
        const SizedBox(height: 10),
        FilledButton.icon(
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(44)),
          onPressed: () {
            Clipboard.setData(ClipboardData(text: token));
            HapticFeedback.lightImpact();
            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t('copied'))));
          },
          icon: const Icon(Icons.copy_rounded, size: 18),
          label: Text(context.t('copyToken')),
        ),
      ],
    );
  }
}

class DetailRows extends StatelessWidget {
  const DetailRows(this.rows, {super.key});
  final List<(String, String, bool)> rows; // label, value, monospace

  @override
  Widget build(BuildContext context) => Column(
        children: [
          for (final (k, v, isMono) in rows)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 10),
              decoration: BoxDecoration(border: Border(bottom: BorderSide(color: context.hc.line))),
              child: Row(children: [
                Text(k, style: TextStyle(color: context.hc.muted, fontSize: 13.5)),
                const SizedBox(width: 12),
                Expanded(child: Text(v, textAlign: TextAlign.right, style: isMono ? mono(12.5) : const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500))),
              ]),
            ),
        ],
      );
}

class ErrorText extends StatelessWidget {
  const ErrorText(this.text, {super.key});
  final String? text;

  @override
  Widget build(BuildContext context) => text == null || text!.isEmpty
      ? const SizedBox.shrink()
      : Padding(padding: const EdgeInsets.only(top: 6), child: Text(text!, style: TextStyle(color: context.hc.bad, fontSize: 13, fontWeight: FontWeight.w500)));
}

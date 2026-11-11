import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/scope.dart';
import '../core/theme.dart';

/// Bottom sheet keypad. [onPin] returns an error message to show, or null to close.
Future<void> showPinSheet(BuildContext context, {required String label, required Future<String?> Function(String pin) onPin}) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: context.hc.surface,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
    builder: (_) => _PinSheet(label: label, onPin: onPin),
  );
}

/// Collects a PIN and closes, returning it (null if dismissed). [error] shows a
/// message from the previous attempt, e.g. "Wrong PIN. 2 tries left."
Future<String?> askPin(BuildContext context, {required String label, String? error}) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    backgroundColor: context.hc.surface,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
    builder: (_) => _PinSheet(label: label, initialError: error, returnsPin: true),
  );
}

class _PinSheet extends StatefulWidget {
  const _PinSheet({required this.label, this.onPin, this.initialError, this.returnsPin = false});
  final String label;
  final Future<String?> Function(String)? onPin;
  final String? initialError;
  final bool returnsPin;

  @override
  State<_PinSheet> createState() => _PinSheetState();
}

class _PinSheetState extends State<_PinSheet> with SingleTickerProviderStateMixin {
  String pin = '';
  late String? error = widget.initialError;
  bool busy = false;
  late final AnimationController shake = AnimationController(vsync: this, duration: const Duration(milliseconds: 350));

  @override
  void dispose() {
    shake.dispose();
    super.dispose();
  }

  Future<void> press(String k) async {
    if (busy) return;
    HapticFeedback.selectionClick();
    setState(() {
      if (k == '⌫') {
        if (pin.isNotEmpty) pin = pin.substring(0, pin.length - 1);
      } else if (pin.length < 4) {
        pin += k;
      }
    });
    if (pin.length == 4) {
      if (widget.returnsPin) {
        Navigator.of(context).pop(pin);
        return;
      }
      setState(() => busy = true);
      final err = await widget.onPin!(pin);
      if (!mounted) return;
      if (err == null) {
        Navigator.of(context).pop();
      } else {
        HapticFeedback.heavyImpact();
        shake.forward(from: 0);
        setState(() {
          error = err;
          pin = '';
          busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(18, 20, 18, 18),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Text(context.t('pin'), style: display(20)),
          const SizedBox(height: 4),
          Text(widget.label, style: TextStyle(color: c.muted, fontSize: 13)),
          const SizedBox(height: 18),
          AnimatedBuilder(
            animation: shake,
            builder: (_, child) => Transform.translate(offset: Offset(math.sin(shake.value * math.pi * 4) * 8 * (1 - shake.value), 0), child: child),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(4, (i) => Container(
                    margin: const EdgeInsets.symmetric(horizontal: 7),
                    width: 14,
                    height: 14,
                    decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: c.accent, width: 2), color: i < pin.length ? c.accent : null),
                  )),
            ),
          ),
          SizedBox(
            height: 34,
            child: Center(
              child: busy
                  ? SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: c.accent))
                  : Text(error ?? '', textAlign: TextAlign.center, style: TextStyle(color: c.bad, fontSize: 13, fontWeight: FontWeight.w500)),
            ),
          ),
          GridView.count(
            crossAxisCount: 3,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 8,
            crossAxisSpacing: 8,
            childAspectRatio: 2.1,
            children: [
              for (final k in ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'])
                k.isEmpty
                    ? const SizedBox()
                    : Material(
                        color: c.surface2,
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () => press(k),
                          child: Center(
                            child: k == '⌫'
                                ? Icon(Icons.backspace_outlined, color: c.ink, semanticLabel: 'Delete')
                                : Text(k, style: display(22, weight: FontWeight.w600, color: c.ink)),
                          ),
                        ),
                      ),
            ],
          ),
        ]),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:uuid/uuid.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../core/scope.dart';
import '../core/theme.dart';
import '../widgets/pin_sheet.dart';
import 'receipt.dart';

/// PIN → processing screen → receipt. One idempotency key per attempt, so a
/// retry after a dropped connection returns the same purchase instead of a second one.
Future<void> runPurchase(BuildContext context, {required Map<String, dynamic> spec, required int total, required String label, required String target}) async {
  final app = AppScope.read(context);
  final me = app.me!;
  if (total > me.balance) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${app.t('notEnough')} ${naira(total - me.balance)} ${app.t('more')}')));
    return;
  }
  final key = const Uuid().v4();
  final nav = Navigator.of(context);
  final messenger = ScaffoldMessenger.of(context);
  String? pinError;

  while (true) {
    if (!context.mounted) return;
    final pin = await askPin(context, label: '${app.t('buy')} ${naira(total)} → $label', error: pinError);
    if (pin == null) return;
    nav.push(PageRouteBuilder(pageBuilder: (_, _, _) => _Processing(total: total, label: label, target: target)));
    try {
      final tx = await app.purchase({...spec, 'pin': pin, 'idempotency_key': key});
      nav.pushReplacement(MaterialPageRoute(builder: (_) => ReceiptScreen(reference: tx.reference, initial: tx, fromPurchase: true)));
      return;
    } on ApiException catch (e) {
      nav.pop();
      if (e.fields.containsKey('pin') && !(e.fields['pin']!.contains('locked') || e.fields['pin']!.contains('Too many'))) {
        pinError = e.fields['pin'];
        continue;
      }
      messenger.showSnackBar(SnackBar(content: Text(e.message)));
      return;
    }
  }
}

class _Processing extends StatefulWidget {
  const _Processing({required this.total, required this.label, required this.target});
  final int total;
  final String label, target;

  @override
  State<_Processing> createState() => _ProcessingState();
}

class _ProcessingState extends State<_Processing> {
  int step = 0;

  @override
  void initState() {
    super.initState();
    Future.delayed(const Duration(milliseconds: 700), () => mounted ? setState(() => step = 1) : null);
    Future.delayed(const Duration(milliseconds: 1600), () => mounted ? setState(() => step = 2) : null);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    Widget line(String text, bool done, bool now) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 3),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.circle, size: 8, color: done ? c.good : now ? c.ink : c.muted),
            const SizedBox(width: 8),
            Text(text, style: TextStyle(color: done ? c.good : now ? c.ink : c.muted, fontWeight: now ? FontWeight.w600 : FontWeight.w400)),
          ]),
        );
    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: c.surface,
        body: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            SizedBox(width: 54, height: 54, child: CircularProgressIndicator(strokeWidth: 4, color: c.accent, backgroundColor: c.accentSoft)),
            const SizedBox(height: 20),
            Text('${context.t('processing')} ${naira(widget.total)}', style: display(21)),
            const SizedBox(height: 6),
            Text('${widget.label} · ${widget.target}', style: TextStyle(color: c.muted)),
            const SizedBox(height: 16),
            Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
              line(context.t('debited'), true, false),
              line(context.t('sent'), step >= 1, step == 0),
              line(context.t('waiting'), false, step >= 2),
            ]),
          ]),
        ),
      ),
    );
  }
}

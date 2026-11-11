import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../core/models.dart';
import '../core/scope.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

class ReceiptScreen extends StatefulWidget {
  const ReceiptScreen({super.key, required this.reference, this.initial, this.fromPurchase = false});
  final String reference;
  final Tx? initial;
  final bool fromPurchase;

  @override
  State<ReceiptScreen> createState() => _ReceiptScreenState();
}

class _ReceiptScreenState extends State<ReceiptScreen> {
  Tx? tx;
  Timer? poll;
  bool checking = false;

  @override
  void initState() {
    super.initState();
    tx = widget.initial;
    if (tx == null) WidgetsBinding.instance.addPostFrameCallback((_) => load());
    // The server requery job resolves pending purchases; we just watch.
    poll = Timer.periodic(const Duration(seconds: 5), (_) {
      if (tx?.status == 'pending') load();
    });
  }

  @override
  void dispose() {
    poll?.cancel();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final t = await context.app.transaction(widget.reference);
      if (mounted) {
        if (tx?.status == 'pending' && t.isFinal) context.app.refreshQuietly();
        setState(() => tx = t);
      }
    } catch (_) {}
  }

  Future<void> checkNow() async {
    setState(() => checking = true);
    try {
      final t = await context.app.requery(widget.reference);
      if (mounted) setState(() => tx = t);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => checking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final c = context.hc;
    final x = tx;
    return Scaffold(
      appBar: AppBar(title: Text(app.t('receipt'))),
      body: x == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(padding: const EdgeInsets.fromLTRB(18, 4, 18, 32), children: [
              Card2(
                padding: const EdgeInsets.all(18),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Center(child: StatusChip(x.status)),
                  const SizedBox(height: 6),
                  Text(naira(x.total), textAlign: TextAlign.center, style: display(34)),
                  Text('${x.serviceName} · ${x.meterType != null ? app.t(x.meterType!) : x.plan ?? app.t('airtime')}', textAlign: TextAlign.center, style: TextStyle(color: c.muted, fontSize: 13.5)),
                  const SizedBox(height: 16),
                  if (x.status == 'success' && x.token != null) LcdToken(token: x.token!, short: x.shortName, units: x.units, meter: x.target),
                  if (x.status == 'success' && x.token == null && x.category == 'electricity') Banner2(tone: 'info', child: Text(app.t('postpaidMsg'))),
                  if (x.status == 'pending')
                    Banner2(
                      tone: 'pending',
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(app.t('pendingMsg')),
                        const SizedBox(height: 10),
                        OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(minimumSize: const Size(0, 40)),
                          onPressed: checking ? null : checkNow,
                          icon: const Icon(Icons.refresh_rounded, size: 18),
                          label: Text(app.t('checkNow')),
                        ),
                      ]),
                    ),
                  if (x.status == 'refunded') Banner2(tone: 'refunded', child: Text('${naira(x.total)} ${app.t('refundMsg')} ${x.failureReason ?? ''}')),
                  const SizedBox(height: 12),
                  DetailRows([
                    (app.t('ref'), x.reference, true),
                    if (x.category == 'electricity') ...[(app.t('meterNo'), x.target, true), (app.t('customer'), x.customerName ?? '—', false)] else (app.t('phone'), x.target, true),
                    (app.t('amount'), naira(x.amount), false),
                    (app.t('fee'), naira(x.fee), false),
                    if (x.agentCommission > 0) ('Commission', '+${naira(x.agentCommission)}', false),
                    (app.t('route'), x.provider == 'baxi' ? 'Baxi (backup route)' : 'VTpass', false),
                    if (x.requeryCount > 0) (app.t('attempts'), '${x.requeryCount}', false),
                    (app.t('when'), when(x.createdAt, app.lang), false),
                  ]),
                  const SizedBox(height: 16),
                  OutlinedButton(onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst), child: Text(app.t('done'))),
                ]),
              ),
            ]),
    );
  }
}

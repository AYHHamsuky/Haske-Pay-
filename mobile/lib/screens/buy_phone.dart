import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/format.dart';
import '../core/models.dart';
import '../core/scope.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import 'buy_electricity.dart' show Summary;
import 'purchase_flow.dart';

/// Airtime and data share one screen; [category] picks which.
class BuyPhoneScreen extends StatefulWidget {
  const BuyPhoneScreen({super.key, required this.category});
  final String category;

  @override
  State<BuyPhoneScreen> createState() => _BuyPhoneScreenState();
}

class _BuyPhoneScreenState extends State<BuyPhoneScreen> {
  final phone = TextEditingController();
  final amount = TextEditingController();
  String? net;
  bool manual = false;
  List<Plan> plans = [];
  Plan? plan;
  bool loadingPlans = false;

  bool get isAir => widget.category == 'airtime';
  Service? serviceFor(String? n) => (context.app.services[widget.category] ?? []).where((s) => s.network == n).firstOrNull;

  @override
  void initState() {
    super.initState();
    phone.text = '';
  }

  void onPhone(String v) {
    if (!manual || normPhone(v).length < 4) {
      final n = detectNetwork(v);
      manual = false;
      if (n != net) setNet(n, byUser: false);
    }
    setState(() {});
  }

  Future<void> setNet(String? n, {bool byUser = true}) async {
    setState(() { net = n; manual = byUser; plan = null; plans = []; });
    final s = serviceFor(n);
    if (isAir || s == null) return;
    setState(() => loadingPlans = true);
    try {
      final p = await context.app.plans(s.code);
      if (mounted && net == n) setState(() => plans = p);
    } catch (_) {
    } finally {
      if (mounted) setState(() => loadingPlans = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final c = context.hc;
    final s = serviceFor(net);
    final amt = isAir ? toKobo(amount.text) : plan?.amount ?? 0;
    final ok = phoneOk(phone.text) && s != null && s.active && (isAir ? amt >= s.min && amt <= s.max : plan != null);
    final nets = app.services['airtime'] ?? [];

    return Scaffold(
      appBar: AppBar(title: Text(app.t(isAir ? 'airtime' : 'data'))),
      body: ListView(padding: const EdgeInsets.fromLTRB(18, 4, 18, 32), children: [
        FieldLabel(app.t('phone')),
        TextField(
          controller: phone,
          keyboardType: TextInputType.phone,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(13)],
          style: mono(18),
          decoration: const InputDecoration(hintText: '0803 123 4567'),
          onChanged: onPhone,
        ),
        if (normPhone(phone.text).length >= 4 && net == null) Padding(padding: const EdgeInsets.only(top: 6), child: Text(app.t('netNotFound'), style: TextStyle(fontSize: 12.5, color: c.muted))),
        const SizedBox(height: 16),
        FieldLabel('${app.t('network')}${net != null && !manual ? (app.lang == 'ha' ? ' · an gane' : ' · detected') : ''}'),
        Wrap(spacing: 6, runSpacing: 6, children: [for (final n in nets) Pill(label: n.name, selected: net == n.network, onTap: () => setNet(n.network))]),
        const SizedBox(height: 16),
        if (isAir) ...[
          FieldLabel('${app.t('amount')} (₦)'),
          TextField(
            controller: amount,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(5)],
            style: mono(18),
            decoration: const InputDecoration(hintText: '₦50 – ₦50,000'),
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: 8),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final a in [100, 200, 500, 1000, 2000]) Pill(label: naira(a * 100), selected: amt == a * 100, onTap: () => setState(() => amount.text = '$a')),
          ]),
        ] else ...[
          FieldLabel(app.t('plan')),
          if (net == null)
            Text(app.t('pickNet'), style: TextStyle(color: c.muted, fontSize: 13))
          else if (loadingPlans)
            const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()))
          else
            for (final p in plans)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Material(
                  color: plan?.code == p.code ? c.accentSoft : c.surface,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: BorderSide(color: plan?.code == p.code ? c.accent : c.line)),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => setState(() => plan = p),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      child: Row(children: [Expanded(child: Text(p.name, style: const TextStyle(fontWeight: FontWeight.w600))), Text(naira(p.amount), style: const TextStyle(fontWeight: FontWeight.w700))]),
                    ),
                  ),
                ),
              ),
        ],
        if (amt > 0) ...[const SizedBox(height: 14), Summary(rows: [(app.t('fee'), naira(0))], total: (app.t('total'), naira(amt)))],
        const SizedBox(height: 20),
        FilledButton(
          onPressed: !ok
              ? null
              : () => runPurchase(context,
                  spec: {'service_code': s.code, 'target': normPhone(phone.text), if (isAir) 'amount': amt, if (!isAir) 'variation_code': plan!.code},
                  total: amt,
                  label: isAir ? '${s.name} ${app.t('airtime').toLowerCase()}' : '${s.name} ${plan!.name}',
                  target: normPhone(phone.text)),
          child: Text(app.t('buy')),
        ),
      ]),
    );
  }
}

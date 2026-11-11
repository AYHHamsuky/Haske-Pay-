import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../core/models.dart';
import '../core/scope.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import 'purchase_flow.dart';

class BuyElectricityScreen extends StatefulWidget {
  const BuyElectricityScreen({super.key});

  @override
  State<BuyElectricityScreen> createState() => _BuyElectricityScreenState();
}

class _BuyElectricityScreenState extends State<BuyElectricityScreen> {
  String? disco;
  String type = 'prepaid';
  final meter = TextEditingController();
  final amount = TextEditingController();
  Customer? customer;
  bool verifying = false;
  String? meterErr;

  List<Service> get discos => context.app.services['electricity'] ?? [];
  Service? get service => discos.where((d) => d.code == disco).firstOrNull;
  bool get meterOk => RegExp(r'^(\d{11}|\d{13})$').hasMatch(meter.text);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    disco ??= discos.any((d) => d.code == 'kaduna-electric') ? 'kaduna-electric' : discos.firstOrNull?.code;
  }

  void reset() => setState(() { customer = null; meterErr = null; });

  Future<void> verify() async {
    if (!meterOk) {
      setState(() => meterErr = context.app.lang == 'ha' ? 'Lambar mita tana da lamba 11 ko 13.' : 'Meter numbers have 11 or 13 digits.');
      return;
    }
    final asked = '$disco|$type|${meter.text}';
    setState(() { verifying = true; meterErr = null; });
    try {
      final r = await context.app.verifyMeter(disco!, meter.text, type);
      if (asked == '$disco|$type|${meter.text}') setState(() => customer = r);
    } on ApiException catch (e) {
      setState(() => meterErr = e.message);
    } finally {
      if (mounted) setState(() => verifying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final c = context.hc;
    final s = service;
    final amt = toKobo(amount.text);
    final fee = app.me!.isAgent ? 0 : (s?.fee ?? 0);
    final amtOk = s != null && amt >= s.min && amt <= s.max;
    final saved = app.meters.where((m) => m.serviceCode == disco && m.type == type).toList();

    return Scaffold(
      appBar: AppBar(title: Text(app.t('elec'))),
      body: ListView(padding: const EdgeInsets.fromLTRB(18, 4, 18, 32), children: [
        FieldLabel(app.t('disco')),
        DropdownButtonFormField<String>(
          initialValue: disco,
          isExpanded: true,
          items: [for (final d in discos) DropdownMenuItem(value: d.code, enabled: d.active, child: Text('${d.name} (${d.shortName})${d.active ? '' : ' · unavailable'}'))],
          onChanged: (v) { disco = v; reset(); },
        ),
        const SizedBox(height: 16),
        FieldLabel(app.t('meterType')),
        Segmented(options: {'prepaid': app.t('prepaid'), 'postpaid': app.t('postpaid')}, value: type, onChanged: (v) { type = v; reset(); }),
        if (saved.isNotEmpty) ...[
          const SizedBox(height: 16),
          FieldLabel(app.t('saved')),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final m in saved)
              Pill(label: '${m.name.split(' ').first} · ${m.meter.substring(m.meter.length - 4)}', selected: meter.text == m.meter, onTap: () { meter.text = m.meter; reset(); verify(); }),
          ]),
        ],
        const SizedBox(height: 16),
        FieldLabel(app.t('meterNo')),
        TextField(
          controller: meter,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(13)],
          style: mono(18),
          decoration: InputDecoration(hintText: '11 or 13 digits', errorText: meterErr),
          onChanged: (_) => reset(),
          onSubmitted: (_) => verify(),
        ),
        const SizedBox(height: 10),
        if (customer != null)
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: c.goodSoft, border: Border.all(color: c.good), borderRadius: BorderRadius.circular(14)),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(customer!.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                  Text([customer!.address, if (customer!.band != null) 'Band ${customer!.band}'].whereType<String>().join(' · '), style: TextStyle(color: c.muted, fontSize: 12.5)),
                ]),
              ),
              Text('✓ ${app.t('verified')}', style: TextStyle(color: c.good, fontWeight: FontWeight.w700, fontSize: 12)),
            ]),
          )
        else ...[
          OutlinedButton(onPressed: !meterOk || verifying ? null : verify, child: Text(verifying ? app.t('verifying') : app.t('verify'))),
          if (apiUrl.contains('10.0.2.2') || apiUrl.contains('localhost')) Padding(padding: const EdgeInsets.only(top: 6), child: Text(app.t('tryMeter'), style: TextStyle(fontSize: 12, color: c.muted))),
        ],
        const SizedBox(height: 16),
        FieldLabel('${app.t('amount')} (₦)'),
        TextField(
          controller: amount,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
          style: mono(18),
          decoration: InputDecoration(hintText: 'Min ${naira(s?.min ?? 100000)}'),
          onChanged: (_) => setState(() {}),
        ),
        const SizedBox(height: 8),
        Wrap(spacing: 6, runSpacing: 6, children: [
          for (final a in [2000, 5000, 10000, 20000]) Pill(label: naira(a * 100), selected: amt == a * 100, onTap: () => setState(() => amount.text = '$a')),
        ]),
        if (amt > 0) ...[
          const SizedBox(height: 14),
          Summary(rows: [(app.t('tokenValue'), naira(amt)), (app.t('fee'), fee == 0 && app.me!.isAgent ? app.t('free') : naira(fee))], total: (app.t('total'), naira(amt + fee))),
          if (!amtOk && s != null) ErrorText('${naira(s.min)} – ${naira(s.max)}'),
        ],
        const SizedBox(height: 20),
        FilledButton(
          onPressed: customer == null || !amtOk
              ? null
              : () => runPurchase(context,
                  spec: {'service_code': disco, 'target': meter.text, 'meter_type': type, 'amount': amt},
                  total: amt + fee,
                  label: s.name,
                  target: meter.text),
          child: Text(app.t('cont')),
        ),
      ]),
    );
  }
}

class Summary extends StatelessWidget {
  const Summary({super.key, required this.rows, required this.total});
  final List<(String, String)> rows;
  final (String, String) total;

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    const tab = TextStyle(fontFeatures: [FontFeature.tabularFigures()], fontSize: 13.5);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: c.line)),
      child: Column(children: [
        for (final (k, v) in rows)
          Padding(padding: const EdgeInsets.symmetric(vertical: 2), child: Row(children: [Text(k, style: tab.copyWith(color: c.muted)), const Spacer(), Text(v, style: tab)])),
        Divider(color: c.line, height: 14),
        Row(children: [Text(total.$1, style: tab.copyWith(fontWeight: FontWeight.w700)), const Spacer(), Text(total.$2, style: tab.copyWith(fontWeight: FontWeight.w700))]),
      ]),
    );
  }
}

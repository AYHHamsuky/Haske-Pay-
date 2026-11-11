import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/format.dart';
import '../core/scope.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import '../widgets/tx_tile.dart';
import 'buy_electricity.dart';
import 'buy_phone.dart';
import 'receipt.dart';

class HomeTab extends StatelessWidget {
  const HomeTab({super.key, required this.onSeeAll});
  final VoidCallback onSeeAll;

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final me = app.me!;
    final c = context.hc;
    final recent = app.history.take(5).toList();
    final todayComm = app.history
        .where((x) => !x.isFunding && x.status == 'success' && x.createdAt != null && DateUtils.isSameDay(x.createdAt!.toLocal(), DateTime.now()))
        .fold<int>(0, (s, x) => s + x.agentCommission);

    void open(Widget w) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => w));

    return RefreshIndicator(
      onRefresh: app.refresh,
      child: ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [c.navy, c.navy2], begin: Alignment.topLeft, end: Alignment.bottomRight),
            borderRadius: BorderRadius.circular(14),
          ),
          child: DefaultTextStyle.merge(
            style: const TextStyle(color: Colors.white),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(app.t('balance').toUpperCase(), style: const TextStyle(fontSize: 11.5, letterSpacing: 1, fontWeight: FontWeight.w600, color: Color(0xFFC5D6E7))),
                    const SizedBox(height: 4),
                    Text.rich(TextSpan(text: '₦${naira(me.balance - me.balance % 100).substring(1)}', style: display(31, weight: FontWeight.w800, color: Colors.white), children: [
                      TextSpan(text: '.${(me.balance % 100).toString().padLeft(2, '0')}', style: display(19, color: Colors.white70)),
                    ])),
                  ]),
                ),
                FilledButton(
                  style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: const Color(0xFF12395D), minimumSize: const Size(0, 38), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)), textStyle: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800)),
                  onPressed: () => _fundSheet(context),
                  child: Text('+ ${app.t('addMoney')}'),
                ),
              ]),
              const SizedBox(height: 14),
              const Divider(color: Color(0x33FFFFFF), height: 1),
              const SizedBox(height: 12),
              me.va == null
                  ? Text(app.lang == 'ha' ? 'Ana shirya lambar asusunka…' : 'Your account number is being set up…', style: const TextStyle(fontSize: 12.5))
                  : Row(children: [
                      Expanded(child: Text.rich(TextSpan(text: '${me.va!.bank} · ', style: const TextStyle(fontSize: 12.5), children: [TextSpan(text: me.va!.number, style: mono(12.5, color: Colors.white))]))),
                      GestureDetector(
                        onTap: () {
                          Clipboard.setData(ClipboardData(text: me.va!.number));
                          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(app.t('copied'))));
                        },
                        child: Text(app.t('copy'), style: const TextStyle(fontSize: 12.5, decoration: TextDecoration.underline)),
                      ),
                    ]),
            ]),
          ),
        ),
        if (me.isAgent) ...[
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: c.goodSoft, borderRadius: BorderRadius.circular(14)),
            child: Row(children: [
              Expanded(child: Text('${app.t('todayComm')}\n${app.t('feeWaived')}', style: TextStyle(color: c.good, fontSize: 13))),
              Text(naira(todayComm), style: display(20, color: c.good)),
            ]),
          ),
        ],
        const SizedBox(height: 16),
        Row(children: [
          _Svc(icon: Icons.bolt_rounded, tint: c.accentSoft, iconColor: c.accent, label: app.t('elec'), onTap: () => open(const BuyElectricityScreen())),
          const SizedBox(width: 8),
          _Svc(icon: Icons.phone_android_rounded, tint: c.orangeSoft, iconColor: const Color(0xFFC27510), label: app.t('airtime'), onTap: () => open(const BuyPhoneScreen(category: 'airtime'))),
          const SizedBox(width: 8),
          _Svc(icon: Icons.wifi_rounded, tint: c.blueSoft, iconColor: const Color(0xFF3A5BC7), label: app.t('data'), onTap: () => open(const BuyPhoneScreen(category: 'data'))),
          const SizedBox(width: 8),
          _Svc(icon: Icons.tv_rounded, tint: c.surface2, iconColor: c.muted, label: app.t('tv'), soon: app.t('soon')),
        ]),
        const SizedBox(height: 20),
        Row(children: [
          Text(app.t('recent'), style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: c.heading)),
          const Spacer(),
          TextButton(onPressed: onSeeAll, child: Text(app.t('seeAll'))),
        ]),
        if (recent.isEmpty)
          Padding(padding: const EdgeInsets.all(28), child: Text(app.t('noTx'), textAlign: TextAlign.center, style: TextStyle(color: c.muted)))
        else
          for (final x in recent) TxTile(x, onTap: x.isFunding ? null : () => open(ReceiptScreen(reference: x.reference, initial: x))),
      ]),
    );
  }

  void _fundSheet(BuildContext context) {
    final app = AppScope.read(context);
    final va = app.me!.va;
    showModalBottomSheet(
      context: context,
      backgroundColor: context.hc.surface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Text(app.t('addMoney'), style: display(20)),
            const SizedBox(height: 14),
            if (va != null)
              Banner2(
                tone: 'info',
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(app.t('vaTitle'), style: const TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 6),
                  SelectableText(va.number, style: mono(22, weight: FontWeight.w600, color: ctx.hc.accent, spacing: 2)),
                  Text('${va.bank} · ${va.name}'),
                ]),
              ),
            const SizedBox(height: 10),
            Text(app.t('vaSub'), style: TextStyle(color: ctx.hc.muted, fontSize: 13.5)),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: va == null
                  ? null
                  : () {
                      Clipboard.setData(ClipboardData(text: va.number));
                      Navigator.pop(ctx);
                      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(app.t('copied'))));
                    },
              icon: const Icon(Icons.copy_rounded, size: 18),
              label: Text(app.lang == 'ha' ? 'Kwafi lamba' : 'Copy number'),
            ),
          ]),
        ),
      ),
    );
  }
}

class _Svc extends StatelessWidget {
  const _Svc({required this.icon, required this.label, required this.tint, required this.iconColor, this.onTap, this.soon});
  final IconData icon;
  final Color tint, iconColor;
  final String label;
  final VoidCallback? onTap;
  final String? soon;

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    return Expanded(
      child: Opacity(
        opacity: onTap == null ? .55 : 1,
        child: Stack(clipBehavior: Clip.none, children: [
          Material(
            color: c.surface,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14), side: BorderSide(color: c.line)),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: onTap,
              child: SizedBox(
                height: 92,
                width: double.infinity,
                child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(color: tint, borderRadius: BorderRadius.circular(12)),
                    child: Icon(icon, color: iconColor, size: 22),
                  ),
                  const SizedBox(height: 8),
                  Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                ]),
              ),
            ),
          ),
          if (soon != null)
            Positioned(
              top: -7,
              right: -2,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                decoration: BoxDecoration(color: c.surface, borderRadius: BorderRadius.circular(6), border: Border.all(color: c.line)),
                child: Text(soon!.toUpperCase(), style: TextStyle(fontSize: 9, color: c.muted, letterSpacing: .5)),
              ),
            ),
        ]),
      ),
    );
  }
}

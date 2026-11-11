import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../core/scope.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import '../widgets/pin_sheet.dart';

class ProfileTab extends StatelessWidget {
  const ProfileTab({super.key});

  Future<void> changePin(BuildContext context) async {
    final app = AppScope.read(context);
    String? current;
    await showPinSheet(context, label: app.lang == 'ha' ? 'PIN na yanzu' : 'Current PIN', onPin: (p) async {
      current = p;
      return null;
    });
    if (current == null || !context.mounted) return;
    String? first;
    await showPinSheet(context, label: app.t('setPin'), onPin: (p) async {
      first = p;
      return null;
    });
    if (first == null || !context.mounted) return;
    await showPinSheet(context, label: app.t('confirmPin'), onPin: (p) async {
      if (p != first) return app.t('pinMismatch');
      try {
        await app.setPin(p, current: current);
        return null;
      } on ApiException catch (e) {
        return e.message;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final me = app.me!;
    final c = context.hc;
    final left = (me.dailyLimit - me.spentToday).clamp(0, me.dailyLimit);

    Widget row(String title, String sub, {Widget? trailing}) => Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(border: Border(bottom: BorderSide(color: c.line))),
          child: Row(children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
                const SizedBox(height: 2),
                Text(sub, style: TextStyle(color: c.muted, fontSize: 12.5)),
              ]),
            ),
            ?trailing,
          ]),
        );

    return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
      Text(me.name ?? '', style: display(24)),
      Text([me.phone, if (me.agentBusiness != null) '${me.agentBusiness} · ${me.agentTier}'].join(' · '), style: mono(12.5, color: c.muted)),
      const SizedBox(height: 8),
      row(app.t('language'), 'English · Hausa',
          trailing: SizedBox(width: 120, child: Segmented(options: const {'en': 'EN', 'ha': 'HA'}, value: app.lang, onChanged: app.setLang))),
      row(app.t('limit'), '${naira(left)} ${app.t('left')} · ${naira(me.dailyLimit)}'),
      row('PIN', app.lang == 'ha' ? 'Ana buƙatar sa a kowane siyayya' : 'Needed for every purchase',
          trailing: TextButton(onPressed: () => changePin(context), child: Text(app.lang == 'ha' ? 'Canza' : 'Change'))),
      const SizedBox(height: 16),
      Text(app.t('saved'), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5)),
      const SizedBox(height: 4),
      if (app.meters.isEmpty) Text(app.lang == 'ha' ? 'Babu' : 'Meters you buy for are saved here.', style: TextStyle(color: c.muted, fontSize: 13)),
      for (final m in app.meters)
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 6),
          child: Row(children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(m.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                Text('${m.meter} · ${app.t(m.type)}', style: mono(11.5, color: c.muted)),
              ]),
            ),
            TextButton(onPressed: () => app.removeMeter(m.id), child: Text(app.t('remove'), style: TextStyle(color: c.bad))),
          ]),
        ),
      const SizedBox(height: 20),
      Card2(child: Text('${app.t('support')}: ${me.support}', style: const TextStyle(fontSize: 13.5))),
      const SizedBox(height: 16),
      OutlinedButton(onPressed: app.logout, child: Text(app.t('logout'))),
    ]);
  }
}

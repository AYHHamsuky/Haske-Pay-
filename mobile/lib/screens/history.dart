import 'package:flutter/material.dart';

import '../core/scope.dart';
import '../core/theme.dart';
import '../widgets/common.dart';
import '../widgets/tx_tile.dart';
import 'receipt.dart';

class HistoryTab extends StatefulWidget {
  const HistoryTab({super.key});

  @override
  State<HistoryTab> createState() => _HistoryTabState();
}

class _HistoryTabState extends State<HistoryTab> {
  String filter = 'all';

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final list = app.history.where((x) => filter == 'all' || (filter == 'bills' ? !x.isFunding : x.isFunding)).toList();
    return RefreshIndicator(
      onRefresh: app.refresh,
      child: ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
        Wrap(spacing: 6, children: [for (final f in ['all', 'bills', 'funding']) Pill(label: app.t(f), selected: filter == f, onTap: () => setState(() => filter = f))]),
        const SizedBox(height: 10),
        if (list.isEmpty)
          Padding(padding: const EdgeInsets.all(32), child: Text(app.t('noTx'), textAlign: TextAlign.center, style: TextStyle(color: context.hc.muted)))
        else
          for (final x in list)
            TxTile(x, onTap: x.isFunding ? null : () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => ReceiptScreen(reference: x.reference, initial: x)))),
      ]),
    );
  }
}

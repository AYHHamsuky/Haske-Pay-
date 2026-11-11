import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/models.dart';
import '../core/scope.dart';
import '../core/theme.dart';
import 'common.dart';

class TxTile extends StatelessWidget {
  const TxTile(this.tx, {super.key, this.onTap});
  final Tx tx;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final c = context.hc;
    final lang = context.app.lang;
    final title = tx.isFunding
        ? (lang == 'ha' ? 'Shigar kuɗi' : 'Wallet top-up')
        : tx.category == 'electricity'
            ? tx.serviceName ?? ''
            : tx.category == 'data'
                ? '${tx.serviceName} ${tx.plan ?? 'data'}'
                : '${tx.serviceName} ${context.t('airtime').toLowerCase()}';
    final badge = tx.isFunding ? '+₦' : (tx.category == 'electricity' ? tx.shortName : tx.serviceName) ?? '';
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
        child: Row(children: [
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: tx.isFunding ? c.goodSoft : tx.category == 'airtime' ? c.orangeSoft : tx.category == 'data' ? c.blueSoft : c.accentSoft,
              borderRadius: BorderRadius.circular(11),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 4),
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(badge.length > 6 ? badge.substring(0, 6) : badge, maxLines: 1, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: tx.isFunding ? c.good : c.accent)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
              Text('${tx.target} · ${when(tx.createdAt, lang)}', style: TextStyle(fontSize: 12, color: c.muted)),
            ]),
          ),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('${tx.isFunding ? '+' : tx.status == 'refunded' ? '' : '−'}${naira(tx.total)}',
                style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, fontFeatures: const [FontFeature.tabularFigures()], color: tx.isFunding ? c.good : null)),
            if (!tx.isFunding) ...[const SizedBox(height: 3), StatusChip(tx.status)],
          ]),
        ]),
      ),
    );
  }
}

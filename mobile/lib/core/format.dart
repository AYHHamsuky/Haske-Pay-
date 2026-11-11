import 'package:intl/intl.dart';

final _whole = NumberFormat('#,##0', 'en_NG');
final _kobo = NumberFormat('#,##0.00', 'en_NG');

/// Server amounts are kobo.
String naira(int kobo) => '₦${kobo % 100 == 0 ? _whole.format(kobo ~/ 100) : _kobo.format(kobo / 100)}';

int toKobo(String naira) => ((double.tryParse(naira.replaceAll(RegExp(r'[^\d.]'), '')) ?? 0) * 100).round();

String tokenGroups(String t) => RegExp(r'.{1,4}').allMatches(t).map((m) => m.group(0)).join(' ');

String when(DateTime? d, String lang) {
  if (d == null) return '';
  final local = d.toLocal();
  final now = DateTime.now();
  final hm = DateFormat('HH:mm').format(local);
  bool same(DateTime a, DateTime b) => a.year == b.year && a.month == b.month && a.day == b.day;
  if (same(local, now)) return '${lang == 'ha' ? 'Yau' : 'Today'} $hm';
  if (same(local, now.subtract(const Duration(days: 1)))) return '${lang == 'ha' ? 'Jiya' : 'Yesterday'} $hm';
  return '${DateFormat('d MMM').format(local)} $hm';
}

const _prefixes = {
  'mtn': ['0803', '0806', '0703', '0706', '0813', '0816', '0810', '0814', '0903', '0906', '0913', '0916', '0704', '0707'],
  'airtel': ['0802', '0808', '0708', '0812', '0701', '0902', '0901', '0904', '0907', '0912', '0911'],
  'glo': ['0805', '0807', '0705', '0815', '0811', '0905', '0915'],
  '9mobile': ['0809', '0818', '0817', '0909', '0908'],
};

String normPhone(String p) {
  final d = p.replaceAll(RegExp(r'\D'), '');
  return d.startsWith('234') && d.length == 13 ? '0${d.substring(3)}' : d;
}

/// VTpass sandbox test numbers; the server accepts them only while pointed at the sandbox.
const vtpassSandboxNumbers = ['201000000000', '500000000000', '400000000000', '300000000000'];

bool phoneOk(String p) => RegExp(r'^0[789][01]\d{8}$').hasMatch(normPhone(p)) || vtpassSandboxNumbers.contains(p.replaceAll(RegExp(r'\D'), ''));

String? detectNetwork(String p) {
  final n = normPhone(p);
  if (n.length < 4) return null;
  final pre = n.substring(0, 4);
  for (final e in _prefixes.entries) {
    if (e.value.contains(pre)) return e.key;
  }
  return null;
}

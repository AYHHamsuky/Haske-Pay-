import 'package:flutter_test/flutter_test.dart';
import 'package:haske_pay/core/format.dart';
import 'package:haske_pay/core/strings.dart';

void main() {
  test('naira formats kobo', () {
    expect(naira(500000), '₦5,000');
    expect(naira(12345), '₦123.45');
    expect(toKobo('2,000'), 200000);
  });

  test('phone numbers normalise and detect network', () {
    expect(normPhone('2348031234567'), '08031234567');
    expect(phoneOk('08031234567'), isTrue);
    expect(phoneOk('0803123'), isFalse);
    expect(detectNetwork('0803 123'), 'mtn');
    expect(detectNetwork('0802'), 'airtel');
    expect(detectNetwork('0905'), 'glo');
  });

  test('token groups of four', () {
    expect(tokenGroups('48107733920156284419'), '4810 7733 9201 5628 4419');
  });

  test('every Hausa string has an English fallback', () {
    expect(tr('ha', 'elec'), 'Wutar lantarki');
    expect(tr('ha', 'unknown-key'), 'unknown-key');
  });
}

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api.dart';
import 'models.dart';
import 'strings.dart';

class AppState extends ChangeNotifier {
  AppState(this.api);

  final Api api;
  Me? me;
  String lang = 'en';
  Map<String, List<Service>> services = {};
  List<Tx> history = [];
  List<SavedMeter> meters = [];
  bool booting = true;

  String t(String key) => tr(lang, key);

  Future<void> boot() async {
    await api.init();
    final prefs = await SharedPreferences.getInstance();
    lang = prefs.getString('lang') ?? 'en';
    if (api.hasToken) {
      try {
        await refresh();
      } on ApiException catch (e) {
        if (e.unauthenticated) await api.setToken(null);
      }
    }
    booting = false;
    notifyListeners();
  }

  Future<void> setLang(String l) async {
    lang = l;
    notifyListeners();
    (await SharedPreferences.getInstance()).setString('lang', l);
    if (me != null) {
      try {
        await api.patch('/me', {'locale': l});
      } catch (_) {}
    }
  }

  Future<String?> requestCode(String phone) async {
    final r = await api.post('/auth/otp', {'phone': phone, 'locale': lang});
    return r['dev_code'] as String?;
  }

  Future<void> verifyCode(String phone, String code) async {
    final r = await api.post('/auth/verify', {'phone': phone, 'code': code, 'device_id': api.deviceId, 'device_name': 'Haske app'});
    await api.setToken(r['token']);
    me = Me.fromJson(r['user']);
    await refresh();
  }

  Future<void> logout() async {
    try {
      await api.post('/auth/logout');
    } catch (_) {}
    await api.setToken(null);
    me = null;
    history = [];
    notifyListeners();
  }

  Future<void> refresh() async {
    final results = await Future.wait([api.get('/me'), api.get('/services'), api.get('/transactions'), api.get('/saved-meters')]);
    me = Me.fromJson(results[0]);
    services = (results[1] as Map).map((k, v) => MapEntry(k as String, (v as List).map((s) => Service.fromJson(s)).toList()));
    history = (results[2] as List).map((x) => Tx.fromJson(x)).toList();
    meters = (results[3] as List).map((m) => SavedMeter.fromJson(m)).toList();
    if (me!.locale != lang && me!.locale.isNotEmpty) lang = me!.locale;
    notifyListeners();
  }

  Future<void> refreshQuietly() async {
    try {
      await refresh();
    } catch (_) {}
  }

  Future<void> saveProfile({String? name}) async {
    await api.patch('/me', {'name': ?name, 'locale': lang});
    await refresh();
  }

  Future<void> setPin(String pin, {String? current}) async {
    await api.post('/me/pin', {'pin': pin, 'current_pin': ?current});
    await refresh();
  }

  Future<Customer> verifyMeter(String service, String meter, String type) async {
    final r = await api.post('/meters/verify', {'service_code': service, 'meter': meter, 'meter_type': type});
    return Customer.fromJson(r);
  }

  Future<List<Plan>> plans(String service) async => ((await api.get('/services/$service/plans')) as List).map((p) => Plan.fromJson(p)).toList();

  Future<Tx> purchase(Map<String, dynamic> spec) async {
    final r = await api.post('/purchases', spec);
    final tx = Tx.fromJson(r['transaction']);
    refreshQuietly();
    return tx;
  }

  Future<Tx> transaction(String ref) async => Tx.fromJson(await api.get('/transactions/$ref'));

  Future<Tx> requery(String ref) async {
    final tx = Tx.fromJson(await api.post('/transactions/$ref/requery'));
    if (tx.isFinal) refreshQuietly();
    return tx;
  }

  Future<void> removeMeter(int id) async {
    await api.delete('/saved-meters/$id');
    meters.removeWhere((m) => m.id == id);
    notifyListeners();
  }
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/api.dart';
import '../core/format.dart';
import '../core/scope.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final phone = TextEditingController();
  final code = TextEditingController();
  bool sent = false, busy = false;
  String? error, devCode;

  Future<void> send() async {
    setState(() { busy = true; error = null; });
    try {
      devCode = await context.app.requestCode(phone.text);
      setState(() => sent = true);
    } on ApiException catch (e) {
      setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> verify() async {
    if (code.text.length != 6 || busy) return;
    setState(() { busy = true; error = null; });
    try {
      await context.app.verifyCode(normPhone(phone.text), code.text);
    } on ApiException catch (e) {
      if (mounted) setState(() { error = e.message; code.clear(); });
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final c = context.hc;
    return Scaffold(
      body: SafeArea(
        child: ListView(padding: const EdgeInsets.fromLTRB(20, 16, 20, 24), children: [
          Row(children: [
            const _Mark(),
            const SizedBox(width: 8),
            Text('Haske', style: display(20, weight: FontWeight.w800)),
            const Spacer(),
            OutlinedButton(
              style: OutlinedButton.styleFrom(minimumSize: const Size(52, 34), shape: const StadiumBorder()),
              onPressed: () => app.setLang(app.lang == 'ha' ? 'en' : 'ha'),
              child: Text(app.lang == 'ha' ? 'EN' : 'HA', style: const TextStyle(fontSize: 12)),
            ),
          ]),
          const SizedBox(height: 48),
          Text(app.lang == 'ha' ? 'Haske cikin daƙiƙa.' : 'Light up in seconds.', style: display(38, weight: FontWeight.w800)),
          const SizedBox(height: 10),
          Text(app.t('loginSub'), style: TextStyle(color: c.muted, fontSize: 15)),
          const SizedBox(height: 32),
          if (!sent) ...[
            FieldLabel(app.t('phone')),
            TextField(
              controller: phone,
              keyboardType: TextInputType.phone,
              autofillHints: const [AutofillHints.telephoneNumber],
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(13)],
              style: mono(18),
              decoration: const InputDecoration(hintText: '0803 123 4567'),
              onChanged: (_) => setState(() {}),
              onSubmitted: (_) => send(),
            ),
            ErrorText(error),
            const SizedBox(height: 16),
            FilledButton(onPressed: busy || !phoneOk(phone.text) ? null : send, child: Text(app.t('sendCode'))),
          ] else ...[
            Row(children: [
              Expanded(child: Text('${app.t('codeSent')} ${normPhone(phone.text)}', style: const TextStyle(fontSize: 14))),
              TextButton(onPressed: () => setState(() { sent = false; code.clear(); error = null; }), child: Text(app.lang == 'ha' ? 'Canza' : 'Change')),
            ]),
            const SizedBox(height: 8),
            FieldLabel(app.t('code')),
            TextField(
              controller: code,
              autofocus: true,
              keyboardType: TextInputType.number,
              autofillHints: const [AutofillHints.oneTimeCode],
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
              textAlign: TextAlign.center,
              style: mono(22, spacing: 8),
              decoration: const InputDecoration(hintText: '••••••'),
              onChanged: (v) => v.length == 6 ? verify() : null,
            ),
            ErrorText(error),
            if (devCode != null) ...[
              const SizedBox(height: 10),
              Banner2(tone: 'pending', child: Text('Sandbox code: $devCode')),
            ],
            const SizedBox(height: 16),
            FilledButton(onPressed: busy || code.text.length != 6 ? null : verify, child: busy ? const _Spinner() : Text(app.t('login'))),
            TextButton(onPressed: busy ? null : send, child: Text(app.lang == 'ha' ? 'Sake aiko lamba' : 'Send a new code')),
          ],
        ]),
      ),
    );
  }
}

/// First login: name (needed for the Paystack account number), then a PIN entered twice.
class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends State<OnboardingScreen> {
  final name = TextEditingController();
  String first = '', pin = '';
  String? error;
  bool busy = false;

  Future<void> saveName() async {
    if (name.text.trim().length < 3) return;
    setState(() { busy = true; error = null; });
    try {
      await context.app.saveProfile(name: name.text.trim());
    } on ApiException catch (e) {
      setState(() => error = e.message);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> key(String k) async {
    HapticFeedback.selectionClick();
    setState(() {
      if (k == '⌫') {
        if (pin.isNotEmpty) pin = pin.substring(0, pin.length - 1);
      } else if (pin.length < 4) {
        pin += k;
      }
    });
    if (pin.length < 4) return;
    if (first.isEmpty) {
      setState(() { first = pin; pin = ''; error = null; });
      return;
    }
    if (pin != first) {
      setState(() { error = context.t('pinMismatch'); first = ''; pin = ''; });
      return;
    }
    setState(() => busy = true);
    try {
      await context.app.setPin(pin);
    } on ApiException catch (e) {
      setState(() { error = e.message; first = ''; pin = ''; });
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final c = context.hc;
    final needsName = (app.me?.name ?? '').isEmpty;
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 32, 20, 20),
          child: needsName
              ? Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text(app.lang == 'ha' ? 'Barka da zuwa' : 'Welcome to Haske', style: display(30, weight: FontWeight.w800)),
                  const SizedBox(height: 8),
                  Text(app.lang == 'ha' ? 'Sunanka zai bayyana a kan lambar asusunka.' : 'Your name goes on your Haske account number.', style: TextStyle(color: c.muted)),
                  const SizedBox(height: 28),
                  FieldLabel(app.t('yourName')),
                  TextField(controller: name, textCapitalization: TextCapitalization.words, autofillHints: const [AutofillHints.name], onChanged: (_) => setState(() {})),
                  ErrorText(error),
                  const Spacer(),
                  FilledButton(onPressed: busy || name.text.trim().length < 3 ? null : saveName, child: Text(app.t('cont'))),
                ])
              : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text(first.isEmpty ? app.t('setPin') : app.t('confirmPin'), style: display(30, weight: FontWeight.w800)),
                  const SizedBox(height: 8),
                  Text(app.t('setPinSub'), style: TextStyle(color: c.muted)),
                  const Spacer(),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(4, (i) => Container(
                          margin: const EdgeInsets.symmetric(horizontal: 8),
                          width: 16,
                          height: 16,
                          decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: c.accent, width: 2), color: i < pin.length ? c.accent : null),
                        )),
                  ),
                  SizedBox(height: 40, child: Center(child: busy ? const _Spinner() : ErrorText(error))),
                  GridView.count(
                    crossAxisCount: 3,
                    shrinkWrap: true,
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                    childAspectRatio: 1.9,
                    physics: const NeverScrollableScrollPhysics(),
                    children: [
                      for (final k in ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫'])
                        k.isEmpty
                            ? const SizedBox()
                            : Material(
                                color: c.surface,
                                borderRadius: BorderRadius.circular(14),
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(14),
                                  onTap: busy ? null : () => key(k),
                                  child: Center(child: k == '⌫' ? const Icon(Icons.backspace_outlined) : Text(k, style: display(24, weight: FontWeight.w600))),
                                ),
                              ),
                    ],
                  ),
                ]),
        ),
      ),
    );
  }
}

class _Mark extends StatelessWidget {
  const _Mark();

  @override
  Widget build(BuildContext context) => Container(
        width: 30,
        height: 30,
        decoration: BoxDecoration(color: const Color(0xFF08A56B), borderRadius: BorderRadius.circular(9)),
        alignment: Alignment.center,
        child: const Text('₦', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 17)),
      );
}

class _Spinner extends StatelessWidget {
  const _Spinner();

  @override
  Widget build(BuildContext context) => const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2));
}

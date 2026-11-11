import 'package:flutter/material.dart';

import 'core/api.dart';
import 'core/app_state.dart';
import 'core/scope.dart';
import 'core/theme.dart';
import 'screens/history.dart';
import 'screens/home.dart';
import 'screens/login.dart';
import 'screens/profile.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final state = AppState(Api())..boot();
  runApp(HaskeApp(state: state));
}

class HaskeApp extends StatelessWidget {
  const HaskeApp({super.key, required this.state});
  final AppState state;

  @override
  Widget build(BuildContext context) => AppScope(
        state: state,
        child: MaterialApp(
          title: 'Haske Pay',
          debugShowCheckedModeBanner: false,
          theme: buildTheme(Brightness.light),
          darkTheme: buildTheme(Brightness.dark),
          home: const _Root(),
        ),
      );
}

class _Root extends StatelessWidget {
  const _Root();

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    if (app.booting) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    if (app.me == null) return const LoginScreen();
    if ((app.me!.name ?? '').isEmpty || !app.me!.hasPin) return const OnboardingScreen();
    return const Shell();
  }
}

class Shell extends StatefulWidget {
  const Shell({super.key});

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> {
  int tab = 0;

  @override
  Widget build(BuildContext context) {
    final app = context.app;
    final c = context.hc;
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 18,
        title: Row(children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(color: const Color(0xFF08A56B), borderRadius: BorderRadius.circular(8)),
            alignment: Alignment.center,
            child: const Text('₦', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
          ),
          const SizedBox(width: 8),
          Text(['Haske', app.t('history'), app.t('profile')][tab], style: display(19, weight: FontWeight.w800)),
        ]),
        actions: [
          if (app.me!.isAgent)
            Container(
              margin: const EdgeInsets.only(right: 8),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(6)),
              child: Text(app.lang == 'ha' ? 'WAKILI' : 'AGENT', style: TextStyle(color: c.accent, fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: .6)),
            ),
          Padding(
            padding: const EdgeInsets.only(right: 14),
            child: OutlinedButton(
              style: OutlinedButton.styleFrom(minimumSize: const Size(48, 32), shape: const StadiumBorder(), padding: EdgeInsets.zero),
              onPressed: () => app.setLang(app.lang == 'ha' ? 'en' : 'ha'),
              child: Text(app.lang == 'ha' ? 'EN' : 'HA', style: const TextStyle(fontSize: 12)),
            ),
          ),
        ],
      ),
      body: IndexedStack(index: tab, children: [HomeTab(onSeeAll: () => setState(() => tab = 1)), const HistoryTab(), const ProfileTab()]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: tab,
        onDestinationSelected: (i) => setState(() => tab = i),
        destinations: [
          NavigationDestination(icon: const Icon(Icons.home_outlined), selectedIcon: const Icon(Icons.home_rounded), label: app.t('home')),
          NavigationDestination(icon: const Icon(Icons.receipt_long_outlined), selectedIcon: const Icon(Icons.receipt_long_rounded), label: app.t('history')),
          NavigationDestination(icon: const Icon(Icons.person_outline_rounded), selectedIcon: const Icon(Icons.person_rounded), label: app.t('profile')),
        ],
      ),
    );
  }
}

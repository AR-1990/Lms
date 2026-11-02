import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/alerts.dart';
import 'core/session.dart';
import 'core/theme.dart';
import 'screens/login_screen.dart';
import 'screens/school_screen.dart';
import 'screens/shell.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final firebaseReady = await startFirebase();
  final session = Session();
  await session.load();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: session),
        ChangeNotifierProvider(create: (_) => Alerts(session, firebaseReady: firebaseReady)),
      ],
      child: const SchoolApp(),
    ),
  );
}

class SchoolApp extends StatelessWidget {
  const SchoolApp({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final alerts = context.read<Alerts>();

    return MaterialApp(
      title: session.schoolName.isEmpty ? 'School' : session.schoolName,
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      scaffoldMessengerKey: alerts.messengerKey,
      // Address first (so one app serves every school), then sign-in, then the role's home.
      home: !session.ready
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : !session.hasSchool
              ? const SchoolScreen()
              : !session.signedIn
                  ? const LoginScreen()
                  : Shell(key: ValueKey(session.userId)),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/alerts.dart';
import '../core/session.dart';
import 'chat/chat_list_screen.dart';
import 'chat/chat_room_screen.dart';
import 'common/more_screen.dart';
import 'common/notices_screen.dart';
import 'common/notifications_screen.dart';
import 'family/attendance_screen.dart';
import 'family/diary_screen.dart';
import 'family/family_home.dart';
import 'family/family_state.dart';
import 'family/fees_screen.dart';
import 'family/online_tests_screen.dart';
import 'family/results_screen.dart';
import 'teacher/mark_attendance_screen.dart';
import 'teacher/teacher_home.dart';
import 'teacher/teacher_options.dart';

class _Tab {
  const _Tab(this.label, this.icon, this.selectedIcon, this.page, {this.badge = 0});

  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final Widget page;
  final int badge;
}

/// The signed-in home: bottom tabs chosen by role, live alerts and opening the screen a notification is about.
class Shell extends StatefulWidget {
  const Shell({super.key});

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> with WidgetsBindingObserver {
  int _index = 0;
  FamilyState? _family;
  late final Alerts _alerts;

  @override
  void initState() {
    super.initState();
    final session = context.read<Session>();
    _alerts = context.read<Alerts>();
    if (session.role != AppRole.staff) {
      _family = FamilyState(session)..load();
    }
    WidgetsBinding.instance.addObserver(this);
    _alerts.start();
    _alerts.addListener(_openPendingTarget);
    WidgetsBinding.instance.addPostFrameCallback((_) => _openPendingTarget());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _alerts.realtime.reconnect();
      _alerts.refresh();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _alerts.removeListener(_openPendingTarget);
    _family?.dispose();
    // Signed out (or another user): forget the teacher's cached classes.
    TeacherOptions.clear();
    _alerts.stop();
    super.dispose();
  }

  /// A tapped notification opens the screen it is about.
  void _openPendingTarget() {
    final target = _alerts.pendingTarget;
    if (target == null || !mounted) {
      return;
    }
    _alerts.pendingTarget = null;
    final session = context.read<Session>();
    if (target.conversationId != null && session.can('use-chat')) {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => ChatRoomScreen(conversationId: target.conversationId!)));
      return;
    }
    final page = screenForEvent(target.eventKey, session.role);
    if (page != null) {
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => _family == null ? page : ChangeNotifierProvider.value(value: _family!, child: page)));
    } else {
      final tabs = _tabs(session, _alerts);
      final alertsTab = tabs.indexWhere((tab) => tab.label == 'Alerts');
      setState(() => _index = alertsTab < 0 ? 0 : alertsTab);
    }
  }

  List<_Tab> _tabs(Session session, Alerts alerts) {
    switch (session.role) {
      case AppRole.parent:
        return [
          const _Tab('Home', Icons.home_outlined, Icons.home_rounded, FamilyHomeScreen()),
          const _Tab('Diary', Icons.menu_book_outlined, Icons.menu_book_rounded, DiaryScreen()),
          const _Tab('Fees', Icons.receipt_long_outlined, Icons.receipt_long_rounded, FeesScreen()),
          _Tab('Alerts', Icons.notifications_outlined, Icons.notifications_rounded, const NotificationsScreen(), badge: alerts.unreadNotifications),
          const _Tab('More', Icons.grid_view_outlined, Icons.grid_view_rounded, MoreScreen()),
        ];
      case AppRole.student:
        return [
          const _Tab('Home', Icons.home_outlined, Icons.home_rounded, FamilyHomeScreen()),
          const _Tab('Diary', Icons.menu_book_outlined, Icons.menu_book_rounded, DiaryScreen()),
          const _Tab('Tests', Icons.quiz_outlined, Icons.quiz_rounded, OnlineTestsScreen()),
          _Tab('Alerts', Icons.notifications_outlined, Icons.notifications_rounded, const NotificationsScreen(), badge: alerts.unreadNotifications),
          const _Tab('More', Icons.grid_view_outlined, Icons.grid_view_rounded, MoreScreen()),
        ];
      case AppRole.staff:
        return [
          const _Tab('Home', Icons.home_outlined, Icons.home_rounded, TeacherHomeScreen()),
          if (session.can('record-attendance')) const _Tab('Attendance', Icons.fact_check_outlined, Icons.fact_check_rounded, MarkAttendanceScreen()),
          if (session.can('use-chat')) _Tab('Chat', Icons.chat_bubble_outline_rounded, Icons.chat_bubble_rounded, const ChatListScreen(), badge: alerts.unreadChat),
          _Tab('Alerts', Icons.notifications_outlined, Icons.notifications_rounded, const NotificationsScreen(), badge: alerts.unreadNotifications),
          const _Tab('More', Icons.grid_view_outlined, Icons.grid_view_rounded, MoreScreen()),
        ];
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final alerts = context.watch<Alerts>();
    final tabs = _tabs(session, alerts);
    final index = _index.clamp(0, tabs.length - 1);

    final body = Scaffold(
      body: IndexedStack(index: index, children: [for (final tab in tabs) tab.page]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: [
          for (final tab in tabs)
            NavigationDestination(
              label: tab.label,
              icon: Badge(isLabelVisible: tab.badge > 0, label: Text(tab.badge > 99 ? '99+' : '${tab.badge}'), child: Icon(tab.icon)),
              selectedIcon: Badge(isLabelVisible: tab.badge > 0, label: Text(tab.badge > 99 ? '99+' : '${tab.badge}'), child: Icon(tab.selectedIcon)),
            ),
        ],
      ),
    );
    return _family == null ? body : ChangeNotifierProvider.value(value: _family!, child: body);
  }
}

/// Which screen a notification's event opens (null = the notifications list).
Widget? screenForEvent(String? eventKey, AppRole role) {
  final key = eventKey ?? '';
  if (key.startsWith('announcement') || key.contains('notice')) {
    return const NoticesScreen();
  }
  if (role == AppRole.staff) {
    return null;
  }
  if (key.contains('diary') || key.contains('homework')) {
    return const DiaryScreen(standalone: true);
  }
  if (key.contains('fee') || key.contains('payment') || key.contains('challan') || key.contains('invoice')) {
    return role == AppRole.parent ? const FeesScreen(standalone: true) : null;
  }
  if (key.contains('absent') || key.contains('arrived') || key.contains('attendance') || key.contains('leave')) {
    return const AttendanceScreen();
  }
  if (key.contains('result') || key.contains('exam') || key.contains('admit') || key.contains('online_test')) {
    return const ResultsScreen();
  }
  return null;
}

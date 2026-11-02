import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../family/attendance_screen.dart';
import '../family/family_state.dart';
import '../family/fees_screen.dart';
import '../family/homework_screen.dart';
import '../family/id_card_screen.dart';
import '../family/leave_screen.dart';
import '../family/online_tests_screen.dart';
import '../family/results_screen.dart';
import '../family/timetable_screen.dart';
import '../teacher/diary_write_screen.dart';
import '../teacher/marks_screen.dart';
import '../teacher/my_hr_screen.dart';
import '../teacher/remarks_screen.dart';
import '../teacher/student_leaves_screen.dart';
import '../teacher/teacher_homework_screen.dart';
import 'notices_screen.dart';
import 'profile_screen.dart';

class _Item {
  const _Item(this.label, this.icon, this.color, this.background, this.page);

  final String label;
  final IconData icon;
  final Color color;
  final Color background;
  final Widget page;
}

/// Every other screen this role can use, as a grid of tiles.
class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  List<_Item> _items(Session session) {
    const brand = (AppColors.brand600, AppColors.brand50);
    const green = (AppColors.green600, AppColors.green50);
    const sky = (AppColors.sky600, AppColors.sky50);
    const violet = (AppColors.violet600, AppColors.violet50);
    const amber = (AppColors.amber700, AppColors.amber50);
    const red = (AppColors.red600, AppColors.red50);
    _Item item(String label, IconData icon, (Color, Color) tone, Widget page) => _Item(label, icon, tone.$1, tone.$2, page);

    switch (session.role) {
      case AppRole.parent:
        return [
          item('Attendance', Icons.how_to_reg_outlined, green, const AttendanceScreen()),
          item('Results', Icons.emoji_events_outlined, violet, const ResultsScreen()),
          item('Homework', Icons.edit_note_rounded, sky, const HomeworkScreen()),
          item('Online tests', Icons.quiz_outlined, brand, const OnlineTestsScreen(standalone: true)),
          item('Leave', Icons.event_busy_outlined, amber, const LeaveScreen()),
          item('Timetable', Icons.calendar_view_week_outlined, sky, const TimetableScreen()),
          item('ID cards', Icons.badge_outlined, violet, const IdCardScreen()),
          item('Notices', Icons.campaign_outlined, red, const NoticesScreen()),
          item('My account', Icons.person_outline_rounded, brand, const ProfileScreen()),
        ];
      case AppRole.student:
        return [
          item('Attendance', Icons.how_to_reg_outlined, green, const AttendanceScreen()),
          item('Results', Icons.emoji_events_outlined, violet, const ResultsScreen()),
          item('Homework', Icons.edit_note_rounded, sky, const HomeworkScreen()),
          item('Timetable', Icons.calendar_view_week_outlined, sky, const TimetableScreen()),
          item('My QR card', Icons.qr_code_2_rounded, brand, const IdCardScreen()),
          if (session.can('view-fees')) item('Fees', Icons.receipt_long_outlined, amber, const FeesScreen(standalone: true)),
          item('Leave', Icons.event_busy_outlined, amber, const LeaveScreen()),
          item('Notices', Icons.campaign_outlined, red, const NoticesScreen()),
          item('My account', Icons.person_outline_rounded, brand, const ProfileScreen()),
        ];
      case AppRole.staff:
        return [
          if (session.can('manage-diary')) item('Diary', Icons.menu_book_outlined, sky, const DiaryWriteScreen()),
          if (session.can('manage-diary')) item('Homework', Icons.edit_note_rounded, brand, const TeacherHomeworkScreen()),
          if (session.can('submit-grades')) item('Marks entry', Icons.fact_check_outlined, violet, const MarksScreen()),
          if (session.can('submit-grades')) item('Result remarks', Icons.rate_review_outlined, green, const RemarksScreen()),
          if (session.can('record-attendance')) item('Student leave', Icons.event_busy_outlined, amber, const StudentLeavesScreen()),
          item('My leave', Icons.beach_access_outlined, green, const MyHrScreen(initialTab: 0)),
          item('Payslips', Icons.payments_outlined, brand, const MyHrScreen(initialTab: 1)),
          item('Loans', Icons.account_balance_wallet_outlined, sky, const MyHrScreen(initialTab: 2)),
          item('My QR card', Icons.qr_code_2_rounded, violet, const IdCardScreen()),
          item('Notices', Icons.campaign_outlined, red, const NoticesScreen()),
          item('My account', Icons.person_outline_rounded, brand, const ProfileScreen()),
        ];
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final family = session.role == AppRole.staff ? null : context.read<FamilyState>();
    final items = _items(session);

    return Scaffold(
      appBar: AppBar(title: const Text('More')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Panel(
            child: Row(
              children: [
                const SchoolLogo(size: 44),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(session.schoolName, style: const TextStyle(fontWeight: FontWeight.w700)),
                      Text(session.userName, style: const TextStyle(color: AppColors.gray500)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          GridView.count(
            crossAxisCount: MediaQuery.of(context).size.width > 600 ? 4 : 3,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 0.95,
            children: [
              for (final entry in items)
                Card(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(12),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => family == null ? entry.page : ChangeNotifierProvider.value(value: family, child: entry.page)),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(color: entry.background, borderRadius: BorderRadius.circular(12)),
                          child: Icon(entry.icon, color: entry.color, size: 26),
                        ),
                        const SizedBox(height: 8),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          child: Text(entry.label, textAlign: TextAlign.center, maxLines: 2, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.gray700)),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

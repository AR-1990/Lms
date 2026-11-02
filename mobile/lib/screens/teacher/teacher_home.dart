import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import '../common/notices_screen.dart';
import 'diary_write_screen.dart';
import 'marks_screen.dart';
import 'mark_attendance_screen.dart';
import 'my_hr_screen.dart';

/// Staff home. Teachers see today's periods (with cover periods) and the day's numbers (GET teacher/dashboard);
/// other staff see their shortcuts.
class TeacherHomeScreen extends StatelessWidget {
  const TeacherHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final isTeacher = session.can('manage-classes') || session.can('record-attendance') || session.can('submit-grades');
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: Row(children: [const SchoolLogo(size: 32), const SizedBox(width: 10), Expanded(child: Text(session.schoolName, overflow: TextOverflow.ellipsis))]),
      ),
      body: isTeacher
          ? AsyncView<Map<String, dynamic>>(
              load: () async => asMap(await session.api.get('erp/teacher/dashboard')),
              builder: (context, data, reload) => _TeacherDashboard(data: data),
            )
          : ListView(padding: const EdgeInsets.all(16), children: [_Greeting(name: session.userName), const SizedBox(height: 12), const _Shortcuts()]),
    );
  }
}

class _Greeting extends StatelessWidget {
  const _Greeting({required this.name, this.detail});

  final String name;
  final String? detail;

  @override
  Widget build(BuildContext context) {
    final hour = DateTime.now().hour;
    final greeting = hour < 12 ? 'Good morning' : (hour < 17 ? 'Good afternoon' : 'Good evening');
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(gradient: const LinearGradient(colors: [AppColors.brand500, AppColors.brand700]), borderRadius: BorderRadius.circular(14)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(greeting, style: const TextStyle(color: Colors.white70)),
          Text(name, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800)),
          if (detail != null) Text(detail!, style: const TextStyle(color: Colors.white)),
        ],
      ),
    );
  }
}

class _TeacherDashboard extends StatelessWidget {
  const _TeacherDashboard({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final session = context.read<Session>();
    final teacher = asMap(data['teacher']);
    final metrics = asMap(data['metrics']);
    final schedule = asList(data['today_schedule']);
    final percentage = metrics['today_attendance_percentage'];
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _Greeting(name: teacher['name']?.toString() ?? session.userName, detail: [teacher['designation'], teacher['department']].whereType<Object>().join(' · ')),
        const SizedBox(height: 12),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 2.2,
          children: [
            StatTile(label: 'Periods today', value: '${metrics['periods_today'] ?? 0}', icon: Icons.schedule_rounded),
            StatTile(label: 'Students', value: '${metrics['students'] ?? 0}', icon: Icons.groups_rounded, color: AppColors.sky600, background: AppColors.sky50),
            StatTile(label: 'Sections marked today', value: '${metrics['sections_marked_today'] ?? 0} / ${metrics['sections'] ?? 0}', icon: Icons.fact_check_rounded, color: AppColors.green600, background: AppColors.green50),
            StatTile(label: 'Present today', value: percentage == null ? '—' : '${(percentage as num).toStringAsFixed(0)}%', icon: Icons.how_to_reg_rounded, color: AppColors.violet600, background: AppColors.violet50),
          ],
        ),
        const SizedBox(height: 12),
        Panel(
          title: 'Today\'s periods',
          child: schedule.isEmpty
              ? const Text('No periods today.', style: TextStyle(color: AppColors.gray500))
              : Column(
                  children: [
                    for (final slot in schedule)
                      Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(color: slot['is_cover'] == true ? AppColors.amber50 : AppColors.gray50, borderRadius: BorderRadius.circular(8)),
                        child: Row(
                          children: [
                            SizedBox(width: 56, child: Text(slot['starts_at']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.brand700))),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('${slot['subject'] ?? slot['period'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                  Text([slot['section'], if (slot['room'] != null) 'Room ${slot['room']}'].whereType<Object>().join(' · '), style: const TextStyle(color: AppColors.gray500, fontSize: 12)),
                                ],
                              ),
                            ),
                            if (slot['is_cover'] == true) const StatusChip('Cover', tone: 'late'),
                          ],
                        ),
                      ),
                  ],
                ),
        ),
        const SizedBox(height: 12),
        const _Shortcuts(),
      ],
    );
  }
}

class _Shortcuts extends StatelessWidget {
  const _Shortcuts();

  @override
  Widget build(BuildContext context) {
    final session = context.read<Session>();
    final items = <(String, IconData, Widget)>[
      if (session.can('record-attendance')) ('Mark attendance', Icons.fact_check_outlined, const MarkAttendanceScreen(standalone: true)),
      if (session.can('manage-diary')) ('Write diary', Icons.menu_book_outlined, const DiaryWriteScreen()),
      if (session.can('submit-grades')) ('Enter marks', Icons.grading_rounded, const MarksScreen()),
      ('My leave', Icons.beach_access_outlined, const MyHrScreen(initialTab: 0)),
      ('Payslips', Icons.payments_outlined, const MyHrScreen(initialTab: 1)),
      ('Notices', Icons.campaign_outlined, const NoticesScreen()),
    ];
    return Panel(
      title: 'Quick actions',
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in items)
            ActionChip(
              avatar: Icon(item.$2, size: 18, color: AppColors.brand700),
              label: Text(item.$1),
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => item.$3)),
            ),
        ],
      ),
    );
  }
}

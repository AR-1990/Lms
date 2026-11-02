import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'attendance_screen.dart';
import 'family_state.dart';
import 'fees_screen.dart';
import 'results_screen.dart';
import 'timetable_screen.dart';

/// Home for parents and students: the selected child's summary (GET family/students/{id} and the month's attendance).
class FamilyHomeScreen extends StatelessWidget {
  const FamilyHomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    final family = context.read<FamilyState>();
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 16,
        title: Row(
          children: [
            const SchoolLogo(size: 32),
            const SizedBox(width: 10),
            Expanded(child: ChildSwitcher(title: context.watch<Session>().schoolName)),
          ],
        ),
      ),
      body: ChildScope(
        builder: (context, studentId) => AsyncView<List<Map<String, dynamic>>>(
          load: () async {
            final results = await Future.wait([
              api.get('erp/family/students/$studentId'),
              api.get('erp/attendance/students/$studentId').catchError((_) => null),
            ]);
            return [asMap(results[0]), asMap(results[1])];
          },
          builder: (context, data, reload) => _Overview(overview: data[0], attendance: data[1], family: family),
        ),
      ),
    );
  }
}

class _Overview extends StatelessWidget {
  const _Overview({required this.overview, required this.attendance, required this.family});

  final Map<String, dynamic> overview;
  final Map<String, dynamic> attendance;
  final FamilyState family;

  void _push(BuildContext context, Widget page) => Navigator.push(context, MaterialPageRoute(builder: (_) => ChangeNotifierProvider.value(value: family, child: page)));

  @override
  Widget build(BuildContext context) {
    final student = asMap(overview['student']);
    final fees = asMap(overview['fees']);
    final exams = asList(overview['exams']);
    final library = asList(overview['library']).where((book) => book['returned_on'] == null).toList();
    final transport = asList(overview['transport']);
    final weekday = DateTime.now().weekday;
    final todaySlots = asList(overview['timetable']).where((slot) => slot['day_of_week'] == weekday).toList()
      ..sort((a, b) => '${asMap(a['period'])['starts_at']}'.compareTo('${asMap(b['period'])['starts_at']}'));
    final percentage = attendance['percentage'];
    final due = (fees['due'] as num?) ?? 0;
    final openChallans = asList(fees['open_challans']);
    final isParent = family.isParent;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(gradient: const LinearGradient(colors: [AppColors.brand500, AppColors.brand700]), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              CircleAvatar(radius: 26, backgroundColor: Colors.white24, foregroundColor: Colors.white, child: Text((student['name'] ?? '?').toString().substring(0, 1).toUpperCase(), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(student['name']?.toString() ?? '', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 2),
                    Text([student['class'], if (student['roll_no'] != null) 'Roll ${student['roll_no']}'].whereType<Object>().join(' · '), style: const TextStyle(color: Colors.white)),
                    Text('Adm. ${student['admission_no'] ?? ''}${student['session'] != null ? ' · ${student['session']}' : ''}', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: StatTile(
                label: 'Attendance this month',
                value: percentage == null ? '—' : '${(percentage as num).toStringAsFixed(0)}%',
                icon: Icons.how_to_reg_rounded,
                color: AppColors.green600,
                background: AppColors.green50,
                onTap: () => _push(context, const AttendanceScreen()),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: StatTile(
                label: due > 0 ? '${openChallans.length} challan(s) due' : 'Fees all clear',
                value: money(due),
                icon: Icons.receipt_long_rounded,
                color: due > 0 ? AppColors.red600 : AppColors.green600,
                background: due > 0 ? AppColors.red50 : AppColors.green50,
                onTap: isParent || context.read<Session>().can('view-fees') ? () => _push(context, const FeesScreen(standalone: true)) : null,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Panel(
          title: 'Today\'s classes',
          trailing: TextButton(onPressed: () => _push(context, const TimetableScreen()), child: const Text('Full week')),
          child: todaySlots.isEmpty
              ? const Text('No classes today.', style: TextStyle(color: AppColors.gray500))
              : Column(
                  children: [
                    for (final slot in todaySlots)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        child: Row(
                          children: [
                            SizedBox(width: 64, child: Text(_time(asMap(slot['period'])['starts_at']), style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.brand700))),
                            Expanded(child: Text(slot['subject']?.toString() ?? asMap(slot['period'])['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600))),
                            Text(slot['teacher']?.toString() ?? '', style: const TextStyle(color: AppColors.gray500, fontSize: 12)),
                          ],
                        ),
                      ),
                  ],
                ),
        ),
        if (exams.isNotEmpty) ...[
          const SizedBox(height: 12),
          Panel(
            title: 'Exams',
            trailing: TextButton(onPressed: () => _push(context, const ResultsScreen()), child: const Text('Results')),
            child: Column(children: [for (final exam in exams.take(4)) ExamTile(exam: exam, studentId: family.childId)]),
          ),
        ],
        if (student['class_teacher'] != null) ...[
          const SizedBox(height: 12),
          Panel(title: 'Class teacher', child: Row(children: [const Icon(Icons.person_rounded, color: AppColors.gray400), const SizedBox(width: 8), Text(student['class_teacher'].toString(), style: const TextStyle(fontWeight: FontWeight.w600))])),
        ],
        if (library.isNotEmpty) ...[
          const SizedBox(height: 12),
          Panel(
            title: 'Library books',
            child: Column(
              children: [
                for (final book in library)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.menu_book_rounded, color: AppColors.sky600),
                    title: Text(book['title']?.toString() ?? ''),
                    subtitle: Text('Return by ${formatDate(book['due_on'])}'),
                    trailing: book['is_overdue'] == true ? const StatusChip('Overdue', tone: 'overdue') : null,
                  ),
              ],
            ),
          ),
        ],
        if (transport.isNotEmpty) ...[
          const SizedBox(height: 12),
          Panel(
            title: 'School transport',
            child: Column(children: [for (final ride in transport) InfoRow(ride['route']?.toString() ?? 'Route', ride['stop']?.toString())]),
          ),
        ],
        if (student['leaving_certificate_url'] != null) ...[
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => openFile(context, student['leaving_certificate_url'].toString(), 'leaving-certificate.pdf'),
            icon: const Icon(Icons.picture_as_pdf_outlined),
            label: const Text('Leaving certificate'),
          ),
        ],
      ],
    );
  }

  String _time(dynamic value) {
    final text = (value ?? '').toString();
    return text.length >= 5 ? text.substring(0, 5) : text;
  }
}

/// One exam with its date sheet, admit card and report card (when published).
class ExamTile extends StatelessWidget {
  const ExamTile({super.key, required this.exam, required this.studentId});

  final Map<String, dynamic> exam;
  final int studentId;

  @override
  Widget build(BuildContext context) {
    final admit = asMap(exam['admit_card']);
    final dateSheet = asList(exam['date_sheet']);
    return ExpansionTile(
      tilePadding: EdgeInsets.zero,
      childrenPadding: const EdgeInsets.only(bottom: 8),
      title: Text(exam['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Text(exam['term']?.toString() ?? ''),
      trailing: StatusChip(exam['status']?.toString() ?? ''),
      children: [
        for (final paper in dateSheet)
          InfoRow(paper['subject']?.toString() ?? '', [formatDate(paper['date'], pattern: 'EEE d MMM'), paper['starts_at']].where((part) => part != null && part.toString().isNotEmpty).join(' · ')),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            if (admit['pdf_url'] != null)
              OutlinedButton.icon(onPressed: () => openFile(context, admit['pdf_url'].toString(), 'admit-card-${exam['id']}.pdf'), icon: const Icon(Icons.badge_outlined, size: 18), label: const Text('Admit card')),
            if (admit.isNotEmpty && admit['is_eligible'] == false)
              StatusChip(admit['reason'] == 'fee_due' ? 'Admit card held: fee due' : 'Admit card held', tone: 'overdue'),
            if (exam['report_card_url'] != null)
              FilledButton.icon(onPressed: () => openFile(context, exam['report_card_url'].toString(), 'result-card-${exam['id']}.pdf'), icon: const Icon(Icons.picture_as_pdf_outlined, size: 18), label: const Text('Result card')),
          ],
        ),
      ],
    );
  }
}

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'family_state.dart';

/// A month of the child's attendance as a calendar, with the month's counts (GET attendance/students/{id}).
class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);

  static const _tones = {
    'present': (AppColors.green50, AppColors.green600),
    'late': (AppColors.amber50, AppColors.amber700),
    'half_day': (AppColors.amber50, AppColors.amber700),
    'leave': (AppColors.sky50, AppColors.sky600),
    'absent': (AppColors.red50, AppColors.red600),
  };

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    final now = DateTime.now();
    final isCurrentMonth = _month.year == now.year && _month.month == now.month;
    final lastDay = DateTime(_month.year, _month.month + 1, 0);
    final to = isCurrentMonth ? now : lastDay;
    final format = DateFormat('yyyy-MM-dd');

    return Scaffold(
      appBar: AppBar(title: const ChildSwitcher(title: 'Attendance')),
      body: ChildScope(
        builder: (context, studentId) => Column(
          children: [
            Container(
              color: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 8),
              child: Row(
                children: [
                  IconButton(icon: const Icon(Icons.chevron_left_rounded), onPressed: () => setState(() => _month = DateTime(_month.year, _month.month - 1))),
                  Expanded(child: Text(DateFormat('MMMM yyyy').format(_month), textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16))),
                  IconButton(icon: const Icon(Icons.chevron_right_rounded), onPressed: isCurrentMonth ? null : () => setState(() => _month = DateTime(_month.year, _month.month + 1))),
                ],
              ),
            ),
            Expanded(
              child: AsyncView<Map<String, dynamic>>(
                key: ValueKey('$studentId-${_month.year}-${_month.month}'),
                load: () async => asMap(await api.get('erp/attendance/students/$studentId', query: {'from': format.format(_month), 'to': format.format(to)})),
                builder: (context, data, reload) {
                  final days = {for (final day in asList(data['days'])) day['date'].toString(): day['status'].toString()};
                  final counts = asMap(data['counts']);
                  final percentage = data['percentage'];
                  final leading = DateTime(_month.year, _month.month, 1).weekday - 1;
                  return ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Panel(
                        child: Row(
                          children: [
                            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              const Text('Attendance', style: TextStyle(color: AppColors.gray500)),
                              Text(percentage == null ? '—' : '${(percentage as num).toStringAsFixed(1)}%', style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: AppColors.gray900)),
                            ])),
                            Wrap(
                              spacing: 6,
                              runSpacing: 6,
                              children: [
                                for (final status in _tones.keys)
                                  if (((counts[status] as num?) ?? 0) > 0) StatusChip('${humanize(status)} ${counts[status]}', tone: status),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      Panel(
                        child: Column(
                          children: [
                            Row(children: [for (final name in ['M', 'T', 'W', 'T', 'F', 'S', 'S']) Expanded(child: Center(child: Text(name, style: const TextStyle(color: AppColors.gray400, fontWeight: FontWeight.w600))))]),
                            const SizedBox(height: 8),
                            GridView.count(
                              crossAxisCount: 7,
                              shrinkWrap: true,
                              physics: const NeverScrollableScrollPhysics(),
                              mainAxisSpacing: 6,
                              crossAxisSpacing: 6,
                              children: [
                                for (var blank = 0; blank < leading; blank++) const SizedBox(),
                                for (var day = 1; day <= lastDay.day; day++)
                                  Builder(builder: (context) {
                                    final status = days[format.format(DateTime(_month.year, _month.month, day))];
                                    final tone = _tones[status];
                                    return Container(
                                      alignment: Alignment.center,
                                      decoration: BoxDecoration(color: tone?.$1 ?? AppColors.gray50, borderRadius: BorderRadius.circular(8)),
                                      child: Text('$day', style: TextStyle(fontWeight: FontWeight.w700, color: tone?.$2 ?? AppColors.gray400)),
                                    );
                                  }),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 12,
                        runSpacing: 6,
                        children: [
                          for (final entry in _tones.entries)
                            Row(mainAxisSize: MainAxisSize.min, children: [
                              Container(width: 12, height: 12, decoration: BoxDecoration(color: entry.value.$2, borderRadius: BorderRadius.circular(3))),
                              const SizedBox(width: 4),
                              Text(humanize(entry.key), style: const TextStyle(fontSize: 12, color: AppColors.gray600)),
                            ]),
                        ],
                      ),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

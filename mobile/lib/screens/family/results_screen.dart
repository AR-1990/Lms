import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'family_state.dart';

/// Published exam results with subject marks and the printable result card (GET exams/results/students/{id}).
class ResultsScreen extends StatelessWidget {
  const ResultsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const ChildSwitcher(title: 'Results')),
      body: ChildScope(
        builder: (context, studentId) => AsyncView<List<Map<String, dynamic>>>(
          load: () async => asList(await api.get('erp/exams/results/students/$studentId')),
          empty: (results) => results.isEmpty,
          builder: (context, results, reload) => ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: results.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final result = results[index];
              final passed = (result['result'] ?? '').toString().toLowerCase().startsWith('pass');
              return Panel(
                title: '${result['exam']}${result['term'] != null ? ' · ${result['term']}' : ''}',
                trailing: StatusChip(result['result']?.toString() ?? '', tone: passed ? 'pass' : 'fail'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        _Figure('Percentage', result['percentage'] == null ? '—' : '${(result['percentage'] as num).toStringAsFixed(1)}%'),
                        _Figure('Grade', result['grade']?.toString() ?? '—'),
                        _Figure('Position', result['position_in_section']?.toString() ?? '—'),
                        _Figure('Attendance', result['attendance_percent'] == null ? '—' : '${(result['attendance_percent'] as num).toStringAsFixed(0)}%'),
                      ],
                    ),
                    const Divider(height: 24),
                    for (final subject in asList(result['subjects']))
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 5),
                        child: Row(
                          children: [
                            Expanded(child: Text(subject['subject']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600))),
                            Text(subject['absent'] == true ? 'Absent' : '${_number(subject['obtained'])} / ${_number(subject['max'])}', style: TextStyle(color: subject['absent'] == true ? AppColors.red600 : AppColors.gray700)),
                            SizedBox(width: 44, child: Text(subject['grade']?.toString() ?? '', textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.brand700))),
                          ],
                        ),
                      ),
                    const SizedBox(height: 10),
                    FilledButton.icon(
                      onPressed: () => openFile(context, 'erp/exams/${result['exam_id']}/report-cards/students/$studentId/pdf', 'result-card-${result['exam_id']}.pdf'),
                      icon: const Icon(Icons.picture_as_pdf_outlined),
                      label: const Text('Result card'),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  static String _number(dynamic value) {
    final number = num.tryParse('${value ?? ''}');
    if (number == null) {
      return '—';
    }
    return number == number.roundToDouble() ? number.toInt().toString() : number.toStringAsFixed(1);
  }
}

class _Figure extends StatelessWidget {
  const _Figure(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Text(value, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: AppColors.gray900)),
          const SizedBox(height: 2),
          Text(label, style: const TextStyle(fontSize: 11, color: AppColors.gray500)),
        ],
      ),
    );
  }
}

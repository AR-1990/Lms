import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'family_state.dart';

/// The child's homework with their own submission, marks and feedback; hand in written work (GET homework?student_id=).
class HomeworkScreen extends StatelessWidget {
  const HomeworkScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const ChildSwitcher(title: 'Homework')),
      body: ChildScope(
        builder: (context, studentId) => AsyncView<List<Map<String, dynamic>>>(
          load: () async => asList(await api.get('erp/homework', query: {'student_id': studentId, 'per_page': 50})),
          empty: (items) => items.isEmpty,
          builder: (context, items, reload) => ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final homework = items[index];
              final mine = asMap(homework['my_submission']);
              final status = mine['status']?.toString();
              final due = homework['due_on'] == null ? null : DateTime.tryParse(homework['due_on'].toString());
              final overdue = status == null && due != null && due.isBefore(DateTime.now().subtract(const Duration(days: 1)));
              return Panel(
                title: homework['title']?.toString() ?? '',
                trailing: StatusChip(status ?? (overdue ? 'missing' : 'pending')),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('${homework['subject_name'] ?? ''}${homework['teacher_name'] != null ? ' · ${homework['teacher_name']}' : ''}', style: const TextStyle(color: AppColors.gray500)),
                    if ((homework['description'] ?? '').toString().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 8), child: Text(homework['description'].toString())),
                    const SizedBox(height: 8),
                    Text('Given ${formatDate(homework['assigned_on'])}${due != null ? ' · Due ${formatDate(homework['due_on'])}' : ''}', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                    if (status == 'graded')
                      Container(
                        margin: const EdgeInsets.only(top: 10),
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(color: AppColors.green50, borderRadius: BorderRadius.circular(8)),
                        child: Text('Marks: ${mine['marks'] ?? '—'}${homework['max_marks'] != null ? ' / ${homework['max_marks']}' : ''}${(mine['feedback'] ?? '').toString().isNotEmpty ? '\n${mine['feedback']}' : ''}', style: const TextStyle(color: AppColors.green600, fontWeight: FontWeight.w600)),
                      ),
                    if (homework['allow_submission'] == true && status != 'graded') ...[
                      const SizedBox(height: 10),
                      OutlinedButton.icon(
                        onPressed: () => _submit(context, homework, studentId, mine['content']?.toString(), reload),
                        icon: const Icon(Icons.send_rounded, size: 18),
                        label: Text(status == null ? 'Hand in' : 'Update my answer'),
                      ),
                    ],
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Future<void> _submit(BuildContext context, Map<String, dynamic> homework, int studentId, String? current, Future<void> Function() reload) async {
    final answer = TextEditingController(text: current ?? '');
    final send = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(sheetContext).viewInsets.bottom + 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(homework['title']?.toString() ?? '', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
            const SizedBox(height: 12),
            TextField(controller: answer, maxLines: 8, minLines: 4, decoration: const InputDecoration(labelText: 'Your answer')),
            const SizedBox(height: 12),
            FilledButton(onPressed: () => Navigator.pop(sheetContext, true), child: const Text('Hand in')),
          ],
        ),
      ),
    );
    if (send == true && context.mounted) {
      final api = context.read<Session>().api;
      final done = await runAction(context, () => api.post('erp/homework/${homework['id']}/submit', {'student_id': studentId, 'content': answer.text.trim()}), success: 'Handed in.');
      if (done != null) {
        reload();
      }
    }
  }
}

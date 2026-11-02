import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'family_state.dart';
import 'homework_screen.dart';

/// The class diary of the last two weeks; parents sign each day's diary (GET diary/students/{id}).
class DiaryScreen extends StatelessWidget {
  const DiaryScreen({super.key, this.standalone = false});

  final bool standalone;

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    final family = context.read<FamilyState>();
    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: standalone,
        title: const ChildSwitcher(title: 'Diary'),
        actions: [
          TextButton.icon(
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ChangeNotifierProvider.value(value: family, child: const HomeworkScreen()))),
            icon: const Icon(Icons.edit_note_rounded),
            label: const Text('Homework'),
          ),
        ],
      ),
      body: ChildScope(
        builder: (context, studentId) => AsyncView<List<Map<String, dynamic>>>(
          load: () async => asList(await api.get('erp/diary/students/$studentId')),
          empty: (days) => days.isEmpty,
          builder: (context, days, reload) => ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: days.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, index) => _DiaryDay(day: days[index], studentId: studentId, canSign: family.isParent, reload: reload),
          ),
        ),
      ),
    );
  }
}

class _DiaryDay extends StatelessWidget {
  const _DiaryDay({required this.day, required this.studentId, required this.canSign, required this.reload});

  final Map<String, dynamic> day;
  final int studentId;
  final bool canSign;
  final Future<void> Function() reload;

  Future<void> _sign(BuildContext context) async {
    final comment = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Sign the diary'),
        content: TextField(controller: comment, maxLines: 3, decoration: const InputDecoration(labelText: 'Message for the teacher (optional)')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Sign')),
        ],
      ),
    );
    if (ok == true && context.mounted) {
      final api = context.read<Session>().api;
      final done = await runAction(context, () => api.post('erp/diaries/${day['id']}/acknowledge', {'student_id': studentId, if (comment.text.trim().isNotEmpty) 'comment': comment.text.trim()}), success: 'Diary signed.');
      if (done != null) {
        reload();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final entries = asList(day['entries']);
    final remarks = asList(day['remarks']);
    final signed = day['signed'] == true;
    return Panel(
      title: formatDate(day['date'], pattern: 'EEEE, d MMMM'),
      trailing: signed ? const StatusChip('Signed', tone: 'approved') : (canSign ? null : const StatusChip('Not signed', tone: 'pending')),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if ((day['note'] ?? '').toString().isNotEmpty)
            Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: AppColors.amber50, borderRadius: BorderRadius.circular(8)),
              child: Text(day['note'].toString(), style: const TextStyle(color: AppColors.amber700)),
            ),
          for (final entry in entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    margin: const EdgeInsets.only(top: 2, right: 10),
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(color: AppColors.brand50, borderRadius: BorderRadius.circular(6)),
                    child: Text(entry['type']?.toString() ?? '', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.brand700)),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text([entry['subject'], entry['title']].whereType<Object>().join(': '), style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text(entry['details']?.toString() ?? ''),
                        if (entry['due_on'] != null) Text('Due ${formatDate(entry['due_on'])}', style: const TextStyle(fontSize: 12, color: AppColors.red600)),
                        if (entry['teacher'] != null) Text(entry['teacher'].toString(), style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          for (final remark in remarks)
            Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: AppColors.sky50, borderRadius: BorderRadius.circular(8)),
              child: Text('${remark['type'] != null ? '${remark['type']}: ' : ''}${remark['remark']}', style: const TextStyle(color: AppColors.sky600)),
            ),
          if (canSign && !signed) FilledButton.icon(onPressed: () => _sign(context), icon: const Icon(Icons.draw_rounded), label: const Text('Sign diary')),
        ],
      ),
    );
  }
}

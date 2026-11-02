import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'teacher_options.dart';

/// Homework for one of the teacher's classes: set new work, see who handed in, give marks and feedback
/// (GET/POST homework, GET homework/{id}/submissions, POST homework/{id}/grade).
class TeacherHomeworkScreen extends StatefulWidget {
  const TeacherHomeworkScreen({super.key});

  @override
  State<TeacherHomeworkScreen> createState() => _TeacherHomeworkScreenState();
}

class _TeacherHomeworkScreenState extends State<TeacherHomeworkScreen> {
  TeacherOptions? _options;
  int? _sectionId;
  int _version = 0;
  String? _error;

  ApiClient get _api => context.read<Session>().api;

  @override
  void initState() {
    super.initState();
    TeacherOptions.load(context.read<Session>().api).then((options) {
      setState(() {
        _options = options;
        _sectionId = options.sections.isEmpty ? null : (options.sections.first['id'] as num).toInt();
      });
    }).catchError((error) {
      setState(() => _error = error.toString());
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Homework')),
      floatingActionButton: _sectionId == null
          ? null
          : FloatingActionButton.extended(
              backgroundColor: AppColors.brand500,
              foregroundColor: Colors.white,
              onPressed: () async {
                final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => _HomeworkForm(api: _api, sectionId: _sectionId!, subjects: _options!.subjectsFor(_sectionId))));
                if (saved == true) {
                  setState(() => _version++);
                }
              },
              icon: const Icon(Icons.add_rounded),
              label: const Text('Set homework'),
            ),
      body: _options == null
          ? (_error == null ? const Center(child: CircularProgressIndicator()) : MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load', message: _error))
          : _options!.sections.isEmpty
              ? const NoSections()
              : Column(
                  children: [
                    Container(color: Colors.white, padding: const EdgeInsets.all(12), child: SectionPicker(sections: _options!.sections, value: _sectionId, onChanged: (value) => setState(() => _sectionId = value))),
                    Expanded(
                      child: AsyncView<List<Map<String, dynamic>>>(
                        key: ValueKey('$_sectionId-$_version'),
                        load: () async => asList(await _api.get('erp/homework', query: {'section_id': _sectionId, 'per_page': 50})),
                        empty: (items) => items.isEmpty,
                        builder: (context, items, reload) => ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 90),
                          itemCount: items.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            final homework = items[index];
                            return Card(
                              child: ListTile(
                                title: Text(homework['title']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w700)),
                                subtitle: Text('${homework['subject_name'] ?? ''} · given ${formatDate(homework['assigned_on'], pattern: 'd MMM')}${homework['due_on'] != null ? ' · due ${formatDate(homework['due_on'], pattern: 'd MMM')}' : ''}'),
                                trailing: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Text('${homework['submitted_count'] ?? 0}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)), const Text('handed in', style: TextStyle(fontSize: 11, color: AppColors.gray500))]),
                                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _Submissions(api: _api, homework: homework))),
                              ),
                            );
                          },
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _HomeworkForm extends StatefulWidget {
  const _HomeworkForm({required this.api, required this.sectionId, required this.subjects});

  final ApiClient api;
  final int sectionId;
  final List<Map<String, dynamic>> subjects;

  @override
  State<_HomeworkForm> createState() => _HomeworkFormState();
}

class _HomeworkFormState extends State<_HomeworkForm> {
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _maxMarks = TextEditingController();
  int? _subjectId;
  DateTime? _dueOn = DateTime.now().add(const Duration(days: 1));
  bool _allowSubmission = true;
  Object? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _subjectId = widget.subjects.isEmpty ? null : (widget.subjects.first['id'] as num).toInt();
  }

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final format = DateFormat('yyyy-MM-dd');
    try {
      await widget.api.post('erp/homework', {
        'section_id': widget.sectionId,
        'subject_id': _subjectId,
        'title': _title.text.trim(),
        'description': _description.text.trim().isEmpty ? null : _description.text.trim(),
        'assigned_on': format.format(DateTime.now()),
        'due_on': _dueOn == null ? null : format.format(_dueOn!),
        'max_marks': int.tryParse(_maxMarks.text),
        'allow_submission': _allowSubmission,
      });
      if (mounted) {
        showMessage(context, 'Homework set.');
        Navigator.pop(context, true);
      }
    } catch (error) {
      setState(() => _error = error);
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Set homework')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DropdownButtonFormField<int>(
            value: _subjectId,
            isExpanded: true,
            decoration: InputDecoration(labelText: 'Subject', errorText: fieldError(_error, 'subject_id')),
            items: [for (final subject in widget.subjects) DropdownMenuItem(value: (subject['id'] as num).toInt(), child: Text(subject['name'].toString()))],
            onChanged: (value) => setState(() => _subjectId = value),
          ),
          const SizedBox(height: 14),
          TextField(controller: _title, decoration: InputDecoration(labelText: 'Title', errorText: fieldError(_error, 'title'))),
          const SizedBox(height: 14),
          TextField(controller: _description, maxLines: 5, decoration: InputDecoration(labelText: 'Instructions (optional)', errorText: fieldError(_error, 'description'))),
          const SizedBox(height: 14),
          InkWell(
            onTap: () async {
              final picked = await showDatePicker(context: context, initialDate: _dueOn ?? DateTime.now(), firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 90)));
              setState(() => _dueOn = picked);
            },
            child: InputDecorator(decoration: InputDecoration(labelText: 'Due date', errorText: fieldError(_error, 'due_on')), child: Text(_dueOn == null ? 'None' : DateFormat('d MMM yyyy').format(_dueOn!))),
          ),
          const SizedBox(height: 14),
          TextField(controller: _maxMarks, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'Out of (marks, optional)', errorText: fieldError(_error, 'max_marks'))),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Students hand in online'), value: _allowSubmission, onChanged: (value) => setState(() => _allowSubmission = value)),
          if (_error != null && fieldError(_error, 'title') == null && fieldError(_error, 'subject_id') == null) Text(_error.toString(), style: const TextStyle(color: AppColors.red600)),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : _save, child: const Text('Save')),
        ],
      ),
    );
  }
}

class _Submissions extends StatelessWidget {
  const _Submissions({required this.api, required this.homework});

  final ApiClient api;
  final Map<String, dynamic> homework;

  Future<void> _grade(BuildContext context, Map<String, dynamic> row, Future<void> Function() reload) async {
    final marks = TextEditingController(text: row['marks']?.toString() ?? '');
    final feedback = TextEditingController(text: row['feedback']?.toString() ?? '');
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(sheetContext).viewInsets.bottom + 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(row['name']?.toString() ?? '', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
            if ((row['content'] ?? '').toString().isNotEmpty)
              Container(margin: const EdgeInsets.only(top: 10), padding: const EdgeInsets.all(10), decoration: BoxDecoration(color: AppColors.gray50, borderRadius: BorderRadius.circular(8)), child: Text(row['content'].toString())),
            const SizedBox(height: 12),
            TextField(controller: marks, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: homework['max_marks'] != null ? 'Marks (out of ${homework['max_marks']})' : 'Marks')),
            const SizedBox(height: 12),
            TextField(controller: feedback, maxLines: 3, decoration: const InputDecoration(labelText: 'Feedback (optional)')),
            const SizedBox(height: 12),
            FilledButton(onPressed: () => Navigator.pop(sheetContext, true), child: const Text('Save marks')),
          ],
        ),
      ),
    );
    if (ok == true && context.mounted) {
      final done = await runAction(context, () => api.post('erp/homework/${homework['id']}/grade', {'student_id': row['student_id'], 'marks': num.tryParse(marks.text), 'feedback': feedback.text.trim().isEmpty ? null : feedback.text.trim()}), success: 'Marked.');
      if (done != null) {
        reload();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(homework['title']?.toString() ?? 'Submissions')),
      body: AsyncView<List<Map<String, dynamic>>>(
        load: () async => asList(await api.get('erp/homework/${homework['id']}/submissions')),
        empty: (rows) => rows.isEmpty,
        builder: (context, rows, reload) => ListView.separated(
          itemCount: rows.length,
          separatorBuilder: (_, __) => const Divider(height: 1),
          itemBuilder: (context, index) {
            final row = rows[index];
            return Material(
              color: Colors.white,
              child: ListTile(
                title: Text(row['name']?.toString() ?? ''),
                subtitle: Text([row['admission_no'], if (row['marks'] != null) 'Marks ${row['marks']}'].whereType<Object>().join(' · ')),
                trailing: StatusChip(row['status']?.toString() ?? 'pending'),
                onTap: () => _grade(context, row, reload),
              ),
            );
          },
        ),
      ),
    );
  }
}

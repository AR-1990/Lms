import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'teacher_options.dart';

/// Class teacher's remarks on each student's result card (GET/PUT exams/{exam}/remarks).
class RemarksScreen extends StatefulWidget {
  const RemarksScreen({super.key});

  @override
  State<RemarksScreen> createState() => _RemarksScreenState();
}

class _RemarksScreenState extends State<RemarksScreen> {
  TeacherOptions? _options;
  int? _examId;
  int? _sectionId;
  List<Map<String, dynamic>> _rows = [];
  final Map<int, TextEditingController> _remarks = {};
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    TeacherOptions.load(context.read<Session>().api).then((options) {
      setState(() => _options = options);
    }).catchError((error) {
      setState(() => _error = error.toString());
    });
  }

  List<Map<String, dynamic>> get _mySections => (_options?.sections ?? []).where((section) => section['is_class_teacher'] == true).toList();

  List<Map<String, dynamic>> get _exams {
    final classIds = _mySections.map((section) => section['class_id']).toSet();
    return (_options?.exams ?? []).where((exam) => classIds.contains(exam['class_id'])).toList();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = asList(await context.read<Session>().api.get('erp/exams/$_examId/remarks', query: {'section_id': _sectionId}));
      for (final controller in _remarks.values) {
        controller.dispose();
      }
      _remarks
        ..clear()
        ..addAll({for (final row in rows) (row['id'] as num).toInt(): TextEditingController(text: row['class_teacher_remarks']?.toString() ?? '')});
      setState(() => _rows = rows);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _save() async {
    await runAction(
      context,
      () => context.read<Session>().api.put('erp/exams/$_examId/remarks', {
        'rows': [for (final entry in _remarks.entries) {'id': entry.key, 'class_teacher_remarks': entry.value.text.trim().isEmpty ? null : entry.value.text.trim()}],
      }),
      success: 'Remarks saved.',
    );
  }

  @override
  void dispose() {
    for (final controller in _remarks.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final exam = _exams.where((item) => item['id'] == _examId).toList();
    final sections = exam.isEmpty ? <Map<String, dynamic>>[] : _mySections.where((section) => section['class_id'] == exam.first['class_id']).toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Result remarks')),
      body: _options == null
          ? (_error == null ? const Center(child: CircularProgressIndicator()) : MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load', message: _error))
          : _mySections.isEmpty
              ? const MessageView(icon: Icons.rate_review_outlined, title: 'Only for class teachers', message: 'You are not the class teacher of any class this session.')
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 90),
                  children: [
                    DropdownButtonFormField<int>(
                      value: _examId,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Exam'),
                      items: [for (final item in _exams) DropdownMenuItem(value: (item['id'] as num).toInt(), child: Text(item['name'].toString()))],
                      onChanged: (value) {
                        final classId = _exams.firstWhere((item) => item['id'] == value)['class_id'];
                        final own = _mySections.where((section) => section['class_id'] == classId).toList();
                        setState(() {
                          _examId = value;
                          _sectionId = own.isEmpty ? null : (own.first['id'] as num).toInt();
                          _rows = [];
                        });
                        _load();
                      },
                    ),
                    if (sections.length > 1) ...[
                      const SizedBox(height: 12),
                      SectionPicker(sections: sections, value: _sectionId, onChanged: (value) {
                        setState(() => _sectionId = value);
                        _load();
                      }),
                    ],
                    if (_loading) const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator())),
                    if (_error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error!, style: const TextStyle(color: AppColors.red600))),
                    if (!_loading && _examId != null && _rows.isEmpty && _error == null)
                      const Padding(padding: EdgeInsets.only(top: 24), child: MessageView(icon: Icons.hourglass_empty_rounded, title: 'Result cards not ready', message: 'The exam cell prepares result cards first; then you can add remarks.')),
                    for (final row in _rows)
                      Padding(
                        padding: const EdgeInsets.only(top: 10),
                        child: Panel(
                          title: '${row['roll_no'] != null ? '${row['roll_no']}. ' : ''}${row['name']}',
                          trailing: Text('${row['percentage'] ?? '—'}% · ${row['grade'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.brand700)),
                          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                            if ((row['grade_remark'] ?? '').toString().isNotEmpty) Text('Grade remark: ${row['grade_remark']}', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                            const SizedBox(height: 8),
                            TextField(controller: _remarks[(row['id'] as num).toInt()], maxLines: 2, maxLength: 500, decoration: const InputDecoration(labelText: 'Class teacher remarks')),
                          ]),
                        ),
                      ),
                  ],
                ),
      bottomSheet: _rows.isEmpty ? null : SafeArea(child: Container(color: Colors.white, padding: const EdgeInsets.fromLTRB(16, 8, 16, 8), child: FilledButton(onPressed: _save, child: const Text('Save remarks')))),
    );
  }
}

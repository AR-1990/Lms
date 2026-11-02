import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'teacher_options.dart';

/// Marks entry for one exam paper in one of the teacher's classes: save as draft, or submit for approval
/// (GET marks, POST marks).
class MarksScreen extends StatefulWidget {
  const MarksScreen({super.key});

  @override
  State<MarksScreen> createState() => _MarksScreenState();
}

class _MarksScreenState extends State<MarksScreen> {
  TeacherOptions? _options;
  int? _examId;
  int? _sectionId;
  int? _paperId;
  Map<String, dynamic>? _paper;
  List<Map<String, dynamic>> _rows = [];
  final Map<int, TextEditingController> _theory = {};
  final Map<int, TextEditingController> _practical = {};
  bool _loading = false;
  String? _error;

  ApiClient get _api => context.read<Session>().api;

  @override
  void initState() {
    super.initState();
    TeacherOptions.load(context.read<Session>().api, fresh: true).then((options) {
      setState(() => _options = options);
    }).catchError((error) {
      setState(() => _error = error.toString());
    });
  }

  Map<String, dynamic>? get _exam {
    for (final exam in _options?.exams ?? <Map<String, dynamic>>[]) {
      if (exam['id'] == _examId) {
        return exam;
      }
    }
    return null;
  }

  List<Map<String, dynamic>> get _sections => _options == null || _exam == null ? [] : _options!.sections.where((section) => section['class_id'] == _exam!['class_id']).toList();

  /// Papers the teacher teaches in the chosen class (the server checks this too).
  List<Map<String, dynamic>> get _papers {
    if (_exam == null || _sectionId == null) {
      return [];
    }
    final section = _options!.section(_sectionId) ?? {};
    final mine = asList(section['subjects']).where((subject) => subject['is_mine'] == true).map((subject) => subject['id']).toSet();
    return asList(_exam!['papers']).where((paper) => mine.contains(paper['subject_id'])).toList();
  }

  Future<void> _loadSheet() async {
    if (_paperId == null || _sectionId == null) {
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final sheet = asMap(await _api.get('erp/marks', query: {'exam_subject_id': _paperId, 'section_id': _sectionId}));
      _apply(sheet);
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  void _apply(Map<String, dynamic> sheet) {
    for (final controller in [..._theory.values, ..._practical.values]) {
      controller.dispose();
    }
    _theory.clear();
    _practical.clear();
    final rows = asList(sheet['rows']);
    for (final row in rows) {
      final id = (row['enrollment_id'] as num).toInt();
      _theory[id] = TextEditingController(text: _clean(row['theory_marks']));
      _practical[id] = TextEditingController(text: _clean(row['practical_marks']));
    }
    setState(() {
      _paper = asMap(sheet['paper']);
      _rows = rows;
    });
  }

  String _clean(dynamic value) {
    final number = num.tryParse('${value ?? ''}');
    if (number == null) {
      return '';
    }
    return number == number.roundToDouble() ? number.toInt().toString() : number.toString();
  }

  Future<void> _save({required bool submit, String? reason}) async {
    final body = {
      'exam_subject_id': _paperId,
      'section_id': _sectionId,
      'submit': submit,
      if (reason != null) 'reason': reason,
      'rows': [
        for (final row in _rows)
          {
            'enrollment_id': row['enrollment_id'],
            'is_absent': row['is_absent'] == true,
            'theory_marks': row['is_absent'] == true ? null : num.tryParse(_theory[(row['enrollment_id'] as num).toInt()]!.text),
            if (((_paper?['practical_max_marks'] as num?) ?? 0) > 0) 'practical_marks': row['is_absent'] == true ? null : num.tryParse(_practical[(row['enrollment_id'] as num).toInt()]!.text),
          },
      ],
    };
    showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator()));
    try {
      final sheet = asMap(await _api.post('erp/marks', body));
      if (!mounted) {
        return;
      }
      Navigator.of(context, rootNavigator: true).pop();
      _apply(sheet);
      showMessage(context, submit ? 'Marks submitted for approval.' : 'Marks saved as draft.');
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      Navigator.of(context, rootNavigator: true).pop();
      if (error.field('reason') != null && reason == null) {
        final why = await _askReason();
        if (why != null) {
          await _save(submit: submit, reason: why);
        }
        return;
      }
      final firstRow = error.fields.keys.where((key) => key.startsWith('rows.')).toList();
      showMessage(context, firstRow.isEmpty ? error.message : '${error.message} (check row ${int.tryParse(firstRow.first.split('.')[1]) == null ? '' : int.parse(firstRow.first.split('.')[1]) + 1})', error: true);
    }
  }

  Future<String?> _askReason() async {
    final reason = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Why are you changing submitted marks?'),
        content: TextField(controller: reason, maxLines: 3, decoration: const InputDecoration(hintText: 'Reason')),
        actions: [TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Save'))],
      ),
    );
    return ok == true && reason.text.trim().isNotEmpty ? reason.text.trim() : null;
  }

  @override
  void dispose() {
    for (final controller in [..._theory.values, ..._practical.values]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final hasPractical = ((_paper?['practical_max_marks'] as num?) ?? 0) > 0;
    return Scaffold(
      appBar: AppBar(title: const Text('Marks entry')),
      body: _options == null
          ? (_error == null ? const Center(child: CircularProgressIndicator()) : MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load', message: _error))
          : _options!.exams.isEmpty
              ? const MessageView(icon: Icons.event_note_outlined, title: 'No exam is open for marks', message: 'Exams appear here once the exam cell schedules them.')
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                  children: [
                    DropdownButtonFormField<int>(
                      value: _examId,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Exam'),
                      items: [for (final exam in _options!.exams) DropdownMenuItem(value: (exam['id'] as num).toInt(), child: Text(exam['name'].toString(), overflow: TextOverflow.ellipsis))],
                      onChanged: (value) => setState(() {
                        _examId = value;
                        _sectionId = _sections.length == 1 ? (_sections.first['id'] as num).toInt() : null;
                        _paperId = null;
                        _rows = [];
                      }),
                    ),
                    const SizedBox(height: 12),
                    if (_examId != null)
                      _sections.isEmpty
                          ? const Text('You do not teach this exam\'s class.', style: TextStyle(color: AppColors.gray500))
                          : SectionPicker(sections: _sections, value: _sectionId, onChanged: (value) => setState(() {
                                _sectionId = value;
                                _paperId = null;
                                _rows = [];
                              })),
                    const SizedBox(height: 12),
                    if (_sectionId != null)
                      _papers.isEmpty
                          ? const Text('None of your subjects is in this exam.', style: TextStyle(color: AppColors.gray500))
                          : DropdownButtonFormField<int>(
                              value: _paperId,
                              isExpanded: true,
                              decoration: const InputDecoration(labelText: 'Subject'),
                              items: [for (final paper in _papers) DropdownMenuItem(value: (paper['id'] as num).toInt(), child: Text(paper['subject']?.toString() ?? ''))],
                              onChanged: (value) {
                                setState(() => _paperId = value);
                                _loadSheet();
                              },
                            ),
                    if (_loading) const Padding(padding: EdgeInsets.all(32), child: Center(child: CircularProgressIndicator())),
                    if (_error != null && !_loading) Padding(padding: const EdgeInsets.only(top: 16), child: Text(_error!, style: const TextStyle(color: AppColors.red600))),
                    if (_paper != null && _rows.isNotEmpty && !_loading) ...[
                      const SizedBox(height: 16),
                      Panel(
                        child: Wrap(spacing: 16, runSpacing: 6, children: [
                          Text('Theory /${_paper!['theory_max_marks']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          if (hasPractical) Text('Practical /${_paper!['practical_max_marks']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          Text('Pass ${_paper!['pass_marks']}', style: const TextStyle(color: AppColors.gray500)),
                          StatusChip(_paper!['exam_status']?.toString() ?? ''),
                        ]),
                      ),
                      const SizedBox(height: 8),
                      for (final row in _rows)
                        Card(
                          margin: const EdgeInsets.only(bottom: 8),
                          child: Padding(
                            padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Text(row['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                                    Row(children: [
                                      Text('Roll ${row['roll_no'] ?? '—'}', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                                      if (row['status'] != null) Padding(padding: const EdgeInsets.only(left: 6), child: StatusChip(row['status'].toString())),
                                    ]),
                                  ]),
                                ),
                                SizedBox(
                                  width: 64,
                                  child: TextField(
                                    controller: _theory[(row['enrollment_id'] as num).toInt()],
                                    enabled: row['is_absent'] != true,
                                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                    textAlign: TextAlign.center,
                                    decoration: const InputDecoration(isDense: true, hintText: 'Th', contentPadding: EdgeInsets.symmetric(vertical: 10, horizontal: 6)),
                                  ),
                                ),
                                if (hasPractical) ...[
                                  const SizedBox(width: 6),
                                  SizedBox(
                                    width: 64,
                                    child: TextField(
                                      controller: _practical[(row['enrollment_id'] as num).toInt()],
                                      enabled: row['is_absent'] != true,
                                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                      textAlign: TextAlign.center,
                                      decoration: const InputDecoration(isDense: true, hintText: 'Pr', contentPadding: EdgeInsets.symmetric(vertical: 10, horizontal: 6)),
                                    ),
                                  ),
                                ],
                                Column(children: [
                                  Checkbox(value: row['is_absent'] == true, onChanged: (value) => setState(() => row['is_absent'] = value == true)),
                                  const Text('Abs', style: TextStyle(fontSize: 11, color: AppColors.gray500)),
                                ]),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ],
                ),
      bottomSheet: _rows.isEmpty || _loading
          ? null
          : SafeArea(
              child: Container(
                color: Colors.white,
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Row(children: [
                  Expanded(child: OutlinedButton(onPressed: () => _save(submit: false), child: const Text('Save draft'))),
                  const SizedBox(width: 10),
                  Expanded(child: FilledButton(onPressed: () => _save(submit: true), child: const Text('Submit'))),
                ]),
              ),
            ),
    );
  }
}

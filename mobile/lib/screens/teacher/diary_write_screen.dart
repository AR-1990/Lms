import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'teacher_options.dart';

/// Write the class diary for a day: entries (homework can also become a homework task), a note, remarks for
/// single students, then publish so parents are notified (GET diary, POST diary/entries, PUT diary/note, ...).
class DiaryWriteScreen extends StatefulWidget {
  const DiaryWriteScreen({super.key});

  @override
  State<DiaryWriteScreen> createState() => _DiaryWriteScreenState();
}

class _DiaryWriteScreenState extends State<DiaryWriteScreen> {
  TeacherOptions? _options;
  int? _sectionId;
  DateTime _date = DateTime.now();
  int _version = 0;
  String? _error;

  ApiClient get _api => context.read<Session>().api;
  String get _day => DateFormat('yyyy-MM-dd').format(_date);

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

  void _reload() => setState(() => _version++);

  Future<void> _addEntry(Map<String, dynamic>? existing) async {
    final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => _EntryForm(api: _api, sectionId: _sectionId!, date: _day, subjects: _options!.subjectsFor(_sectionId), entry: existing)));
    if (saved == true) {
      _reload();
    }
  }

  Future<void> _deleteEntry(Map<String, dynamic> entry) async {
    if (!await confirm(context, 'Delete this entry?', entry['title']?.toString() ?? entry['details']?.toString() ?? '', action: 'Delete') || !mounted) {
      return;
    }
    try {
      // Staff deletes go to the admin for approval (the answer then carries the request); admins delete at once.
      final result = await _api.delete('erp/diary/entries/${entry['id']}');
      if (mounted) {
        showMessage(context, result == null ? 'Entry deleted.' : 'Sent to the admin for approval.');
      }
    } catch (error) {
      if (mounted) {
        showMessage(context, error.toString(), error: true);
      }
    }
    _reload();
  }

  Future<void> _editNote(String? current) async {
    final note = TextEditingController(text: current ?? '');
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Note for parents'),
        content: TextField(controller: note, maxLines: 5, decoration: const InputDecoration(hintText: 'e.g. Tomorrow is sports day')),
        actions: [TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Save'))],
      ),
    );
    if (ok == true && mounted) {
      await runAction(context, () => _api.put('erp/diary/note', {'section_id': _sectionId, 'date': _day, 'note': note.text.trim()}), success: 'Note saved.');
      _reload();
    }
  }

  Future<void> _addRemark(int diaryId, List<Map<String, dynamic>> students) async {
    final types = asList(await _api.get('erp/lookup-options/diary_remark_type').catchError((_) => <dynamic>[]));
    if (!mounted) {
      return;
    }
    int? studentId;
    int? typeId;
    final remark = TextEditingController();
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (sheetContext, setSheet) => Padding(
          padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.of(sheetContext).viewInsets.bottom + 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Remark for one student', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
              const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                value: studentId,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Student'),
                items: [for (final student in students) DropdownMenuItem(value: (student['student_id'] as num).toInt(), child: Text(student['name'].toString()))],
                onChanged: (value) => setSheet(() => studentId = value),
              ),
              if (types.isNotEmpty) ...[
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  value: typeId,
                  decoration: const InputDecoration(labelText: 'Type'),
                  items: [for (final type in types) DropdownMenuItem(value: (type['id'] as num).toInt(), child: Text(type['name'].toString()))],
                  onChanged: (value) => setSheet(() => typeId = value),
                ),
              ],
              const SizedBox(height: 12),
              TextField(controller: remark, maxLines: 3, decoration: const InputDecoration(labelText: 'Remark')),
              const SizedBox(height: 12),
              FilledButton(onPressed: () => Navigator.pop(sheetContext, true), child: const Text('Add remark')),
            ],
          ),
        ),
      ),
    );
    if (ok == true && studentId != null && mounted) {
      await runAction(context, () => _api.post('erp/diaries/$diaryId/remarks', {'student_id': studentId, if (typeId != null) 'remark_type_id': typeId, 'remark': remark.text.trim()}), success: 'Remark added.');
      _reload();
    }
  }

  Future<void> _publish(int diaryId) async {
    if (await confirm(context, 'Publish the diary?', 'Parents will be notified and can read and sign it.', action: 'Publish') && mounted) {
      await runAction(context, () => _api.post('erp/diaries/$diaryId/publish'), success: 'Diary published. Parents have been notified.');
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Class diary')),
      body: _options == null
          ? (_error == null ? const Center(child: CircularProgressIndicator()) : MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load', message: _error))
          : _options!.sections.isEmpty
              ? const NoSections()
              : Column(
                  children: [
                    Container(
                      color: Colors.white,
                      padding: const EdgeInsets.all(12),
                      child: Row(children: [
                        Expanded(flex: 3, child: SectionPicker(sections: _options!.sections, value: _sectionId, onChanged: (value) => setState(() => _sectionId = value))),
                        const SizedBox(width: 10),
                        Expanded(
                          flex: 2,
                          child: InkWell(
                            onTap: () async {
                              final picked = await showDatePicker(context: context, initialDate: _date, firstDate: DateTime.now().subtract(const Duration(days: 60)), lastDate: DateTime.now().add(const Duration(days: 7)));
                              if (picked != null) {
                                setState(() => _date = picked);
                              }
                            },
                            child: InputDecorator(decoration: const InputDecoration(labelText: 'Date'), child: Text(DateFormat('d MMM').format(_date))),
                          ),
                        ),
                      ]),
                    ),
                    Expanded(
                      child: AsyncView<Map<String, dynamic>>(
                        key: ValueKey('$_sectionId-$_day-$_version'),
                        load: () async => asMap(await _api.get('erp/diary', query: {'section_id': _sectionId, 'date': _day})),
                        builder: (context, diary, reload) {
                          final entries = asList(diary['entries']);
                          final remarks = asList(diary['remarks']);
                          final status = diary['status']?.toString() ?? 'empty';
                          final diaryId = (diary['id'] as num?)?.toInt();
                          final unsigned = asList(diary['unsigned']);
                          return ListView(
                            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                            children: [
                              Row(children: [StatusChip(status == 'empty' ? 'Not started' : status, tone: status == 'published' ? 'published' : 'draft'), const Spacer(), if (status == 'published' && unsigned.isNotEmpty) Text('${unsigned.length} not signed yet', style: const TextStyle(color: AppColors.amber700))]),
                              const SizedBox(height: 12),
                              Panel(
                                title: 'Note for parents',
                                trailing: IconButton(icon: const Icon(Icons.edit_rounded), onPressed: () => _editNote(diary['note']?.toString())),
                                child: Text((diary['note'] ?? '').toString().isEmpty ? 'No note.' : diary['note'].toString(), style: TextStyle(color: (diary['note'] ?? '').toString().isEmpty ? AppColors.gray400 : AppColors.gray800)),
                              ),
                              const SizedBox(height: 12),
                              Panel(
                                title: 'Entries',
                                child: entries.isEmpty
                                    ? const Text('No entries yet. Tap "Add entry".', style: TextStyle(color: AppColors.gray400))
                                    : Column(
                                        children: [
                                          for (final entry in entries)
                                            ListTile(
                                              contentPadding: EdgeInsets.zero,
                                              title: Text('${entry['entry_type'] ?? ''}${entry['subject'] != null ? ' · ${entry['subject']}' : ''}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                              subtitle: Text([entry['title'], entry['details'], if (entry['due_on'] != null) 'Due ${formatDate(entry['due_on'])}'].whereType<Object>().join('\n')),
                                              trailing: PopupMenuButton<String>(
                                                onSelected: (action) => action == 'edit' ? _addEntry(entry) : _deleteEntry(entry),
                                                itemBuilder: (_) => const [PopupMenuItem(value: 'edit', child: Text('Edit')), PopupMenuItem(value: 'delete', child: Text('Delete'))],
                                              ),
                                            ),
                                        ],
                                      ),
                              ),
                              const SizedBox(height: 12),
                              Panel(
                                title: 'Student remarks',
                                trailing: diaryId == null ? null : TextButton(onPressed: () => _addRemark(diaryId, asList(diary['students'])), child: const Text('Add')),
                                child: remarks.isEmpty
                                    ? Text(diaryId == null ? 'Add an entry or note first.' : 'No remarks.', style: const TextStyle(color: AppColors.gray400))
                                    : Column(children: [for (final remark in remarks) InfoRow(remark['student']?.toString() ?? '', '${remark['type'] != null ? '${remark['type']}: ' : ''}${remark['remark']}')]),
                              ),
                              const SizedBox(height: 16),
                              Row(
                                children: [
                                  Expanded(child: OutlinedButton.icon(onPressed: () => _addEntry(null), icon: const Icon(Icons.add_rounded), label: const Text('Add entry'))),
                                  const SizedBox(width: 10),
                                  Expanded(child: FilledButton.icon(onPressed: diaryId == null || status == 'published' ? null : () => _publish(diaryId), icon: const Icon(Icons.send_rounded), label: Text(status == 'published' ? 'Published' : 'Publish'))),
                                ],
                              ),
                            ],
                          );
                        },
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _EntryForm extends StatefulWidget {
  const _EntryForm({required this.api, required this.sectionId, required this.date, required this.subjects, this.entry});

  final ApiClient api;
  final int sectionId;
  final String date;
  final List<Map<String, dynamic>> subjects;
  final Map<String, dynamic>? entry;

  @override
  State<_EntryForm> createState() => _EntryFormState();
}

class _EntryFormState extends State<_EntryForm> {
  List<Map<String, dynamic>> _types = [];
  int? _typeId;
  int? _subjectId;
  late final _title = TextEditingController(text: widget.entry?['title']?.toString() ?? '');
  late final _details = TextEditingController(text: widget.entry?['details']?.toString() ?? '');
  final _maxMarks = TextEditingController();
  DateTime? _dueOn;
  bool _createHomework = false;
  bool _allowSubmission = true;
  Object? _error;
  bool _busy = false;

  bool get _editing => widget.entry != null;

  @override
  void initState() {
    super.initState();
    _typeId = (widget.entry?['entry_type_id'] as num?)?.toInt();
    _subjectId = (widget.entry?['subject_id'] as num?)?.toInt();
    _dueOn = widget.entry?['due_on'] == null ? null : DateTime.tryParse(widget.entry!['due_on'].toString());
    widget.api.get('erp/lookup-options/diary_entry_type').then((types) {
      setState(() {
        _types = asList(types);
        _typeId ??= _types.isEmpty ? null : (_types.first['id'] as num).toInt();
      });
    }).catchError((error) {
      setState(() => _error = error);
    });
  }

  bool get _isHomeworkType => _types.any((type) => type['id'] == _typeId && type['name'].toString().toLowerCase().contains('homework'));

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final body = <String, dynamic>{
      if (!_editing) 'section_id': widget.sectionId,
      if (!_editing) 'diary_date': widget.date,
      'entry_type_id': _typeId,
      'subject_id': _subjectId,
      'title': _title.text.trim().isEmpty ? null : _title.text.trim(),
      'details': _details.text.trim(),
      'due_on': _dueOn == null ? null : DateFormat('yyyy-MM-dd').format(_dueOn!),
      if (!_editing && _createHomework) 'create_homework': true,
      if (!_editing && _createHomework && int.tryParse(_maxMarks.text) != null) 'max_marks': int.parse(_maxMarks.text),
      if (!_editing && _createHomework) 'allow_submission': _allowSubmission,
    };
    try {
      if (_editing) {
        await widget.api.put('erp/diary/entries/${widget.entry!['id']}', body);
      } else {
        await widget.api.post('erp/diary/entries', body);
      }
      if (mounted) {
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
    final general = _error != null && _error is ApiException && (_error as ApiException).fields.isEmpty ? _error.toString() : null;
    return Scaffold(
      appBar: AppBar(title: Text(_editing ? 'Edit entry' : 'Add diary entry')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DropdownButtonFormField<int>(
            value: _typeId,
            decoration: InputDecoration(labelText: 'Type', errorText: fieldError(_error, 'entry_type_id')),
            items: [for (final type in _types) DropdownMenuItem(value: (type['id'] as num).toInt(), child: Text(type['name'].toString()))],
            onChanged: (value) => setState(() => _typeId = value),
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<int?>(
            value: _subjectId,
            isExpanded: true,
            decoration: InputDecoration(labelText: 'Subject', errorText: fieldError(_error, 'subject_id')),
            items: [const DropdownMenuItem<int?>(value: null, child: Text('General')), for (final subject in widget.subjects) DropdownMenuItem<int?>(value: (subject['id'] as num).toInt(), child: Text(subject['name'].toString()))],
            onChanged: (value) => setState(() => _subjectId = value),
          ),
          const SizedBox(height: 14),
          TextField(controller: _title, decoration: InputDecoration(labelText: 'Title (optional)', errorText: fieldError(_error, 'title'))),
          const SizedBox(height: 14),
          TextField(controller: _details, maxLines: 5, decoration: InputDecoration(labelText: 'Details', errorText: fieldError(_error, 'details'))),
          const SizedBox(height: 14),
          InkWell(
            onTap: () async {
              final picked = await showDatePicker(context: context, initialDate: _dueOn ?? DateTime.now().add(const Duration(days: 1)), firstDate: DateTime.now().subtract(const Duration(days: 7)), lastDate: DateTime.now().add(const Duration(days: 90)));
              setState(() => _dueOn = picked);
            },
            child: InputDecorator(decoration: InputDecoration(labelText: 'Due date (optional)', errorText: fieldError(_error, 'due_on')), child: Text(_dueOn == null ? 'None' : DateFormat('d MMM yyyy').format(_dueOn!))),
          ),
          if (!_editing && _isHomeworkType) ...[
            const SizedBox(height: 8),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Also create a homework task'), subtitle: const Text('Students can hand it in and you can mark it'), value: _createHomework, onChanged: (value) => setState(() => _createHomework = value)),
            if (_createHomework) ...[
              TextField(controller: _maxMarks, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'Out of (marks, optional)', errorText: fieldError(_error, 'max_marks'))),
              SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Students hand in online'), value: _allowSubmission, onChanged: (value) => setState(() => _allowSubmission = value)),
              if (fieldError(_error, 'create_homework') != null) Text(fieldError(_error, 'create_homework')!, style: const TextStyle(color: AppColors.red600)),
            ],
          ],
          if (general != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(general, style: const TextStyle(color: AppColors.red600))),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _save, child: const Text('Save')),
        ],
      ),
    );
  }
}

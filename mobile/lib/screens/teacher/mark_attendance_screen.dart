import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'teacher_options.dart';

/// The class register: pick a class and date, tap each student's status, save (GET attendance/sheet, POST attendance).
/// Parents of absent students are alerted by the server.
class MarkAttendanceScreen extends StatefulWidget {
  const MarkAttendanceScreen({super.key, this.standalone = false});

  final bool standalone;

  @override
  State<MarkAttendanceScreen> createState() => _MarkAttendanceScreenState();
}

class _MarkAttendanceScreenState extends State<MarkAttendanceScreen> {
  TeacherOptions? _options;
  int? _sectionId;
  DateTime _date = DateTime.now();
  Map<String, dynamic>? _sheet;
  List<Map<String, dynamic>> _rows = [];
  String? _error;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    try {
      final options = await TeacherOptions.load(context.read<Session>().api);
      final classTeacher = options.sections.where((section) => section['is_class_teacher'] == true).toList();
      setState(() {
        _options = options;
        _sectionId = options.sections.isEmpty ? null : ((classTeacher.isNotEmpty ? classTeacher.first : options.sections.first)['id'] as num).toInt();
      });
      await _loadSheet();
    } catch (error) {
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  Future<void> _loadSheet() async {
    if (_sectionId == null) {
      setState(() => _loading = false);
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final sheet = asMap(await context.read<Session>().api.get('erp/attendance/sheet', query: {'section_id': _sectionId, 'date': DateFormat('yyyy-MM-dd').format(_date)}));
      setState(() {
        _sheet = sheet;
        // Unmarked students start as present, so the teacher only taps the exceptions.
        _rows = asList(sheet['rows']).map((row) => {...row, 'status': row['status'] ?? 'present'}).toList();
      });
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final counts = await runAction(context, () => context.read<Session>().api.post('erp/attendance', {
          'section_id': _sectionId,
          'date': DateFormat('yyyy-MM-dd').format(_date),
          'rows': [
            for (final row in _rows) {'enrollment_id': row['enrollment_id'], 'status': row['status'], if ((row['remarks'] ?? '').toString().isNotEmpty) 'remarks': row['remarks']},
          ],
        }));
    if (mounted) {
      setState(() => _saving = false);
      if (counts is Map) {
        showMessage(context, 'Saved: ${counts.entries.map((entry) => '${entry.value} ${humanize(entry.key.toString()).toLowerCase()}').join(', ')}');
        _loadSheet();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final counts = <String, int>{};
    for (final row in _rows) {
      counts[row['status'].toString()] = (counts[row['status'].toString()] ?? 0) + 1;
    }
    final blocked = _sheet != null && (_sheet!['holiday'] != null || _sheet!['is_sunday'] == true);

    return Scaffold(
      appBar: AppBar(automaticallyImplyLeading: widget.standalone, title: const Text('Attendance')),
      body: _options == null && _loading
          ? const Center(child: CircularProgressIndicator())
          : _options == null
              ? MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load', message: _error, actionLabel: 'Try again', onAction: _init)
              : _options!.sections.isEmpty
                  ? const NoSections()
                  : Column(
                      children: [
                        Container(
                          color: Colors.white,
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                          child: Row(
                            children: [
                              Expanded(
                                flex: 3,
                                child: SectionPicker(sections: _options!.sections, value: _sectionId, onChanged: (value) {
                                  setState(() => _sectionId = value);
                                  _loadSheet();
                                }),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                flex: 2,
                                child: InkWell(
                                  onTap: () async {
                                    final picked = await showDatePicker(context: context, initialDate: _date, firstDate: DateTime.now().subtract(const Duration(days: 60)), lastDate: DateTime.now());
                                    if (picked != null) {
                                      setState(() => _date = picked);
                                      _loadSheet();
                                    }
                                  },
                                  child: InputDecorator(decoration: const InputDecoration(labelText: 'Date'), child: Text(DateFormat('d MMM').format(_date))),
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (_sheet != null && !_loading)
                          Container(
                            width: double.infinity,
                            color: blocked ? AppColors.amber50 : (_sheet!['marked'] == true ? AppColors.green50 : AppColors.brand50),
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            child: Text(
                              blocked ? (_sheet!['holiday'] != null ? 'Holiday: ${_sheet!['holiday']}' : 'Sunday: no attendance') : (_sheet!['marked'] == true ? 'Already marked. Changes will update it.' : 'Not marked yet. Everyone starts as present.'),
                              style: TextStyle(color: blocked ? AppColors.amber700 : (_sheet!['marked'] == true ? AppColors.green600 : AppColors.brand700), fontWeight: FontWeight.w600),
                            ),
                          ),
                        Expanded(
                          child: _loading
                              ? const Center(child: CircularProgressIndicator())
                              : _error != null
                                  ? MessageView(icon: Icons.error_outline_rounded, title: 'Could not load', message: _error, actionLabel: 'Try again', onAction: _loadSheet)
                                  : RefreshIndicator(
                                      onRefresh: _loadSheet,
                                      child: ListView.separated(
                                        padding: const EdgeInsets.only(bottom: 90),
                                        itemCount: _rows.length,
                                        separatorBuilder: (_, __) => const Divider(height: 1),
                                        itemBuilder: (context, index) {
                                          final row = _rows[index];
                                          return Container(
                                            color: Colors.white,
                                            padding: const EdgeInsets.fromLTRB(16, 10, 12, 10),
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Row(children: [
                                                  SizedBox(width: 32, child: Text('${row['roll_no'] ?? index + 1}', style: const TextStyle(color: AppColors.gray400, fontWeight: FontWeight.w700))),
                                                  Expanded(child: Text(row['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600))),
                                                  if (row['on_leave'] == true) const StatusChip('On leave', tone: 'leave'),
                                                ]),
                                                const SizedBox(height: 6),
                                                Row(
                                                  children: [
                                                    const SizedBox(width: 32),
                                                    for (final status in attendanceStatuses)
                                                      Padding(
                                                        padding: const EdgeInsets.only(right: 6),
                                                        child: ChoiceChip(
                                                          label: Text(shortStatus(status)),
                                                          selected: row['status'] == status,
                                                          showCheckmark: false,
                                                          selectedColor: statusBackground(status),
                                                          labelStyle: TextStyle(fontWeight: FontWeight.w700, color: row['status'] == status ? Colors.white : AppColors.gray600),
                                                          visualDensity: VisualDensity.compact,
                                                          onSelected: blocked ? null : (_) => setState(() => row['status'] = status),
                                                        ),
                                                      ),
                                                  ],
                                                ),
                                              ],
                                            ),
                                          );
                                        },
                                      ),
                                    ),
                        ),
                      ],
                    ),
      bottomSheet: _rows.isEmpty || _loading || _options == null
          ? null
          : SafeArea(
              child: Container(
                color: Colors.white,
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        attendanceStatuses.where((status) => (counts[status] ?? 0) > 0).map((status) => '${shortStatus(status)} ${counts[status]}').join('  ·  '),
                        style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.gray700),
                      ),
                    ),
                    FilledButton(onPressed: _saving || blocked ? null : _save, child: const Text('Save register')),
                  ],
                ),
              ),
            ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'family_state.dart';

/// The child's leave requests and a form to ask for leave (GET/POST leave-requests).
class LeaveScreen extends StatelessWidget {
  const LeaveScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const ChildSwitcher(title: 'Leave')),
      body: ChildScope(
        builder: (context, studentId) => AsyncView<List<Map<String, dynamic>>>(
          load: () async => asList(await api.get('erp/leave-requests', query: {'student_id': studentId, 'per_page': 50})),
          builder: (context, items, reload) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              FilledButton.icon(
                onPressed: () async {
                  final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => _ApplyLeave(studentId: studentId)));
                  if (saved == true) {
                    reload();
                  }
                },
                icon: const Icon(Icons.add_rounded),
                label: const Text('Ask for leave'),
              ),
              const SizedBox(height: 16),
              if (items.isEmpty) const MessageView(icon: Icons.event_available_rounded, title: 'No leave requests'),
              for (final leave in items)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Panel(
                    title: leave['from_date'] == leave['to_date'] ? formatDate(leave['from_date']) : '${formatDate(leave['from_date'], pattern: 'd MMM')} – ${formatDate(leave['to_date'])}',
                    trailing: StatusChip(leave['status']?.toString() ?? ''),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(leave['reason']?.toString() ?? ''),
                        if ((leave['review_remarks'] ?? '').toString().isNotEmpty)
                          Padding(padding: const EdgeInsets.only(top: 6), child: Text('School: ${leave['review_remarks']}', style: const TextStyle(color: AppColors.gray500))),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ApplyLeave extends StatefulWidget {
  const _ApplyLeave({required this.studentId});

  final int studentId;

  @override
  State<_ApplyLeave> createState() => _ApplyLeaveState();
}

class _ApplyLeaveState extends State<_ApplyLeave> {
  final _reason = TextEditingController();
  late DateTimeRange _range = DateTimeRange(start: DateTime.now(), end: DateTime.now());
  Object? _error;
  bool _busy = false;

  Future<void> _save() async {
    final format = DateFormat('yyyy-MM-dd');
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Session>().api.post('erp/leave-requests', {
        'student_id': widget.studentId,
        'from_date': format.format(_range.start),
        'to_date': format.format(_range.end),
        'reason': _reason.text.trim(),
      });
      if (mounted) {
        showMessage(context, 'Leave request sent to the school.');
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
    final general = _error != null && (fieldError(_error, 'reason') ?? fieldError(_error, 'from_date') ?? fieldError(_error, 'to_date')) == null ? _error.toString() : null;
    return Scaffold(
      appBar: AppBar(title: const Text('Ask for leave')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          InkWell(
            onTap: () async {
              final picked = await showDateRangePicker(context: context, firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 120)), initialDateRange: _range);
              if (picked != null) {
                setState(() => _range = picked);
              }
            },
            child: InputDecorator(
              decoration: InputDecoration(labelText: 'Dates', errorText: fieldError(_error, 'from_date') ?? fieldError(_error, 'to_date'), suffixIcon: const Icon(Icons.date_range_rounded)),
              child: Text(_range.start == _range.end ? formatDate(_range.start.toIso8601String()) : '${formatDate(_range.start.toIso8601String(), pattern: 'd MMM')} – ${formatDate(_range.end.toIso8601String())}'),
            ),
          ),
          const SizedBox(height: 14),
          TextField(controller: _reason, maxLines: 4, decoration: InputDecoration(labelText: 'Reason', errorText: fieldError(_error, 'reason'))),
          if (general != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(general, style: const TextStyle(color: AppColors.red600))),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _save, child: const Text('Send request')),
        ],
      ),
    );
  }
}

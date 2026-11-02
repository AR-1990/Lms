import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// Leave requests from parents: approve or reject the pending ones (GET leave-requests, POST .../decision).
class StudentLeavesScreen extends StatefulWidget {
  const StudentLeavesScreen({super.key});

  @override
  State<StudentLeavesScreen> createState() => _StudentLeavesScreenState();
}

class _StudentLeavesScreenState extends State<StudentLeavesScreen> {
  String _status = 'pending';

  Future<void> _decide(Map<String, dynamic> leave, String decision, Future<void> Function() reload) async {
    final remarks = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(decision == 'approved' ? 'Approve leave?' : 'Reject leave?'),
        content: TextField(controller: remarks, maxLines: 2, decoration: InputDecoration(labelText: decision == 'approved' ? 'Remarks (optional)' : 'Reason for parents')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(decision == 'approved' ? 'Approve' : 'Reject')),
        ],
      ),
    );
    if (ok == true && mounted) {
      final done = await runAction(context, () => context.read<Session>().api.post('erp/leave-requests/${leave['id']}/decision', {'decision': decision, 'remarks': remarks.text.trim().isEmpty ? null : remarks.text.trim()}), success: decision == 'approved' ? 'Approved. Parents are notified.' : 'Rejected. Parents are notified.');
      if (done != null) {
        reload();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const Text('Student leave')),
      body: Column(
        children: [
          Container(
            color: Colors.white,
            padding: const EdgeInsets.all(12),
            child: SegmentedButton<String>(
              segments: const [ButtonSegment(value: 'pending', label: Text('Pending')), ButtonSegment(value: 'approved', label: Text('Approved')), ButtonSegment(value: 'rejected', label: Text('Rejected'))],
              selected: {_status},
              onSelectionChanged: (value) => setState(() => _status = value.first),
            ),
          ),
          Expanded(
            child: AsyncView<List<Map<String, dynamic>>>(
              key: ValueKey(_status),
              load: () async => asList(await api.get('erp/leave-requests', query: {'status': _status, 'per_page': 50})),
              empty: (items) => items.isEmpty,
              builder: (context, items, reload) => ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, index) {
                  final leave = items[index];
                  return Panel(
                    title: leave['student_name']?.toString() ?? '',
                    trailing: StatusChip(leave['status']?.toString() ?? ''),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(leave['from_date'] == leave['to_date'] ? formatDate(leave['from_date']) : '${formatDate(leave['from_date'], pattern: 'd MMM')} – ${formatDate(leave['to_date'])}', style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.gray700)),
                        const SizedBox(height: 4),
                        Text(leave['reason']?.toString() ?? ''),
                        if (leave['requested_by_name'] != null) Text('By ${leave['requested_by_name']}', style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
                        if ((leave['review_remarks'] ?? '').toString().isNotEmpty) Text('Remarks: ${leave['review_remarks']}', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                        if (leave['status'] == 'pending') ...[
                          const SizedBox(height: 10),
                          Row(children: [
                            Expanded(child: OutlinedButton(onPressed: () => _decide(leave, 'rejected', reload), child: const Text('Reject'))),
                            const SizedBox(width: 10),
                            Expanded(child: FilledButton(onPressed: () => _decide(leave, 'approved', reload), child: const Text('Approve'))),
                          ]),
                        ],
                      ],
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

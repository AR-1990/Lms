import 'dart:io';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// School notices (announcements) for this user; opening one marks it read, and some ask for a confirmation.
class NoticesScreen extends StatelessWidget {
  const NoticesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const Text('Notices')),
      body: AsyncView<List<Map<String, dynamic>>>(
        load: () async => asList(await api.get('erp/my-announcements', query: {'per_page': 50})),
        empty: (items) => items.isEmpty,
        builder: (context, items, reload) => ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (context, index) {
            final notice = items[index];
            final category = asMap(notice['category']);
            final unread = notice['read_at'] == null;
            return Card(
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () async {
                  await Navigator.push(context, MaterialPageRoute(builder: (_) => NoticeDetailScreen(notice: notice)));
                  reload();
                },
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          if (notice['is_pinned'] == true) const Padding(padding: EdgeInsets.only(right: 6), child: Icon(Icons.push_pin_rounded, size: 16, color: AppColors.brand600)),
                          if (category['name'] != null) StatusChip(category['name'].toString(), tone: 'other'),
                          if (notice['is_urgent'] == true) const Padding(padding: EdgeInsets.only(left: 6), child: StatusChip('Urgent', tone: 'overdue')),
                          const Spacer(),
                          if (unread) Container(width: 9, height: 9, decoration: const BoxDecoration(color: AppColors.brand500, shape: BoxShape.circle)),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(notice['title']?.toString() ?? '', style: TextStyle(fontSize: 16, fontWeight: unread ? FontWeight.w800 : FontWeight.w600, color: AppColors.gray900)),
                      if ((notice['summary'] ?? '').toString().isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(notice['summary'].toString(), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AppColors.gray600)),
                      ],
                      const SizedBox(height: 8),
                      Text(formatDate(notice['publish_at']), style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

class NoticeDetailScreen extends StatefulWidget {
  const NoticeDetailScreen({super.key, required this.notice});

  final Map<String, dynamic> notice;

  @override
  State<NoticeDetailScreen> createState() => _NoticeDetailScreenState();
}

class _NoticeDetailScreenState extends State<NoticeDetailScreen> {
  late Map<String, dynamic> notice = widget.notice;

  @override
  void initState() {
    super.initState();
    if (notice['read_at'] == null) {
      _mark(false);
    }
  }

  Future<void> _mark(bool acknowledge) async {
    final api = context.read<Session>().api;
    final call = api.post('erp/my-announcements/${notice['id']}/read', {'acknowledge': acknowledge, 'device': Platform.isIOS ? 'ios' : 'android'});
    final data = acknowledge ? await runAction(context, () => call, success: 'Thank you for confirming.') : await call.catchError((_) => null);
    if (data is Map && mounted) {
      setState(() => notice = {...notice, 'read_at': data['read_at'], 'acknowledged_at': data['acknowledged_at']});
    }
  }

  @override
  Widget build(BuildContext context) {
    final needsAck = notice['requires_acknowledgement'] == true && notice['acknowledged_at'] == null;
    return Scaffold(
      appBar: AppBar(title: const Text('Notice')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(notice['title']?.toString() ?? '', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 6),
                Text(formatDate(notice['publish_at'], pattern: 'EEEE, d MMMM yyyy'), style: const TextStyle(color: AppColors.gray500)),
                const Divider(height: 28),
                SelectableText(_plain(notice['body']?.toString() ?? notice['summary']?.toString() ?? ''), style: const TextStyle(fontSize: 15, height: 1.5)),
              ],
            ),
          ),
          if (notice['requires_acknowledgement'] == true) ...[
            const SizedBox(height: 16),
            needsAck
                ? FilledButton.icon(onPressed: () => _mark(true), icon: const Icon(Icons.check_rounded), label: const Text('I have read this'))
                : Row(children: [const Icon(Icons.verified_rounded, color: AppColors.green600), const SizedBox(width: 8), Text('Confirmed ${formatDate(notice['acknowledged_at'])}', style: const TextStyle(color: AppColors.green600, fontWeight: FontWeight.w600))]),
          ],
        ],
      ),
    );
  }

  /// Notice bodies may hold simple HTML from the editor; show them as clean text.
  String _plain(String html) => html
      .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
      .replaceAll(RegExp(r'</(p|div|li|h[1-6])>', caseSensitive: false), '\n')
      .replaceAll(RegExp(r'<li[^>]*>', caseSensitive: false), '• ')
      .replaceAll(RegExp(r'<[^>]+>'), '')
      .replaceAll('&nbsp;', ' ')
      .replaceAll('&amp;', '&')
      .replaceAll('&lt;', '<')
      .replaceAll('&gt;', '>')
      .replaceAll('&quot;', '"')
      .replaceAll('&#039;', "'")
      .replaceAll(RegExp(r'\n{3,}'), '\n\n')
      .trim();
}

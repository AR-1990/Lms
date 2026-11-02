import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/alerts.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// The bell: every notification the school sent this user (the portal always gets them; push is extra).
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final List<Map<String, dynamic>> _items = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  int _seenUnread = -1;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool more = false}) async {
    final api = context.read<Session>().api;
    if (more) {
      setState(() => _loadingMore = true);
    } else {
      _error = null;
    }
    try {
      final data = await api.get('erp/notifications', query: {'page': more ? _page + 1 : 1, 'per_page': 20});
      final meta = asMap(data['meta']);
      setState(() {
        if (!more) {
          _items.clear();
        }
        _items.addAll(asList(data));
        _page = (meta['current_page'] as num?)?.toInt() ?? 1;
        _lastPage = (meta['last_page'] as num?)?.toInt() ?? 1;
      });
    } catch (error) {
      if (!more) {
        setState(() => _error = error.toString());
      }
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _loadingMore = false;
        });
      }
    }
  }

  Future<void> _open(Map<String, dynamic> item) async {
    final alerts = context.read<Alerts>();
    if (item['read_at'] == null) {
      try {
        final data = await context.read<Session>().api.post('erp/notifications/${item['id']}/read');
        setState(() => item['read_at'] = DateTime.now().toIso8601String());
        alerts.setUnreadNotifications((data['unread'] as num?)?.toInt() ?? alerts.unreadNotifications);
      } catch (_) {}
    }
    alerts.open(AlertTarget(eventKey: item['event_key']?.toString()));
  }

  Future<void> _readAll() async {
    final alerts = context.read<Alerts>();
    final done = await runAction(context, () => context.read<Session>().api.post('erp/notifications/read-all'));
    if (done != null && mounted) {
      setState(() {
        for (final item in _items) {
          item['read_at'] ??= DateTime.now().toIso8601String();
        }
      });
      alerts.setUnreadNotifications(0);
    }
  }

  @override
  Widget build(BuildContext context) {
    final unread = context.watch<Alerts>().unreadNotifications;
    // A live notification arrived: reload so it shows at the top.
    if (_seenUnread != -1 && unread > _seenUnread && !_loading) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _load());
    }
    _seenUnread = unread;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [if (unread > 0) TextButton(onPressed: _readAll, child: const Text('Mark all read'))],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _items.isEmpty
              ? MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load', message: _error, actionLabel: 'Try again', onAction: _load)
              : RefreshIndicator(
                  onRefresh: () async {
                    await _load();
                    if (context.mounted) {
                      await context.read<Alerts>().refresh();
                    }
                  },
                  child: _items.isEmpty
                      ? ListView(children: const [SizedBox(height: 120), MessageView(icon: Icons.notifications_none_rounded, title: 'No notifications', message: 'School alerts will appear here.')])
                      : NotificationListener<ScrollNotification>(
                          onNotification: (scroll) {
                            if (scroll.metrics.extentAfter < 300 && !_loadingMore && _page < _lastPage) {
                              _load(more: true);
                            }
                            return false;
                          },
                          child: ListView.separated(
                            itemCount: _items.length + (_page < _lastPage ? 1 : 0),
                            separatorBuilder: (_, __) => const Divider(height: 1),
                            itemBuilder: (context, index) {
                              if (index >= _items.length) {
                                return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()));
                              }
                              final item = _items[index];
                              final isUnread = item['read_at'] == null;
                              return Material(
                                color: isUnread ? AppColors.brand50 : Colors.white,
                                child: ListTile(
                                  onTap: () => _open(item),
                                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                                  leading: CircleAvatar(
                                    backgroundColor: item['is_urgent'] == true ? AppColors.red50 : AppColors.brand100,
                                    foregroundColor: item['is_urgent'] == true ? AppColors.red600 : AppColors.brand700,
                                    child: Icon(_iconFor(item['event_key']?.toString() ?? '')),
                                  ),
                                  title: Text(item['title']?.toString() ?? '', style: TextStyle(fontWeight: isUnread ? FontWeight.w700 : FontWeight.w500)),
                                  subtitle: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      if ((item['body'] ?? '').toString().isNotEmpty) Text(item['body'].toString(), maxLines: 3, overflow: TextOverflow.ellipsis),
                                      const SizedBox(height: 2),
                                      Text(timeAgo(item['created_at']), style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
                                    ],
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                ),
    );
  }

  IconData _iconFor(String key) {
    if (key.contains('fee') || key.contains('payment') || key.contains('challan') || key.contains('salary')) {
      return Icons.payments_outlined;
    }
    if (key.contains('absent') || key.contains('arrived') || key.contains('attendance')) {
      return Icons.how_to_reg_outlined;
    }
    if (key.contains('diary') || key.contains('homework')) {
      return Icons.menu_book_outlined;
    }
    if (key.contains('result') || key.contains('exam') || key.contains('admit')) {
      return Icons.emoji_events_outlined;
    }
    if (key.contains('chat')) {
      return Icons.chat_bubble_outline_rounded;
    }
    if (key.contains('library')) {
      return Icons.local_library_outlined;
    }
    return Icons.notifications_outlined;
  }
}

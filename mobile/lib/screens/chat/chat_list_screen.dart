import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/alerts.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'chat_room_screen.dart';

/// Staff chat list: pinned first, unread counts, updated live (GET chat/conversations).
class ChatListScreen extends StatefulWidget {
  const ChatListScreen({super.key});

  @override
  State<ChatListScreen> createState() => _ChatListScreenState();
}

class _ChatListScreenState extends State<ChatListScreen> {
  List<Map<String, dynamic>> _chats = [];
  bool _loading = true;
  String? _error;
  String _search = '';
  late final Alerts _alerts;

  @override
  void initState() {
    super.initState();
    _alerts = context.read<Alerts>();
    _alerts.addChatListener(_onLive);
    _load();
  }

  @override
  void dispose() {
    _alerts.removeChatListener(_onLive);
    super.dispose();
  }

  void _onLive(Map<String, dynamic> data) => _load();

  Future<void> _load() async {
    try {
      final chats = asList(await context.read<Session>().api.get('erp/chat/conversations'));
      if (mounted) {
        setState(() {
          _chats = chats;
          _error = null;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() => _error = error.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _open(int id) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => ChatRoomScreen(conversationId: id)));
    _load();
    _alerts.refresh();
  }

  @override
  Widget build(BuildContext context) {
    final visible = _search.isEmpty ? _chats : _chats.where((chat) => chat['name'].toString().toLowerCase().contains(_search.toLowerCase())).toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Chat')),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppColors.brand500,
        foregroundColor: Colors.white,
        tooltip: 'New chat',
        onPressed: () async {
          final id = await Navigator.push<int>(context, MaterialPageRoute(builder: (_) => const NewChatScreen()));
          if (id != null) {
            _open(id);
          }
        },
        child: const Icon(Icons.edit_rounded),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _chats.isEmpty
              ? MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load chats', message: _error, actionLabel: 'Try again', onAction: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                        child: TextField(decoration: const InputDecoration(hintText: 'Search chats', prefixIcon: Icon(Icons.search_rounded), isDense: true), onChanged: (value) => setState(() => _search = value)),
                      ),
                      if (visible.isEmpty) const MessageView(icon: Icons.forum_outlined, title: 'No chats yet', message: 'Tap the pencil to message a colleague.'),
                      for (final chat in visible) _ChatTile(chat: chat, onTap: () => _open((chat['id'] as num).toInt())),
                    ],
                  ),
                ),
    );
  }
}

class _ChatTile extends StatelessWidget {
  const _ChatTile({required this.chat, required this.onTap});

  final Map<String, dynamic> chat;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final last = asMap(chat['last_message']);
    final unread = (chat['unread_count'] as num?)?.toInt() ?? 0;
    final name = chat['name']?.toString() ?? '';
    final isGroup = chat['type'] == 'group';
    return Material(
      color: Colors.white,
      child: ListTile(
        onTap: onTap,
        leading: CircleAvatar(
          backgroundColor: isGroup ? AppColors.sky50 : AppColors.brand100,
          foregroundColor: isGroup ? AppColors.sky600 : AppColors.brand700,
          child: isGroup ? const Icon(Icons.groups_rounded) : Text(name.isEmpty ? '?' : name[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.w800)),
        ),
        title: Row(children: [
          Expanded(child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: unread > 0 ? FontWeight.w800 : FontWeight.w600))),
          if (chat['is_pinned'] == true) const Icon(Icons.push_pin_rounded, size: 14, color: AppColors.gray400),
          if (chat['is_muted'] == true) const Padding(padding: EdgeInsets.only(left: 4), child: Icon(Icons.notifications_off_rounded, size: 14, color: AppColors.gray400)),
        ]),
        subtitle: Text(
          last.isEmpty ? 'No messages yet' : '${isGroup && last['sender_name'] != null ? '${last['sender_name']}: ' : ''}${last['body'] ?? ''}',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(color: unread > 0 ? AppColors.gray800 : AppColors.gray500),
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(timeAgo(last['created_at']), style: TextStyle(fontSize: 11, color: unread > 0 ? AppColors.brand600 : AppColors.gray400)),
            const SizedBox(height: 4),
            if (unread > 0)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(color: AppColors.brand500, borderRadius: BorderRadius.circular(10)),
                child: Text('$unread', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
              ),
          ],
        ),
      ),
    );
  }
}

/// Start a direct chat with a colleague, or create a group (GET chat/people, POST chat/direct, POST chat/groups).
class NewChatScreen extends StatefulWidget {
  const NewChatScreen({super.key});

  @override
  State<NewChatScreen> createState() => _NewChatScreenState();
}

class _NewChatScreenState extends State<NewChatScreen> {
  bool _group = false;
  final Set<int> _picked = {};
  final _groupName = TextEditingController();
  String _search = '';

  Future<void> _direct(int userId) async {
    final chat = await runAction(context, () => context.read<Session>().api.post('erp/chat/direct', {'user_id': userId}));
    if (chat is Map && mounted) {
      Navigator.pop(context, (chat['id'] as num).toInt());
    }
  }

  Future<void> _createGroup() async {
    if (_groupName.text.trim().isEmpty || _picked.isEmpty) {
      showMessage(context, 'Give the group a name and pick at least one person.', error: true);
      return;
    }
    final chat = await runAction(context, () => context.read<Session>().api.post('erp/chat/groups', {'name': _groupName.text.trim(), 'member_ids': _picked.toList()}));
    if (chat is Map && mounted) {
      Navigator.pop(context, (chat['id'] as num).toInt());
    }
  }

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(
        title: Text(_group ? 'New group' : 'New chat'),
        actions: [
          TextButton(onPressed: () => setState(() => _group = !_group), child: Text(_group ? 'Direct chat' : 'New group')),
        ],
      ),
      floatingActionButton: _group ? FloatingActionButton.extended(backgroundColor: AppColors.brand500, foregroundColor: Colors.white, onPressed: _createGroup, icon: const Icon(Icons.check_rounded), label: Text('Create (${_picked.length})')) : null,
      body: Column(
        children: [
          if (_group) Padding(padding: const EdgeInsets.fromLTRB(16, 12, 16, 0), child: TextField(controller: _groupName, decoration: const InputDecoration(labelText: 'Group name'))),
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(decoration: const InputDecoration(hintText: 'Search colleagues', prefixIcon: Icon(Icons.search_rounded), isDense: true), onChanged: (value) => setState(() => _search = value)),
          ),
          Expanded(
            child: AsyncView<List<Map<String, dynamic>>>(
              load: () async => asList(await api.get('erp/chat/people')),
              empty: (people) => people.isEmpty,
              builder: (context, people, reload) {
                final visible = people.where((person) => person['name'].toString().toLowerCase().contains(_search.toLowerCase())).toList();
                return ListView.builder(
                  padding: const EdgeInsets.only(bottom: 90),
                  itemCount: visible.length,
                  itemBuilder: (context, index) {
                    final person = visible[index];
                    final id = (person['id'] as num).toInt();
                    final name = person['name'].toString();
                    return Material(
                      color: Colors.white,
                      child: _group
                          ? CheckboxListTile(
                              value: _picked.contains(id),
                              onChanged: (value) => setState(() => value == true ? _picked.add(id) : _picked.remove(id)),
                              title: Text(name),
                              secondary: CircleAvatar(backgroundColor: AppColors.brand100, foregroundColor: AppColors.brand700, child: Text(name.isEmpty ? '?' : name[0].toUpperCase())),
                            )
                          : ListTile(
                              onTap: () => _direct(id),
                              leading: CircleAvatar(backgroundColor: AppColors.brand100, foregroundColor: AppColors.brand700, child: Text(name.isEmpty ? '?' : name[0].toUpperCase())),
                              title: Text(name),
                              subtitle: person['last_seen_at'] == null ? null : Text('Last seen ${timeAgo(person['last_seen_at'])}'),
                            ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

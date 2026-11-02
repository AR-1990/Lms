import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';

import '../../core/alerts.dart';
import '../../core/api.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// One chat: messages arrive live, ticks show sent / delivered / read, with replies, reactions, photos and
/// files, edit and delete (GET/POST chat/conversations/{id}/messages and the message actions).
class ChatRoomScreen extends StatefulWidget {
  const ChatRoomScreen({super.key, required this.conversationId});

  final int conversationId;

  @override
  State<ChatRoomScreen> createState() => _ChatRoomScreenState();
}

class _ChatRoomScreenState extends State<ChatRoomScreen> {
  static const _reactions = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

  Map<String, dynamic>? _chat;
  final List<Map<String, dynamic>> _messages = [];
  final _input = TextEditingController();
  final _scroll = ScrollController();
  Map<String, dynamic>? _replyTo;
  Map<String, dynamic>? _editing;
  bool _loading = true;
  bool _loadingOlder = false;
  bool _noMoreOlder = false;
  String? _error;
  late final Alerts _alerts;
  late final ApiClient _api;
  late final int _me;

  String get _channel => 'chat.${widget.conversationId}';

  @override
  void initState() {
    super.initState();
    _alerts = context.read<Alerts>();
    _api = context.read<Session>().api;
    _me = context.read<Session>().userId;
    _alerts.realtime.subscribe(_channel, _onLive);
    _scroll.addListener(() {
      if (_scroll.position.pixels >= _scroll.position.maxScrollExtent - 200) {
        _loadOlder();
      }
    });
    _load();
  }

  @override
  void dispose() {
    _alerts.realtime.unsubscribe(_channel, _onLive);
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final results = await Future.wait([_api.get('erp/chat/conversations'), _api.get('erp/chat/conversations/${widget.conversationId}/messages')]);
      final chat = asList(results[0]).where((item) => item['id'] == widget.conversationId).toList();
      setState(() {
        _chat = chat.isEmpty ? null : chat.first;
        _messages
          ..clear()
          ..addAll(asList(results[1]));
        _error = null;
      });
      _markRead();
    } catch (error) {
      setState(() => _error = error.toString());
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _loadOlder() async {
    if (_loadingOlder || _noMoreOlder || _messages.isEmpty) {
      return;
    }
    _loadingOlder = true;
    try {
      final older = asList(await _api.get('erp/chat/conversations/${widget.conversationId}/messages', query: {'before': _messages.first['id']}));
      setState(() {
        _noMoreOlder = older.isEmpty;
        _messages.insertAll(0, older);
      });
    } catch (_) {}
    _loadingOlder = false;
  }

  void _markRead() {
    final last = _messages.lastWhere((message) => message['sender_id'] != _me, orElse: () => <String, dynamic>{});
    if (last.isNotEmpty) {
      _api.post('erp/chat/conversations/${widget.conversationId}/read', {'up_to_id': last['id']}).then((_) => _alerts.refresh()).catchError((_) {});
    }
  }

  void _onLive(String event, Map<String, dynamic> data) {
    if (!mounted) {
      return;
    }
    if (event == 'message.sent' || event == 'message.updated') {
      setState(() => _upsert(data));
      if (event == 'message.sent' && data['sender_id'] != _me) {
        _markRead();
      }
    }
    if (event == 'messages.read' && data['user_id'] != _me) {
      // Someone read the chat: refresh ticks on my messages.
      _api.get('erp/chat/conversations/${widget.conversationId}/messages').then((latest) {
        if (mounted) {
          setState(() {
            for (final message in asList(latest)) {
              _upsert(message);
            }
          });
        }
      }).catchError((_) {});
    }
  }

  void _upsert(Map<String, dynamic> message) {
    final index = _messages.indexWhere((item) => item['id'] == message['id'] || (message['client_uuid'] != null && item['client_uuid'] == message['client_uuid']));
    if (index >= 0) {
      _messages[index] = message;
    } else {
      _messages.add(message);
    }
  }

  Future<void> _send({List<File> files = const []}) async {
    final text = _input.text.trim();
    if (text.isEmpty && files.isEmpty) {
      return;
    }
    if (_editing != null) {
      final editing = _editing!;
      setState(() => _editing = null);
      _input.clear();
      try {
        final updated = asMap(await _api.put('erp/chat/messages/${editing['id']}', {'body': text}));
        setState(() => _upsert(updated));
      } catch (error) {
        if (mounted) {
          showMessage(context, error.toString(), error: true);
        }
      }
      return;
    }
    final uuid = const Uuid().v4();
    final replyTo = _replyTo;
    // Show it straight away; the server's copy replaces it (same client_uuid, so retries never duplicate).
    final Map<String, dynamic> pending = {'id': -DateTime.now().millisecondsSinceEpoch, 'client_uuid': uuid, 'sender_id': _me, 'body': text.isEmpty ? (files.isEmpty ? '' : 'Sending file…') : text, 'type': 'text', 'status': 'pending', 'created_at': DateTime.now().toIso8601String(), 'reply_to': replyTo == null ? null : {'sender_name': replyTo['sender_name'], 'body': replyTo['body']}};
    setState(() {
      _messages.add(pending);
      _replyTo = null;
    });
    _input.clear();
    try {
      final path = 'erp/chat/conversations/${widget.conversationId}/messages';
      final saved = files.isEmpty
          ? await _api.post(path, {'body': text, 'client_uuid': uuid, if (replyTo != null) 'reply_to_message_id': replyTo['id']})
          : await _api.upload(path, fields: {if (text.isNotEmpty) 'body': text, 'client_uuid': uuid, if (replyTo != null) 'reply_to_message_id': '${replyTo['id']}'}, files: {'files[]': files});
      setState(() => _upsert(asMap(saved)));
    } catch (error) {
      setState(() => pending['status'] = 'failed');
      if (mounted) {
        showMessage(context, error.toString(), error: true);
      }
    }
  }

  Future<void> _attach() async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(leading: const Icon(Icons.photo_camera_outlined), title: const Text('Camera'), onTap: () => Navigator.pop(sheetContext, 'camera')),
          ListTile(leading: const Icon(Icons.photo_library_outlined), title: const Text('Photos'), onTap: () => Navigator.pop(sheetContext, 'gallery')),
          ListTile(leading: const Icon(Icons.attach_file_rounded), title: const Text('Document'), onTap: () => Navigator.pop(sheetContext, 'file')),
        ]),
      ),
    );
    List<File> files = [];
    if (choice == 'camera' || choice == 'gallery') {
      final picker = ImagePicker();
      if (choice == 'camera') {
        final photo = await picker.pickImage(source: ImageSource.camera, imageQuality: 80, maxWidth: 2000);
        files = photo == null ? [] : [File(photo.path)];
      } else {
        final photos = await picker.pickMultiImage(imageQuality: 80, maxWidth: 2000, limit: 5);
        files = photos.take(5).map((photo) => File(photo.path)).toList();
      }
    } else if (choice == 'file') {
      final result = await FilePicker.platform.pickFiles(allowMultiple: true, type: FileType.custom, allowedExtensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'jpg', 'jpeg', 'png']);
      files = (result?.files ?? []).where((file) => file.path != null).take(5).map((file) => File(file.path!)).toList();
    }
    if (files.isNotEmpty) {
      await _send(files: files);
    }
  }

  Future<void> _actions(Map<String, dynamic> message) async {
    if ((message['id'] as num) < 0 || message['type'] == 'system') {
      return;
    }
    final mine = message['sender_id'] == _me;
    final created = DateTime.tryParse('${message['created_at']}');
    final canEdit = mine && message['is_deleted'] != true && (message['attachments'] as List? ?? []).isEmpty && created != null && DateTime.now().difference(created).inMinutes < 15;
    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [for (final emoji in _reactions) InkWell(onTap: () => Navigator.pop(sheetContext, 'react:$emoji'), child: Padding(padding: const EdgeInsets.all(8), child: Text(emoji, style: const TextStyle(fontSize: 26))))]),
          ),
          ListTile(leading: const Icon(Icons.reply_rounded), title: const Text('Reply'), onTap: () => Navigator.pop(sheetContext, 'reply')),
          if ((message['body'] ?? '').toString().isNotEmpty) ListTile(leading: const Icon(Icons.copy_rounded), title: const Text('Copy'), onTap: () => Navigator.pop(sheetContext, 'copy')),
          if (canEdit) ListTile(leading: const Icon(Icons.edit_rounded), title: const Text('Edit'), onTap: () => Navigator.pop(sheetContext, 'edit')),
          ListTile(leading: const Icon(Icons.delete_outline_rounded), title: const Text('Delete for me'), onTap: () => Navigator.pop(sheetContext, 'delete_me')),
          if (mine && message['is_deleted'] != true) ListTile(leading: const Icon(Icons.delete_forever_rounded, color: AppColors.red600), title: const Text('Delete for everyone', style: TextStyle(color: AppColors.red600)), onTap: () => Navigator.pop(sheetContext, 'delete_all')),
        ]),
      ),
    );
    if (action == null || !mounted) {
      return;
    }
    try {
      if (action.startsWith('react:')) {
        final emoji = action.substring(6);
        final already = asList(message['reactions']).any((reaction) => reaction['emoji'] == emoji && (reaction['user_ids'] as List).contains(_me));
        final updated = asMap(await _api.post('erp/chat/messages/${message['id']}/react', {'emoji': already ? null : emoji}));
        setState(() => _upsert(updated));
      } else if (action == 'reply') {
        setState(() => _replyTo = message);
      } else if (action == 'copy') {
        await Clipboard.setData(ClipboardData(text: message['body'].toString()));
      } else if (action == 'edit') {
        setState(() {
          _editing = message;
          _input.text = message['body']?.toString() ?? '';
        });
      } else if (action == 'delete_me') {
        await _api.delete('erp/chat/messages/${message['id']}', {'for_everyone': false});
        setState(() => _messages.removeWhere((item) => item['id'] == message['id']));
      } else if (action == 'delete_all') {
        await _api.delete('erp/chat/messages/${message['id']}', {'for_everyone': true});
        setState(() {
          message['is_deleted'] = true;
          message['body'] = null;
          message['attachments'] = [];
        });
      }
    } catch (error) {
      if (mounted) {
        showMessage(context, error.toString(), error: true);
      }
    }
  }

  Future<void> _toggleMute() async {
    final muted = _chat?['is_muted'] == true;
    final done = await runAction(context, () => _api.patch('erp/chat/conversations/${widget.conversationId}/preferences', {'muted': !muted}), success: muted ? 'Notifications on.' : 'Chat muted.');
    if (done != null) {
      setState(() => _chat = {...?_chat, 'is_muted': !muted});
    }
  }

  @override
  Widget build(BuildContext context) {
    final chat = _chat;
    final isGroup = chat?['type'] == 'group';
    final other = asMap(chat?['other_user']);
    final canPost = chat == null || chat['only_admins_can_post'] != true || chat['my_role'] == 'admin';
    final reversed = _messages.reversed.toList();

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(chat?['name']?.toString() ?? 'Chat'),
            if (isGroup) Text('${asList(chat?['members']).length} members', style: const TextStyle(fontSize: 12, color: AppColors.gray500, fontWeight: FontWeight.w500)),
            if (!isGroup && other['last_seen_at'] != null) Text('Last seen ${timeAgo(other['last_seen_at'])}', style: const TextStyle(fontSize: 12, color: AppColors.gray500, fontWeight: FontWeight.w500)),
          ],
        ),
        actions: [
          if (chat != null) IconButton(tooltip: chat['is_muted'] == true ? 'Unmute' : 'Mute', icon: Icon(chat['is_muted'] == true ? Icons.notifications_off_rounded : Icons.notifications_active_outlined), onPressed: _toggleMute),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null && _messages.isEmpty
                    ? MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load', message: _error, actionLabel: 'Try again', onAction: _load)
                    : ListView.builder(
                        controller: _scroll,
                        reverse: true,
                        padding: const EdgeInsets.all(12),
                        itemCount: reversed.length,
                        itemBuilder: (context, index) {
                          final message = reversed[index];
                          final previous = index + 1 < reversed.length ? reversed[index + 1] : null;
                          final showSender = isGroup && message['sender_id'] != _me && previous?['sender_id'] != message['sender_id'];
                          return _Bubble(message: message, mine: message['sender_id'] == _me, showSender: showSender, onLongPress: () => _actions(message), onRetry: message['status'] == 'failed' ? () {
                            setState(() => _messages.remove(message));
                            _input.text = message['body']?.toString() ?? '';
                          } : null);
                        },
                      ),
          ),
          if (_replyTo != null || _editing != null)
            Container(
              color: AppColors.gray50,
              padding: const EdgeInsets.fromLTRB(16, 8, 8, 8),
              child: Row(children: [
                Icon(_editing != null ? Icons.edit_rounded : Icons.reply_rounded, size: 18, color: AppColors.brand600),
                const SizedBox(width: 8),
                Expanded(child: Text(_editing != null ? 'Editing message' : 'Replying to ${_replyTo!['sender_name'] ?? ''}: ${_replyTo!['body'] ?? ''}', maxLines: 1, overflow: TextOverflow.ellipsis)),
                IconButton(icon: const Icon(Icons.close_rounded), onPressed: () => setState(() {
                      _replyTo = null;
                      if (_editing != null) {
                        _editing = null;
                        _input.clear();
                      }
                    })),
              ]),
            ),
          SafeArea(
            top: false,
            child: Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(8, 6, 8, 6),
              child: canPost
                  ? Row(children: [
                      IconButton(icon: const Icon(Icons.attach_file_rounded, color: AppColors.gray500), onPressed: _editing == null ? _attach : null),
                      Expanded(
                        child: TextField(
                          controller: _input,
                          minLines: 1,
                          maxLines: 5,
                          textCapitalization: TextCapitalization.sentences,
                          decoration: InputDecoration(hintText: 'Message', isDense: true, border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: const BorderSide(color: AppColors.gray200)), enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: const BorderSide(color: AppColors.gray200))),
                        ),
                      ),
                      const SizedBox(width: 6),
                      IconButton.filled(style: IconButton.styleFrom(backgroundColor: AppColors.brand500), icon: Icon(_editing != null ? Icons.check_rounded : Icons.send_rounded, color: Colors.white), onPressed: () => _send()),
                    ])
                  : const Padding(padding: EdgeInsets.all(12), child: Text('Only group admins can post here.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.gray500))),
            ),
          ),
        ],
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.mine, required this.showSender, required this.onLongPress, this.onRetry});

  final Map<String, dynamic> message;
  final bool mine;
  final bool showSender;
  final VoidCallback onLongPress;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    if (message['type'] == 'system') {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Center(child: Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4), decoration: BoxDecoration(color: AppColors.gray200, borderRadius: BorderRadius.circular(10)), child: Text(message['body']?.toString() ?? '', style: const TextStyle(fontSize: 12, color: AppColors.gray600)))),
      );
    }
    final deleted = message['is_deleted'] == true;
    final reply = asMap(message['reply_to']);
    final reactions = asList(message['reactions']);
    final attachments = asList(message['attachments']);
    final created = DateTime.tryParse('${message['created_at']}')?.toLocal();
    final time = created == null ? '' : '${created.hour.toString().padLeft(2, '0')}:${created.minute.toString().padLeft(2, '0')}';

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: GestureDetector(
        onLongPress: onLongPress,
        onTap: onRetry,
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 3),
          constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.78),
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
          decoration: BoxDecoration(
            color: mine ? AppColors.brand100 : Colors.white,
            border: mine ? null : Border.all(color: AppColors.gray200),
            borderRadius: BorderRadius.only(topLeft: const Radius.circular(14), topRight: const Radius.circular(14), bottomLeft: Radius.circular(mine ? 14 : 4), bottomRight: Radius.circular(mine ? 4 : 14)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (showSender) Text(message['sender_name']?.toString() ?? '', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.brand700)),
              if (message['is_forwarded'] == true) const Text('Forwarded', style: TextStyle(fontSize: 11, fontStyle: FontStyle.italic, color: AppColors.gray500)),
              if (reply.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(bottom: 4, top: 2),
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(6), border: const Border(left: BorderSide(color: AppColors.brand500, width: 3))),
                  child: Text('${reply['sender_name'] ?? ''}\n${reply['body'] ?? 'Deleted message'}', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: AppColors.gray600)),
                ),
              for (final file in attachments)
                (file['mime_type'] ?? '').toString().startsWith('image/')
                    ? GestureDetector(
                        onTap: () => openLink(context, file['url'].toString()),
                        child: Padding(padding: const EdgeInsets.only(bottom: 4), child: ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.network(file['url'].toString(), height: 180, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const Icon(Icons.broken_image_outlined)))),
                      )
                    : InkWell(
                        onTap: () => openFile(context, file['url'].toString(), file['name']?.toString() ?? 'file'),
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: 4),
                          child: Row(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.insert_drive_file_outlined, color: AppColors.brand600), const SizedBox(width: 6), Flexible(child: Text(file['name']?.toString() ?? 'File', style: const TextStyle(decoration: TextDecoration.underline)))]),
                        ),
                      ),
              if (deleted)
                const Text('This message was deleted', style: TextStyle(fontStyle: FontStyle.italic, color: AppColors.gray500))
              else if ((message['body'] ?? '').toString().isNotEmpty)
                Text(message['body'].toString(), style: const TextStyle(fontSize: 15, color: AppColors.gray900)),
              const SizedBox(height: 2),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (message['is_edited'] == true && !deleted) const Text('edited · ', style: TextStyle(fontSize: 10, color: AppColors.gray400)),
                  Text(time, style: const TextStyle(fontSize: 10, color: AppColors.gray500)),
                  if (mine) ...[const SizedBox(width: 3), _Tick(status: message['status']?.toString() ?? 'sent')],
                ],
              ),
              if (reactions.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Wrap(spacing: 4, children: [
                    for (final reaction in reactions)
                      Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: AppColors.gray200)), child: Text('${reaction['emoji']} ${(reaction['user_ids'] as List?)?.length ?? 1}', style: const TextStyle(fontSize: 12))),
                  ]),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Tick extends StatelessWidget {
  const _Tick({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    switch (status) {
      case 'pending':
        return const Icon(Icons.schedule_rounded, size: 13, color: AppColors.gray400);
      case 'failed':
        return const Icon(Icons.error_outline_rounded, size: 14, color: AppColors.red600);
      case 'read':
        return const Icon(Icons.done_all_rounded, size: 15, color: AppColors.sky600);
      case 'delivered':
        return const Icon(Icons.done_all_rounded, size: 15, color: AppColors.gray400);
      default:
        return const Icon(Icons.done_rounded, size: 15, color: AppColors.gray400);
    }
  }
}

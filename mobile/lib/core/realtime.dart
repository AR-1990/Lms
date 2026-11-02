import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:web_socket_channel/web_socket_channel.dart';

import 'session.dart';

typedef RealtimeListener = void Function(String event, Map<String, dynamic> data);

/// Live updates from the school's Reverb server (Pusher protocol) while the app is open: new bell
/// notifications and chat messages arrive at once. Reconnects by itself when the internet comes back.
class Realtime {
  Realtime(this.session);

  final Session session;
  WebSocketChannel? _channel;
  StreamSubscription? _subscription;
  String? _socketId;
  Timer? _ping;
  Timer? _retry;
  int _attempt = 0;
  bool _wanted = false;
  final Map<String, List<RealtimeListener>> _listeners = {};

  bool get connected => _socketId != null;

  void connect() {
    _wanted = true;
    final config = session.realtime;
    if (config == null || !session.signedIn || _channel != null) {
      return;
    }
    final secure = config['scheme'] == 'https';
    final url = '${secure ? 'wss' : 'ws'}://${config['host']}:${config['port']}/app/${config['key']}?protocol=7&client=flutter&version=1.0';
    try {
      _channel = WebSocketChannel.connect(Uri.parse(url));
      _subscription = _channel!.stream.listen(_onMessage, onDone: _lost, onError: (_) => _lost(), cancelOnError: true);
    } catch (_) {
      _lost();
    }
  }

  void disconnect() {
    _wanted = false;
    _retry?.cancel();
    _close();
  }

  /// Call when the phone gets its connection back or the app returns to the screen.
  void reconnect() {
    _attempt = 0;
    _retry?.cancel();
    if (_channel == null) {
      connect();
    }
  }

  /// Listen on a private channel, e.g. "App.Models.User.5" or "chat.12" (the "private-" prefix is added).
  void subscribe(String channel, RealtimeListener listener) {
    final name = 'private-$channel';
    final isNew = !_listeners.containsKey(name);
    _listeners.putIfAbsent(name, () => []).add(listener);
    if (isNew && _socketId != null) {
      _authorize(name);
    }
  }

  void unsubscribe(String channel, RealtimeListener listener) {
    final name = 'private-$channel';
    _listeners[name]?.remove(listener);
    if (_listeners[name]?.isEmpty ?? false) {
      _listeners.remove(name);
      _send({'event': 'pusher:unsubscribe', 'data': {'channel': name}});
    }
  }

  void _onMessage(dynamic raw) {
    Map<String, dynamic> message;
    try {
      message = Map<String, dynamic>.from(jsonDecode(raw.toString()) as Map);
    } catch (_) {
      return;
    }
    final event = message['event']?.toString() ?? '';
    final data = message['data'] is String ? _tryDecode(message['data'] as String) : (message['data'] is Map ? Map<String, dynamic>.from(message['data'] as Map) : <String, dynamic>{});

    if (event == 'pusher:connection_established') {
      _socketId = data['socket_id']?.toString();
      _attempt = 0;
      _ping?.cancel();
      _ping = Timer.periodic(const Duration(seconds: 30), (_) => _send({'event': 'pusher:ping', 'data': {}}));
      for (final name in _listeners.keys) {
        _authorize(name);
      }
      return;
    }
    if (event == 'pusher:ping') {
      _send({'event': 'pusher:pong', 'data': {}});
      return;
    }
    if (event.startsWith('pusher')) {
      return;
    }
    final channel = message['channel']?.toString();
    for (final listener in List<RealtimeListener>.from(_listeners[channel] ?? const [])) {
      listener(event, data);
    }
  }

  /// Private channels need a signature from the school server (Laravel /api/broadcasting/auth).
  Future<void> _authorize(String name) async {
    final socketId = _socketId;
    if (socketId == null) {
      return;
    }
    try {
      final response = await http.post(
        session.api.uri('broadcasting/auth'),
        headers: {...session.api.headers, 'Content-Type': 'application/json'},
        body: jsonEncode({'socket_id': socketId, 'channel_name': name}),
      );
      final body = jsonDecode(response.body);
      final auth = body is Map ? (body['auth'] ?? (body['data'] is Map ? body['data']['auth'] : null)) : null;
      if (auth != null && socketId == _socketId) {
        _send({'event': 'pusher:subscribe', 'data': {'auth': auth, 'channel': name}});
      }
    } catch (_) {
      // Not allowed or offline: the screen still refreshes on pull and push notifications still arrive.
    }
  }

  void _send(Map<String, dynamic> message) {
    try {
      _channel?.sink.add(jsonEncode(message));
    } catch (_) {}
  }

  void _lost() {
    _close();
    if (!_wanted) {
      return;
    }
    _attempt++;
    final seconds = _attempt > 6 ? 60 : (1 << _attempt);
    _retry?.cancel();
    _retry = Timer(Duration(seconds: seconds), connect);
  }

  void _close() {
    _ping?.cancel();
    _subscription?.cancel();
    try {
      _channel?.sink.close();
    } catch (_) {}
    _channel = null;
    _subscription = null;
    _socketId = null;
  }

  Map<String, dynamic> _tryDecode(String raw) {
    try {
      final value = jsonDecode(raw);
      return value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
    } catch (_) {
      return <String, dynamic>{};
    }
  }
}

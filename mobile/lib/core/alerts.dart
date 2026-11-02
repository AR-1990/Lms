import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'realtime.dart';
import 'session.dart';

/// Runs in the background when a push arrives while the app is closed. The phone itself shows the
/// notification (it carries a title and body), so nothing else is needed here.
@pragma('vm:entry-point')
Future<void> firebaseBackgroundHandler(RemoteMessage message) async {}

/// Tries to start Firebase. Without google-services.json the app still works: live updates while open,
/// and the bell list on every refresh; only closed-app push is missing.
Future<bool> startFirebase() async {
  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(firebaseBackgroundHandler);
    return true;
  } catch (_) {
    return false;
  }
}

/// A tap on a notification: what it was about, so the app can open the right screen.
class AlertTarget {
  AlertTarget({this.eventKey, this.conversationId});

  final String? eventKey;
  final int? conversationId;

  factory AlertTarget.fromData(Map<String, dynamic> data) => AlertTarget(
        eventKey: data['event_key']?.toString(),
        conversationId: data['type'] == 'chat' ? int.tryParse('${data['conversation_id']}') : null,
      );
}

/// Bell and chat badges, live updates, push registration and coming back online.
class Alerts extends ChangeNotifier {
  Alerts(this.session, {required this.firebaseReady}) : realtime = Realtime(session);

  final Session session;
  final Realtime realtime;
  final bool firebaseReady;

  /// Shows in-app banners for alerts that arrive while the app is open.
  final GlobalKey<ScaffoldMessengerState> messengerKey = GlobalKey<ScaffoldMessengerState>();

  int unreadNotifications = 0;
  int unreadChat = 0;

  /// Set when the user taps a notification; the home screen opens the matching screen and clears it.
  AlertTarget? pendingTarget;

  final _chatListeners = <void Function(Map<String, dynamic>)>[];
  StreamSubscription? _connectivity;
  StreamSubscription? _foreground;
  StreamSubscription? _opened;
  StreamSubscription? _tokenRefresh;
  int? _listeningUser;

  /// Call once signed in.
  Future<void> start() async {
    if (!session.signedIn) {
      return;
    }
    if (_listeningUser != session.userId) {
      _listeningUser = session.userId;
      realtime.subscribe('App.Models.User.${session.userId}', _onUserEvent);
    }
    realtime.connect();
    _connectivity ??= Connectivity().onConnectivityChanged.listen((results) {
      if (results.any((result) => result != ConnectivityResult.none)) {
        realtime.reconnect();
        refresh();
      }
    });
    await refresh();
    await _startPush();
  }

  Future<void> stop() async {
    realtime.disconnect();
    if (_listeningUser != null) {
      realtime.unsubscribe('App.Models.User.$_listeningUser', _onUserEvent);
      _listeningUser = null;
    }
    unreadNotifications = 0;
    unreadChat = 0;
    // Called while the home screen is being removed, so tell listeners after this frame.
    Future.microtask(notifyListeners);
  }

  /// Re-reads the badge counts (also on app resume and when the internet comes back).
  Future<void> refresh() async {
    if (!session.signedIn) {
      return;
    }
    try {
      final summary = await session.api.get('erp/notifications/summary');
      unreadNotifications = (summary['unread'] as num?)?.toInt() ?? 0;
      if (session.can('use-chat')) {
        final chat = await session.api.get('erp/chat/unread');
        unreadChat = (chat['unread'] as num?)?.toInt() ?? 0;
      }
      notifyListeners();
    } catch (_) {}
  }

  void setUnreadNotifications(int value) {
    unreadNotifications = value;
    notifyListeners();
  }

  /// Chat screens listen here for "conversation.updated" on the user's own channel.
  void addChatListener(void Function(Map<String, dynamic>) listener) => _chatListeners.add(listener);
  void removeChatListener(void Function(Map<String, dynamic>) listener) => _chatListeners.remove(listener);

  void open(AlertTarget target) {
    pendingTarget = target;
    notifyListeners();
  }

  void _onUserEvent(String event, Map<String, dynamic> data) {
    if (event == 'notification.created') {
      unreadNotifications++;
      notifyListeners();
      _banner(data['title']?.toString() ?? 'New notification', data['body']?.toString(), AlertTarget(eventKey: data['event_key']?.toString()));
    }
    if (event == 'conversation.updated') {
      for (final listener in List.of(_chatListeners)) {
        listener(data);
      }
      if (data['is_new'] == true && data['sender_id'] != session.userId) {
        refresh();
      }
    }
  }

  Future<void> _startPush() async {
    if (!firebaseReady) {
      return;
    }
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      final token = await messaging.getToken();
      if (token != null) {
        await session.savePushToken(token);
      }
      _tokenRefresh ??= messaging.onTokenRefresh.listen((token) => session.savePushToken(token).catchError((_) {}));
      // While the app is open the system does not show the push, so show our own banner (unless the live
      // connection already did).
      _foreground ??= FirebaseMessaging.onMessage.listen((message) {
        if (!realtime.connected || message.data['type'] == 'chat') {
          final target = AlertTarget.fromData(message.data);
          if (target.conversationId == null) {
            unreadNotifications++;
          }
          refresh();
          _banner(message.notification?.title ?? 'New notification', message.notification?.body, target);
        }
      });
      _opened ??= FirebaseMessaging.onMessageOpenedApp.listen((message) => open(AlertTarget.fromData(message.data)));
      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        open(AlertTarget.fromData(initial.data));
      }
    } catch (_) {
      // Push is optional; the bell still works.
    }
  }

  void _banner(String title, String? body, AlertTarget target) {
    messengerKey.currentState
      ?..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        duration: const Duration(seconds: 5),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
            if (body != null && body.isNotEmpty) Text(body, maxLines: 2, overflow: TextOverflow.ellipsis),
          ],
        ),
        action: SnackBarAction(label: 'Open', onPressed: () => open(target)),
      ));
  }

  @override
  void dispose() {
    _connectivity?.cancel();
    _foreground?.cancel();
    _opened?.cancel();
    _tokenRefresh?.cancel();
    realtime.disconnect();
    super.dispose();
  }
}

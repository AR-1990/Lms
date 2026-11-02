import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

import 'api.dart';

/// Which part of the app the signed-in user sees.
enum AppRole { parent, student, staff }

/// Everything the app remembers: the school's address and branding (logo saved on the phone), the sign-in
/// token (secure storage) and who is signed in. One instance for the whole app.
class Session extends ChangeNotifier {
  static const appVersion = '1.0.0';
  static const _secure = FlutterSecureStorage(aOptions: AndroidOptions(encryptedSharedPreferences: true));

  late SharedPreferences _prefs;
  final ApiClient api = ApiClient(baseUrl: '');

  String? baseUrl;
  String schoolName = '';
  String? schoolTagline;
  File? logoFile;
  Map<String, dynamic>? realtime;
  bool pushEnabled = false;

  Map<String, dynamic>? user;
  List<String> roles = [];
  Set<String> permissions = {};
  String deviceId = '';
  bool ready = false;

  bool get hasSchool => baseUrl != null && baseUrl!.isNotEmpty;
  bool get signedIn => api.token != null && user != null;
  int get userId => (user?['id'] as num?)?.toInt() ?? 0;
  String get userName => (user?['name'] ?? '').toString();

  AppRole get role {
    if (roles.contains('parent')) {
      return AppRole.parent;
    }
    if (roles.contains('student')) {
      return AppRole.student;
    }
    return AppRole.staff;
  }

  bool can(String permission) => permissions.contains(permission) || roles.contains('admin');

  Future<void> load() async {
    _prefs = await SharedPreferences.getInstance();
    deviceId = _prefs.getString('device_id') ?? const Uuid().v4();
    await _prefs.setString('device_id', deviceId);
    api.onUnauthorized = _expired;

    baseUrl = _prefs.getString('base_url');
    if (hasSchool) {
      api.baseUrl = baseUrl!;
      schoolName = _prefs.getString('school_name') ?? '';
      schoolTagline = _prefs.getString('school_tagline');
      final logoPath = _prefs.getString('logo_path');
      logoFile = logoPath != null && File(logoPath).existsSync() ? File(logoPath) : null;
      api.token = await _secure.read(key: 'token');
      if (api.token != null) {
        _applyProfile(_prefs.getString('profile_user_id') == null ? null : await _cachedProfile());
      }
      // Refresh branding and profile in the background; the saved copies keep the app usable offline.
      refreshSchool().catchError((_) {});
      if (api.token != null) {
        refreshProfile().catchError((_) {});
      }
    }
    ready = true;
    notifyListeners();
  }

  /// Step 1: check the address the user typed really is a school server, then save its name and logo.
  Future<void> connectSchool(String input) async {
    final url = ApiClient.normalizeBaseUrl(input);
    if (url.isEmpty) {
      throw ApiException('Please enter your school\'s web address.');
    }
    final probe = ApiClient(baseUrl: url);
    Map<String, dynamic> config;
    try {
      config = Map<String, dynamic>.from(await probe.get('site/app-config') as Map);
    } on ApiException catch (error) {
      if (error.status == 404) {
        throw ApiException('This address is not a school server. Please check it with the school office.');
      }
      rethrow;
    }
    baseUrl = url;
    api.baseUrl = url;
    await _prefs.setString('base_url', url);
    await _saveBranding(config);
    notifyListeners();
  }

  Future<void> refreshSchool() async {
    if (!hasSchool) {
      return;
    }
    await _saveBranding(Map<String, dynamic>.from(await api.get('site/app-config') as Map));
    notifyListeners();
  }

  /// Keeps the logo file on the phone; it is downloaded again only when the school uploads a new one.
  Future<void> _saveBranding(Map<String, dynamic> config) async {
    schoolName = (config['school_name'] ?? '').toString();
    schoolTagline = config['school_tagline']?.toString();
    realtime = config['realtime'] is Map ? Map<String, dynamic>.from(config['realtime'] as Map) : null;
    pushEnabled = config['push_enabled'] == true;
    await _prefs.setString('school_name', schoolName);
    if (schoolTagline != null) {
      await _prefs.setString('school_tagline', schoolTagline!);
    }

    final version = config['logo_version']?.toString();
    final logoUrl = config['logo_url']?.toString();
    if (version == null || logoUrl == null) {
      logoFile = null;
      await _prefs.remove('logo_path');
      await _prefs.remove('logo_version');
      return;
    }
    if (version == _prefs.getString('logo_version') && logoFile != null && logoFile!.existsSync()) {
      return;
    }
    final response = await http.get(Uri.parse(logoUrl));
    if (response.statusCode == 200 && response.bodyBytes.isNotEmpty) {
      final directory = await getApplicationSupportDirectory();
      final file = File('${directory.path}/school-logo-${version.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_')}');
      await file.writeAsBytes(response.bodyBytes);
      logoFile = file;
      await _prefs.setString('logo_path', file.path);
      await _prefs.setString('logo_version', version);
    }
  }

  /// Step 2: sign in with email, username or phone. The token is kept in secure storage.
  Future<void> login(String identifier, String password, {String? pushToken}) async {
    final data = await api.post('login', {
      'email': identifier.trim(),
      'password': password,
      'device_name': '${Platform.isIOS ? 'iPhone' : 'Android'} app',
      'device_id': deviceId,
      'platform': Platform.isIOS ? 'ios' : 'android',
      'app_version': appVersion,
      if (pushToken != null) 'push_token': pushToken,
    }) as Map;
    api.token = data['token'].toString();
    await _secure.write(key: 'token', value: api.token);
    final profile = {'user': data['user'], 'authorization': data['authorization']};
    _applyProfile(profile);
    await _cacheProfile(profile);
    notifyListeners();
  }

  Future<void> refreshProfile() async {
    final profile = Map<String, dynamic>.from(await api.get('me') as Map);
    _applyProfile(profile);
    await _cacheProfile(profile);
    notifyListeners();
  }

  Future<void> savePushToken(String pushToken) async {
    if (signedIn) {
      await api.put('erp/devices/push-token', {'push_token': pushToken});
    }
  }

  Future<void> logout() async {
    try {
      await api.post('logout');
    } catch (_) {
      // Signed out on the phone even when the server can't be reached.
    }
    await _clearSignIn();
  }

  /// Change school: forget the address, branding and sign-in.
  Future<void> forgetSchool() async {
    await logout();
    baseUrl = null;
    schoolName = '';
    logoFile = null;
    for (final key in ['base_url', 'school_name', 'school_tagline', 'logo_path', 'logo_version']) {
      await _prefs.remove(key);
    }
    notifyListeners();
  }

  void _expired() {
    if (api.token != null) {
      _clearSignIn();
    }
  }

  Future<void> _clearSignIn() async {
    api.token = null;
    user = null;
    roles = [];
    permissions = {};
    await _secure.delete(key: 'token');
    for (final key in ['profile_user_id', 'profile_name', 'profile_email', 'profile_roles', 'profile_permissions']) {
      await _prefs.remove(key);
    }
    notifyListeners();
  }

  void _applyProfile(Map<String, dynamic>? profile) {
    if (profile == null) {
      return;
    }
    user = Map<String, dynamic>.from(profile['user'] as Map);
    final authorization = Map<String, dynamic>.from(profile['authorization'] as Map);
    roles = List<String>.from((authorization['roles'] as List?) ?? const []);
    permissions = Set<String>.from((authorization['permissions'] as List?) ?? const []);
  }

  /// Saves who is signed in as plain values, so the app opens offline.
  Future<void> _cacheProfile(Map<String, dynamic> profile) async {
    await _prefs.setString('profile_user_id', profile['user']['id'].toString());
    await _prefs.setString('profile_name', (profile['user']['name'] ?? '').toString());
    await _prefs.setString('profile_email', (profile['user']['email'] ?? '').toString());
    await _prefs.setStringList('profile_roles', roles);
    await _prefs.setStringList('profile_permissions', permissions.toList());
  }

  Future<Map<String, dynamic>?> _cachedProfile() async {
    return {
      'user': {'id': int.tryParse(_prefs.getString('profile_user_id') ?? '') ?? 0, 'name': _prefs.getString('profile_name') ?? '', 'email': _prefs.getString('profile_email') ?? ''},
      'authorization': {'roles': _prefs.getStringList('profile_roles') ?? <String>[], 'permissions': _prefs.getStringList('profile_permissions') ?? <String>[]},
    };
  }
}

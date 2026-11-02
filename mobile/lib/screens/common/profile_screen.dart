import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/alerts.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// Account: who is signed in, change password, signed-in devices, change school, sign out.
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  Future<void> _signOut(BuildContext context, {bool changeSchool = false}) async {
    final ok = await confirm(context, changeSchool ? 'Change school?' : 'Sign out?', changeSchool ? 'You will be signed out and asked for the new school\'s address.' : 'You will stop getting notifications on this phone until you sign in again.', action: changeSchool ? 'Change' : 'Sign out');
    if (!ok || !context.mounted) {
      return;
    }
    final session = context.read<Session>();
    await context.read<Alerts>().stop();
    if (changeSchool) {
      await session.forgetSchool();
    } else {
      await session.logout();
    }
    if (context.mounted) {
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final user = session.user ?? {};
    return Scaffold(
      appBar: AppBar(title: const Text('My account')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Panel(
            child: Row(
              children: [
                CircleAvatar(radius: 28, backgroundColor: AppColors.brand100, foregroundColor: AppColors.brand700, child: Text(session.userName.isEmpty ? '?' : session.userName[0].toUpperCase(), style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(session.userName, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                      if ((user['email'] ?? '').toString().isNotEmpty) Text(user['email'].toString(), style: const TextStyle(color: AppColors.gray500)),
                      const SizedBox(height: 4),
                      Wrap(spacing: 6, children: [for (final role in session.roles) StatusChip(role, tone: 'other')]),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: [
                ListTile(leading: const Icon(Icons.lock_reset_rounded), title: const Text('Change password'), trailing: const Icon(Icons.chevron_right_rounded), onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ChangePasswordScreen()))),
                const Divider(),
                ListTile(leading: const Icon(Icons.devices_rounded), title: const Text('Signed-in devices'), trailing: const Icon(Icons.chevron_right_rounded), onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const DevicesScreen()))),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: [
                ListTile(leading: const SchoolLogo(size: 32), title: Text(session.schoolName), subtitle: Text(session.baseUrl ?? '')),
                const Divider(),
                ListTile(leading: const Icon(Icons.swap_horiz_rounded), title: const Text('Change school'), onTap: () => _signOut(context, changeSchool: true)),
                const Divider(),
                ListTile(leading: const Icon(Icons.logout_rounded, color: AppColors.red600), title: const Text('Sign out', style: TextStyle(color: AppColors.red600)), onTap: () => _signOut(context)),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const Center(child: Text('App version ${Session.appVersion}', style: TextStyle(color: AppColors.gray400, fontSize: 12))),
        ],
      ),
    );
  }
}

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  Object? _error;
  bool _busy = false;

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Session>().api.put('password', {'current_password': _current.text, 'password': _password.text, 'password_confirmation': _confirm.text});
      if (mounted) {
        showMessage(context, 'Your password has been changed.');
        Navigator.pop(context);
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
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final general = _error != null && fieldError(_error, 'current_password') == null && fieldError(_error, 'password') == null ? _error.toString() : null;
    return Scaffold(
      appBar: AppBar(title: const Text('Change password')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(controller: _current, obscureText: true, decoration: InputDecoration(labelText: 'Current password', errorText: fieldError(_error, 'current_password'))),
          const SizedBox(height: 14),
          TextField(controller: _password, obscureText: true, decoration: InputDecoration(labelText: 'New password (at least 8 characters)', errorText: fieldError(_error, 'password'), errorMaxLines: 2)),
          const SizedBox(height: 14),
          TextField(controller: _confirm, obscureText: true, decoration: const InputDecoration(labelText: 'Confirm new password')),
          if (general != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(general, style: const TextStyle(color: AppColors.red600))),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _save, child: const Text('Save password')),
        ],
      ),
    );
  }
}

class DevicesScreen extends StatelessWidget {
  const DevicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const Text('Signed-in devices')),
      body: AsyncView<List<Map<String, dynamic>>>(
        load: () async => asList(await api.get('erp/devices')),
        empty: (items) => items.isEmpty,
        builder: (context, devices, reload) => ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: devices.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final device = devices[index];
            final current = device['is_current'] == true;
            return Card(
              child: ListTile(
                leading: Icon(device['platform'] == 'web' ? Icons.computer_rounded : Icons.smartphone_rounded),
                title: Text(device['device_name']?.toString() ?? humanize(device['platform']?.toString() ?? 'Device')),
                subtitle: Text(current ? 'This phone' : 'Last active ${timeAgo(device['last_active_at'])}'),
                trailing: current
                    ? null
                    : IconButton(
                        tooltip: 'Sign out this device',
                        icon: const Icon(Icons.logout_rounded, color: AppColors.red600),
                        onPressed: () async {
                          if (await confirm(context, 'Sign out this device?', 'It will need to sign in again.', action: 'Sign out') && context.mounted) {
                            await runAction(context, () => api.delete('erp/devices/${device['id']}'), success: 'Device signed out.');
                            reload();
                          }
                        },
                      ),
              ),
            );
          },
        ),
      ),
    );
  }
}

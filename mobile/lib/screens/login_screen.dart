import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../core/widgets.dart';

/// Sign in with the account the school created (email, username or phone). There is no sign-up.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _identifier = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  bool _hidden = true;
  Object? _error;

  Future<void> _login() async {
    FocusScope.of(context).unfocus();
    if (_identifier.text.trim().isEmpty || _password.text.isEmpty) {
      setState(() => _error = ApiException('Please enter your login and password.'));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Session>().login(_identifier.text, _password.text);
    } catch (error) {
      if (mounted) {
        setState(() => _error = error);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _forgot() async {
    final controller = TextEditingController(text: _identifier.text);
    final sent = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(sheetContext).viewInsets.bottom + 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Reset your password', style: Theme.of(sheetContext).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 6),
            const Text('We will email a reset link to the address on your account.', style: TextStyle(color: AppColors.gray500)),
            const SizedBox(height: 16),
            TextField(controller: controller, decoration: const InputDecoration(labelText: 'Email, username or phone')),
            const SizedBox(height: 16),
            FilledButton(onPressed: () => Navigator.pop(sheetContext, true), child: const Text('Send reset link')),
          ],
        ),
      ),
    );
    if (sent == true && mounted) {
      final message = await runAction(context, () async {
        await context.read<Session>().api.post('forgot-password', {'identifier': controller.text.trim()});
        return 'If that account has an email address, a reset link is on its way.';
      });
      if (message != null && mounted) {
        showMessage(context, message);
      }
    }
  }

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final text = Theme.of(context).textTheme;
    final general = _error is ApiException && (_error as ApiException).fields.isEmpty ? _error.toString() : (_error is! ApiException && _error != null ? _error.toString() : null);

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: AutofillGroup(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Center(child: SchoolLogo(size: 84)),
                    const SizedBox(height: 16),
                    Text(session.schoolName, textAlign: TextAlign.center, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800, color: AppColors.gray900)),
                    if (session.schoolTagline != null && session.schoolTagline!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(session.schoolTagline!, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.gray500)),
                    ],
                    const SizedBox(height: 32),
                    TextField(
                      controller: _identifier,
                      autofillHints: const [AutofillHints.username],
                      keyboardType: TextInputType.emailAddress,
                      autocorrect: false,
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(labelText: 'Email, username or phone', prefixIcon: const Icon(Icons.person_outline_rounded), errorText: fieldError(_error, 'email'), errorMaxLines: 2),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: _password,
                      obscureText: _hidden,
                      autofillHints: const [AutofillHints.password],
                      textInputAction: TextInputAction.done,
                      onSubmitted: (_) => _busy ? null : _login(),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline_rounded),
                        errorText: fieldError(_error, 'password'),
                        suffixIcon: IconButton(icon: Icon(_hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined), onPressed: () => setState(() => _hidden = !_hidden)),
                      ),
                    ),
                    if (general != null) ...[
                      const SizedBox(height: 12),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(color: AppColors.red50, borderRadius: BorderRadius.circular(8)),
                        child: Text(general, style: const TextStyle(color: AppColors.red600)),
                      ),
                    ],
                    Align(alignment: Alignment.centerRight, child: TextButton(onPressed: _forgot, child: const Text('Forgot password?'))),
                    const SizedBox(height: 4),
                    FilledButton(
                      onPressed: _busy ? null : _login,
                      child: _busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white)) : const Text('Sign in'),
                    ),
                    const SizedBox(height: 24),
                    TextButton.icon(
                      onPressed: _busy ? null : () => context.read<Session>().forgetSchool(),
                      icon: const Icon(Icons.swap_horiz_rounded, size: 18),
                      label: const Text('Not your school? Change address'),
                      style: TextButton.styleFrom(foregroundColor: AppColors.gray500),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

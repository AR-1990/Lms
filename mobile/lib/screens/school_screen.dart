import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/session.dart';
import '../core/theme.dart';

/// First screen: the school's web address. The app then loads that school's name and logo and talks only to
/// its server, so the same app works for every school.
class SchoolScreen extends StatefulWidget {
  const SchoolScreen({super.key});

  @override
  State<SchoolScreen> createState() => _SchoolScreenState();
}

class _SchoolScreenState extends State<SchoolScreen> {
  final _address = TextEditingController();
  bool _busy = false;
  String? _error;

  Future<void> _connect() async {
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Session>().connectSchool(_address.text);
    } catch (error) {
      if (mounted) {
        setState(() => _error = error.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  void dispose() {
    _address.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      padding: const EdgeInsets.all(18),
                      decoration: const BoxDecoration(color: AppColors.brand50, shape: BoxShape.circle),
                      child: const Icon(Icons.school_rounded, size: 44, color: AppColors.brand600),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text('Connect to your school', textAlign: TextAlign.center, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800, color: AppColors.gray900)),
                  const SizedBox(height: 8),
                  const Text('Enter the website address the school gave you. You only do this once.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.gray500)),
                  const SizedBox(height: 28),
                  TextField(
                    controller: _address,
                    keyboardType: TextInputType.url,
                    autocorrect: false,
                    textInputAction: TextInputAction.go,
                    onSubmitted: (_) => _busy ? null : _connect(),
                    decoration: InputDecoration(
                      labelText: 'School address',
                      hintText: 'e.g. myschool.edu.pk',
                      prefixIcon: const Icon(Icons.language_rounded),
                      errorText: _error,
                      errorMaxLines: 3,
                    ),
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: _busy ? null : _connect,
                    child: _busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white)) : const Text('Continue'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

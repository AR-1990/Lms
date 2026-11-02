import 'dart:io';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:open_filex/open_filex.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api.dart';
import 'session.dart';
import 'theme.dart';

/// Loads data once (and again on pull-to-refresh), showing a spinner, a friendly error with Retry, or the content.
class AsyncView<T> extends StatefulWidget {
  const AsyncView({super.key, required this.load, required this.builder, this.empty});

  final Future<T> Function() load;
  final Widget Function(BuildContext context, T data, Future<void> Function() reload) builder;

  /// Returns true when the data has nothing to show (an empty state is drawn instead).
  final bool Function(T data)? empty;

  @override
  State<AsyncView<T>> createState() => _AsyncViewState<T>();
}

class _AsyncViewState<T> extends State<AsyncView<T>> {
  T? _data;
  Object? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _data == null;
      _error = null;
    });
    await _fetch();
  }

  Future<void> _fetch() async {
    try {
      final data = await widget.load();
      if (mounted) {
        setState(() {
          _data = data;
          _loading = false;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error;
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _data == null) {
      return MessageView(icon: Icons.wifi_off_rounded, title: 'Could not load', message: _error.toString(), actionLabel: 'Try again', onAction: _reload);
    }
    final data = _data as T;
    if (widget.empty != null && widget.empty!(data)) {
      return RefreshIndicator(
        onRefresh: _reload,
        child: ListView(children: const [SizedBox(height: 120), MessageView(icon: Icons.inbox_outlined, title: 'Nothing here yet', message: 'New items will show up here.')]),
      );
    }
    return RefreshIndicator(onRefresh: _reload, child: widget.builder(context, data, _reload));
  }
}

class MessageView extends StatelessWidget {
  const MessageView({super.key, required this.icon, required this.title, this.message, this.actionLabel, this.onAction});

  final IconData icon;
  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(color: AppColors.brand50, shape: BoxShape.circle),
              child: Icon(icon, size: 32, color: AppColors.brand600),
            ),
            const SizedBox(height: 16),
            Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700), textAlign: TextAlign.center),
            if (message != null) ...[
              const SizedBox(height: 6),
              Text(message!, style: const TextStyle(color: AppColors.gray500), textAlign: TextAlign.center),
            ],
            if (actionLabel != null) ...[
              const SizedBox(height: 16),
              OutlinedButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}

/// A white rounded card with an optional heading, like the portal's panels.
class Panel extends StatelessWidget {
  const Panel({super.key, this.title, this.trailing, required this.child, this.padding = const EdgeInsets.all(16)});

  final String? title;
  final Widget? trailing;
  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: padding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (title != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Row(
                  children: [
                    Expanded(child: Text(title!, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700, color: AppColors.gray900))),
                    if (trailing != null) trailing!,
                  ],
                ),
              ),
            child,
          ],
        ),
      ),
    );
  }
}

/// Small stat tile: big number with a label, tinted like the portal's stat cards.
class StatTile extends StatelessWidget {
  const StatTile({super.key, required this.label, required this.value, this.icon, this.color = AppColors.brand600, this.background = AppColors.brand50, this.onTap});

  final String label;
  final String value;
  final IconData? icon;
  final Color color;
  final Color background;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              if (icon != null) ...[
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(10)),
                  child: Icon(icon, color: color, size: 22),
                ),
                const SizedBox(width: 12),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.gray900)),
                    const SizedBox(height: 2),
                    Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Coloured status pill (paid / unpaid / present / absent ...).
class StatusChip extends StatelessWidget {
  const StatusChip(this.label, {super.key, this.tone});

  final String label;
  final String? tone;

  static (Color, Color) colorsFor(String value) {
    final key = value.toLowerCase();
    if (['paid', 'present', 'approved', 'published', 'pass', 'passed', 'submitted', 'graded', 'returned', 'active', 'verified', 'sent', 'completed', 'read'].contains(key)) {
      return (AppColors.green50, AppColors.green600);
    }
    if (['late', 'partial', 'partially_paid', 'pending', 'leave', 'on_leave', 'due', 'issued', 'draft', 'review', 'in_progress'].contains(key)) {
      return (AppColors.amber50, AppColors.amber700);
    }
    if (['absent', 'unpaid', 'overdue', 'rejected', 'fail', 'failed', 'cancelled', 'missing', 'lost'].contains(key)) {
      return (AppColors.red50, AppColors.red600);
    }
    return (AppColors.gray100, AppColors.gray600);
  }

  @override
  Widget build(BuildContext context) {
    final (background, foreground) = colorsFor(tone ?? label);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(999)),
      child: Text(humanize(label), style: TextStyle(color: foreground, fontSize: 12, fontWeight: FontWeight.w600)),
    );
  }
}

/// Label on the left, value on the right; for detail rows.
class InfoRow extends StatelessWidget {
  const InfoRow(this.label, this.value, {super.key});

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(flex: 2, child: Text(label, style: const TextStyle(color: AppColors.gray500))),
          const SizedBox(width: 12),
          Expanded(flex: 3, child: Text(value == null || value!.isEmpty ? '—' : value!, textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.gray800))),
        ],
      ),
    );
  }
}

/// The school's saved logo, or its initials when none is uploaded.
class SchoolLogo extends StatelessWidget {
  const SchoolLogo({super.key, this.size = 64});

  final double size;

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    if (session.logoFile != null) {
      return ClipRRect(borderRadius: BorderRadius.circular(size / 5), child: Image.file(session.logoFile!, width: size, height: size, fit: BoxFit.contain));
    }
    final initials = session.schoolName.split(' ').where((word) => word.isNotEmpty).take(2).map((word) => word[0].toUpperCase()).join();
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: AppColors.brand500, borderRadius: BorderRadius.circular(size / 5)),
      child: Text(initials.isEmpty ? 'S' : initials, style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: size / 2.8)),
    );
  }
}

/* ---------- Helpers ---------- */

String humanize(String value) {
  final text = value.replaceAll('_', ' ').trim();
  return text.isEmpty ? text : text[0].toUpperCase() + text.substring(1);
}

/// Money comes from the API in paisa (integer minor units).
String money(dynamic paisa, {String currency = 'Rs'}) {
  final amount = (num.tryParse('${paisa ?? 0}') ?? 0) / 100;
  return '$currency ${NumberFormat('#,##0', 'en_US').format(amount)}';
}

String formatDate(dynamic value, {String pattern = 'd MMM yyyy'}) {
  final date = value == null ? null : DateTime.tryParse(value.toString());
  return date == null ? '' : DateFormat(pattern).format(date.toLocal());
}

String timeAgo(dynamic value) {
  final date = value == null ? null : DateTime.tryParse(value.toString())?.toLocal();
  if (date == null) {
    return '';
  }
  final difference = DateTime.now().difference(date);
  if (difference.inMinutes < 1) {
    return 'just now';
  }
  if (difference.inMinutes < 60) {
    return '${difference.inMinutes}m ago';
  }
  if (difference.inHours < 24) {
    return '${difference.inHours}h ago';
  }
  if (difference.inDays < 7) {
    return '${difference.inDays}d ago';
  }
  return DateFormat('d MMM').format(date);
}

/// Today as Y-m-d in the phone's own time zone.
String today() => DateFormat('yyyy-MM-dd').format(DateTime.now());

List<Map<String, dynamic>> asList(dynamic value) {
  if (value is Map && value['items'] is List) {
    value = value['items'];
  }
  if (value is! List) {
    return [];
  }
  return value.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList();
}

Map<String, dynamic> asMap(dynamic value) => value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

void showMessage(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message), backgroundColor: error ? AppColors.red600 : AppColors.gray900));
}

/// Runs an action with a progress dialog and shows the server's message (or error) when done.
Future<T?> runAction<T>(BuildContext context, Future<T> Function() action, {String? success}) async {
  showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator()));
  try {
    final result = await action();
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
      if (success != null) {
        showMessage(context, success);
      }
    }
    return result;
  } catch (error) {
    if (context.mounted) {
      Navigator.of(context, rootNavigator: true).pop();
      showMessage(context, error.toString(), error: true);
    }
    return null;
  }
}

/// Downloads a PDF/file with the sign-in token and opens it in the phone's viewer.
Future<void> openFile(BuildContext context, String path, String fileName, {Map<String, dynamic>? query}) async {
  final api = context.read<Session>().api;
  final File? file = await runAction(context, () => api.download(path, fileName, query: query));
  if (file != null) {
    final result = await OpenFilex.open(file.path);
    if (result.type != ResultType.done && context.mounted) {
      showMessage(context, 'No app on this phone can open this file.', error: true);
    }
  }
}

Future<void> openLink(BuildContext context, String url) async {
  if (!await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication) && context.mounted) {
    showMessage(context, 'Could not open the link.', error: true);
  }
}

Future<bool> confirm(BuildContext context, String title, String message, {String action = 'Yes'}) async {
  final answer = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Cancel')),
        FilledButton(onPressed: () => Navigator.pop(dialogContext, true), child: Text(action)),
      ],
    ),
  );
  return answer == true;
}

/// Shows field errors from the API under the right inputs.
String? fieldError(Object? error, String field) => error is ApiException ? error.field(field) : null;

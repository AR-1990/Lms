import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'family_state.dart';

/// Online MCQ tests. Students take them here (answers are saved as they go, so a dropped connection loses
/// nothing); parents see the scores (GET my-online-tests).
class OnlineTestsScreen extends StatelessWidget {
  const OnlineTestsScreen({super.key, this.standalone = false});

  final bool standalone;

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    final family = context.read<FamilyState>();
    return Scaffold(
      appBar: AppBar(automaticallyImplyLeading: standalone, title: const ChildSwitcher(title: 'Online tests')),
      body: ChildScope(
        builder: (context, studentId) => AsyncView<List<Map<String, dynamic>>>(
          load: () async => asList(await api.get('erp/my-online-tests', query: family.isParent ? {'student_id': studentId} : null)),
          empty: (tests) => tests.isEmpty,
          builder: (context, tests, reload) => ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: tests.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final test = tests[index];
              final status = test['attempt_status']?.toString();
              final canTake = !family.isParent && test['is_open'] == true && status != 'submitted';
              return Panel(
                title: test['title']?.toString() ?? '',
                trailing: status == 'submitted'
                    ? StatusChip('${test['score'] ?? 0} / ${test['total_marks']}', tone: 'graded')
                    : StatusChip(test['is_open'] == true ? (status == 'in_progress' ? 'In progress' : 'Open') : 'Closed', tone: test['is_open'] == true ? 'due' : 'other'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text('${test['subject'] ?? ''} · ${test['duration_minutes']} min · ${test['total_marks']} marks', style: const TextStyle(color: AppColors.gray500)),
                    if (test['closes_at'] != null) Text('Closes ${formatDate(test['closes_at'], pattern: 'd MMM, h:mm a')}', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                    if (canTake) ...[
                      const SizedBox(height: 10),
                      FilledButton(
                        onPressed: () async {
                          await Navigator.push(context, MaterialPageRoute(builder: (_) => TakeTestScreen(testId: (test['id'] as num).toInt())));
                          reload();
                        },
                        child: Text(status == 'in_progress' ? 'Continue test' : 'Start test'),
                      ),
                    ],
                    if (!family.isParent && status == 'submitted') ...[
                      const SizedBox(height: 10),
                      OutlinedButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TestReviewScreen(testId: (test['id'] as num).toInt()))), child: const Text('See my answers')),
                    ],
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class TakeTestScreen extends StatefulWidget {
  const TakeTestScreen({super.key, required this.testId});

  final int testId;

  @override
  State<TakeTestScreen> createState() => _TakeTestScreenState();
}

class _TakeTestScreenState extends State<TakeTestScreen> {
  Map<String, dynamic>? _attempt;
  final Map<int, int> _answers = {};
  int _secondsLeft = 0;
  Timer? _timer;
  String? _error;
  bool _submitting = false;
  bool _savePending = false;

  @override
  void initState() {
    super.initState();
    _start();
  }

  Future<void> _start() async {
    try {
      final data = asMap(await context.read<Session>().api.post('erp/online-tests/${widget.testId}/start'));
      final saved = data['saved'];
      if (saved is Map) {
        saved.forEach((question, option) {
          final questionId = int.tryParse('$question');
          final optionId = int.tryParse('$option');
          if (questionId != null && optionId != null) {
            _answers[questionId] = optionId;
          }
        });
      }
      setState(() {
        _attempt = data;
        _secondsLeft = (data['seconds_left'] as num?)?.toInt() ?? 0;
      });
      _timer = Timer.periodic(const Duration(seconds: 1), (_) {
        if (_secondsLeft <= 1) {
          _timer?.cancel();
          _submit(auto: true);
        } else {
          setState(() => _secondsLeft--);
        }
      });
    } catch (error) {
      setState(() => _error = error.toString());
    }
  }

  List<Map<String, dynamic>> get _payload => [for (final entry in _answers.entries) {'question_id': entry.key, 'option_id': entry.value}];

  /// Save each answer straight away; if the phone is offline, the next save (or the submit) sends it again.
  Future<void> _choose(int questionId, int optionId) async {
    setState(() => _answers[questionId] = optionId);
    try {
      await context.read<Session>().api.put('erp/online-tests/${widget.testId}/answers', {'answers': _payload});
      if (mounted && _savePending) {
        setState(() => _savePending = false);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _savePending = true);
      }
    }
  }

  Future<void> _submit({bool auto = false}) async {
    if (_submitting) {
      return;
    }
    if (!auto) {
      final total = asList(_attempt?['questions']).length;
      final ok = await confirm(context, 'Submit the test?', 'You answered ${_answers.length} of $total questions. You cannot change answers after submitting.', action: 'Submit');
      if (!ok) {
        return;
      }
    }
    setState(() => _submitting = true);
    try {
      final review = asMap(await context.read<Session>().api.post('erp/online-tests/${widget.testId}/submit', {'answers': _payload}));
      _timer?.cancel();
      if (mounted) {
        Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => TestReviewScreen(testId: widget.testId, review: review)));
      }
    } catch (error) {
      if (mounted) {
        setState(() => _submitting = false);
        showMessage(context, '${error.toString()} Your answers are saved; try again.', error: true);
      }
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return Scaffold(appBar: AppBar(), body: MessageView(icon: Icons.block_rounded, title: 'Cannot open the test', message: _error));
    }
    if (_attempt == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    final questions = asList(_attempt!['questions']);
    final minutes = (_secondsLeft ~/ 60).toString().padLeft(2, '0');
    final seconds = (_secondsLeft % 60).toString().padLeft(2, '0');
    final low = _secondsLeft < 60;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (!didPop && await confirm(context, 'Leave the test?', 'Your answers are saved. The timer keeps running.', action: 'Leave') && context.mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(_attempt!['title']?.toString() ?? 'Test'),
          actions: [
            Container(
              margin: const EdgeInsets.only(right: 12),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(color: low ? AppColors.red50 : AppColors.brand50, borderRadius: BorderRadius.circular(20)),
              child: Row(children: [Icon(Icons.timer_outlined, size: 18, color: low ? AppColors.red600 : AppColors.brand700), const SizedBox(width: 4), Text('$minutes:$seconds', style: TextStyle(fontWeight: FontWeight.w800, color: low ? AppColors.red600 : AppColors.brand700))]),
            ),
          ],
        ),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_savePending)
              Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: AppColors.amber50, borderRadius: BorderRadius.circular(8)),
                child: const Text('No internet: your latest answer will be saved when you are back online.', style: TextStyle(color: AppColors.amber700)),
              ),
            if ((_attempt!['instructions'] ?? '').toString().isNotEmpty) Panel(child: Text(_attempt!['instructions'].toString())),
            for (var index = 0; index < questions.length; index++) ...[
              const SizedBox(height: 12),
              Panel(
                title: 'Q${index + 1}. ${questions[index]['question']}',
                trailing: Text('${questions[index]['marks']} mark${questions[index]['marks'] == 1 ? '' : 's'}', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                child: Column(
                  children: [
                    for (final option in asList(questions[index]['options']))
                      Builder(builder: (context) {
                        final questionId = (questions[index]['id'] as num).toInt();
                        final optionId = (option['id'] as num).toInt();
                        final chosen = _answers[questionId] == optionId;
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(8),
                            onTap: _submitting ? null : () => _choose(questionId, optionId),
                            child: Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: chosen ? AppColors.brand50 : Colors.white,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(color: chosen ? AppColors.brand500 : AppColors.gray200, width: chosen ? 2 : 1),
                              ),
                              child: Row(children: [
                                Icon(chosen ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded, color: chosen ? AppColors.brand600 : AppColors.gray400),
                                const SizedBox(width: 10),
                                Expanded(child: Text(option['option_text']?.toString() ?? '')),
                              ]),
                            ),
                          ),
                        );
                      }),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 20),
            FilledButton(onPressed: _submitting ? null : () => _submit(), child: Text(_submitting ? 'Submitting…' : 'Submit test')),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }
}

class TestReviewScreen extends StatelessWidget {
  const TestReviewScreen({super.key, required this.testId, this.review});

  final int testId;
  final Map<String, dynamic>? review;

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const Text('Test result')),
      body: AsyncView<Map<String, dynamic>>(
        load: () async => review ?? asMap(await api.get('erp/online-tests/$testId/review')),
        builder: (context, data, reload) {
          final questions = asList(data['questions']);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Panel(
                child: Column(
                  children: [
                    Text(data['title']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    Text('${data['score'] ?? 0} / ${data['total_marks'] ?? 0}', style: const TextStyle(fontSize: 34, fontWeight: FontWeight.w800, color: AppColors.brand600)),
                    Text('${data['correct_count'] ?? 0} correct', style: const TextStyle(color: AppColors.gray500)),
                    if (data['late'] == true) const Padding(padding: EdgeInsets.only(top: 6), child: StatusChip('Submitted late', tone: 'late')),
                  ],
                ),
              ),
              if (questions.isEmpty) const Padding(padding: EdgeInsets.only(top: 16), child: Text('Your teacher will share the answers later.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.gray500))),
              for (final question in questions) ...[
                const SizedBox(height: 12),
                Panel(
                  title: question['question']?.toString(),
                  trailing: Icon(question['is_correct'] == true ? Icons.check_circle_rounded : Icons.cancel_rounded, color: question['is_correct'] == true ? AppColors.green600 : AppColors.red600),
                  child: Column(
                    children: [
                      for (final option in asList(question['options']))
                        ListTile(
                          dense: true,
                          contentPadding: EdgeInsets.zero,
                          leading: Icon(
                            option['is_correct'] == true ? Icons.check_rounded : (option['id'] == question['chosen_option_id'] ? Icons.close_rounded : Icons.circle_outlined),
                            color: option['is_correct'] == true ? AppColors.green600 : (option['id'] == question['chosen_option_id'] ? AppColors.red600 : AppColors.gray300),
                          ),
                          title: Text(option['option_text']?.toString() ?? ''),
                        ),
                    ],
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

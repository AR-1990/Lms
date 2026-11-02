import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/api.dart';
import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// Staff self-service: leave balances and requests, payslips (PDF) and loans (GET/POST my-hr/...).
class MyHrScreen extends StatelessWidget {
  const MyHrScreen({super.key, this.initialTab = 0});

  final int initialTab;

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return DefaultTabController(
      length: 3,
      initialIndex: initialTab,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('My HR'),
          bottom: const TabBar(labelColor: AppColors.brand700, indicatorColor: AppColors.brand500, tabs: [Tab(text: 'Leave'), Tab(text: 'Payslips'), Tab(text: 'Loans')]),
        ),
        body: TabBarView(children: [_Leave(api: api), _Payslips(api: api), _Loans(api: api)]),
      ),
    );
  }
}

class _Leave extends StatelessWidget {
  const _Leave({required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return AsyncView<Map<String, dynamic>>(
      load: () async => asMap(await api.get('erp/my-hr/leave')),
      builder: (context, data, reload) {
        final balances = asList(data['balances']);
        final requests = asList(data['requests']);
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            FilledButton.icon(
              onPressed: balances.isEmpty
                  ? null
                  : () async {
                      final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => _ApplyLeave(api: api, types: balances)));
                      if (saved == true) {
                        reload();
                      }
                    },
              icon: const Icon(Icons.add_rounded),
              label: const Text('Apply for leave'),
            ),
            const SizedBox(height: 12),
            if (balances.isNotEmpty)
              Panel(
                title: 'Balance',
                child: Column(children: [
                  for (final balance in balances)
                    InfoRow(balance['name']?.toString() ?? '', balance['remaining'] == null ? '${_days(balance['used'])} used' : '${_days(balance['remaining'])} left of ${_days((num.tryParse('${balance['allocated'] ?? 0}') ?? 0) + (num.tryParse('${balance['carried_forward'] ?? 0}') ?? 0))}'),
                ]),
              ),
            const SizedBox(height: 12),
            if (requests.isEmpty) const MessageView(icon: Icons.beach_access_outlined, title: 'No leave requests yet'),
            for (final leave in requests)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Panel(
                  title: '${leave['leave_type']} · ${_days(leave['days'])} day${leave['days'] == 1 ? '' : 's'}',
                  trailing: StatusChip(leave['status']?.toString() ?? ''),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Text(leave['from_date'] == leave['to_date'] ? formatDate(leave['from_date']) : '${formatDate(leave['from_date'], pattern: 'd MMM')} – ${formatDate(leave['to_date'])}', style: const TextStyle(fontWeight: FontWeight.w600)),
                    Text(leave['reason']?.toString() ?? '', style: const TextStyle(color: AppColors.gray600)),
                    if ((leave['review_remarks'] ?? '').toString().isNotEmpty) Text('Remarks: ${leave['review_remarks']}', style: const TextStyle(fontSize: 12, color: AppColors.gray500)),
                    if (_cancellable(leave))
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: () async {
                            if (await confirm(context, 'Cancel this leave?', 'The days go back to your balance.', action: 'Cancel leave') && context.mounted) {
                              final done = await runAction(context, () => api.post('erp/my-hr/leave/${leave['id']}/cancel'), success: 'Leave cancelled.');
                              if (done != null) {
                                reload();
                              }
                            }
                          },
                          child: const Text('Cancel leave', style: TextStyle(color: AppColors.red600)),
                        ),
                      ),
                  ]),
                ),
              ),
          ],
        );
      },
    );
  }

  bool _cancellable(Map<String, dynamic> leave) {
    if (leave['status'] == 'pending') {
      return true;
    }
    final from = DateTime.tryParse('${leave['from_date']}');
    return leave['status'] == 'approved' && from != null && from.isAfter(DateTime.now());
  }
}

String _days(dynamic value) {
  final number = num.tryParse('${value ?? 0}') ?? 0;
  return number == number.roundToDouble() ? number.toInt().toString() : number.toStringAsFixed(1);
}

class _ApplyLeave extends StatefulWidget {
  const _ApplyLeave({required this.api, required this.types});

  final ApiClient api;
  final List<Map<String, dynamic>> types;

  @override
  State<_ApplyLeave> createState() => _ApplyLeaveState();
}

class _ApplyLeaveState extends State<_ApplyLeave> {
  late int _typeId = (widget.types.first['leave_type_id'] as num).toInt();
  DateTimeRange _range = DateTimeRange(start: DateTime.now(), end: DateTime.now());
  bool _halfDay = false;
  final _reason = TextEditingController();
  Object? _error;
  bool _busy = false;

  Future<void> _save() async {
    final format = DateFormat('yyyy-MM-dd');
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.api.post('erp/my-hr/leave', {
        'leave_type_id': _typeId,
        'from_date': format.format(_range.start),
        'to_date': format.format(_range.end),
        'is_half_day': _halfDay && _range.start == _range.end,
        'reason': _reason.text.trim(),
      });
      if (mounted) {
        showMessage(context, 'Leave request sent.');
        Navigator.pop(context, true);
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
  Widget build(BuildContext context) {
    final fieldErrors = ['leave_type_id', 'from_date', 'to_date', 'reason'].map((field) => fieldError(_error, field)).whereType<String>().toList();
    return Scaffold(
      appBar: AppBar(title: const Text('Apply for leave')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DropdownButtonFormField<int>(
            value: _typeId,
            decoration: InputDecoration(labelText: 'Leave type', errorText: fieldError(_error, 'leave_type_id')),
            items: [
              for (final type in widget.types)
                DropdownMenuItem(value: (type['leave_type_id'] as num).toInt(), child: Text('${type['name']}${type['remaining'] != null ? ' (${_days(type['remaining'])} left)' : ''}')),
            ],
            onChanged: (value) => setState(() => _typeId = value ?? _typeId),
          ),
          const SizedBox(height: 14),
          InkWell(
            onTap: () async {
              final picked = await showDateRangePicker(context: context, firstDate: DateTime.now().subtract(const Duration(days: 30)), lastDate: DateTime.now().add(const Duration(days: 180)), initialDateRange: _range);
              if (picked != null) {
                setState(() => _range = picked);
              }
            },
            child: InputDecorator(
              decoration: InputDecoration(labelText: 'Dates', errorText: fieldError(_error, 'from_date') ?? fieldError(_error, 'to_date'), suffixIcon: const Icon(Icons.date_range_rounded)),
              child: Text(_range.start == _range.end ? DateFormat('d MMM yyyy').format(_range.start) : '${DateFormat('d MMM').format(_range.start)} – ${DateFormat('d MMM yyyy').format(_range.end)}'),
            ),
          ),
          if (_range.start == _range.end) SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Half day'), value: _halfDay, onChanged: (value) => setState(() => _halfDay = value)),
          const SizedBox(height: 8),
          TextField(controller: _reason, maxLines: 3, decoration: InputDecoration(labelText: 'Reason', errorText: fieldError(_error, 'reason'))),
          if (_error != null && fieldErrors.isEmpty) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error.toString(), style: const TextStyle(color: AppColors.red600))),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _save, child: const Text('Send request')),
        ],
      ),
    );
  }
}

class _Payslips extends StatelessWidget {
  const _Payslips({required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<Map<String, dynamic>>>(
      load: () async => asList(await api.get('erp/my-hr/payslips')),
      empty: (items) => items.isEmpty,
      builder: (context, items, reload) => ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          final slip = items[index];
          return Card(
            child: ExpansionTile(
              shape: const Border(),
              title: Text(formatDate(slip['month'], pattern: 'MMMM yyyy'), style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text('Net ${money(slip['net'])}${slip['paid_on'] != null ? ' · paid ${formatDate(slip['paid_on'], pattern: 'd MMM')}' : ''}'),
              trailing: StatusChip(slip['status']?.toString() ?? ''),
              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              children: [
                InfoRow('Gross', money(slip['gross'])),
                InfoRow('Deductions', money(slip['deductions'])),
                InfoRow('Working days', _days(slip['working_days'])),
                InfoRow('Present', _days(slip['present_days'])),
                InfoRow('Absent', _days(slip['absent_days'])),
                InfoRow('Leave', _days(slip['leave_days'])),
                if ((slip['late_count'] ?? 0) != 0) InfoRow('Late', '${slip['late_count']} times'),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: () => openFile(context, 'erp/my-hr/payslips/${slip['id']}/pdf', 'payslip-${slip['month']}.pdf'),
                    icon: const Icon(Icons.picture_as_pdf_outlined),
                    label: const Text('Open payslip'),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _Loans extends StatelessWidget {
  const _Loans({required this.api});

  final ApiClient api;

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<Map<String, dynamic>>>(
      load: () async => asList(await api.get('erp/my-hr/loans')),
      builder: (context, loans, reload) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          FilledButton.icon(
            onPressed: () async {
              final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => _RequestLoan(api: api)));
              if (saved == true) {
                reload();
              }
            },
            icon: const Icon(Icons.add_rounded),
            label: const Text('Request a loan / advance'),
          ),
          const SizedBox(height: 12),
          if (loans.isEmpty) const MessageView(icon: Icons.account_balance_wallet_outlined, title: 'No loans'),
          for (final loan in loans)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Panel(
                title: '${asMap(loan['loan_type'])['name'] ?? 'Loan'} · ${money(loan['amount'])}',
                trailing: StatusChip(loan['status']?.toString() ?? ''),
                child: Column(children: [
                  InfoRow('Instalments', '${loan['instalments']} × ${money(loan['instalment_amount'])}'),
                  InfoRow('Starts', formatDate(loan['start_month'], pattern: 'MMM yyyy')),
                  InfoRow('Outstanding', money(loan['outstanding'])),
                  if ((loan['review_remarks'] ?? '').toString().isNotEmpty) InfoRow('Remarks', loan['review_remarks'].toString()),
                ]),
              ),
            ),
        ],
      ),
    );
  }
}

class _RequestLoan extends StatefulWidget {
  const _RequestLoan({required this.api});

  final ApiClient api;

  @override
  State<_RequestLoan> createState() => _RequestLoanState();
}

class _RequestLoanState extends State<_RequestLoan> {
  List<Map<String, dynamic>> _types = [];
  int? _typeId;
  final _amount = TextEditingController();
  final _instalments = TextEditingController(text: '6');
  final _reason = TextEditingController();
  DateTime _start = DateTime(DateTime.now().year, DateTime.now().month + 1);
  Object? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    widget.api.get('erp/lookup-options/loan_type').then((types) => setState(() => _types = asList(types))).catchError((_) {});
  }

  Future<void> _save() async {
    final rupees = double.tryParse(_amount.text.replaceAll(',', ''));
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.api.post('erp/my-hr/loans', {
        if (_typeId != null) 'loan_type_id': _typeId,
        'amount': rupees == null ? null : (rupees * 100).round(),
        'instalments': int.tryParse(_instalments.text),
        'start_month': DateFormat('yyyy-MM').format(_start),
        if (_reason.text.trim().isNotEmpty) 'reason': _reason.text.trim(),
      });
      if (mounted) {
        showMessage(context, 'Loan request sent.');
        Navigator.pop(context, true);
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
  Widget build(BuildContext context) {
    final months = [for (var offset = 0; offset < 6; offset++) DateTime(DateTime.now().year, DateTime.now().month + offset)];
    final hasFieldError = ['amount', 'instalments', 'start_month', 'loan_type_id'].any((field) => fieldError(_error, field) != null);
    return Scaffold(
      appBar: AppBar(title: const Text('Request a loan')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_types.isNotEmpty) ...[
            DropdownButtonFormField<int>(
              value: _typeId,
              decoration: InputDecoration(labelText: 'Type', errorText: fieldError(_error, 'loan_type_id')),
              items: [for (final type in _types) DropdownMenuItem(value: (type['id'] as num).toInt(), child: Text(type['name'].toString()))],
              onChanged: (value) => setState(() => _typeId = value),
            ),
            const SizedBox(height: 14),
          ],
          TextField(controller: _amount, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'Amount (Rs)', errorText: fieldError(_error, 'amount'))),
          const SizedBox(height: 14),
          TextField(controller: _instalments, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'Number of monthly instalments', errorText: fieldError(_error, 'instalments'))),
          const SizedBox(height: 14),
          DropdownButtonFormField<DateTime>(
            value: months.firstWhere((month) => month.year == _start.year && month.month == _start.month, orElse: () => months.first),
            decoration: InputDecoration(labelText: 'First deduction', errorText: fieldError(_error, 'start_month')),
            items: [for (final month in months) DropdownMenuItem(value: month, child: Text(DateFormat('MMMM yyyy').format(month)))],
            onChanged: (value) => setState(() => _start = value ?? _start),
          ),
          const SizedBox(height: 14),
          TextField(controller: _reason, maxLines: 3, decoration: const InputDecoration(labelText: 'Reason (optional)')),
          if (_error != null && !hasFieldError) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_error.toString(), style: const TextStyle(color: AppColors.red600))),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _save, child: const Text('Send request')),
        ],
      ),
    );
  }
}

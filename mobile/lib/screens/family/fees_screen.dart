import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'family_state.dart';

/// Fees: open challans (PDF), receipts (PDF), and "paid online? send the screenshot" with its review status.
class FeesScreen extends StatelessWidget {
  const FeesScreen({super.key, this.standalone = false});

  /// Opened from another screen (has a back button) rather than as a tab.
  final bool standalone;

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          automaticallyImplyLeading: standalone,
          title: const ChildSwitcher(title: 'Fees'),
          bottom: const TabBar(labelColor: AppColors.brand700, indicatorColor: AppColors.brand500, tabs: [Tab(text: 'Challans'), Tab(text: 'Receipts'), Tab(text: 'Paid online')]),
        ),
        body: ChildScope(
          builder: (context, studentId) => AsyncView<List<Map<String, dynamic>>>(
            load: () async {
              final results = await Future.wait([api.get('erp/family/students/$studentId'), api.get('erp/fees/students/$studentId/payment-proofs')]);
              return [asMap(asMap(results[0])['fees']), asMap(results[1])];
            },
            builder: (context, data, reload) => TabBarView(
              children: [
                _Challans(fees: data[0]),
                _Receipts(fees: data[0]),
                _Proofs(proofs: data[1], challans: asList(data[0]['open_challans']), studentId: studentId, reload: reload),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Challans extends StatelessWidget {
  const _Challans({required this.fees});

  final Map<String, dynamic> fees;

  @override
  Widget build(BuildContext context) {
    final challans = asList(fees['open_challans']);
    final advance = (fees['advance'] as num?) ?? 0;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            Expanded(child: StatTile(label: 'Total due', value: money(fees['due']), icon: Icons.account_balance_wallet_rounded, color: AppColors.red600, background: AppColors.red50)),
            if (advance > 0) ...[
              const SizedBox(width: 10),
              Expanded(child: StatTile(label: 'Advance', value: money(advance), icon: Icons.savings_rounded, color: AppColors.green600, background: AppColors.green50)),
            ],
          ],
        ),
        const SizedBox(height: 12),
        if (challans.isEmpty) const MessageView(icon: Icons.check_circle_outline_rounded, title: 'No fee due', message: 'All challans are paid.'),
        for (final challan in challans)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Panel(
              title: '${humanize(challan['invoice_type']?.toString() ?? 'Fee')}${challan['billing_month'] != null ? ' · ${challan['billing_month']}' : ''}',
              trailing: StatusChip(challan['is_overdue'] == true ? 'Overdue' : 'Due', tone: challan['is_overdue'] == true ? 'overdue' : 'due'),
              child: Column(
                children: [
                  InfoRow('Challan no.', challan['invoice_no']?.toString()),
                  InfoRow('Due date', formatDate(challan['due_date'])),
                  InfoRow('Amount', money(challan['total'])),
                  InfoRow('Balance', money(challan['balance'])),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: () => openFile(context, challan['pdf_url'].toString(), 'challan-${challan['invoice_no']}.pdf'),
                      icon: const Icon(Icons.picture_as_pdf_outlined),
                      label: const Text('Open challan'),
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _Receipts extends StatelessWidget {
  const _Receipts({required this.fees});

  final Map<String, dynamic> fees;

  @override
  Widget build(BuildContext context) {
    final receipts = asList(fees['receipts']);
    if (receipts.isEmpty) {
      return const MessageView(icon: Icons.receipt_outlined, title: 'No receipts yet');
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: receipts.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final receipt = receipts[index];
        return Card(
          child: ListTile(
            leading: const CircleAvatar(backgroundColor: AppColors.green50, foregroundColor: AppColors.green600, child: Icon(Icons.receipt_rounded)),
            title: Text(money(receipt['amount']), style: const TextStyle(fontWeight: FontWeight.w700)),
            subtitle: Text('${receipt['receipt_no']} · ${formatDate(receipt['received_on'])}${receipt['method'] != null ? ' · ${receipt['method']}' : ''}'),
            trailing: receipt['status'] == 'reversed' ? const StatusChip('Reversed', tone: 'cancelled') : const Icon(Icons.picture_as_pdf_outlined, color: AppColors.gray500),
            onTap: () => openFile(context, receipt['pdf_url'].toString(), 'receipt-${receipt['receipt_no']}.pdf'),
          ),
        );
      },
    );
  }
}

class _Proofs extends StatelessWidget {
  const _Proofs({required this.proofs, required this.challans, required this.studentId, required this.reload});

  final Map<String, dynamic> proofs;
  final List<Map<String, dynamic>> challans;
  final int studentId;
  final Future<void> Function() reload;

  @override
  Widget build(BuildContext context) {
    final payTo = asMap(proofs['pay_to']);
    final bank = asMap(payTo['bank']);
    final methods = asList(payTo['methods']);
    final sent = asList(proofs['proofs']);
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Panel(
          title: 'Pay to',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (bank.isNotEmpty) ...[
                InfoRow('Bank', bank['bank']?.toString()),
                InfoRow('Account title', bank['account_title']?.toString()),
                _CopyRow('Account no.', bank['account_number']?.toString()),
                if (bank['iban'] != null) _CopyRow('IBAN', bank['iban'].toString()),
              ],
              for (final method in methods)
                if ((method['payment_instructions'] ?? '').toString().isNotEmpty)
                  Padding(padding: const EdgeInsets.only(top: 8), child: Text('${method['name']}: ${method['payment_instructions']}', style: const TextStyle(color: AppColors.gray600))),
            ],
          ),
        ),
        const SizedBox(height: 12),
        FilledButton.icon(
          onPressed: methods.isEmpty
              ? null
              : () async {
                  final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => SendProofScreen(studentId: studentId, methods: methods, challans: challans)));
                  if (saved == true) {
                    reload();
                  }
                },
          icon: const Icon(Icons.upload_rounded),
          label: const Text('I paid online: send screenshot'),
        ),
        const SizedBox(height: 16),
        if (sent.isNotEmpty) const Text('Sent screenshots', style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.gray700)),
        for (final proof in sent)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Card(
              child: ListTile(
                title: Text(money(proof['amount']), style: const TextStyle(fontWeight: FontWeight.w700)),
                subtitle: Text([
                  proof['reference_no'],
                  formatDate(proof['paid_on']),
                  if (proof['status'] == 'approved' && proof['receipt_no'] != null) 'Receipt ${proof['receipt_no']}',
                  if (proof['status'] == 'rejected' && proof['reject_reason'] != null) 'Reason: ${proof['reject_reason']}',
                ].whereType<Object>().join(' · ')),
                trailing: StatusChip(proof['status']?.toString() ?? ''),
              ),
            ),
          ),
      ],
    );
  }
}

class _CopyRow extends StatelessWidget {
  const _CopyRow(this.label, this.value);

  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: value == null
          ? null
          : () {
              Clipboard.setData(ClipboardData(text: value!));
              showMessage(context, '$label copied');
            },
      child: Row(children: [Expanded(child: InfoRow(label, value)), const Padding(padding: EdgeInsets.only(left: 6), child: Icon(Icons.copy_rounded, size: 16, color: AppColors.gray400))]),
    );
  }
}

/// The payment-proof form (POST fees/students/{id}/payment-proofs, multipart with the screenshot).
class SendProofScreen extends StatefulWidget {
  const SendProofScreen({super.key, required this.studentId, required this.methods, required this.challans});

  final int studentId;
  final List<Map<String, dynamic>> methods;
  final List<Map<String, dynamic>> challans;

  @override
  State<SendProofScreen> createState() => _SendProofScreenState();
}

class _SendProofScreenState extends State<SendProofScreen> {
  final _amount = TextEditingController();
  final _reference = TextEditingController();
  final _payer = TextEditingController();
  final _note = TextEditingController();
  int? _methodId;
  int? _challanId;
  DateTime _paidOn = DateTime.now();
  File? _screenshot;
  Object? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _methodId = (widget.methods.first['id'] as num).toInt();
    if (widget.challans.isNotEmpty) {
      _pickChallan((widget.challans.first['id'] as num).toInt());
    }
  }

  void _pickChallan(int? id) {
    _challanId = id;
    final challan = widget.challans.where((item) => item['id'] == id).toList();
    if (challan.isNotEmpty) {
      _amount.text = (((challan.first['balance'] as num?) ?? 0) / 100).toStringAsFixed(0);
    }
  }

  Future<void> _pick(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source, imageQuality: 80, maxWidth: 2000);
    if (picked != null) {
      setState(() => _screenshot = File(picked.path));
    }
  }

  Future<void> _send() async {
    final rupees = double.tryParse(_amount.text.replaceAll(',', '').trim());
    if (_screenshot == null || rupees == null || rupees <= 0 || _reference.text.trim().isEmpty) {
      showMessage(context, 'Please fill the amount, transaction ID and add the screenshot.', error: true);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Session>().api.upload('erp/fees/students/${widget.studentId}/payment-proofs', fields: {
        if (_challanId != null) 'fee_invoice_id': '$_challanId',
        'payment_method_id': '$_methodId',
        'amount': '${(rupees * 100).round()}',
        'paid_on': '${_paidOn.year}-${_paidOn.month.toString().padLeft(2, '0')}-${_paidOn.day.toString().padLeft(2, '0')}',
        'reference_no': _reference.text.trim(),
        if (_payer.text.trim().isNotEmpty) 'payer_name': _payer.text.trim(),
        if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
      }, files: {
        'screenshot': [_screenshot!],
      });
      if (mounted) {
        showMessage(context, 'Sent. The accounts office will check it and issue the receipt.');
        Navigator.pop(context, true);
      }
    } catch (error) {
      setState(() => _error = error);
      if (mounted) {
        showMessage(context, error.toString(), error: true);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Send payment screenshot')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (widget.challans.isNotEmpty) ...[
            DropdownButtonFormField<int?>(
              value: _challanId,
              decoration: InputDecoration(labelText: 'For challan', errorText: fieldError(_error, 'fee_invoice_id')),
              items: [
                for (final challan in widget.challans) DropdownMenuItem(value: (challan['id'] as num).toInt(), child: Text('${challan['invoice_no']} · ${money(challan['balance'])}')),
                const DropdownMenuItem(value: null, child: Text('Other / advance')),
              ],
              onChanged: (value) => setState(() => _pickChallan(value)),
            ),
            const SizedBox(height: 14),
          ],
          DropdownButtonFormField<int>(
            value: _methodId,
            decoration: InputDecoration(labelText: 'Paid through', errorText: fieldError(_error, 'payment_method_id')),
            items: [for (final method in widget.methods) DropdownMenuItem(value: (method['id'] as num).toInt(), child: Text(method['name'].toString()))],
            onChanged: (value) => setState(() => _methodId = value),
          ),
          const SizedBox(height: 14),
          TextField(controller: _amount, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'Amount paid (Rs)', errorText: fieldError(_error, 'amount'))),
          const SizedBox(height: 14),
          TextField(controller: _reference, textCapitalization: TextCapitalization.characters, decoration: InputDecoration(labelText: 'Transaction ID / reference no.', errorText: fieldError(_error, 'reference_no'))),
          const SizedBox(height: 14),
          InkWell(
            onTap: () async {
              final picked = await showDatePicker(context: context, initialDate: _paidOn, firstDate: DateTime.now().subtract(const Duration(days: 59)), lastDate: DateTime.now());
              if (picked != null) {
                setState(() => _paidOn = picked);
              }
            },
            child: InputDecorator(decoration: InputDecoration(labelText: 'Paid on', errorText: fieldError(_error, 'paid_on')), child: Text(formatDate(_paidOn.toIso8601String()))),
          ),
          const SizedBox(height: 14),
          TextField(controller: _payer, decoration: const InputDecoration(labelText: 'Paid by (optional)')),
          const SizedBox(height: 14),
          TextField(controller: _note, maxLines: 2, decoration: const InputDecoration(labelText: 'Note (optional)')),
          const SizedBox(height: 16),
          if (_screenshot != null)
            ClipRRect(borderRadius: BorderRadius.circular(10), child: Image.file(_screenshot!, height: 220, fit: BoxFit.cover))
          else if (fieldError(_error, 'screenshot') != null)
            Text(fieldError(_error, 'screenshot')!, style: const TextStyle(color: AppColors.red600)),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(child: OutlinedButton.icon(onPressed: () => _pick(ImageSource.gallery), icon: const Icon(Icons.photo_library_outlined), label: const Text('Gallery'))),
              const SizedBox(width: 10),
              Expanded(child: OutlinedButton.icon(onPressed: () => _pick(ImageSource.camera), icon: const Icon(Icons.photo_camera_outlined), label: const Text('Camera'))),
            ],
          ),
          const SizedBox(height: 20),
          FilledButton(onPressed: _busy ? null : _send, child: _busy ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white)) : const Text('Send for checking')),
        ],
      ),
    );
  }
}

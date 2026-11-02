import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// The user's own gate QR (shown at the attendance kiosk) and every ID card they or their children hold.
class IdCardScreen extends StatelessWidget {
  const IdCardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const Text('ID cards')),
      body: AsyncView<List<dynamic>>(
        load: () => Future.wait([api.get('erp/my-attendance-qr'), api.get('erp/my-id-cards')]),
        builder: (context, data, reload) {
          final qr = data[0] is Map ? asMap(data[0]) : null;
          final cards = asList(data[1]);
          final qrBytes = qr == null ? null : _decodeImage(qr['qr']?.toString());
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (qr != null)
                Panel(
                  child: Column(
                    children: [
                      Text(qr['holder_name']?.toString() ?? '', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                      Text('Card ${qr['card_no']}', style: const TextStyle(color: AppColors.gray500)),
                      const SizedBox(height: 12),
                      if (qrBytes != null) Container(color: Colors.white, padding: const EdgeInsets.all(8), child: Image.memory(qrBytes, width: 240, height: 240, gaplessPlayback: true, filterQuality: FilterQuality.none)),
                      const SizedBox(height: 8),
                      const Text('Show this at the school gate to mark attendance.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.gray500)),
                      if (qr['valid_until'] != null) Text('Valid until ${formatDate(qr['valid_until'])}', style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
                    ],
                  ),
                ),
              if (qr != null) const SizedBox(height: 16),
              if (cards.isEmpty && qr == null) const MessageView(icon: Icons.badge_outlined, title: 'No ID card yet', message: 'The school office issues ID cards.'),
              for (final card in cards)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Card(
                    child: ListTile(
                      leading: const CircleAvatar(backgroundColor: AppColors.violet50, foregroundColor: AppColors.violet600, child: Icon(Icons.badge_rounded)),
                      title: Text(card['holder_name']?.toString() ?? ''),
                      subtitle: Text('${card['card_no']}${card['valid_until'] != null ? ' · valid till ${formatDate(card['valid_until'])}' : ''}'),
                      trailing: const Icon(Icons.download_rounded),
                      onTap: () => openFile(context, 'erp/my-id-cards/${card['id']}/download', 'id-card-${card['card_no']}.pdf'),
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  /// The API sends the QR as a PNG data URI ("data:image/png;base64,...").
  static Uint8List? _decodeImage(String? value) {
    if (value == null || value.isEmpty) {
      return null;
    }
    final comma = value.indexOf(',');
    try {
      return base64Decode(comma >= 0 ? value.substring(comma + 1) : value);
    } catch (_) {
      return null;
    }
  }
}

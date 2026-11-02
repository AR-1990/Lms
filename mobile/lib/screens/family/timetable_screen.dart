import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';
import 'family_state.dart';

/// The child's weekly timetable, one tab per school day (from GET family/students/{id}).
class TimetableScreen extends StatelessWidget {
  const TimetableScreen({super.key});

  static const _days = {1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun'};

  @override
  Widget build(BuildContext context) {
    final api = context.read<Session>().api;
    return Scaffold(
      appBar: AppBar(title: const ChildSwitcher(title: 'Timetable')),
      body: ChildScope(
        builder: (context, studentId) => AsyncView<List<Map<String, dynamic>>>(
          load: () async => asList(asMap(await api.get('erp/family/students/$studentId'))['timetable']),
          empty: (slots) => slots.isEmpty,
          builder: (context, slots, reload) {
            final days = slots.map((slot) => (slot['day_of_week'] as num).toInt()).toSet().toList()..sort();
            final today = DateTime.now().weekday;
            return DefaultTabController(
              length: days.length,
              initialIndex: days.contains(today) ? days.indexOf(today) : 0,
              child: Column(
                children: [
                  Material(color: Colors.white, child: TabBar(isScrollable: days.length > 5, labelColor: AppColors.brand700, indicatorColor: AppColors.brand500, tabs: [for (final day in days) Tab(text: _days[day])])),
                  Expanded(
                    child: TabBarView(
                      children: [
                        for (final day in days)
                          Builder(builder: (context) {
                            final daySlots = slots.where((slot) => slot['day_of_week'] == day).toList()
                              ..sort((a, b) => '${asMap(a['period'])['starts_at']}'.compareTo('${asMap(b['period'])['starts_at']}'));
                            return ListView.separated(
                              padding: const EdgeInsets.all(16),
                              itemCount: daySlots.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 8),
                              itemBuilder: (context, index) {
                                final slot = daySlots[index];
                                final period = asMap(slot['period']);
                                return Card(
                                  child: ListTile(
                                    leading: Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Text(_hm(period['starts_at']), style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.brand700)),
                                        Text(_hm(period['ends_at']), style: const TextStyle(fontSize: 12, color: AppColors.gray400)),
                                      ],
                                    ),
                                    title: Text(slot['subject']?.toString() ?? period['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w700)),
                                    subtitle: Text([period['name'], slot['teacher'], if (slot['room'] != null) 'Room ${slot['room']}'].whereType<Object>().join(' · ')),
                                  ),
                                );
                              },
                            );
                          }),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  static String _hm(dynamic value) {
    final text = (value ?? '').toString();
    return text.length >= 5 ? text.substring(0, 5) : text;
  }
}

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/session.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// The children a parent can see (a student account gets only themself) and which one is selected.
class FamilyState extends ChangeNotifier {
  FamilyState(this.session);

  final Session session;
  List<Map<String, dynamic>> children = [];
  int? selectedId;
  bool loading = true;
  String? error;

  Map<String, dynamic>? get child {
    for (final item in children) {
      if (item['id'] == selectedId) {
        return item;
      }
    }
    return null;
  }
  int get childId => selectedId ?? 0;
  String get childName => (child?['name'] ?? '').toString();
  bool get isParent => session.role == AppRole.parent;

  Future<void> load() async {
    loading = true;
    error = null;
    notifyListeners();
    try {
      children = asList(await session.api.get('erp/family/students'));
      if (children.isNotEmpty && (selectedId == null || child == null)) {
        selectedId = (children.firstWhere((item) => item['status'] == 'active', orElse: () => children.first)['id'] as num).toInt();
      }
    } catch (failure) {
      error = failure.toString();
    }
    loading = false;
    notifyListeners();
  }

  void select(int id) {
    selectedId = id;
    notifyListeners();
  }
}

/// Wraps a family screen: waits for the children list, then rebuilds the page for the selected child.
class ChildScope extends StatelessWidget {
  const ChildScope({super.key, required this.builder});

  final Widget Function(BuildContext context, int studentId) builder;

  @override
  Widget build(BuildContext context) {
    final family = context.watch<FamilyState>();
    if (family.loading && family.children.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (family.children.isEmpty) {
      return MessageView(
        icon: Icons.family_restroom_rounded,
        title: family.error == null ? 'No student linked yet' : 'Could not load',
        message: family.error ?? 'Ask the school office to link your child to this account.',
        actionLabel: 'Try again',
        onAction: family.load,
      );
    }
    return KeyedSubtree(key: ValueKey(family.childId), child: builder(context, family.childId));
  }
}

/// App bar title that lets a parent switch between children.
class ChildSwitcher extends StatelessWidget {
  const ChildSwitcher({super.key, required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    final family = context.watch<FamilyState>();
    final heading = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title),
        if (family.childName.isNotEmpty && family.isParent)
          Text(family.childName, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: AppColors.gray500)),
      ],
    );
    if (!family.isParent || family.children.length < 2) {
      return heading;
    }
    return InkWell(
      borderRadius: BorderRadius.circular(8),
      onTap: () => showModalBottomSheet(
        context: context,
        showDragHandle: true,
        builder: (sheetContext) => SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Padding(padding: EdgeInsets.only(bottom: 8), child: Text('Choose child', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16))),
              for (final item in family.children)
                ListTile(
                  leading: CircleAvatar(backgroundColor: AppColors.brand100, foregroundColor: AppColors.brand700, child: Text(item['name'].toString().substring(0, 1).toUpperCase())),
                  title: Text(item['name'].toString()),
                  subtitle: Text([item['class'], item['admission_no']].whereType<Object>().join(' · ')),
                  trailing: item['id'] == family.selectedId ? const Icon(Icons.check_circle_rounded, color: AppColors.brand600) : null,
                  onTap: () {
                    family.select((item['id'] as num).toInt());
                    Navigator.pop(sheetContext);
                  },
                ),
            ],
          ),
        ),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [Flexible(child: heading), const Icon(Icons.expand_more_rounded)]),
    );
  }
}

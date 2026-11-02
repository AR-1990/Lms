import 'package:flutter/material.dart';

import '../../core/api.dart';
import '../../core/theme.dart';
import '../../core/widgets.dart';

/// The teacher's sections (with their subjects) and the exams open for marks, from GET teacher/options.
/// Loaded once per app run and shared by the attendance, diary, homework and marks screens.
class TeacherOptions {
  TeacherOptions(this.sections, this.exams);

  final List<Map<String, dynamic>> sections;
  final List<Map<String, dynamic>> exams;

  static TeacherOptions? _cache;

  static Future<TeacherOptions> load(ApiClient api, {bool fresh = false}) async {
    if (_cache != null && !fresh) {
      return _cache!;
    }
    final data = asMap(await api.get('erp/teacher/options'));
    return _cache = TeacherOptions(asList(data['sections']), asList(data['exams']));
  }

  static void clear() => _cache = null;

  Map<String, dynamic>? section(int? id) {
    for (final section in sections) {
      if (section['id'] == id) {
        return section;
      }
    }
    return null;
  }

  /// Subjects the teacher teaches in a section; a class teacher may write for every subject of the class.
  List<Map<String, dynamic>> subjectsFor(int? sectionId) {
    final section = this.section(sectionId);
    if (section == null) {
      return [];
    }
    final all = asList(section['subjects']);
    final mine = all.where((subject) => subject['is_mine'] == true).toList();
    return section['is_class_teacher'] == true || mine.isEmpty ? all : mine;
  }
}

/// Dropdown of the teacher's sections, used at the top of teacher screens.
class SectionPicker extends StatelessWidget {
  const SectionPicker({super.key, required this.sections, required this.value, required this.onChanged});

  final List<Map<String, dynamic>> sections;
  final int? value;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<int>(
      value: value,
      isExpanded: true,
      decoration: const InputDecoration(labelText: 'Class', prefixIcon: Icon(Icons.groups_rounded)),
      items: [
        for (final section in sections)
          DropdownMenuItem(
            value: (section['id'] as num).toInt(),
            child: Text('${section['name']}${section['is_class_teacher'] == true ? '  (class teacher)' : ''}', overflow: TextOverflow.ellipsis),
          ),
      ],
      onChanged: onChanged,
    );
  }
}

/// Shown when a teacher has no sections this session.
class NoSections extends StatelessWidget {
  const NoSections({super.key});

  @override
  Widget build(BuildContext context) {
    return const MessageView(icon: Icons.class_outlined, title: 'No classes assigned', message: 'Ask the school office to assign your classes for this session.');
  }
}

Color statusColor(String status) => StatusChip.colorsFor(status).$2;

const attendanceStatuses = ['present', 'absent', 'late', 'leave', 'half_day'];

String shortStatus(String status) => const {'present': 'P', 'absent': 'A', 'late': 'L', 'leave': 'Lv', 'half_day': 'H'}[status] ?? status;

Color statusBackground(String status) => status == 'present'
    ? AppColors.green600
    : status == 'absent'
        ? AppColors.red600
        : status == 'leave'
            ? AppColors.sky600
            : AppColors.amber700;

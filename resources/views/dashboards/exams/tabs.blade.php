@include('partials.section-tabs', [
    'label' => 'Exams',
    'current' => $current,
    'tabs' => [
        'admin.exams.index' => ['Exams', 'sparkles', 'manage-exams'],
        'admin.exams.marks' => ['Marks Entry', 'chart', ['submit-grades', 'manage-exams']],
        'admin.exams.setup.terms' => ['Terms', 'calendar', 'manage-exams'],
        'admin.exams.setup.scales' => ['Grading Scales', 'layers', 'manage-exams'],
        'admin.exams.setup.halls' => ['Exam Halls', 'building', 'manage-exams'],
    ],
])

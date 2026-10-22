@include('partials.section-tabs', [
    'label' => 'Academic setup',
    'current' => $current,
    'tabs' => [
        'admin.academics.classes' => ['Classes', 'layers', null],
        'admin.academics.sections' => ['Sections', 'grid', null],
        'admin.academics.subjects' => ['Subjects', 'book', null],
        'admin.academics.curriculum' => ['Curriculum', 'check', null],
        'admin.academics.sessions' => ['Sessions', 'calendar', null],
        'admin.academics.periods' => ['Bell Schedule', 'clock', null],
    ],
])

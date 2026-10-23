@include('partials.section-tabs', [
    'label' => 'Performance',
    'current' => $current,
    'tabs' => [
        'admin.performance.overview' => ['Overview', 'trending-up', 'view-performance'],
        'admin.performance.skills' => ['Skill Ratings', 'sparkles', 'view-performance'],
        'admin.performance.alerts' => ['Alerts', 'alert', 'view-performance'],
        'admin.performance.counselling' => ['Counselling', 'users', 'manage-counselling'],
        'admin.exams.setup.skills' => ['Skills', 'layers', 'manage-exams'],
        'admin.exams.setup.rules' => ['Alert Rules', 'settings', 'manage-exams'],
    ],
])

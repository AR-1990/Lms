@include('partials.section-tabs', [
    'label' => 'Students & admissions',
    'current' => $current,
    'tabs' => [
        'admin.students' => ['Students', 'student', 'manage-students'],
        'admin.admissions.applications' => ['Applications', 'user-plus', 'manage-admissions'],
        'admin.admissions.inquiries' => ['Inquiries', 'mail', 'manage-admissions'],
    ],
])

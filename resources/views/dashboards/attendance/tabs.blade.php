@include('partials.section-tabs', [
    'label' => 'Attendance',
    'current' => $current,
    'tabs' => [
        'admin.attendance.register' => ['Daily Register', 'check', 'record-attendance'],
        'admin.attendance.monthly' => ['Monthly Summary', 'chart', 'record-attendance'],
        'admin.attendance.leaves' => ['Leave Requests', 'mail', 'record-attendance'],
        'admin.attendance.staff' => ['Staff Attendance', 'teacher', 'manage-staff-attendance'],
        'admin.attendance.scan' => ['QR Scan', 'eye', ['record-attendance', 'manage-staff-attendance']],
    ],
])

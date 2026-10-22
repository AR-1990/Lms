@include('partials.section-tabs', [
    'label' => 'HR',
    'current' => $current,
    'tabs' => [
        'admin.hr.employees' => ['Employees', 'users', 'manage-hr'],
        'admin.hr.assignments' => ['Teacher Assignments', 'teacher', 'manage-hr'],
        'admin.hr.candidates' => ['Candidates', 'user-plus', 'manage-recruitment'],
        'admin.hr.openings' => ['Job Openings', 'sparkles', 'manage-recruitment'],
        'admin.hr.departments' => ['Departments', 'building', 'manage-hr'],
        'admin.hr.designations' => ['Designations', 'key', 'manage-hr'],
        'admin.hr.purchases' => ['Purchase Requests', 'inbox', 'manage-purchases'],
    ],
])

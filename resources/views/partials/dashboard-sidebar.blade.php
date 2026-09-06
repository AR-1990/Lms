@php
    $roleSlug = $user->roles->first()->slug ?? 'user';
    $roleLabel = match ($roleSlug) {
        'admin' => 'Administration',
        'teacher' => 'Faculty',
        'student' => 'Student LMS',
        'parent' => 'Parent Portal',
        'accounts' => 'Accounts',
        default => 'Portal',
    };

    $navItems = match ($roleSlug) {
        'admin' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'href' => route('dashboard')],
            ['key' => 'users', 'label' => 'Users', 'href' => route('admin.users')],
            ['key' => 'roles', 'label' => 'Roles', 'href' => route('admin.roles')],
            ['key' => 'reports', 'label' => 'Reports', 'href' => route('admin.reports')],
        ],
        'teacher' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'href' => route('dashboard')],
            ['key' => 'classes', 'label' => 'My Classes', 'href' => route('teacher.classes')],
            ['key' => 'attendance', 'label' => 'Attendance', 'href' => route('teacher.attendance')],
            ['key' => 'grades', 'label' => 'Gradebook', 'href' => route('teacher.grades')],
        ],
        'student' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'href' => route('dashboard')],
            ['key' => 'courses', 'label' => 'Courses', 'href' => route('student.courses')],
            ['key' => 'attendance', 'label' => 'Attendance', 'href' => route('student.attendance')],
            ['key' => 'grades', 'label' => 'Grades', 'href' => route('student.grades')],
        ],
        'parent' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'href' => route('dashboard')],
            ['key' => 'children', 'label' => 'Children', 'href' => route('parent.children')],
            ['key' => 'fees', 'label' => 'Fees', 'href' => route('parent.fees')],
            ['key' => 'notices', 'label' => 'Notices', 'href' => route('parent.notices')],
        ],
        'accounts' => [
            ['key' => 'dashboard', 'label' => 'Overview', 'href' => route('dashboard')],
            ['key' => 'collections', 'label' => 'Collections', 'href' => route('accounts.collections')],
            ['key' => 'challans', 'label' => 'Challans', 'href' => route('accounts.challans')],
            ['key' => 'payroll', 'label' => 'Payroll', 'href' => route('accounts.payroll')],
        ],
        default => [
            ['key' => 'dashboard', 'label' => 'Overview', 'href' => route('dashboard')],
        ],
    };
@endphp

<aside class="dash-sidebar" id="dashSidebar">
    <div class="dash-sidebar-brand">
        <div class="dash-brand-mark">P</div>
        <div>
            <div class="dash-brand-name">Pinnacle</div>
            <div class="dash-brand-role">{{ $roleLabel }}</div>
        </div>
    </div>

    <nav class="dash-nav" aria-label="Dashboard navigation">
        @foreach ($navItems as $item)
            <a href="{{ $item['href'] }}" class="dash-nav-link {{ ($active ?? '') === $item['key'] ? 'is-active' : '' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="dash-sidebar-footer">
        <a href="{{ route('home') }}" class="dash-nav-link">Main Website</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dash-logout-btn">Sign Out</button>
        </form>
    </div>
</aside>

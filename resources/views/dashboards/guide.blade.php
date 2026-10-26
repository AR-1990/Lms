@extends('layouts.dashboard')

@section('title', 'User Guide')
@section('page_heading', 'User Guide')

@section('content')
@php
    // Topics this user can use: [key, title, icon, shown?]. Content lives in resources/views/guide/{key}.blade.php.
    $can = fn (string ...$permissions): bool => $user->hasPermission($permissions);
    $topics = collect([
        ['getting-started', 'Getting started', 'grid', true],
        ['notifications', 'Notifications & messages', 'bell', true],
        ['parent', 'For parents', 'users', $user->hasRole('parent')],
        ['student', 'For students', 'student', $user->hasRole('student')],
        ['my-work', 'My HR, leave & payslips', 'calendar', $data['is_staff']],
        ['teaching', 'Teaching: attendance, diary & marks', 'teacher', $can('record-attendance', 'submit-grades', 'manage-diary')],
        ['approvals', 'Approvals inbox', 'inbox', $can('approve-requests', 'manage-fees', 'manage-fee-setup', 'record-attendance', 'manage-leave')],
        ['admissions', 'Admissions', 'user-plus', $can('manage-admissions')],
        ['students', 'Students', 'student', $can('manage-students')],
        ['attendance', 'Attendance, gate kiosks & live board', 'check', $can('record-attendance', 'manage-staff-attendance')],
        ['academics', 'Classes, timetable & substitutions', 'layers', $can('manage-academics', 'manage-timetable', 'manage-calendar')],
        ['exams', 'Exams, result cards & online tests', 'sparkles', $can('manage-exams', 'submit-grades')],
        ['fees', 'Fees, challans & online payments', 'hash', $can('manage-fees', 'manage-fee-setup', 'issue-refunds')],
        ['accounts', 'Accounts & expenses', 'book', $can('view-ledger', 'manage-accounts', 'manage-vouchers', 'manage-expenses')],
        ['hr', 'HR & payroll', 'teacher', $can('manage-hr', 'manage-payroll', 'manage-leave', 'manage-recruitment', 'manage-appraisals')],
        ['library', 'Library', 'book', $can('manage-library')],
        ['inventory', 'Inventory, assets & transport', 'server', $can('manage-inventory', 'manage-assets', 'manage-transport')],
        ['communication', 'Announcements & chat', 'megaphone', $can('manage-announcements', 'use-chat')],
        ['cards', 'ID cards', 'key', $can('manage-cards')],
        ['reports', 'Reports & daily summary', 'chart', $can('view-stats', 'view-ledger', 'view-fees', 'manage-fees', 'manage-students', 'manage-hr')],
        ['administration', 'Users, settings & data', 'settings', $can('manage-users', 'manage-roles', 'manage-settings', 'manage-campuses', 'manage-lookups')],
    ])->filter(fn (array $topic): bool => $topic[3])->values();
@endphp

<section class="dash-page-intro">
    <h1>User guide</h1>
    <p>Step-by-step help for the screens you can use. Search for a task, or pick a topic. Tip: press <kbd>Ctrl</kbd> + <kbd>K</kbd> anywhere to jump straight to a page.</p>
</section>

<div class="dash-guide" data-guide>
    <aside class="dash-panel dash-guide-index">
        <label class="dash-guide-search">
            @include('partials.dashboard-icon', ['name' => 'search'])
            <span class="dash-visually-hidden">Search the guide</span>
            <input type="search" placeholder="Search the guide…" data-guide-search>
        </label>
        <nav aria-label="Guide topics">
            @foreach ($topics as [$key, $title, $icon])
                <a href="#{{ $key }}" data-guide-link="{{ $key }}">@include('partials.dashboard-icon', ['name' => $icon]) {{ $title }}</a>
            @endforeach
        </nav>
    </aside>

    <div class="dash-guide-body">
        <p class="dash-panel dash-guide-empty" data-guide-empty hidden>Nothing in the guide matches your search.</p>
        @foreach ($topics as [$key, $title, $icon])
            <section class="dash-panel dash-guide-topic" id="{{ $key }}" data-guide-topic="{{ $key }}">
                <h2>@include('partials.dashboard-icon', ['name' => $icon]) {{ $title }}</h2>
                @include('guide.'.$key)
            </section>
        @endforeach
    </div>
</div>
@endsection

@section('extra_js')
<script src="{{ asset('js/guide.js') }}?v={{ filemtime(public_path('js/guide.js')) }}" defer></script>
@endsection

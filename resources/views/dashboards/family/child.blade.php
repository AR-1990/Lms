@extends('layouts.dashboard')

@section('title', $data['student']->fullName())
@section('page_heading', $data['is_parent'] ? 'Child Overview' : 'My School Day')

@section('content')
@php
    $student = $data['student'];
@endphp
<div data-family
     data-student-id="{{ $student->id }}"
     data-overview-url="{{ route('erp.family.students.show', $student) }}"
     data-attendance-url="{{ route('erp.attendance.student', $student) }}"
     data-diary-url="{{ route('erp.diary.student', $student) }}"
     data-results-url="{{ route('erp.exams.results.student', $student) }}"
     data-history-url="{{ route('erp.students.history', $student) }}"
     data-leave-url="{{ route('erp.leave-requests.store') }}"
     data-sign-url="{{ url('/api/erp/diaries') }}"
     data-can-sign="{{ $data['is_parent'] ? 1 : 0 }}">

    <section class="dash-card dash-family-head">
        <span class="dash-profile-avatar" aria-hidden="true">{{ strtoupper(substr($student->first_name, 0, 1)) }}</span>
        <div class="dash-family-who">
            <h1>{{ $student->fullName() }}</h1>
            <p data-family-place>{{ $student->admission_no }}</p>
            <div class="dash-badges" data-family-badges></div>
        </div>
        @if ($data['children']->count() > 1)
            <label class="dash-select dash-family-switch">
                <span class="dash-visually-hidden">Child</span>
                <select data-child-switch>
                    @foreach ($data['children'] as $child)
                        <option value="{{ route('family.child', $child['id']) }}" @selected($child['id'] === $student->id)>{{ $child['name'] }}{{ $child['class'] ? ' — '.$child['class'] : '' }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </section>

    <section class="dash-stats dash-stats-compact" data-family-stats>
        <article class="dash-stat"><span class="dash-stat-label">Attendance this month</span><strong class="dash-stat-value" data-stat="attendance">…</strong></article>
        <article class="dash-stat"><span class="dash-stat-label">Fees due</span><strong class="dash-stat-value dash-stat-value-sm" data-stat="fees">…</strong></article>
        <article class="dash-stat"><span class="dash-stat-label">Next paper</span><strong class="dash-stat-value dash-stat-value-sm" data-stat="exam">…</strong></article>
        <article class="dash-stat"><span class="dash-stat-label">Latest result</span><strong class="dash-stat-value dash-stat-value-sm" data-stat="result">…</strong></article>
    </section>

    <nav class="dash-section-tabs" role="tablist" aria-label="Child sections">
        @foreach (['today' => ['Today', 'clock'], 'attendance' => ['Attendance', 'check'], 'exams' => ['Exams & Results', 'chart'], 'fees' => ['Fees', 'wallet'], 'leave' => ['Leave', 'calendar'], 'years' => ['Year by year', 'layers']] as $key => [$label, $icon])
            <button type="button" role="tab" @class(['dash-section-tab', 'is-active' => $loop->first]) aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-family-tab="{{ $key }}">
                @include('partials.dashboard-icon', ['name' => $icon])
                <span>{{ $label }}</span>
            </button>
        @endforeach
    </nav>

    {{-- Today: timetable and diary / homework --}}
    <div data-family-panel="today">
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2 data-today-title>Today's timetable</h2><p data-teacher-line></p></div></div>
            <ul class="dash-list dash-family-periods" data-today-periods><li class="dash-muted">Loading…</li></ul>
        </section>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Diary &amp; homework</h2><p>The last two weeks, newest first.@if ($data['is_parent']) Sign each day's diary to tell the teacher you have read it.@endif</p></div></div>
            <div class="dash-family-diary" data-diary><p class="dash-muted">Loading…</p></div>
        </section>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Weekly timetable</h2></div></div>
            <div class="dash-table-wrap"><table class="dash-table dash-family-week" data-week></table></div>
        </section>
    </div>

    {{-- Attendance --}}
    <div data-family-panel="attendance" hidden>
        <section class="dash-panel">
            <div class="dash-panel-head">
                <div><h2>Attendance</h2><p data-attendance-range>This month.</p></div>
                <label class="dash-select"><span class="dash-visually-hidden">Month</span><input type="month" data-attendance-month></label>
            </div>
            <ul class="dash-family-counts" data-attendance-counts></ul>
            <div class="dash-family-days" data-attendance-days></div>
        </section>
    </div>

    {{-- Exams & results --}}
    <div data-family-panel="exams" hidden>
        <div data-exams><p class="dash-muted">Loading…</p></div>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Published results</h2><p>Subject marks of every published exam.</p></div></div>
            <div data-results><p class="dash-muted">Loading…</p></div>
        </section>
    </div>

    {{-- Fees --}}
    <div data-family-panel="fees" hidden>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Unpaid challans</h2><p>Download a challan and pay it at the bank or the school office.</p></div><span class="dash-badge" data-fee-badge>…</span></div>
            <div class="dash-table-wrap"><table class="dash-table">
                <thead><tr><th>Challan</th><th>Due date</th><th class="dash-col-num">Amount due</th><th class="dash-col-actions"><span class="dash-visually-hidden">Download</span></th></tr></thead>
                <tbody data-open-challans></tbody>
            </table></div>
        </section>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Payments</h2><p>Every payment has a receipt you can download.</p></div></div>
            <div class="dash-table-wrap"><table class="dash-table">
                <thead><tr><th>Receipt</th><th>Date</th><th class="dash-col-num">Amount</th><th>Status</th><th class="dash-col-actions"><span class="dash-visually-hidden">Download</span></th></tr></thead>
                <tbody data-receipts></tbody>
            </table></div>
        </section>
    </div>

    {{-- Leave --}}
    <div data-family-panel="leave" hidden>
        @if ($data['is_parent'])
            <section class="dash-panel">
                <div class="dash-panel-head"><div><h2>Ask for leave</h2><p>The class teacher approves or rejects it; you get a notification either way.</p></div></div>
                <form class="dash-form-grid dash-family-leave" data-leave-form novalidate>
                    @include('dashboards.partials.field', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'required' => true, 'id' => 'leaveFrom'])
                    @include('dashboards.partials.field', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'required' => true, 'id' => 'leaveTo'])
                    @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'leaveReason'])
                    <p class="dash-form-error dash-field-wide" data-form-error hidden></p>
                    <div><button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'send']) Send request</button></div>
                </form>
            </section>
        @endif
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Leave requests</h2></div></div>
            <ul class="dash-list" data-leave-list><li class="dash-muted">Loading…</li></ul>
        </section>
    </div>

    {{-- Year by year --}}
    <div data-family-panel="years" hidden>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Achievements &amp; notes from school</h2><p>Achievements, and any discipline note the school has shared.</p></div></div>
            <ul class="dash-list" data-family-records><li class="dash-muted">Loading…</li></ul>
        </section>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Year by year</h2><p>Class, results, attendance and fees of each session.</p></div><div data-certificate></div></div>
            <div class="dash-table-wrap"><table class="dash-table">
                <thead><tr><th>Session</th><th>Class</th><th>Year ended</th><th>Results</th><th class="dash-col-num">Attendance</th><th class="dash-col-num">Fees billed</th><th class="dash-col-num">Due</th></tr></thead>
                <tbody data-years></tbody>
            </table></div>
        </section>
    </div>
</div>
@endsection

@section('extra_js')
<script src="{{ asset('js/data-grid.js') }}?v={{ filemtime(public_path('js/data-grid.js')) }}" defer></script>
<script src="{{ asset('js/resource-screen.js') }}?v={{ filemtime(public_path('js/resource-screen.js')) }}" defer></script>
<script src="{{ asset('js/family.js') }}?v={{ filemtime(public_path('js/family.js')) }}" defer></script>
@endsection

@extends('layouts.dashboard')

@section('title', 'Live Attendance')
@section('page_heading', 'Attendance')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Live Today</h1>
        <p>Who has arrived, updated every 15 seconds. Gate kiosk and QR scans appear as they happen; teachers still mark the register by hand — open a class to mark or correct it.</p>
    </div>
    <span class="dash-badge dash-badge-ok dash-live-dot" data-live-clock>Live</span>
</section>

@include('dashboards.attendance.tabs', ['current' => 'admin.attendance.live'])

<div data-live-board data-url="{{ route('erp.attendance.live') }}" data-register-url="{{ route('admin.attendance.register') }}" data-staff-url="{{ route('admin.attendance.staff') }}">
    <section class="dash-panel dash-live-holiday" data-live-holiday hidden></section>

    <section class="dash-stats" data-live-students hidden>
        <article class="dash-stat"><span class="dash-stat-label">Students present</span><strong class="dash-stat-value" data-count="present">0</strong><span class="dash-muted" data-count-of="total"></span></article>
        <article class="dash-stat"><span class="dash-stat-label">Late</span><strong class="dash-stat-value" data-count="late">0</strong></article>
        <article class="dash-stat"><span class="dash-stat-label">Absent</span><strong class="dash-stat-value" data-count="absent">0</strong></article>
        <article class="dash-stat"><span class="dash-stat-label">Not marked yet</span><strong class="dash-stat-value" data-count="unmarked">0</strong></article>
    </section>

    <section class="dash-stats" data-live-staff hidden>
        <article class="dash-stat"><span class="dash-stat-label">Staff in</span><strong class="dash-stat-value" data-staff="in">0</strong><span class="dash-muted" data-staff-of="total"></span></article>
        <article class="dash-stat"><span class="dash-stat-label">Checked out</span><strong class="dash-stat-value" data-staff="out">0</strong></article>
        <article class="dash-stat"><span class="dash-stat-label">Late</span><strong class="dash-stat-value" data-staff="late">0</strong></article>
        <article class="dash-stat"><span class="dash-stat-label">Not in yet</span><strong class="dash-stat-value" data-staff="not_in">0</strong></article>
    </section>

    <div class="dash-live-grid">
        <section class="dash-panel" data-live-sections-panel hidden>
            <div class="dash-panel-head"><div><h2>Classes</h2><p>Open a class to mark the register by hand.</p></div></div>
            <div class="dash-table-wrap"><table class="dash-table">
                <thead><tr><th>Class</th><th class="dash-col-num">Present</th><th class="dash-col-num">Absent</th><th class="dash-col-num">Not marked</th><th class="dash-col-actions"><span class="dash-visually-hidden">Mark</span></th></tr></thead>
                <tbody data-live-sections></tbody>
            </table></div>
        </section>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Latest arrivals</h2><p>Gate, scanner, biometric and register entries.</p></div><a class="dash-link-btn" data-staff-link hidden>Staff register</a></div>
            <ul class="dash-list dash-live-feed" data-live-feed><li class="dash-muted">Loading…</li></ul>
        </section>
    </div>
</div>
@endsection

@section('extra_js')
<script src="{{ asset('js/attendance-live.js') }}?v={{ filemtime(public_path('js/attendance-live.js')) }}" defer></script>
@endsection

@extends('layouts.dashboard')

@section('title', 'Staff Attendance')
@section('page_heading', 'Attendance')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Staff Attendance</h1>
        <p>Mark every current employee for the day; check-in and check-out times are optional.</p>
    </div>
</section>

@include('dashboards.attendance.tabs', ['current' => 'admin.attendance.staff'])

<section class="dash-panel" data-attendance="staff" data-sheet-url="{{ route('erp.attendance.staff') }}" data-save-url="{{ route('erp.attendance.staff.store') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Date</span>
                <input type="date" data-date max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" aria-label="Date">
            </label>
        </div>
        <div class="dash-head-actions">
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-all-present>All present</button>
        </div>
    </div>
    <p class="dash-register-note" data-register-note hidden></p>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-register">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Mark</th>
                    <th class="dash-hide-md">In / out</th>
                </tr>
            </thead>
            <tbody data-register-rows>
                <tr><td colspan="3" class="dash-muted">Loading…</td></tr>
            </tbody>
        </table>
    </div>
    <div class="dash-register-foot">
        <p class="dash-muted" data-register-counts></p>
        <button type="button" class="dash-btn dash-btn-primary" data-save-register>@include('partials.dashboard-icon', ['name' => 'check']) Save Register</button>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-attendance.js') }}?v={{ filemtime(public_path('js/admin-attendance.js')) }}" defer></script>
@endsection

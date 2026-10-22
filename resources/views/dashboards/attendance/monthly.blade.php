@extends('layouts.dashboard')

@section('title', 'Monthly Attendance')
@section('page_heading', 'Attendance')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Monthly Summary</h1>
        <p>Days present, absent, late, on leave and half day for each student, with the attendance percentage.</p>
    </div>
</section>

@include('dashboards.attendance.tabs', ['current' => 'admin.attendance.monthly'])

<section class="dash-panel" data-attendance="monthly" data-monthly-url="{{ route('erp.attendance.monthly') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Section</span>
                <select data-section>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Month</span>
                <input type="month" data-month value="{{ now()->format('Y-m') }}" aria-label="Month">
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th class="dash-col-num">Present</th>
                    <th class="dash-col-num">Absent</th>
                    <th class="dash-col-num dash-hide-md">Late</th>
                    <th class="dash-col-num dash-hide-md">Leave</th>
                    <th class="dash-col-num dash-hide-md">Half day</th>
                    <th class="dash-col-num">%</th>
                </tr>
            </thead>
            <tbody data-monthly-rows>
                <tr><td colspan="7" class="dash-muted">Loading…</td></tr>
            </tbody>
        </table>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-attendance.js') }}?v={{ filemtime(public_path('js/admin-attendance.js')) }}" defer></script>
@endsection

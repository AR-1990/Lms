@extends('layouts.dashboard')

@section('title', 'Teacher Assignments')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Teacher Assignments</h1>
        <p>Who teaches which subject in which section. One subject has one teacher per section; one class teacher per section.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Assign Teacher
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.assignments'])

<section class="dash-panel" data-hr="assignments" data-session-id="{{ $data['current_session_id'] }}" data-datatable-url="{{ route('erp.teacher-assignments.datatable') }}" data-resource-url="{{ route('erp.teacher-assignments.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Current session</h2>
            <p>The subject must already be in the class curriculum.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Section</span>
                <select data-filter="section_id">
                    <option value="">All sections</option>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Teacher</span>
                <select data-filter="employee_id">
                    <option value="">All teachers</option>
                    @foreach ($data['teachers'] as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->fullName() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th class="dash-hide-md">Periods</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="hrModal" aria-labelledby="hrModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'teacher'])</span>
            <div>
                <h3 id="hrModalTitle" data-modal-title>Assign teacher</h3>
                <p>Ticking class teacher replaces the section's current class teacher.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="assignTeacher">Teacher</label>
                <select id="assignTeacher" name="employee_id" required>
                    @foreach ($data['teachers'] as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->fullName() }} ({{ $teacher->employee_code }})</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="employee_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="assignSection">Section</label>
                <select id="assignSection" name="section_id" required>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="section_id" hidden></p>
            </div>
            @include('dashboards.partials.select', ['name' => 'subject_id', 'label' => 'Subject', 'options' => $data['subjects'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'periods_per_week', 'label' => 'Periods per week', 'type' => 'number', 'min' => 0, 'max' => 60])
            <label class="dash-switch">
                <input type="checkbox" name="is_class_teacher">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Class teacher</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-hr.js') }}?v={{ filemtime(public_path('js/admin-hr.js')) }}" defer></script>
@endsection

@extends('layouts.dashboard')

@section('title', 'Students')
@section('page_heading', 'Students')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Students</h1>
        <p>New children come through Admissions. Use Add Student to bring in children who are already at school.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Student
    </button>
</section>

@include('dashboards.students.tabs', ['current' => 'admin.students'])

<section class="dash-panel" data-students="students" data-session-id="{{ $data['current_session_id'] }}" data-datatable-url="{{ route('erp.students.datatable') }}" data-resource-url="{{ route('erp.students.index') }}" data-profile-url="{{ url('/admin/students') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Current session</h2>
            <p>Search by name, admission number or B-Form.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Class</span>
                <select data-filter="class_id">
                    <option value="">All classes</option>
                    @foreach ($data['classes'] as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Student::STATUSES as $status)
                        <option value="{{ $status }}" @selected($status === 'active')>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                    <th class="dash-hide-md">Date of birth</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="studentsModal" aria-labelledby="studentsModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'student'])</span>
            <div>
                <h3 id="studentsModalTitle" data-modal-title>Add student</h3>
                <p>Leave the admission number empty to get the next one automatically.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.field', ['name' => 'first_name', 'label' => 'First name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'middle_name', 'label' => 'Middle name'])
            @include('dashboards.partials.field', ['name' => 'last_name', 'label' => 'Last name'])
            @include('dashboards.partials.select', ['name' => 'gender_id', 'label' => 'Gender', 'options' => $data['lookups']['gender'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'b_form_no', 'label' => 'B-Form number', 'placeholder' => '35202-1234567-1'])
            @include('dashboards.partials.select', ['name' => 'religion_id', 'label' => 'Religion', 'options' => $data['lookups']['religion']])
            @include('dashboards.partials.select', ['name' => 'blood_group_id', 'label' => 'Blood group', 'options' => $data['lookups']['blood_group']])
            @include('dashboards.partials.field', ['name' => 'city', 'label' => 'City'])
            @include('dashboards.partials.field', ['name' => 'address_line', 'label' => 'Address', 'wide' => true])
        </div>

        <div class="dash-form-grid dash-form-grid-3" data-create-only>
            <h4 class="dash-form-section">Admission &amp; class</h4>
            @include('dashboards.partials.field', ['name' => 'admission_no', 'label' => 'Admission no', 'hint' => 'automatic if empty'])
            @include('dashboards.partials.field', ['name' => 'admission_date', 'label' => 'Admission date', 'type' => 'date', 'required' => true])
            <div class="dash-field">
                <label for="studentSection">Class &amp; section</label>
                <select id="studentSection" name="section_id" required>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="enrollment" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'roll_no', 'label' => 'Roll no'])

            <h4 class="dash-form-section">Guardian</h4>
            @include('dashboards.partials.select', ['name' => 'guardian_relation_id', 'label' => 'Relation', 'options' => $data['lookups']['guardian_relation'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'guardian_name', 'label' => 'Guardian name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'guardian_phone', 'label' => 'Guardian phone', 'type' => 'tel', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'guardian_cnic', 'label' => 'Guardian CNIC', 'hint' => 'links an existing parent'])
            <p class="dash-field-error dash-field-wide" data-error-for="guardians" hidden></p>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Student</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-students.js') }}?v={{ filemtime(public_path('js/admin-students.js')) }}" defer></script>
@endsection

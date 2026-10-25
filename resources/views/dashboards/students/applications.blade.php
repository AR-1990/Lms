@extends('layouts.dashboard')

@section('title', 'Applications')
@section('page_heading', 'Admissions')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Applications</h1>
        <p>Submitted → documents → entry test → interview → offer → accepted (admission challan) → enrolled.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Application
    </button>
</section>

@include('dashboards.students.tabs', ['current' => 'admin.admissions.applications'])

<section class="dash-panel" data-students="applications" data-session-id="{{ $data['current_session_id'] }}" data-datatable-url="{{ route('erp.admission-applications.datatable') }}" data-resource-url="{{ route('erp.admission-applications.index') }}" data-tests-url="{{ url('/api/erp/admission-tests') }}" data-interviews-url="{{ url('/api/erp/admission-interviews') }}" data-receipts-url="{{ route('accounts.fees.receipts') }}" data-profile-url="{{ url('/admin/students') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Applications</h2>
            <p>Open an application to move it along, record tests and interviews, or enrol.</p>
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
                    @foreach (array_keys(\App\Models\AdmissionApplication::TRANSITIONS) as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Child</th>
                    <th>Class</th>
                    <th class="dash-hide-md">Applied</th>
                    <th>Fees due</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

@include('dashboards.students.application-form')

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="detailModal" aria-labelledby="detailTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'book'])</span>
        <div>
            <h3 id="detailTitle" data-detail-title>Application</h3>
            <p data-detail-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body" data-detail-body></div>

    <form class="dash-inline-form" data-step-form novalidate>
        <h4>Next step</h4>
        <div class="dash-step-buttons" data-step-buttons></div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'id' => 'stepRemarks'])
            @include('dashboards.partials.select', ['name' => 'rejection_reason_id', 'label' => 'Rejection reason', 'options' => $data['lookups']['admission_rejection_reason'], 'id' => 'stepReason'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
    </form>

    <form class="dash-inline-form" data-test-form novalidate>
        <h4>Schedule an entry test</h4>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.field', ['name' => 'scheduled_at', 'label' => 'Date & time', 'type' => 'datetime-local', 'required' => true, 'id' => 'testAt'])
            @include('dashboards.partials.select', ['name' => 'subject_id', 'label' => 'Subject', 'options' => $data['subjects'], 'id' => 'testSubject'])
            @include('dashboards.partials.field', ['name' => 'max_marks', 'label' => 'Out of', 'type' => 'number', 'min' => 1, 'id' => 'testMax'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Schedule test</button></div>
    </form>

    <form class="dash-inline-form" data-interview-form novalidate>
        <h4>Schedule an interview</h4>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'scheduled_at', 'label' => 'Date & time', 'type' => 'datetime-local', 'required' => true, 'id' => 'interviewAt'])
            @include('dashboards.partials.field', ['name' => 'venue_or_link', 'label' => 'Venue / link', 'id' => 'interviewVenue'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Schedule interview</button></div>
    </form>

    <form class="dash-inline-form" data-enrol-form novalidate hidden>
        <h4>Enrol</h4>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.field', ['name' => 'admission_date', 'label' => 'Admission date', 'type' => 'date', 'required' => true, 'id' => 'enrolDate'])
            <div class="dash-field">
                <label for="enrolSectionPick">Section <span class="dash-optional">optional</span></label>
                <select id="enrolSectionPick" name="section_id">
                    <option value="">Assign later</option>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="section_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'roll_no', 'label' => 'Roll no', 'id' => 'enrolRollNo'])
            @include('dashboards.partials.field', ['name' => 'parent_password', 'label' => 'Parent login password', 'type' => 'password', 'hint' => 'optional — login by phone', 'id' => 'enrolParentPassword', 'wide' => true])
            @include('dashboards.partials.field', ['name' => 'student_password', 'label' => 'Student login password', 'type' => 'password', 'hint' => 'optional — login by admission no', 'id' => 'enrolStudentPassword', 'wide' => true])
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="issue_first_challan" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Make this month's fee challan now</span>
            </label>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="issue_id_card" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Issue the student ID card (when a student card design exists)</span>
            </label>
        </div>
        <p class="dash-field-error" data-error-for="fees" hidden></p>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-primary">Enrol student</button></div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-students.js') }}?v={{ filemtime(public_path('js/admin-students.js')) }}" defer></script>
@endsection

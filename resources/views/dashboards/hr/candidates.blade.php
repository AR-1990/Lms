@extends('layouts.dashboard')

@section('title', 'Candidates')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Candidates</h1>
        <p>Received → shortlisted → interview / demo → offered → hired. Every step is kept in the candidate's history.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Candidate
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.candidates'])

<section class="dash-panel" data-hr="candidates" data-datatable-url="{{ route('erp.job-applications.datatable') }}" data-resource-url="{{ route('erp.job-applications.index') }}" data-interviews-url="{{ url('/api/erp/job-interviews') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Applications</h2>
            <p>Open a candidate to move them along, schedule interviews or hire.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Opening</span>
                <select data-filter="job_opening_id">
                    <option value="">All openings</option>
                    @foreach ($data['all_openings'] as $opening)
                        <option value="{{ $opening->id }}">{{ $opening->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    @foreach (array_keys(\App\Models\JobApplication::TRANSITIONS) as $status)
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
                    <th>Candidate</th>
                    <th class="dash-hide-md">Opening</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="hrModal" aria-labelledby="hrModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'user-plus'])</span>
            <div>
                <h3 id="hrModalTitle" data-modal-title>Add candidate</h3>
                <p>Attach the CV from the candidate's documents later. Salaries are in rupees.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-grid-3">
            <div class="dash-field dash-field-wide" data-create-only>
                <label for="candidateOpening">Job opening</label>
                <select id="candidateOpening" name="job_opening_id">
                    @foreach ($data['openings'] as $opening)
                        <option value="{{ $opening->id }}">{{ $opening->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="job_opening_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'first_name', 'label' => 'First name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'last_name', 'label' => 'Last name'])
            @include('dashboards.partials.select', ['name' => 'gender_id', 'label' => 'Gender', 'options' => $data['lookups']['gender']])
            @include('dashboards.partials.field', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email'])
            @include('dashboards.partials.field', ['name' => 'cnic', 'label' => 'CNIC', 'placeholder' => '35202-1234567-1'])
            @include('dashboards.partials.select', ['name' => 'highest_qualification_id', 'label' => 'Highest qualification', 'options' => $data['lookups']['qualification_level']])
            @include('dashboards.partials.field', ['name' => 'experience_years', 'label' => 'Experience (years)', 'type' => 'number', 'step' => '0.5', 'min' => 0])
            @include('dashboards.partials.select', ['name' => 'source_id', 'label' => 'Source', 'options' => $data['lookups']['job_source']])
            @include('dashboards.partials.field', ['name' => 'current_employer', 'label' => 'Current employer'])
            @include('dashboards.partials.field', ['name' => 'expected_salary', 'label' => 'Expected salary (Rs)', 'type' => 'number', 'min' => 0])
            @include('dashboards.partials.field', ['name' => 'rating', 'label' => 'Rating (1–5)', 'type' => 'number', 'min' => 1, 'max' => 5])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Candidate</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="detailModal" aria-labelledby="detailTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'book'])</span>
        <div>
            <h3 id="detailTitle" data-detail-title>Candidate</h3>
            <p data-detail-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body" data-detail-body></div>

    <form class="dash-inline-form" data-step-form novalidate>
        <h4>Next step</h4>
        <div class="dash-step-buttons" data-step-buttons></div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'id' => 'stepRemarks', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
    </form>

    <form class="dash-inline-form" data-interview-form novalidate>
        <h4>Schedule an interview / demo</h4>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.select', ['name' => 'round_type_id', 'label' => 'Round', 'options' => $data['lookups']['interview_round_type'], 'id' => 'roundType'])
            @include('dashboards.partials.field', ['name' => 'scheduled_at', 'label' => 'Date & time', 'type' => 'datetime-local', 'required' => true, 'id' => 'roundAt'])
            @include('dashboards.partials.field', ['name' => 'venue_or_link', 'label' => 'Venue / link', 'id' => 'roundVenue'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Schedule</button></div>
    </form>

    <form class="dash-inline-form" data-hire-form novalidate hidden>
        <h4>Hire</h4>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.field', ['name' => 'joining_date', 'label' => 'Joining date', 'type' => 'date', 'required' => true, 'id' => 'hireDate'])
            @include('dashboards.partials.select', ['name' => 'designation_id', 'label' => 'Designation', 'options' => $data['designations'], 'id' => 'hireDesignation', 'empty' => 'As in the opening'])
            @include('dashboards.partials.select', ['name' => 'employment_type_id', 'label' => 'Employment type', 'options' => $data['lookups']['employment_type'], 'id' => 'hireType', 'empty' => 'As in the opening'])
            @include('dashboards.partials.select', ['name' => 'gender_id', 'label' => 'Gender', 'options' => $data['lookups']['gender'], 'id' => 'hireGender', 'empty' => 'As on the application'])
            <div class="dash-field">
                <label for="hireRole">Portal login role <span class="dash-optional">optional</span></label>
                <select id="hireRole" name="portal_role">
                    <option value="">No login now</option>
                    @foreach ($data['staff_roles'] as $role)
                        <option value="{{ $role->slug }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="portal_role" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'portal_password', 'label' => 'Login password', 'type' => 'password', 'id' => 'hirePassword'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-primary">Hire &amp; create employee</button></div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-hr.js') }}?v={{ filemtime(public_path('js/admin-hr.js')) }}" defer></script>
@endsection

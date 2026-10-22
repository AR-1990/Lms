@extends('layouts.dashboard')

@section('title', 'Job Openings')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Job Openings</h1>
        <p>Vacancies. An opening is marked filled automatically when all positions are hired.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Opening
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.openings'])

<section class="dash-panel" data-hr="openings" data-datatable-url="{{ route('erp.job-openings.datatable') }}" data-resource-url="{{ route('erp.job-openings.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Openings</h2>
            <p>Only open or on-hold openings take new candidates.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\JobOpening::STATUSES as $status)
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
                    <th>Opening</th>
                    <th class="dash-hide-md">Closes</th>
                    <th>Positions</th>
                    <th>Candidates</th>
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
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'sparkles'])</span>
            <div>
                <h3 id="hrModalTitle" data-modal-title>Add opening</h3>
                <p>Salaries are in rupees per month.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'wide' => true])
            @include('dashboards.partials.select', ['name' => 'designation_id', 'label' => 'Designation', 'options' => $data['designations'], 'required' => true])
            @include('dashboards.partials.select', ['name' => 'department_id', 'label' => 'Department', 'options' => $data['departments']])
            @include('dashboards.partials.select', ['name' => 'employment_type_id', 'label' => 'Employment type', 'options' => $data['lookups']['employment_type']])
            @include('dashboards.partials.field', ['name' => 'positions', 'label' => 'Positions', 'type' => 'number', 'min' => 1, 'required' => true])
            @include('dashboards.partials.field', ['name' => 'salary_min', 'label' => 'Salary from (Rs)', 'type' => 'number', 'min' => 0])
            @include('dashboards.partials.field', ['name' => 'salary_max', 'label' => 'Salary to (Rs)', 'type' => 'number', 'min' => 0])
            @include('dashboards.partials.field', ['name' => 'opens_on', 'label' => 'Opens on', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'closes_on', 'label' => 'Closes on', 'type' => 'date'])
            <div class="dash-field">
                <label for="openingStatus">Status</label>
                <select id="openingStatus" name="status">
                    @foreach (\App\Models\JobOpening::STATUSES as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="status" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'subjects_note', 'label' => 'Subjects (teaching posts)', 'wide' => true])
            @include('dashboards.partials.field', ['name' => 'requirements', 'label' => 'Requirements', 'type' => 'textarea', 'wide' => true])
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_published">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Show on the website careers page</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Opening</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-hr.js') }}?v={{ filemtime(public_path('js/admin-hr.js')) }}" defer></script>
@endsection

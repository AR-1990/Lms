@extends('layouts.dashboard')

@section('title', 'Appraisal Cycles')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Appraisal Cycles</h1>
        <p>Create a review period, then press Launch: every current employee gets an appraisal with their manager (or department head) as reviewer.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) New Cycle</button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.appraisal-cycles'])

<section class="dash-panel" data-appraisals="cycles" data-datatable-url="{{ route('erp.appraisal-cycles.datatable') }}" data-resource-url="{{ url('/api/erp/appraisal-cycles') }}" data-appraisals-page="{{ route('admin.hr.appraisals') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Cycle</th>
                    <th>Period</th>
                    <th>Staff</th>
                    <th>Progress</th>
                    <th>Average</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="cycleModal" aria-labelledby="cycleModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'calendar'])</span>
            <div>
                <h3 id="cycleModalTitle" data-modal-title>New cycle</h3>
                <p>After launch only the name and end date can change.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'wide' => true, 'placeholder' => 'Annual Review '.now()->year])
            @include('dashboards.partials.field', ['name' => 'starts_on', 'label' => 'Period from', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'ends_on', 'label' => 'Period to', 'type' => 'date'])
            <div class="dash-field dash-field-wide">
                <label for="cycleDepartment">Staff</label>
                <select id="cycleDepartment" name="department_id">
                    <option value="">All staff</option>
                    @foreach ($data['departments'] as $department)
                        <option value="{{ $department->id }}">Only {{ $department->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="department_id" hidden></p>
            </div>
            <label class="dash-switch">
                <input type="checkbox" name="self_review" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Staff rate themselves first</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="cycleView" aria-labelledby="cycleViewTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'trending-up'])</span>
        <div><h3 id="cycleViewTitle" data-view-title>Cycle</h3><p data-view-sub></p></div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll" data-view-body></div>
    <div class="dash-modal-actions" data-view-actions></div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-appraisals.js') }}?v={{ filemtime(public_path('js/admin-appraisals.js')) }}" defer></script>
@endsection

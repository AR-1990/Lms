@extends('layouts.dashboard')

@section('title', 'Departments')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Departments</h1>
        <p>Administration, Academics, Accounts… A department with staff can be made inactive but not deleted.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Department
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.departments'])

<section class="dash-panel" data-hr="departments" data-datatable-url="{{ route('erp.departments.datatable') }}" data-resource-url="{{ route('erp.departments.index') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Department</th>
                    <th class="dash-hide-md">Head</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="hrModal" aria-labelledby="hrModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'building'])</span>
            <div>
                <h3 id="hrModalTitle" data-modal-title>Add department</h3>
                <p>The head is chosen from current employees.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'code', 'label' => 'Code'])
            <div class="dash-field dash-field-wide">
                <label for="deptHead">Head of department <span class="dash-optional">optional</span></label>
                <select id="deptHead" name="head_employee_id" data-employee-select>
                    <option value="">No head</option>
                </select>
                <p class="dash-field-error" data-error-for="head_employee_id" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
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

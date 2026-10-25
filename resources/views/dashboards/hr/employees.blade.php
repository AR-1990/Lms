@extends('layouts.dashboard')

@section('title', 'Employees')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Employees</h1>
        <p>Every staff member gets an employee code and a service record of joining, promotions and exits.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Employee
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.employees'])

<section class="dash-panel" data-hr="employees" data-datatable-url="{{ route('erp.employees.datatable') }}" data-resource-url="{{ route('erp.employees.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Staff</h2>
            <p>Open an employee to change status or create their portal login.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Department</span>
                <select data-filter="department_id">
                    <option value="">All departments</option>
                    @foreach ($data['departments'] as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Employee::STATUSES as $status)
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
                    <th>Employee</th>
                    <th class="dash-hide-md">Department</th>
                    <th>Phone</th>
                    <th class="dash-hide-md">Joined</th>
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
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
            <div>
                <h3 id="hrModalTitle" data-modal-title>Add employee</h3>
                <p>Name, phone, designation and joining date are required; the rest can be filled later.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.field', ['name' => 'first_name', 'label' => 'First name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'middle_name', 'label' => 'Middle name'])
            @include('dashboards.partials.field', ['name' => 'last_name', 'label' => 'Last name'])
            @include('dashboards.partials.field', ['name' => 'father_or_spouse_name', 'label' => 'Father / spouse name'])
            @include('dashboards.partials.select', ['name' => 'gender_id', 'label' => 'Gender', 'options' => $data['lookups']['gender'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'cnic', 'label' => 'CNIC', 'placeholder' => '35202-1234567-1'])
            @include('dashboards.partials.field', ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email'])
            @include('dashboards.partials.select', ['name' => 'department_id', 'label' => 'Department', 'options' => $data['departments']])
            @include('dashboards.partials.select', ['name' => 'designation_id', 'label' => 'Designation', 'options' => $data['designations'], 'required' => true])
            @include('dashboards.partials.select', ['name' => 'work_shift_id', 'label' => 'Shift', 'options' => $data['shifts'], 'empty' => 'Default shift'])
            @include('dashboards.partials.select', ['name' => 'employment_type_id', 'label' => 'Employment type', 'options' => $data['lookups']['employment_type'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'joining_date', 'label' => 'Joining date', 'type' => 'date', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'probation_end_date', 'label' => 'Probation ends', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'city', 'label' => 'City'])
            @include('dashboards.partials.select', ['name' => 'bank_id', 'label' => 'Salary bank', 'options' => $data['lookups']['bank']])
            @include('dashboards.partials.field', ['name' => 'bank_account_no', 'label' => 'Bank account no'])
            @include('dashboards.partials.field', ['name' => 'ntn', 'label' => 'NTN'])
            @include('dashboards.partials.field', ['name' => 'current_address', 'label' => 'Address', 'wide' => true])
            <label class="dash-switch">
                <input type="checkbox" name="is_teaching_staff">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Teaching staff</span>
            </label>
        </div>

        <div class="dash-form-grid" data-create-only>
            <div class="dash-field">
                <label for="portalRole">Portal login role <span class="dash-optional">optional — username is the employee code</span></label>
                <select id="portalRole" name="portal_role">
                    <option value="">No login now</option>
                    @foreach ($data['staff_roles'] as $role)
                        <option value="{{ $role->slug }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="portal_role" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'portal_password', 'label' => 'Login password', 'type' => 'password'])
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Employee</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="detailModal" aria-labelledby="detailTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'book'])</span>
        <div>
            <h3 id="detailTitle" data-detail-title>Employee</h3>
            <p data-detail-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body" data-detail-body></div>
    <form class="dash-inline-form" data-status-form novalidate>
        <h4>Change status</h4>
        <div class="dash-form-grid dash-form-grid-3">
            <div class="dash-field">
                <label for="statusValue">New status</label>
                <select id="statusValue" name="status">
                    @foreach (\App\Models\Employee::STATUSES as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="status" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'effective_on', 'label' => 'Effective on', 'type' => 'date', 'required' => true, 'id' => 'statusDate'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'id' => 'statusRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Save status</button></div>
    </form>
    <form class="dash-inline-form" data-portal-form novalidate>
        <h4>Portal login</h4>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="portalRoleLater">Role</label>
                <select id="portalRoleLater" name="portal_role">
                    @foreach ($data['staff_roles'] as $role)
                        <option value="{{ $role->slug }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="portal_role" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'portal_password', 'label' => 'Password', 'type' => 'password', 'id' => 'portalPasswordLater'])
        </div>
        <p class="dash-field-error" data-error-for="email" hidden></p>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Create login</button></div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-hr.js') }}?v={{ filemtime(public_path('js/admin-hr.js')) }}" defer></script>
@endsection

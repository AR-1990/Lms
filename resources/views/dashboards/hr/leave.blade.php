@extends('layouts.dashboard')

@section('title', 'Staff Leave')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Staff Leave</h1>
        <p>Approve or reject leave. Approved days are marked as leave in staff attendance; paid leave comes off the balance.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Apply for Staff
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.leave'])

<section class="dash-panel" data-payroll="leave" data-datatable-url="{{ route('erp.leave-requests.datatable') }}" data-resource-url="{{ url('/api/erp/leave-requests') }}">
    <div class="dash-panel-head">
        <h2>Requests</h2>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="">All</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave</th>
                    <th class="dash-hide-md">Reason</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="leaveModal" aria-labelledby="leaveModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'calendar'])</span>
            <div>
                <h3 id="leaveModalTitle" data-modal-title>Apply for staff</h3>
                <p>For a phoned-in sick day or leave entered by HR.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            <div class="dash-field">
                <label for="leaveEmployee">Employee</label>
                <select id="leaveEmployee" name="employee_id" >
                    <option value="">—</option>
                    @foreach ($data['staff'] as $option)
                        <option value="{{ $option->id }}">{{ $option->first_name }} {{ $option->last_name }} ({{ $option->employee_code }})</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="employee_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="leaveType">Leave type</label>
                <select id="leaveType" name="leave_type_id" >
                    <option value="">—</option>
                    @foreach ($data['leave_types'] as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="leave_type_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'required' => true])
            <label class="dash-switch">
                <input type="checkbox" name="is_half_day">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Half day (one date only)</span>
            </label>
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
<dialog class="dash-modal dash-form-modal" id="decideModal" aria-labelledby="decideModalTitle">
    <form data-decide-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'alert'])</span>
            <div>
                <h3 id="decideModalTitle" data-modal-title>Reject leave</h3>
                <p>Tell the employee why.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'decideRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Reject</button>
        </div>
    </form>
</dialog>

@endsection

@section('extra_js')
<script src="{{ asset('js/admin-payroll.js') }}?v={{ filemtime(public_path('js/admin-payroll.js')) }}" defer></script>
@endsection

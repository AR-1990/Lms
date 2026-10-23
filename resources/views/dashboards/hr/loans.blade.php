@extends('layouts.dashboard')

@section('title', 'Loans & Advances')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Loans & Advances</h1>
        <p>An administrator approves every loan; paying it out posts a payment voucher and the instalments are deducted in payroll.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Loan
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.loans'])

<section class="dash-panel" data-payroll="loans" data-datatable-url="{{ route('erp.employee-loans.datatable') }}" data-resource-url="{{ url('/api/erp/employee-loans') }}" data-can-approve="{{ auth()->user()->hasPermission('approve-requests') ? 1 : 0 }}">
    <div class="dash-panel-head">
        <h2>Requests</h2>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All</option>
                    <option value="pending">Waiting for approval</option>
                    <option value="approved">Approved, not paid</option>
                    <option value="active">Being repaid</option>
                    <option value="closed">Closed</option>
                    <option value="rejected">Rejected</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Amount</th>
                    <th class="dash-hide-md">Instalments</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="loanModal" aria-labelledby="loanModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'key'])</span>
            <div>
                <h3 id="loanModalTitle" data-modal-title>New loan</h3>
                <p>Entered for an employee; an administrator approves it.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            <div class="dash-field">
                <label for="loanEmployee">Employee</label>
                <select id="loanEmployee" name="employee_id" >
                    <option value="">—</option>
                    @foreach ($data['staff'] as $option)
                        <option value="{{ $option->id }}">{{ $option->first_name }} {{ $option->last_name }} ({{ $option->employee_code }})</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="employee_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="loanType">Type</label>
                <select id="loanType" name="loan_type_id" >
                    <option value="">—</option>
                    @foreach ($data['lookups']['loan_type'] as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="loan_type_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'amount', 'label' => 'Amount (Rs)', 'type' => 'number', 'required' => true, 'min' => 1, 'step' => '0.01'])
            @include('dashboards.partials.field', ['name' => 'instalments', 'label' => 'Instalments (months)', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 60])
            @include('dashboards.partials.field', ['name' => 'start_month', 'label' => 'First deduction month', 'type' => 'month', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="disburseModal" aria-labelledby="disburseModalTitle">
    <form data-disburse-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="disburseModalTitle">Pay out loan</h3>
                <p>Dr Employee Loans & Advances, Cr cash / bank.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="disburseMethod">Paid by</label>
                <select id="disburseMethod" name="payment_method_id">
                    @foreach ($data['payment_methods'] as $method)
                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                    @endforeach
                </select>
            </div>
            @include('dashboards.partials.field', ['name' => 'issued_on', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'disburseDate'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>Pay out</button>
        </div>
    </form>
</dialog>
<dialog class="dash-modal dash-form-modal" id="decideModal" aria-labelledby="decideModalTitle">
    <form data-decide-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'alert'])</span>
            <div>
                <h3 id="decideModalTitle" data-modal-title>Reject loan</h3>
                <p>Tell the employee why.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'loanRemarks'])
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

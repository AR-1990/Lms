@extends('layouts.dashboard')

@section('title', 'Pay Adjustments')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Pay Adjustments</h1>
        <p>One-off amounts for a single month: bonus, incentive, overtime, arrears or a fine. They land on that month's payslip; a draft payroll updates straight away.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Add Adjustment</button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.adjustments'])

<section class="dash-panel" data-payroll="adjustments" data-datatable-url="{{ route('erp.payroll-adjustments.datatable') }}" data-resource-url="{{ url('/api/erp/payroll-adjustments') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Adjustments</h2>
            <p>Need another kind? Add it under Salary Setup and it appears here.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Month</span>
                <input type="month" data-filter="month" value="{{ now()->format('Y-m') }}" aria-label="Month">
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Month</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Note</th>
                    <th>Payroll</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="adjustmentModal" aria-labelledby="adjustmentModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'hash'])</span>
            <div>
                <h3 id="adjustmentModalTitle" data-modal-title>Add pay adjustment</h3>
                <p>Earnings are added to gross pay; a fine is deducted.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            <div class="dash-field dash-field-wide">
                <label for="adjustmentEmployee">Employee</label>
                <select id="adjustmentEmployee" name="employee_id">
                    <option value="">Choose…</option>
                    @foreach ($data['staff'] as $employee)<option value="{{ $employee->id }}">{{ $employee->fullName() }} · {{ $employee->employee_code }}</option>@endforeach
                </select>
                <p class="dash-field-error" data-error-for="employee_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'month', 'label' => 'Payroll month', 'type' => 'month', 'required' => true, 'id' => 'adjustmentMonth'])
            <div class="dash-field">
                <label for="adjustmentType">Type</label>
                <select id="adjustmentType" name="salary_component_id">
                    @foreach ($data['components']->groupBy('type') as $type => $components)
                        <optgroup label="{{ $type === 'earning' ? 'Add to pay' : 'Deduct from pay' }}">
                            @foreach ($components as $component)<option value="{{ $component->id }}">{{ $component->name }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="salary_component_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'amount', 'label' => 'Amount (Rs)', 'type' => 'number', 'required' => true, 'min' => 1, 'step' => '0.01', 'id' => 'adjustmentAmount'])
            @include('dashboards.partials.field', ['name' => 'note', 'label' => 'Note (shown on the payslip)', 'type' => 'text', 'wide' => true, 'id' => 'adjustmentNote', 'placeholder' => 'e.g. 12 hours overtime, Eid bonus, late report fine'])
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
<script src="{{ asset('js/admin-payroll.js') }}?v={{ filemtime(public_path('js/admin-payroll.js')) }}" defer></script>
@endsection

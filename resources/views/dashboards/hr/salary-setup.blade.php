@extends('layouts.dashboard')

@section('title', 'Salary Setup')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Salary Setup</h1>
        <p>Allowances and deductions with the ledger account each posts to, and the income-tax slabs. Built-in items (Basic, Absent, Loan, Tax) are filled by payroll itself.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Component
    </button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.salary-setup'])

<section class="dash-panel" data-payroll="components" data-datatable-url="{{ route('erp.salary-components.datatable') }}" data-resource-url="{{ url('/api/erp/salary-components') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Default</th>
                    <th class="dash-hide-md">Ledger account</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<section class="dash-panel" data-tax-slabs data-url="{{ url('/api/erp/tax-slabs') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Income tax slabs</h2>
            <p>Yearly taxable pay → tax. Check them against the current Finance Act; payroll uses the slabs of the payroll month's fiscal year.</p>
        </div>
        <label class="dash-select">
            <span class="dash-visually-hidden">Fiscal year</span>
            <select data-slab-year>
                @foreach ($data['fiscal_years'] as $year)
                    <option value="{{ $year->id }}" @selected($year->is_current)>{{ $year->name }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table">
            <thead><tr><th>Yearly income from</th><th>Up to</th><th>Fixed tax</th><th>Rate on excess</th><th></th></tr></thead>
            <tbody data-slab-rows><tr><td colspan="5" class="dash-muted">Loading…</td></tr></tbody>
            <tfoot>
                <tr class="dash-slab-new">
                    <td><input type="number" min="0" step="1" placeholder="Rs" data-slab-field="min_income" aria-label="From"></td>
                    <td><input type="number" min="0" step="1" placeholder="Rs (empty = no limit)" data-slab-field="max_income" aria-label="Up to"></td>
                    <td><input type="number" min="0" step="1" placeholder="Rs" data-slab-field="fixed_tax" aria-label="Fixed tax"></td>
                    <td><input type="number" min="0" max="100" step="0.01" placeholder="%" data-slab-field="rate_percent" aria-label="Rate"></td>
                    <td><button type="button" class="dash-btn dash-btn-ghost" data-slab-add>Add</button></td>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="payrollModal" aria-labelledby="payrollModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'hash'])</span>
            <div>
                <h3 id="payrollModalTitle" data-modal-title>Add component</h3>
                <p>Percent of basic: e.g. 45 for house rent at 45%.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'placeholder' => 'HRA'])
            <div class="dash-field">
                <label for="componentType">Type</label>
                <select id="componentType" name="type"><option value="earning">Earning (allowance)</option><option value="deduction">Deduction</option></select>
                <p class="dash-field-error" data-error-for="type" hidden></p>
            </div>
            <div class="dash-field">
                <label for="componentCalc">Calculation</label>
                <select id="componentCalc" name="calculation"><option value="fixed">Fixed amount (Rs)</option><option value="percent_of_basic">% of basic salary</option></select>
            </div>
            @include('dashboards.partials.field', ['name' => 'default_value', 'label' => 'Default (Rs or %)', 'type' => 'number', 'min' => 0, 'step' => '0.01'])
            <div class="dash-field">
                <label for="componentAccount">Ledger account</label>
                <select id="componentAccount" name="account_id" >
                    <option value="">—</option>
                    @foreach ($data['accounts'] as $option)
                        <option value="{{ $option->id }}">{{ $option->code }} {{ $option->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="account_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
            <label class="dash-switch">
                <input type="checkbox" name="is_taxable" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Taxable</span>
            </label>
            <label class="dash-switch">
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
<script src="{{ asset('js/admin-payroll.js') }}?v={{ filemtime(public_path('js/admin-payroll.js')) }}" defer></script>
@endsection

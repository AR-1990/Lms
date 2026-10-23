@extends('layouts.dashboard')

@section('title', 'Salaries')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Salaries</h1>
        <p>Each employee’s basic salary, allowances and deductions. Saving from a new date starts a new structure and records the increment.</p>
    </div>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.salaries'])

<section class="dash-panel" data-payroll="salaries" data-datatable-url="{{ route('erp.salary-structures.datatable') }}" data-api="{{ url('/api/erp') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="dash-hide-md">Designation</th>
                    <th>Basic</th>
                    <th>Gross</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="salaryModal" aria-labelledby="salaryModalTitle">
    <form data-salary-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'trending-up'])</span>
            <div>
                <h3 id="salaryModalTitle" data-salary-title>Salary</h3>
                <p data-salary-current>Leave an amount empty to use the component's default.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'effective_from', 'label' => 'From date', 'type' => 'date', 'required' => true, 'id' => 'salaryFrom'])
            @include('dashboards.partials.field', ['name' => 'basic_salary', 'label' => 'Basic salary (Rs)', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01', 'id' => 'salaryBasic'])
            <div class="dash-field dash-field-wide">
                <span class="dash-label">Allowances and deductions</span>
                <table class="dash-table dash-salary-items">
                    <thead><tr><th></th><th>Component</th><th>Amount (Rs)</th></tr></thead>
                    <tbody>
                        @foreach ($data['components'] as $component)
                            <tr>
                                <td><input type="checkbox" data-item-on="{{ $component->id }}" aria-label="Include {{ $component->name }}"></td>
                                <td>{{ $component->name }} <span class="dash-muted">{{ $component->type === 'earning' ? 'earning' : 'deduction' }} · {{ $component->calculation === 'percent_of_basic' ? number_format($component->default_value / 100, 2).'% of basic' : 'Rs '.number_format($component->default_value / 100) }}</span></td>
                                <td><input type="number" min="0" step="0.01" placeholder="default" data-item-amount="{{ $component->id }}" aria-label="{{ $component->name }} amount"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="dash-field-error" data-error-for="items" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'salaryRemarks'])
            <div class="dash-field dash-field-wide">
                <span class="dash-label">Increment history</span>
                <ul class="dash-kv" data-salary-history></ul>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save salary</button>
        </div>
    </form>
</dialog>

@endsection

@section('extra_js')
<script src="{{ asset('js/admin-payroll.js') }}?v={{ filemtime(public_path('js/admin-payroll.js')) }}" defer></script>
@endsection

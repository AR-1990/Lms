@extends('layouts.dashboard')

@section('title', 'Payroll '.$data['run']->month->format('F Y'))
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
@php($run = $data['run'])
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Payroll {{ $run->month->format('F Y') }}</h1>
        <p>Status: <strong data-run-status>{{ ucfirst($run->status) }}</strong>@if ($run->approver) · approved by {{ $run->approver->name }}@endif</p>
    </div>
    <div class="dash-head-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-run-print>@include('partials.dashboard-icon', ['name' => 'book']) Print payslips</button>
        @if ($run->status === 'draft')
            <button type="button" class="dash-btn dash-btn-ghost" data-run-action="regenerate">↻ Regenerate</button>
            @if (auth()->user()->hasPermission('approve-requests'))
                <button type="button" class="dash-btn dash-btn-primary" data-run-action="approve">@include('partials.dashboard-icon', ['name' => 'check']) Approve</button>
            @endif
        @elseif ($run->status === 'approved')
            <button type="button" class="dash-btn dash-btn-primary" data-run-action="post">@include('partials.dashboard-icon', ['name' => 'book']) Post to Ledger</button>
        @elseif ($run->status === 'posted')
            <button type="button" class="dash-btn dash-btn-ghost" data-run-action="reverse">Reverse</button>
            <button type="button" class="dash-btn dash-btn-primary" data-run-action="pay">@include('partials.dashboard-icon', ['name' => 'check']) Pay Salaries</button>
        @endif
    </div>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.payroll'])

<section class="dash-stats">
    <article class="dash-stat"><span class="dash-stat-label">Staff</span><strong class="dash-stat-value">{{ $run->employee_count }}</strong></article>
    <article class="dash-stat"><span class="dash-stat-label">Gross</span><strong class="dash-stat-value">Rs {{ number_format($run->total_gross / 100) }}</strong></article>
    <article class="dash-stat"><span class="dash-stat-label">Deductions</span><strong class="dash-stat-value">Rs {{ number_format($run->total_deductions / 100) }}</strong></article>
    <article class="dash-stat"><span class="dash-stat-label">Net pay</span><strong class="dash-stat-value">Rs {{ number_format($run->total_net / 100) }}</strong></article>
</section>

<section class="dash-panel" data-payroll="payslips" data-run-id="{{ $run->id }}" data-run-month="{{ $run->month->format('Y-m') }}" data-api="{{ url('/api/erp') }}" data-datatable-url="{{ route('erp.payroll-runs.payslips', $run) }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="dash-hide-md">Days</th>
                    <th class="dash-hide-md">Gross</th>
                    <th class="dash-hide-md">Deductions</th>
                    <th>Net</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="payModal" aria-labelledby="payModalTitle">
    <form data-pay-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="payModalTitle">Pay salaries</h3>
                <p>Posts a payment voucher: Dr Salaries Payable, Cr the cash / bank account.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="payMethod">Paid by</label>
                <select id="payMethod" name="payment_method_id">
                    @foreach ($data['payment_methods'] as $method)
                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="payment_method_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'paid_on', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'payDate'])
            @include('dashboards.partials.field', ['name' => 'reference', 'label' => 'Bank / cheque reference', 'wide' => true, 'id' => 'payReference'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Pay Rs {{ number_format($run->total_net / 100) }}</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="reverseModal" aria-labelledby="reverseModalTitle">
    <form data-reverse-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'alert'])</span>
            <div>
                <h3 id="reverseModalTitle">Reverse this payroll</h3>
                <p>A reversal voucher cancels the salary journal; the run becomes a draft again.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Narration (why)', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'reverseReason'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-danger" data-submit>Reverse</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-payroll.js') }}?v={{ filemtime(public_path('js/admin-payroll.js')) }}" defer></script>
@endsection

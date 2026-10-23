@extends('layouts.dashboard')

@section('title', 'My HR')
@section('page_heading', 'My HR')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>My HR</h1>
        <p>Your leave balance, leave requests, payslips, loans and appraisals.</p>
    </div>
    @if ($data['employee'])
        <div class="dash-head-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-open="loanRequestModal">@include('partials.dashboard-icon', ['name' => 'key']) Request Loan</button>
            <button type="button" class="dash-btn dash-btn-primary" data-open="applyLeaveModal">@include('partials.dashboard-icon', ['name' => 'calendar']) Apply for Leave</button>
        </div>
    @endif
</section>

@if (! $data['employee'])
    <div class="dash-empty-note">
        @include('partials.dashboard-icon', ['name' => 'users'])
        <p>Your login is not linked to an employee record. Please ask HR.</p>
    </div>
@else
<div data-my-hr data-api="{{ url('/api/erp/my-hr') }}" data-team-url="{{ url('/api/erp/leave-requests') }}" data-is-manager="{{ $data['is_manager'] ? 1 : 0 }}" data-appraisals-url="{{ url('/api/erp/my-appraisals') }}">
    <section class="dash-leave-balances" data-balances aria-label="Leave balances"><p class="dash-muted">Loading…</p></section>

    <div class="dash-my-hr-grid">
        <section class="dash-panel">
            <div class="dash-panel-head"><h2>My leave</h2></div>
            <ul class="dash-list" data-leave-list><li class="dash-muted">Loading…</li></ul>
        </section>

        <section class="dash-panel">
            <div class="dash-panel-head"><h2>Payslips</h2></div>
            <ul class="dash-list" data-payslip-list><li class="dash-muted">Loading…</li></ul>
        </section>
    </div>

    @if ($data['is_manager'])
        <section class="dash-panel">
            <div class="dash-panel-head">
                <div>
                    <h2>My team's leave</h2>
                    <p>People who report to you. Approve or reject their requests here.</p>
                </div>
            </div>
            <ul class="dash-list" data-team-list><li class="dash-muted">Loading…</li></ul>
        </section>
    @endif

    <section class="dash-panel">
        <div class="dash-panel-head"><h2>Loans &amp; advances</h2></div>
        <ul class="dash-list" data-loan-list><li class="dash-muted">Loading…</li></ul>
    </section>

    <div class="dash-my-hr-grid">
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>My appraisals</h2><p>Rate yourself when a review starts, then read and acknowledge what your reviewer wrote.</p></div></div>
            <ul class="dash-list" data-appraisal-mine><li class="dash-muted">Loading…</li></ul>
        </section>

        <section class="dash-panel" data-appraisal-review-panel hidden>
            <div class="dash-panel-head"><div><h2>Reviews to do</h2><p>Staff you appraise this cycle.</p></div></div>
            <ul class="dash-list" data-appraisal-review></ul>
        </section>
    </div>

    <section class="dash-panel">
        <div class="dash-panel-head"><h2>Assets I hold</h2></div>
        @if ($data['assets'] === [])
            <p class="dash-muted">No school assets are handed to you.</p>
        @else
            <ul class="dash-list">
                @foreach ($data['assets'] as $held)
                    <li><strong>{{ $held['asset_tag'] }} · {{ $held['name'] }}</strong> <span class="dash-muted">{{ collect([$held['category'], $held['serial_no'], 'since '.$held['since'], $held['expected_return_on'] ? 'return by '.$held['expected_return_on'] : null])->filter()->implode(' · ') }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

<dialog class="dash-modal dash-form-modal" id="applyLeaveModal" aria-labelledby="applyLeaveTitle">
    <form data-apply-leave novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'calendar'])</span>
            <div>
                <h3 id="applyLeaveTitle">Apply for leave</h3>
                <p>Sundays and school holidays are not counted.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="myLeaveType">Leave type</label>
                <select id="myLeaveType" name="leave_type_id">
                    @foreach ($data['leave_types'] as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}{{ $type->is_paid ? '' : ' (unpaid)' }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="leave_type_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'required' => true, 'id' => 'myLeaveFrom'])
            @include('dashboards.partials.field', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'required' => true, 'id' => 'myLeaveTo'])
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_half_day">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Half day (one date only)</span>
            </label>
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'myLeaveReason'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Send request</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="loanRequestModal" aria-labelledby="loanRequestTitle">
    <form data-request-loan novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'key'])</span>
            <div>
                <h3 id="loanRequestTitle">Request a loan or advance</h3>
                <p>An administrator approves it; instalments are deducted from your salary.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="myLoanType">Type</label>
                <select id="myLoanType" name="loan_type_id">
                    @foreach ($data['loan_types'] as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            @include('dashboards.partials.field', ['name' => 'amount', 'label' => 'Amount (Rs)', 'type' => 'number', 'required' => true, 'min' => 1, 'id' => 'myLoanAmount'])
            @include('dashboards.partials.field', ['name' => 'instalments', 'label' => 'Months to repay', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 60, 'id' => 'myLoanInstalments'])
            @include('dashboards.partials.field', ['name' => 'start_month', 'label' => 'First deduction', 'type' => 'month', 'required' => true, 'id' => 'myLoanStart'])
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'wide' => true, 'id' => 'myLoanReason'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Send request</button>
        </div>
    </form>
</dialog>
<dialog class="dash-modal dash-form-modal" id="teamRejectModal" aria-labelledby="teamRejectTitle">
    <form data-team-reject novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'alert'])</span>
            <div>
                <h3 id="teamRejectTitle">Reject leave</h3>
                <p>Your reason is sent to the employee.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Reason', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'teamRejectReason'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-danger" data-submit>Reject</button>
        </div>
    </form>
</dialog>
@include('dashboards.partials.appraisal-modal')
@endif
@endsection

@section('extra_js')
@include('partials.datatable-assets')
<script src="{{ asset('js/my-hr.js') }}?v={{ filemtime(public_path('js/my-hr.js')) }}" defer></script>
@endsection

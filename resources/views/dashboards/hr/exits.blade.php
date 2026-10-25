@extends('layouts.dashboard')

@section('title', 'Staff Exits')
@section('page_heading', 'HR')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Staff Exits</h1>
        <p>When someone leaves: each area clears them, school assets come back, then the final settlement is paid as one voucher and the experience letter is ready.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Start Exit</button>
</section>

@include('dashboards.hr.tabs', ['current' => 'admin.hr.exits'])

<section class="dash-panel" data-exits data-datatable-url="{{ route('erp.employee-exits.datatable') }}" data-resource-url="{{ url('/api/erp/employee-exits') }}" data-clearance-url="{{ url('/api/erp/exit-clearances') }}">
    <div class="dash-panel-head">
        <h2>Exits</h2>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All</option>
                    <option value="clearance">In clearance</option>
                    <option value="settled">Settled</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leaving</th>
                    <th class="dash-hide-md">Clearance</th>
                    <th class="dash-col-num">Settlement</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="exitModal" aria-labelledby="exitModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'logout'])</span>
            <div>
                <h3 id="exitModalTitle" data-modal-title>Start exit</h3>
                <p>Opens the clearance checklist. The employee keeps working until the last working day.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="exitEmployee">Employee</label>
                <select id="exitEmployee" name="employee_id" required>
                    <option value="">—</option>
                    @foreach ($data['staff'] as $option)
                        <option value="{{ $option->id }}">{{ trim($option->first_name.' '.$option->last_name) }} ({{ $option->employee_code }})</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="employee_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="exitType">Type</label>
                <select id="exitType" name="exit_type" required>
                    <option value="resignation">Resignation</option>
                    <option value="termination">Termination</option>
                    <option value="retirement">Retirement</option>
                    <option value="contract_end">Contract end</option>
                </select>
                <p class="dash-field-error" data-error-for="exit_type" hidden></p>
            </div>
            <div class="dash-field">
                <label for="exitReason">Reason <span class="dash-optional">optional</span></label>
                <select id="exitReason" name="reason_id">
                    <option value="">—</option>
                    @foreach ($data['exit_reasons'] as $reason)
                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="reason_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'notice_date', 'label' => 'Notice given on', 'type' => 'date', 'id' => 'exitNotice'])
            @include('dashboards.partials.field', ['name' => 'last_working_day', 'label' => 'Last working day', 'type' => 'date', 'required' => true, 'id' => 'exitLastDay'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'exitRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Start exit</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal dash-exit-detail" id="exitDetail" aria-labelledby="exitDetailTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
        <div>
            <h3 id="exitDetailTitle" data-detail-title>Exit</h3>
            <p data-detail-subtitle></p>
        </div>
        <button type="button" class="dash-btn dash-btn-ghost" data-letter hidden>@include('partials.dashboard-icon', ['name' => 'file']) Experience letter</button>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body">
        <h4>Clearance</h4>
        <ul class="dash-leave-checks" data-clearances></ul>
        <p class="dash-muted" data-assets></p>

        <h4>Final settlement</h4>
        <div class="dash-table-wrap"><table class="dash-table"><tbody data-lines></tbody></table></div>

        <form class="dash-inline-form" data-settle-form novalidate>
            <div class="dash-exit-extra" data-extra></div>
            <button type="button" class="dash-link-btn" data-add-line>+ Add a line (gratuity, bonus, notice-period deduction…)</button>
            <div class="dash-form-grid">
                <div class="dash-field">
                    <label for="settleMethod">Paid by</label>
                    <select id="settleMethod" name="payment_method_id">
                        @foreach ($data['payment_methods'] as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                @include('dashboards.partials.field', ['name' => 'paid_on', 'label' => 'Paid on', 'type' => 'date', 'required' => true, 'id' => 'settleDate'])
                @include('dashboards.partials.field', ['name' => 'exit_interview_notes', 'label' => 'Exit interview notes', 'type' => 'textarea', 'wide' => true, 'id' => 'settleNotes'])
            </div>
            <p class="dash-form-error" data-blocking hidden></p>
            <p class="dash-form-error" data-form-error hidden></p>
            <div class="dash-inline-actions">
                <button type="button" class="dash-btn dash-btn-ghost" data-cancel-exit>Cancel exit</button>
                <button type="submit" class="dash-btn dash-btn-primary" data-pay>@include('partials.dashboard-icon', ['name' => 'check']) Pay settlement &amp; close</button>
            </div>
        </form>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-hr-exits.js') }}?v={{ filemtime(public_path('js/admin-hr-exits.js')) }}" defer></script>
@endsection

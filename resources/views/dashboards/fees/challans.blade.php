@extends('layouts.dashboard')

@section('title', 'Challans')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Challans</h1>
        <p>Every challan is posted to the ledger. Mistakes are cancelled or waived, never edited.</p>
    </div>
    <div class="dash-head-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-open-generate>
            @include('partials.dashboard-icon', ['name' => 'calendar'])
            Generate Month
        </button>
        <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
            @include('partials.dashboard-icon', ['name' => 'plus'])
            Manual Challan
        </button>
    </div>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.challans'])

<section class="dash-panel" data-fees="challans" data-datatable-url="{{ route('erp.fees.invoices.datatable') }}" data-resource-url="{{ route('erp.fees.invoices.index') }}" data-students-url="{{ route('erp.fees.students.index') }}" data-receipts-url="{{ route('accounts.fees.receipts') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Challans</h2>
            <p>Open a challan to see its lines, payments and write-offs.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="overdue" @selected(request('status') === 'overdue')>Overdue (past due date)</option>
                    <option value="paid">Paid</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Type</span>
                <select data-filter="invoice_type">
                    <option value="">All types</option>
                    @foreach (\App\Models\FeeInvoice::TYPES as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Class</span>
                <select data-filter="class_id">
                    <option value="">All classes</option>
                    @foreach ($data['classes'] as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Month</span>
                <input type="month" data-filter="billing_month" aria-label="Billing month">
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Challan</th>
                    <th class="dash-hide-md">Type</th>
                    <th class="dash-hide-md">Due</th>
                    <th>Total</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="feesModal" aria-labelledby="feesModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'hash'])</span>
            <div>
                <h3 id="feesModalTitle" data-modal-title>Manual challan</h3>
                <p>For one-off charges (exam, trip, fine). Amounts in rupees.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.fees.student-picker', ['id' => 'manualStudent'])
            @include('dashboards.partials.field', ['name' => 'issue_date', 'label' => 'Issue date', 'type' => 'date', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'due_date', 'label' => 'Due date', 'type' => 'date', 'required' => true])
        </div>
        <div class="dash-line-items" data-line-items>
            <h4 class="dash-form-section">Lines</h4>
            @for ($line = 0; $line < 4; $line++)
                <div class="dash-line-item" data-line>
                    <select data-line-field="fee_head_id" aria-label="Fee head {{ $line + 1 }}">
                        <option value="">Fee head…</option>
                        @foreach ($data['fee_heads'] as $head)
                            <option value="{{ $head->id }}">{{ $head->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" min="1" step="0.01" placeholder="Amount" data-line-field="amount" aria-label="Amount {{ $line + 1 }}">
                    <input type="text" maxlength="255" placeholder="Description (optional)" data-line-field="description" aria-label="Description {{ $line + 1 }}">
                </div>
            @endfor
            <p class="dash-field-error" data-error-for="items" hidden></p>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Issue Challan</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="generateModal" aria-labelledby="generateTitle">
    <form data-generate-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'calendar'])</span>
            <div>
                <h3 id="generateTitle">Generate monthly challans</h3>
                <p>Students who already have a challan for the month are skipped.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="generateSession">Session</label>
                <select id="generateSession" name="academic_session_id">
                    @foreach ($data['sessions'] as $session)
                        <option value="{{ $session->id }}" @selected($session->id === $data['current_session_id'])>{{ $session->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="academic_session_id" hidden></p>
            </div>
            @include('dashboards.partials.select', ['name' => 'class_id', 'label' => 'Class', 'options' => $data['classes'], 'empty' => 'All classes', 'id' => 'generateClass'])
            @include('dashboards.partials.field', ['name' => 'billing_month', 'label' => 'Month', 'type' => 'month', 'required' => true, 'id' => 'generateMonth', 'wide' => true])
            <label class="dash-switch">
                <input type="checkbox" name="print_after">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Open the challans to print when done</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Generate</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="challanModal" aria-labelledby="challanTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'hash'])</span>
        <div>
            <h3 id="challanTitle" data-detail-title>Challan</h3>
            <p data-detail-subtitle></p>
        </div>
        <button type="button" class="dash-btn dash-btn-ghost" data-print-challan>@include('partials.dashboard-icon', ['name' => 'book']) Print</button>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body" data-detail-body></div>

    <form class="dash-inline-form" data-waive-form novalidate>
        <h4>Write off part of the balance</h4>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'amount', 'label' => 'Amount (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => 1, 'required' => true, 'id' => 'waiveAmount'])
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Narration', 'required' => true, 'id' => 'waiveReason', 'placeholder' => 'At least 10 characters — goes on the voucher'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Waive</button></div>
    </form>

    <form class="dash-inline-form" data-cancel-form novalidate>
        <h4>Cancel challan</h4>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Narration', 'required' => true, 'id' => 'cancelReason', 'wide' => true, 'placeholder' => 'Why is this challan cancelled? Goes on the reversal voucher'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-danger">Cancel challan</button></div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-fees.js') }}?v={{ filemtime(public_path('js/admin-fees.js')) }}" defer></script>
@endsection

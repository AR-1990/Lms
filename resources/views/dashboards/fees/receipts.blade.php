@extends('layouts.dashboard')

@section('title', 'Receipts')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Receipts</h1>
        <p>Money received goes to the oldest challans first; anything extra is kept as the student's advance.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Receive Payment
    </button>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.receipts'])

<section class="dash-panel" data-fees="receipts" data-datatable-url="{{ route('erp.fees.payments.datatable') }}" data-resource-url="{{ route('erp.fees.payments.index') }}" data-students-url="{{ route('erp.fees.students.index') }}" data-invoices-url="{{ route('erp.fees.invoices.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Receipts</h2>
            <p>A wrong receipt or a bounced cheque is reversed, never deleted.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All receipts</option>
                    <option value="posted">Posted</option>
                    <option value="reversed">Reversed</option>
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Method</span>
                <select data-filter="payment_method_id">
                    <option value="">All methods</option>
                    @foreach ($data['payment_methods'] as $method)
                        <option value="{{ $method->id }}">{{ $method->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">From</span>
                <input type="date" data-filter="date_from" aria-label="From date">
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">To</span>
                <input type="date" data-filter="date_to" aria-label="To date">
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th class="dash-hide-md">Method</th>
                    <th class="dash-hide-md">Received</th>
                    <th>Amount</th>
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
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="feesModalTitle" data-modal-title>Receive payment</h3>
                <p>Tick the challans being paid, or leave all unticked to pay the oldest first.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.fees.student-picker', ['id' => 'receiptStudent'])
            <p class="dash-applicant-note dash-field-wide" data-applicant hidden></p>
            <div class="dash-field dash-field-wide">
                <span class="dash-label">Open challans</span>
                <ul class="dash-checks" data-open-invoices>
                    <li class="dash-muted">Pick a student to see their open challans.</li>
                </ul>
                <p class="dash-field-error" data-error-for="invoice_ids" hidden></p>
            </div>
            <div class="dash-field">
                <label for="receiptMethod">Method</label>
                <select id="receiptMethod" name="payment_method_id" required>
                    @foreach ($data['payment_methods'] as $method)
                        <option value="{{ $method->id }}" data-requires-reference="{{ $method->requires_reference ? 1 : 0 }}" data-requires-bank="{{ $method->requires_bank_details ? 1 : 0 }}">{{ $method->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="payment_method_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'amount', 'label' => 'Amount (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => 1, 'required' => true])
            @include('dashboards.partials.field', ['name' => 'received_on', 'label' => 'Received on', 'type' => 'date', 'required' => true])
            <div data-reference-field>
                @include('dashboards.partials.field', ['name' => 'reference_no', 'label' => 'Transaction / cheque no'])
            </div>
            <div data-bank-field>
                @include('dashboards.partials.select', ['name' => 'cheque_bank_id', 'label' => 'Bank', 'options' => $data['banks']])
            </div>
            <div data-bank-field>
                @include('dashboards.partials.field', ['name' => 'cheque_date', 'label' => 'Cheque date', 'type' => 'date'])
            </div>
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Receipt</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="receiptModal" aria-labelledby="receiptTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
        <div>
            <h3 id="receiptTitle" data-detail-title>Receipt</h3>
            <p data-detail-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body" data-detail-body></div>
    <form class="dash-inline-form" data-reverse-form novalidate>
        <h4>Reverse receipt</h4>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Narration', 'required' => true, 'id' => 'reverseReason', 'wide' => true, 'placeholder' => 'Why is this receipt reversed? Goes on the reversal voucher'])
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="cheque_bounced">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Cheque bounced</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-danger">Reverse</button></div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-fees.js') }}?v={{ filemtime(public_path('js/admin-fees.js')) }}" defer></script>
@endsection

@extends('layouts.dashboard')

@section('title', 'Online Payments')
@section('page_heading', 'Fees')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Online Payments</h1>
        <p>Payments families made by JazzCash, EasyPaisa or bank transfer and sent with a screenshot. Check the transaction in your account, then approve it to make the receipt, or reject it with the reason. Set the "How families pay" text under Accounts → Payment Methods.</p>
    </div>
</section>

@include('dashboards.fees.tabs', ['current' => 'accounts.fees.online-payments'])

<section class="dash-panel" data-online-payments data-datatable-url="{{ route('erp.fees.payment-proofs.datatable') }}" data-resource-url="{{ url('/api/erp/fees/payment-proofs') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Sent by families</h2>
            <p>Open one to see the screenshot.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="pending">Waiting to check</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="">All</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Paid by</th>
                    <th class="dash-hide-md">Transaction ID</th>
                    <th>Paid on</th>
                    <th class="dash-col-num">Amount</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-modal-wide" id="proofReview" aria-labelledby="proofReviewTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'wallet'])</span>
        <div>
            <h3 id="proofReviewTitle" data-proof-title>Online payment</h3>
            <p data-proof-sub></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll">
        <div class="dash-proof">
            <a class="dash-proof-shot" data-proof-link target="_blank" rel="noopener">
                <img data-proof-image alt="Payment screenshot">
                <span data-proof-file hidden>Open the attached file</span>
            </a>
            <div>
                <dl class="dash-asset-facts" data-proof-facts></dl>
                <div class="dash-field" data-proof-reject hidden>
                    <label for="proofReason">Why is it rejected?</label>
                    <textarea id="proofReason" rows="2" maxlength="255" placeholder="Transaction not found in our JazzCash account"></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="dash-modal-actions" data-proof-actions>
        <button type="button" class="dash-btn dash-btn-ghost" data-proof-reject-btn>Reject</button>
        <button type="button" class="dash-btn dash-btn-primary" data-proof-approve>@include('partials.dashboard-icon', ['name' => 'check']) Approve &amp; make receipt</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-online-payments.js') }}?v={{ filemtime(public_path('js/admin-online-payments.js')) }}" defer></script>
@endsection

@extends('layouts.dashboard')

@section('title', 'Approvals')
@section('page_heading', 'Approvals')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Approvals</h1>
        <p>Deletions and purchases wait here for an administrator. Approved deletions form the deleted-records register: who asked, why, and who approved.</p>
    </div>
</section>

<section class="dash-panel" data-approvals data-datatable-url="{{ route('erp.approvals.datatable') }}" data-resource-url="{{ url('/api/erp/approvals') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Requests</h2>
            <p>Open a request to approve or reject it.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="">All</option>
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Type</span>
                <select data-filter="request_type">
                    <option value="">Deletions &amp; purchases</option>
                    <option value="delete">Deletions</option>
                    <option value="purchase">Purchases</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Record</th>
                    <th class="dash-hide-md">Requested by</th>
                    <th class="dash-hide-md">Reason</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="approvalModal" aria-labelledby="approvalTitle">
    <form data-decision-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'shield'])</span>
            <div>
                <h3 id="approvalTitle" data-detail-title>Request</h3>
                <p data-detail-subtitle></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-detail-body" data-detail-body></div>
        <div class="dash-form-grid" data-decision-fields>
            @include('dashboards.partials.field', ['name' => 'decision_note', 'label' => 'Note', 'type' => 'textarea', 'wide' => true, 'hint' => 'required when rejecting'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions" data-decision-actions>
            <button type="button" class="dash-btn dash-btn-ghost" data-decision="reject">Reject</button>
            <button type="button" class="dash-btn dash-btn-primary" data-decision="approve">@include('partials.dashboard-icon', ['name' => 'check']) Approve</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-approvals.js') }}?v={{ filemtime(public_path('js/admin-approvals.js')) }}" defer></script>
@endsection

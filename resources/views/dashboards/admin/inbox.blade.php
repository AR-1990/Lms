@extends('layouts.dashboard')

@section('title', 'Approvals Inbox')
@section('page_heading', 'Approvals Inbox')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Approvals Inbox</h1>
        <p>Everything waiting for your decision — deletions, purchases, expenses, purchase orders, loans, concessions and leave — in one place. You only see what you are allowed to approve.</p>
    </div>
    <span class="dash-badge dash-badge-warn" data-inbox-total>…</span>
</section>

<div data-inbox data-url="{{ route('erp.approvals.inbox') }}">
    <p class="dash-muted">Loading…</p>
</div>

<dialog class="dash-modal dash-form-modal" id="inboxReject" aria-labelledby="inboxRejectTitle">
    <form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon">@include('partials.dashboard-icon', ['name' => 'close'])</span>
            <div>
                <h3 id="inboxRejectTitle">Reject</h3>
                <p data-reject-title></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'wide' => true, 'id' => 'inboxReason'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">Reject</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/approvals-inbox.js') }}?v={{ filemtime(public_path('js/approvals-inbox.js')) }}" defer></script>
@endsection

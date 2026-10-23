@extends('layouts.dashboard')

@section('title', 'Verification')
@section('page_heading', 'Assets')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Verification</h1>
        <p>Physical checks: walk a room (or the whole school) and tick what is there. Missing items can be marked lost.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) Start Verification</button>
</section>

@include('dashboards.assets.tabs', ['current' => 'admin.assets.verification'])

<section class="dash-panel" data-assets="verification" data-datatable-url="{{ url('/api/erp/asset-audits/datatable') }}" data-resource-url="{{ url('/api/erp/asset-audits') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Verifications</h2>
            <p>Open one to tick found / missing and record condition.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Location</th>
                    <th class="dash-hide-md">By</th>
                    <th>Found / missing</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="auditModal" aria-labelledby="auditModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="auditModalTitle" data-modal-title>Start verification</h3>
                <p data-action-asset>One line is created for every asset expected there.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'audit_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'auditDate'])
            @include('dashboards.partials.select', ['name' => 'location_id', 'label' => 'Location', 'options' => $data['locations'], 'empty' => 'All locations', 'id' => 'auditLocation'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'auditRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Start</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="auditSheet" aria-labelledby="auditSheetTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
        <div>
            <h3 id="auditSheetTitle" data-sheet-title>Verification</h3>
            <p data-sheet-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll">
        <div class="dash-scan-bar" data-scan-bar>
            <input type="search" class="dash-input" placeholder="Scan the label or type an asset tag, then press Enter" autocomplete="off" data-scan aria-label="Scan or type asset tag">
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-mark-all>Mark all found</button>
            <span class="dash-badge dash-badge-brand" data-progress>0 of 0 checked</span>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead><tr><th>Asset</th><th>Should be</th><th>Found?</th><th>Condition</th><th>Remarks</th></tr></thead>
                <tbody data-sheet-rows></tbody>
            </table>
        </div>
        <template data-condition-options>
            <option value="">—</option>
            @foreach ($data['conditions'] as $condition)<option value="{{ $condition->id }}">{{ $condition->name }}</option>@endforeach
        </template>
    </div>
    <div class="dash-modal-actions" data-sheet-actions>
        <label class="dash-switch">
            <input type="checkbox" data-mark-lost>
            <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
            <span>Mark missing assets as lost</span>
        </label>
        <button type="button" class="dash-btn dash-btn-ghost" data-sheet-save>Save</button>
        <button type="button" class="dash-btn dash-btn-primary" data-sheet-complete>@include('partials.dashboard-icon', ['name' => 'check']) Complete</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-assets.js') }}?v={{ filemtime(public_path('js/admin-assets.js')) }}" defer></script>
@endsection

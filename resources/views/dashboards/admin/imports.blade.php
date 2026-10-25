@extends('layouts.dashboard')

@section('title', 'Data Import')
@section('page_heading', 'Data Import')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Data Import</h1>
        <p>Bring existing students, staff and fee balances in from a spreadsheet. Download the template, fill it in Excel, save it as CSV and check it here. Nothing is saved until every row is correct.</p>
    </div>
</section>

<div class="dash-import-grid" data-imports data-url="{{ route('erp.imports.index') }}" data-store-url="{{ url('/api/erp/imports') }}">
    <p class="dash-muted" data-imports-loading>Loading…</p>
</div>

<section class="dash-panel" data-import-result hidden>
    <div class="dash-panel-head">
        <div><h2 data-result-title>Check result</h2><p data-result-text></p></div>
        <button type="button" class="dash-btn dash-btn-primary" data-import-save hidden>@include('partials.dashboard-icon', ['name' => 'check']) Import all rows</button>
    </div>
    <div class="dash-table-wrap" data-result-table hidden>
        <table class="dash-table">
            <thead><tr><th>Row</th><th>What to fix</th></tr></thead>
            <tbody data-result-rows></tbody>
        </table>
    </div>
</section>

<section class="dash-panel">
    <div class="dash-panel-head"><div><h2>Filling the template</h2></div></div>
    <ul class="dash-import-help">
        <li><strong>Required columns</strong> end with * in the template; leave other columns empty when you do not have the value.</li>
        <li><strong>Dates</strong> may be 2026-08-01 or 01/08/2026.</li>
        <li><strong>Names from lists</strong> (gender, class, section, religion, relation, department, designation, employment type, bank) must match the names in the system; the check tells you the allowed names.</li>
        <li><strong>Students</strong> go into the current session. A guardian with the same CNIC is linked to all their children.</li>
        <li><strong>Opening balances</strong>: amount in rupees by admission number. A plus amount is money the family owes (a "Previous Balance" challan); a minus amount is money already paid in advance.</li>
        <li>Up to {{ \App\Services\ImportService::MAX_ROWS }} rows per file.</li>
    </ul>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-imports.js') }}?v={{ filemtime(public_path('js/admin-imports.js')) }}" defer></script>
@endsection

@extends('layouts.dashboard')

@section('title', 'Numbering')
@section('page_heading', 'Document Numbering')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Document Numbering</h1>
        <p>How admission numbers, invoices, receipts, and vouchers are numbered. Counters only move forward, so a number is never issued twice.</p>
    </div>
</section>

<section
    class="dash-panel"
    data-numbering
    data-datatable-url="{{ route('erp.number-sequences.datatable') }}"
    data-resource-url="{{ route('erp.number-sequences.index') }}"
>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Next number</th>
                    <th>Prefix</th>
                    <th>Digits</th>
                    <th>Yearly reset</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="numberingModal" aria-labelledby="numberingModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'hash'])</span>
            <div>
                <h3 id="numberingModalTitle" data-modal-title>Edit number format</h3>
                <p>Preview: <strong class="dash-number-preview" data-preview>—</strong></p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="numberingLabel">Name</label>
                <input id="numberingLabel" name="label" required maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="label" hidden></p>
            </div>
            <div class="dash-field">
                <label for="numberingPrefix">Prefix <span class="dash-optional">optional</span></label>
                <input id="numberingPrefix" name="prefix" maxlength="20" autocomplete="off" placeholder="INV-">
                <p class="dash-field-error" data-error-for="prefix" hidden></p>
            </div>
            <div class="dash-field">
                <label for="numberingPadding">Digits</label>
                <input id="numberingPadding" name="padding" type="number" min="1" max="10" step="1" required>
                <p class="dash-field-error" data-error-for="padding" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="numberingNext">Next number</label>
                <input id="numberingNext" name="next_number" type="number" min="1" step="1" required>
                <p class="dash-field-hint" data-next-hint></p>
                <p class="dash-field-error" data-error-for="next_number" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="include_year">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Include the year (e.g. INV-2026-000001)</span>
            </label>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="reset_yearly">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Start again from 1 every new year</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Format</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-numbering.js') }}?v={{ filemtime(public_path('js/admin-numbering.js')) }}" defer></script>
@endsection

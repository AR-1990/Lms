@extends('layouts.dashboard')

@section('title', 'Bell Schedule')
@section('page_heading', 'Academics')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Bell Schedule</h1>
        <p>Daily periods and breaks. The timetable is built on these slots.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Period
    </button>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.periods'])

<section class="dash-panel" data-academics="periods" data-datatable-url="{{ route('erp.periods.datatable') }}" data-resource-url="{{ route('erp.periods.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Periods</h2>
            <p>Periods may not overlap each other.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Period</th>
                    <th>Starts</th>
                    <th>Ends</th>
                    <th>Type</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="academicsModal" aria-labelledby="academicsModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'clock'])</span>
            <div>
                <h3 id="academicsModalTitle" data-modal-title>Add period</h3>
                <p>Use 24-hour times.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="periodName">Name</label>
                <input id="periodName" name="name" required maxlength="50" autocomplete="off" placeholder="Period 1">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="periodOrder">Order <span class="dash-optional">optional</span></label>
                <input id="periodOrder" name="sort_order" type="number" min="0" max="32000">
                <p class="dash-field-error" data-error-for="sort_order" hidden></p>
            </div>
            <div class="dash-field">
                <label for="periodStarts">Starts at</label>
                <input id="periodStarts" name="starts_at" type="time" required>
                <p class="dash-field-error" data-error-for="starts_at" hidden></p>
            </div>
            <div class="dash-field">
                <label for="periodEnds">Ends at</label>
                <input id="periodEnds" name="ends_at" type="time" required>
                <p class="dash-field-error" data-error-for="ends_at" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_break">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Break / assembly (no teaching)</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Period</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-academics.js') }}?v={{ filemtime(public_path('js/admin-academics.js')) }}" defer></script>
@endsection

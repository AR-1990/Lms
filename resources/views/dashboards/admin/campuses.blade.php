@extends('layouts.dashboard')

@section('title', 'Campuses')
@section('page_heading', 'Campuses')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Campuses</h1>
        <p>Branches of the school. Students, staff, fees, and stock can later be kept per campus.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Campus
    </button>
</section>

<section
    class="dash-panel"
    data-campuses
    data-datatable-url="{{ route('erp.campuses.datatable') }}"
    data-resource-url="{{ route('erp.campuses.index') }}"
>
    <div class="dash-panel-head">
        <div>
            <h2>All campuses</h2>
            <p>At least one campus always stays active.</p>
        </div>
        <label class="dash-select dash-status-filter">
            <span class="dash-visually-hidden">Status</span>
            <select data-status-filter>
                <option value="">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </label>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Campus</th>
                    <th>City</th>
                    <th>Contact</th>
                    <th>Users</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="campusModal" aria-labelledby="campusModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'building'])</span>
            <div>
                <h3 id="campusModalTitle" data-modal-title>Add campus</h3>
                <p>The code appears on reports and printed documents.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="campusName">Name</label>
                <input id="campusName" name="name" required maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="campusCode">Code</label>
                <input id="campusCode" name="code" required maxlength="20" autocomplete="off" placeholder="MAIN" class="dash-uppercase">
                <p class="dash-field-error" data-error-for="code" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="campusAddress">Address <span class="dash-optional">optional</span></label>
                <textarea id="campusAddress" name="address" rows="2" maxlength="1000"></textarea>
                <p class="dash-field-error" data-error-for="address" hidden></p>
            </div>
            <div class="dash-field">
                <label for="campusCity">City <span class="dash-optional">optional</span></label>
                <input id="campusCity" name="city" maxlength="100" autocomplete="off">
                <p class="dash-field-error" data-error-for="city" hidden></p>
            </div>
            <div class="dash-field">
                <label for="campusPhone">Phone <span class="dash-optional">optional</span></label>
                <input id="campusPhone" name="phone" maxlength="30" autocomplete="off">
                <p class="dash-field-error" data-error-for="phone" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="campusEmail">Email <span class="dash-optional">optional</span></label>
                <input id="campusEmail" name="email" type="email" maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="email" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
            <p class="dash-field-error dash-field-wide" data-error-for="is_active" hidden></p>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Campus</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-campuses.js') }}?v={{ filemtime(public_path('js/admin-campuses.js')) }}" defer></script>
@endsection

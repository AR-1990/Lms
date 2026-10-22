@extends('layouts.dashboard')

@section('title', 'Academic Sessions')
@section('page_heading', 'Academics')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Sessions</h1>
        <p>School years. Exactly one session is current; sessions may not overlap.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Session
    </button>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.sessions'])

<section class="dash-panel" data-academics="sessions" data-datatable-url="{{ route('erp.academic-sessions.datatable') }}" data-resource-url="{{ route('erp.academic-sessions.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All sessions</h2>
            <p>The current session cannot be deleted. Start a new year by adding it and marking it current.</p>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Starts</th>
                    <th>Ends</th>
                    <th class="dash-hide-md">Sections</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="academicsModal" aria-labelledby="academicsModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'calendar'])</span>
            <div>
                <h3 id="academicsModalTitle" data-modal-title>Add session</h3>
                <p>For example 2027-28, from August to July.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="sessionName">Name</label>
                <input id="sessionName" name="name" required maxlength="30" autocomplete="off" placeholder="2027-28">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="sessionStarts">Starts on</label>
                <input id="sessionStarts" name="starts_on" type="date" required>
                <p class="dash-field-error" data-error-for="starts_on" hidden></p>
            </div>
            <div class="dash-field">
                <label for="sessionEnds">Ends on</label>
                <input id="sessionEnds" name="ends_on" type="date" required>
                <p class="dash-field-error" data-error-for="ends_on" hidden></p>
            </div>
            <label class="dash-switch">
                <input type="checkbox" name="is_current">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Current session</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="admissions_open">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Admissions open</span>
            </label>
            <p class="dash-field-error dash-field-wide" data-error-for="is_current" hidden></p>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Session</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-academics.js') }}?v={{ filemtime(public_path('js/admin-academics.js')) }}" defer></script>
@endsection

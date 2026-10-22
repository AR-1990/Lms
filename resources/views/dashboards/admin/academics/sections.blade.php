@extends('layouts.dashboard')

@section('title', 'Sections')
@section('page_heading', 'Academics')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Sections</h1>
        <p>Sections belong to one class in one session, so every year starts with a fresh set.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Section
    </button>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.sections'])

<section class="dash-panel" data-academics="sections" data-datatable-url="{{ route('erp.sections.datatable') }}" data-resource-url="{{ route('erp.sections.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Sections</h2>
            <p>Pick a session and class to narrow the list.</p>
        </div>
        @include('dashboards.admin.academics.filters')
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Section</th>
                    <th>Room</th>
                    <th>Capacity</th>
                    <th class="dash-hide-md">Session</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="academicsModal" aria-labelledby="academicsModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'grid'])</span>
            <div>
                <h3 id="academicsModalTitle" data-modal-title>Add section</h3>
                <p>Section names repeat across classes (A, B, Blue…).</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            @include('dashboards.admin.academics.session-class-fields')
            <div class="dash-field">
                <label for="sectionName">Section name</label>
                <input id="sectionName" name="name" required maxlength="20" autocomplete="off" placeholder="A">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="sectionRoom">Room <span class="dash-optional">optional</span></label>
                <input id="sectionRoom" name="room" maxlength="50" autocomplete="off">
                <p class="dash-field-error" data-error-for="room" hidden></p>
            </div>
            <div class="dash-field">
                <label for="sectionCapacity">Capacity <span class="dash-optional">optional</span></label>
                <input id="sectionCapacity" name="capacity" type="number" min="1" max="500">
                <p class="dash-field-error" data-error-for="capacity" hidden></p>
            </div>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Section</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-academics.js') }}?v={{ filemtime(public_path('js/admin-academics.js')) }}" defer></script>
@endsection

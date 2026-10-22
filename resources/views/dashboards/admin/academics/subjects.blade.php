@extends('layouts.dashboard')

@section('title', 'Subjects')
@section('page_heading', 'Academics')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Subjects</h1>
        <p>The school's subject list. Add them to classes on the Curriculum tab.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Add Subject
    </button>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.subjects'])

<section class="dash-panel" data-academics="subjects" data-datatable-url="{{ route('erp.subjects.datatable') }}" data-resource-url="{{ route('erp.subjects.index') }}">
    <div class="dash-panel-head">
        <div>
            <h2>All subjects</h2>
            <p>A subject used in any curriculum can be made inactive but not deleted.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="is_active">
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th class="dash-hide-md">Type</th>
                    <th>Practical</th>
                    <th>Classes</th>
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
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'book'])</span>
            <div>
                <h3 id="academicsModalTitle" data-modal-title>Add subject</h3>
                <p>Subject types come from the Lookups screen.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="subjectName">Name</label>
                <input id="subjectName" name="name" required maxlength="100" autocomplete="off" placeholder="Mathematics">
                <p class="dash-field-error" data-error-for="name" hidden></p>
            </div>
            <div class="dash-field">
                <label for="subjectCode">Code <span class="dash-optional">optional</span></label>
                <input id="subjectCode" name="code" maxlength="20" autocomplete="off" class="dash-uppercase" placeholder="MATH">
                <p class="dash-field-error" data-error-for="code" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="subjectType">Type <span class="dash-optional">optional</span></label>
                <select id="subjectType" name="subject_type_id">
                    <option value="">Not specified</option>
                    @foreach ($data['subject_types'] as $subjectType)
                        <option value="{{ $subjectType->id }}">{{ $subjectType->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="subject_type_id" hidden></p>
            </div>
            <label class="dash-switch">
                <input type="checkbox" name="has_practical">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Has practical</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Active</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save Subject</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-academics.js') }}?v={{ filemtime(public_path('js/admin-academics.js')) }}" defer></script>
@endsection

@extends('layouts.dashboard')

@section('title', 'Homework')
@section('page_heading', 'Homework')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Homework</h1>
        <p>Set homework, see who handed it in, and grade it — paper work can be graded too.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        Set Homework
    </button>
</section>

<section class="dash-panel" data-homework data-datatable-url="{{ route('erp.homework.datatable') }}" data-resource-url="{{ url('/api/erp/homework') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Section</span>
                <select data-filter="section_id">
                    <option value="">All my sections</option>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Homework</th>
                    <th class="dash-hide-md">Class</th>
                    <th>Due</th>
                    <th class="dash-col-num">Handed in</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="homeworkModal" aria-labelledby="homeworkModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'pen'])</span>
            <div>
                <h3 id="homeworkModalTitle" data-modal-title>Set homework</h3>
                <p>Only subjects in the class curriculum are listed.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="hwSection">Section</label>
                <select id="hwSection" name="section_id">
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="section_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="hwSubject">Subject</label>
                <select id="hwSubject" name="subject_id">
                    @foreach ($data['curriculum'] as $subject)
                        <option value="{{ $subject->id }}" data-class-id="{{ $subject->class_id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="subject_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'wide' => true, 'id' => 'hwTitle'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Instructions', 'type' => 'textarea', 'wide' => true, 'id' => 'hwDescription'])
            @include('dashboards.partials.field', ['name' => 'assigned_on', 'label' => 'Set on', 'type' => 'date', 'required' => true, 'id' => 'hwAssigned'])
            @include('dashboards.partials.field', ['name' => 'due_on', 'label' => 'Due on', 'type' => 'date', 'id' => 'hwDue'])
            @include('dashboards.partials.field', ['name' => 'max_marks', 'label' => 'Marks out of', 'type' => 'number', 'min' => 1, 'id' => 'hwMarks'])
            <label class="dash-switch">
                <input type="checkbox" name="allow_submission">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Submit online</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="submissionsModal" aria-labelledby="submissionsTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
        <div>
            <h3 id="submissionsTitle" data-submissions-title>Submissions</h3>
            <p data-submissions-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-mini-table">
            <thead><tr><th>Student</th><th>Status</th><th>Marks</th><th class="dash-hide-md">Feedback</th><th></th></tr></thead>
            <tbody data-submission-rows></tbody>
        </table>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-diary.js') }}?v={{ filemtime(public_path('js/admin-diary.js')) }}" defer></script>
@endsection

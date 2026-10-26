@extends('layouts.dashboard')

@section('title', 'Online Tests')
@section('page_heading', 'Exams')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Online Tests</h1>
        <p>Make an MCQ test for your class: write the questions, give the options and tick the right answer. Students take it online and are marked as soon as they submit. Link it to an exam paper and the scores go straight into marks entry.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) New Test</button>
</section>

@include('dashboards.exams.tabs', ['current' => 'admin.exams.online-tests'])

<section class="dash-panel" data-online-tests data-import-url="{{ route('erp.imports.store', 'online_test_questions') }}" data-template-url="{{ route('erp.imports.template', 'online_test_questions') }}" data-datatable-url="{{ route('erp.online-tests.datatable') }}" data-resource-url="{{ url('/api/erp/online-tests') }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Class</th>
                    <th class="dash-hide-md">Subject</th>
                    <th class="dash-col-num">Questions</th>
                    <th class="dash-col-num">Submitted</th>
                    <th class="dash-hide-md">Closes</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="onlineTestModal" aria-labelledby="onlineTestModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'pen'])</span>
            <div>
                <h3 id="onlineTestModalTitle" data-modal-title>New online test</h3>
                <p>Saved as a draft; publish it when the questions are ready.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-scroll">
            <div class="dash-form-grid">
                @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'wide' => true, 'placeholder' => 'Chapter 3 quiz'])
                <div class="dash-field">
                    <label for="ot_section">Class</label>
                    <select id="ot_section" name="section_id" required data-ot-section>
                        <option value="">Choose…</option>
                        @foreach ($data['sections'] as $section)
                            <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}">{{ $section->class_name }} {{ $section->name }}</option>
                        @endforeach
                    </select>
                    <p class="dash-field-error" data-error-for="section_id" hidden></p>
                </div>
                <div class="dash-field">
                    <label for="ot_subject">Subject</label>
                    <select id="ot_subject" name="subject_id" required data-ot-subject>
                        <option value="">Choose a class first</option>
                        @foreach ($data['subjects'] as $subject)
                            <option value="{{ $subject['id'] }}" data-section-id="{{ $subject['section_id'] }}" hidden>{{ $subject['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="dash-field-error" data-error-for="subject_id" hidden></p>
                </div>
                <div class="dash-field dash-field-wide">
                    <label for="ot_paper">Exam paper <span class="dash-optional">optional</span></label>
                    <select id="ot_paper" name="exam_subject_id" data-ot-paper>
                        <option value="">Not linked — a class test only</option>
                        @foreach ($data['papers'] as $paper)
                            <option value="{{ $paper->id }}" data-class-id="{{ $paper->class_id }}" data-subject-id="{{ $paper->subject_id }}" hidden>{{ $paper->exam_name }} (scaled to {{ $paper->theory_max_marks }} marks)</option>
                        @endforeach
                    </select>
                    <p class="dash-field-hint">When linked, each student's score is written into that paper's marks for the exam cell to approve.</p>
                    <p class="dash-field-error" data-error-for="exam_subject_id" hidden></p>
                </div>
                @include('dashboards.partials.field', ['name' => 'opens_at', 'label' => 'Opens', 'type' => 'datetime-local'])
                @include('dashboards.partials.field', ['name' => 'closes_at', 'label' => 'Closes', 'type' => 'datetime-local'])
                @include('dashboards.partials.field', ['name' => 'duration_minutes', 'label' => 'Time allowed (minutes)', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 300])
                <div class="dash-field dash-ot-switches">
                    <label class="dash-switch">
                        <input type="checkbox" name="shuffle_questions" checked>
                        <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                        <span>Shuffle questions</span>
                    </label>
                    <label class="dash-switch">
                        <input type="checkbox" name="show_answers">
                        <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                        <span>Show right answers after submitting</span>
                    </label>
                </div>
                <div class="dash-field dash-field-wide">
                    <label for="ot_instructions">Instructions <span class="dash-optional">optional</span></label>
                    <textarea id="ot_instructions" name="instructions" rows="2" maxlength="2000"></textarea>
                </div>
            </div>

            <div class="dash-ot-questions">
                <div class="dash-ot-questions-head">
                    <h4>Questions <span class="dash-muted" data-ot-total></span></h4>
                    <div class="dash-ot-question-tools" data-ot-tools>
                        <label class="dash-select">
                            <span class="dash-visually-hidden">Copy questions from a test</span>
                            <select data-ot-copy><option value="">Copy from a test…</option></select>
                        </label>
                        <label class="dash-btn dash-btn-ghost dash-ot-upload">
                            @include('partials.dashboard-icon', ['name' => 'upload']) CSV
                            <input type="file" accept=".csv,text/csv" data-ot-csv hidden>
                        </label>
                        <a class="dash-link-btn" data-ot-template>Template</a>
                        <button type="button" class="dash-btn dash-btn-ghost" data-ot-add-question>@include('partials.dashboard-icon', ['name' => 'plus']) Question</button>
                    </div>
                </div>
                <p class="dash-muted" data-ot-locked hidden>Students have already attempted this test, so its questions are locked.</p>
                <ol class="dash-ot-list" data-ot-list></ol>
                <p class="dash-field-error" data-error-for="questions" hidden></p>
            </div>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<template id="otQuestionTemplate">
    <li class="dash-ot-question">
        <div class="dash-ot-question-head">
            <span class="dash-ot-number" data-ot-number></span>
            <textarea rows="2" maxlength="2000" placeholder="Type the question" aria-label="Question" data-ot-question></textarea>
            <label class="dash-ot-marks">Marks <input type="number" min="1" max="100" value="1" data-ot-marks></label>
            <button type="button" class="dash-icon-btn" data-ot-remove-question aria-label="Remove question">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <ul class="dash-ot-options" data-ot-options></ul>
        <button type="button" class="dash-link-btn" data-ot-add-option>+ Option</button>
    </li>
</template>

<template id="otOptionTemplate">
    <li class="dash-ot-option">
        <label class="dash-ot-correct" data-tooltip="Right answer"><input type="radio" data-ot-correct aria-label="Right answer"></label>
        <input type="text" maxlength="500" placeholder="Option" aria-label="Option" data-ot-option>
        <button type="button" class="dash-icon-btn" data-ot-remove-option aria-label="Remove option">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </li>
</template>

<dialog class="dash-modal dash-modal-wide" id="onlineTestResults" aria-labelledby="onlineTestResultsTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'chart'])</span>
        <div>
            <h3 id="onlineTestResultsTitle" data-results-title>Results</h3>
            <p data-results-summary></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body">
        <div class="dash-grid-wrap">
            <table class="dash-table">
                <thead><tr><th>Roll</th><th>Student</th><th class="dash-col-num">Score</th><th>Status</th></tr></thead>
                <tbody data-results-rows></tbody>
            </table>
        </div>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/online-tests.js') }}?v={{ filemtime(public_path('js/online-tests.js')) }}" defer></script>
@endsection

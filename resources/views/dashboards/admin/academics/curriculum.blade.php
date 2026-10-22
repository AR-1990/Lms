@extends('layouts.dashboard')

@section('title', 'Curriculum')
@section('page_heading', 'Academics')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Curriculum</h1>
        <p>Which subjects each class studies this session, with default marks for exams.</p>
    </div>
    <div class="dash-head-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-copy-open>
            @include('partials.dashboard-icon', ['name' => 'layers'])
            Copy from session
        </button>
        <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
            @include('partials.dashboard-icon', ['name' => 'plus'])
            Add Subject to Class
        </button>
    </div>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.curriculum'])

<section class="dash-panel" data-academics="curriculum" data-datatable-url="{{ route('erp.curriculum.datatable') }}" data-resource-url="{{ route('erp.curriculum.index') }}" data-copy-url="{{ route('erp.curriculum.copy') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Class subjects</h2>
            <p>Order controls the report card sequence.</p>
        </div>
        @include('dashboards.admin.academics.filters')
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Subject</th>
                    <th class="dash-hide-md">Periods / week</th>
                    <th>Marks</th>
                    <th class="dash-hide-md">Pass</th>
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
                <h3 id="academicsModalTitle" data-modal-title>Add subject to class</h3>
                <p>Marks here are defaults; each exam can still change them.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            @include('dashboards.admin.academics.session-class-fields')
            <div class="dash-field">
                <label for="curriculumSubject">Subject</label>
                <select id="curriculumSubject" name="subject_id" required>
                    @foreach ($data['subjects'] as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="subject_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="curriculumPeriods">Periods per week <span class="dash-optional">optional</span></label>
                <input id="curriculumPeriods" name="periods_per_week" type="number" min="0" max="60">
                <p class="dash-field-error" data-error-for="periods_per_week" hidden></p>
            </div>
            <div class="dash-field">
                <label for="curriculumTheory">Theory marks <span class="dash-optional">optional</span></label>
                <input id="curriculumTheory" name="theory_max_marks" type="number" min="0" max="1000">
                <p class="dash-field-error" data-error-for="theory_max_marks" hidden></p>
            </div>
            <div class="dash-field">
                <label for="curriculumPractical">Practical marks <span class="dash-optional">optional</span></label>
                <input id="curriculumPractical" name="practical_max_marks" type="number" min="0" max="1000">
                <p class="dash-field-error" data-error-for="practical_max_marks" hidden></p>
            </div>
            <div class="dash-field">
                <label for="curriculumPass">Pass marks <span class="dash-optional">optional</span></label>
                <input id="curriculumPass" name="pass_marks" type="number" min="0" max="2000">
                <p class="dash-field-error" data-error-for="pass_marks" hidden></p>
            </div>
            <div class="dash-field">
                <label for="curriculumOrder">Report order <span class="dash-optional">optional</span></label>
                <input id="curriculumOrder" name="sort_order" type="number" min="0" max="32000">
                <p class="dash-field-error" data-error-for="sort_order" hidden></p>
            </div>
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="is_optional">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Optional / elective for this class</span>
            </label>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="copyModal" aria-labelledby="copyModalTitle">
    <form data-copy-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="copyModalTitle">Copy curriculum</h3>
                <p>Subjects the target session already has are skipped.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="copyFrom">From session</label>
                <select id="copyFrom" name="from_session_id" required>
                    @foreach ($data['sessions'] as $session)
                        <option value="{{ $session->id }}">{{ $session->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="from_session_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="copyTo">Into session</label>
                <select id="copyTo" name="to_session_id" required>
                    @foreach ($data['sessions'] as $session)
                        <option value="{{ $session->id }}">{{ $session->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="to_session_id" hidden></p>
            </div>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Copy</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-academics.js') }}?v={{ filemtime(public_path('js/admin-academics.js')) }}" defer></script>
@endsection

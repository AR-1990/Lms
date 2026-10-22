@extends('layouts.dashboard')

@section('title', 'Daily Diary')
@section('page_heading', 'Diary')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Daily Diary</h1>
        <p>Each teacher adds what was taught and the homework; the page goes to parents when published, and they sign it as seen.</p>
    </div>
    <div class="dash-head-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-open-remark>@include('partials.dashboard-icon', ['name' => 'user-plus']) Student Remark</button>
        <button type="button" class="dash-btn dash-btn-primary" data-open-entry>@include('partials.dashboard-icon', ['name' => 'plus']) Add Entry</button>
    </div>
</section>

<section class="dash-panel" data-diary data-page-url="{{ route('erp.diary.page') }}" data-entries-url="{{ url('/api/erp/diary/entries') }}" data-note-url="{{ route('erp.diary.note') }}" data-diaries-url="{{ url('/api/erp/diaries') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Section</span>
                <select data-section>
                    @forelse ($data['sections'] as $section)
                        <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @empty
                        <option value="">No sections assigned to you</option>
                    @endforelse
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Date</span>
                <input type="date" data-date value="{{ now()->toDateString() }}" aria-label="Date">
            </label>
        </div>
        <div class="dash-head-actions">
            <span data-diary-status></span>
            <button type="button" class="dash-btn dash-btn-primary dash-btn-sm" data-publish>@include('partials.dashboard-icon', ['name' => 'check']) Publish to Parents</button>
        </div>
    </div>

    <article class="dash-diary-page">
        <header class="dash-diary-head">
            <span data-diary-date></span>
        </header>
        <div class="dash-field">
            <label for="diaryNote">Class teacher's note</label>
            <textarea id="diaryNote" rows="2" maxlength="3000" placeholder="Bring PE kit tomorrow, PTM on Saturday…" data-note></textarea>
        </div>
        <div class="dash-inline-actions"><button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-save-note>Save note</button></div>
        <ol class="dash-diary-entries" data-entries></ol>
        <section data-remarks-wrap hidden>
            <h4 class="dash-detail-heading">Remarks for individual students</h4>
            <ul class="dash-kv" data-remarks></ul>
        </section>
        <section data-unsigned-wrap hidden>
            <h4 class="dash-detail-heading">Not signed by parents yet</h4>
            <p class="dash-muted" data-unsigned></p>
        </section>
    </article>
</section>

<dialog class="dash-modal dash-form-modal" id="entryModal" aria-labelledby="entryTitle">
    <form data-entry-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'book'])</span>
            <div>
                <h3 id="entryTitle">Diary entry</h3>
                <p>Tick "gradable homework" to also create a homework students can hand in.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @include('dashboards.partials.select', ['name' => 'entry_type_id', 'label' => 'Type', 'options' => $data['entry_types'], 'required' => true, 'id' => 'entryType'])
            <div class="dash-field">
                <label for="entrySubject">Subject <span class="dash-optional">optional</span></label>
                <select id="entrySubject" name="subject_id">
                    <option value="">General</option>
                    @foreach ($data['curriculum'] as $subject)
                        <option value="{{ $subject->id }}" data-class-id="{{ $subject->class_id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="subject_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Title', 'wide' => true, 'id' => 'entryTitleField'])
            @include('dashboards.partials.field', ['name' => 'details', 'label' => 'Details', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'entryDetails'])
            @include('dashboards.partials.field', ['name' => 'due_on', 'label' => 'Due on', 'type' => 'date', 'id' => 'entryDue'])
            @include('dashboards.partials.field', ['name' => 'max_marks', 'label' => 'Marks out of', 'type' => 'number', 'min' => 1, 'id' => 'entryMarks'])
            <label class="dash-switch">
                <input type="checkbox" name="create_homework">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Gradable homework</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="allow_submission">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Submit online</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Add</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal" id="remarkModal" aria-labelledby="remarkTitle">
    <form data-remark-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'user-plus'])</span>
            <div>
                <h3 id="remarkTitle">Remark for one student</h3>
                <p>Only that student's parents see it.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="remarkStudent">Student</label>
                <select id="remarkStudent" name="student_id" data-remark-students></select>
                <p class="dash-field-error" data-error-for="student_id" hidden></p>
            </div>
            @include('dashboards.partials.select', ['name' => 'remark_type_id', 'label' => 'Type', 'options' => $data['remark_types'], 'id' => 'remarkType'])
            @include('dashboards.partials.field', ['name' => 'remark', 'label' => 'Remark', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'remarkText'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Add Remark</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-diary.js') }}?v={{ filemtime(public_path('js/admin-diary.js')) }}" defer></script>
@endsection

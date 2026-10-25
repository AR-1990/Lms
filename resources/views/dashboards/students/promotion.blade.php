@extends('layouts.dashboard')

@section('title', 'Year-end Promotion')
@section('page_heading', 'Students')

@section('content')
@php
    $canSetUp = auth()->user()->hasPermission('manage-academics');
@endphp
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Year-end Promotion</h1>
        <p>Move every class into next year's session in a few steps. Pass results are promoted, failed results are kept in their class, and the last class graduates. You can change any student's move before saving.</p>
    </div>
</section>

@include('dashboards.students.tabs', ['current' => 'admin.students.promotion'])

<div data-promotion
     data-summary-url="{{ route('erp.promotions.index') }}"
     data-students-url="{{ route('erp.promotions.students') }}"
     data-prepare-url="{{ route('erp.promotions.prepare') }}"
     data-promote-url="{{ route('erp.promotions.store') }}"
     data-undo-url="{{ route('erp.promotions.undo') }}"
     data-roll-url="{{ route('erp.promotions.roll-numbers') }}"
     data-sessions-url="{{ route('erp.academic-sessions.index') }}"
     data-profile-url="{{ url('/admin/students') }}"
     data-current-session-id="{{ $data['current_session_id'] }}">

    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2><span class="dash-promo-step">1</span> Choose the years</h2>
                <p>Students move from the first session into the second. Fee balances stay on their old challans.</p>
            </div>
        </div>
        <div class="dash-form-grid dash-promo-years">
            <div class="dash-field">
                <label for="promoFrom">From session</label>
                <select id="promoFrom" data-from>
                    @foreach ($data['sessions'] as $session)
                        <option value="{{ $session->id }}" @selected($session->is_current)>{{ $session->name }}{{ $session->is_current ? ' (current)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dash-field">
                <label for="promoTo">Into session</label>
                <select id="promoTo" data-to>
                    @foreach ($data['sessions'] as $session)
                        <option value="{{ $session->id }}">{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <p class="dash-empty-note" data-no-next hidden>
            Next year's session does not exist yet.
            @if ($canSetUp)
                <a href="{{ route('admin.academics.sessions') }}">Add it under Academics → Sessions</a>, then come back here.
            @else
                Ask the academics office to add it.
            @endif
        </p>
        <div class="dash-promo-prepare" data-prepare-box hidden>
            <div>
                <strong data-prepare-title>Get the new session ready</strong>
                <span class="dash-muted" data-prepare-text>Copy this year's sections, subjects and fee structures into the new session. Anything already there is kept.</span>
            </div>
            @if ($canSetUp)
                <button type="button" class="dash-btn dash-btn-ghost" data-prepare>@include('partials.dashboard-icon', ['name' => 'layers']) Copy sections &amp; subjects</button>
            @endif
        </div>
    </section>

    <section class="dash-panel" data-classes-panel hidden>
        <div class="dash-panel-head">
            <div>
                <h2><span class="dash-promo-step">2</span> Move each class</h2>
                <p>Open a class, check each student's move, and save. Start from the top class so sections fill in order.</p>
            </div>
            <span class="dash-badge dash-badge-brand" data-overall>…</span>
        </div>
        <ul class="dash-list dash-promo-classes" data-class-list></ul>
    </section>

    <section class="dash-panel" data-students-panel hidden>
        <div class="dash-panel-head">
            <div>
                <h2 data-class-title>Students</h2>
                <p data-class-hint>Choose what happens to each student.</p>
            </div>
            <button type="button" class="dash-btn dash-btn-ghost" data-close-class>@include('partials.dashboard-icon', ['name' => 'close']) Close</button>
        </div>
        <div class="dash-promo-toolbar">
            <label class="dash-check"><input type="checkbox" data-select-all checked> <span>Select all</span></label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Section for promoted students</span>
                <select data-bulk-section></select>
            </label>
            <button type="button" class="dash-link-btn" data-reset-suggested>Use suggested moves</button>
        </div>
        <div class="dash-promo-toolbar dash-promo-carry">
            <label class="dash-check"><input type="checkbox" data-carry-concessions checked> <span>Give this year's concessions again for the new year</span></label>
            <label class="dash-check"><input type="checkbox" data-carry-transport checked> <span>Keep their school transport</span></label>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table dash-promo-table">
                <thead><tr><th class="dash-col-check"><span class="dash-visually-hidden">Move</span></th><th>Student</th><th>Final result</th><th>Move</th><th>New section</th></tr></thead>
                <tbody data-student-rows></tbody>
            </table>
        </div>
        <p class="dash-form-error" data-move-error hidden></p>
        <div class="dash-promo-footer">
            <span class="dash-muted" data-move-count></span>
            <button type="button" class="dash-btn dash-btn-primary" data-move>@include('partials.dashboard-icon', ['name' => 'check']) Save moves</button>
        </div>
    </section>

    @if ($canSetUp)
        <section class="dash-panel" data-finish-panel hidden>
            <div class="dash-panel-head">
                <div>
                    <h2><span class="dash-promo-step">3</span> Start the new year</h2>
                    <p data-finish-text>When every class is done, make the new session current. Attendance, timetables, exams and fee challans then use it.</p>
                </div>
                <button type="button" class="dash-btn dash-btn-primary" data-make-current>@include('partials.dashboard-icon', ['name' => 'calendar']) Make it current</button>
            </div>
        </section>
    @endif
</div>

<dialog class="dash-modal dash-form-modal" id="rollNumbersModal" aria-labelledby="rollNumbersTitle">
    <form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'hash'])</span>
            <div>
                <h3 id="rollNumbersTitle" data-roll-title>Roll numbers</h3>
                <p>Every section of the class is numbered 1, 2, 3… Students without a section are left out.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field dash-field-wide">
                <label for="rollOrder">Number them by</label>
                <select id="rollOrder" name="order">
                    <option value="name">Name (A to Z)</option>
                    <option value="admission_no">Admission number</option>
                </select>
            </div>
            <p class="dash-field-error dash-field-wide" data-error-for="class_id" hidden></p>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Give roll numbers</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal" id="promotionConfirm" aria-labelledby="promotionConfirmTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
        <div>
            <h3 id="promotionConfirmTitle" data-confirm-title>Save these moves?</h3>
            <p data-confirm-text></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-modal-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
        <button type="button" class="dash-btn dash-btn-primary" data-confirm-yes>@include('partials.dashboard-icon', ['name' => 'check']) Yes, save</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-promotion.js') }}?v={{ filemtime(public_path('js/admin-promotion.js')) }}" defer></script>
@endsection

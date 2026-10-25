@extends('layouts.dashboard')

@section('title', 'Online Tests')
@section('page_heading', 'Online Tests')

@section('content')
<section class="dash-page-intro">
    <h1>Online Tests</h1>
    <p>Tests your teachers have set for your class. Each test can be taken once; the timer starts when you press Start.</p>
</section>

<section data-student-tests data-url="{{ url('/api/erp') }}">
    <div class="dash-ot-cards" data-test-list>
        <p class="dash-muted">Loading tests…</p>
    </div>

    <section class="dash-panel dash-ot-take" data-test-take hidden>
        <div class="dash-ot-take-head">
            <div>
                <h2 data-take-title></h2>
                <p class="dash-muted" data-take-instructions></p>
            </div>
            <div class="dash-ot-timer" data-take-timer aria-live="polite">--:--</div>
        </div>
        <div class="dash-ot-progress" aria-hidden="true"><span data-take-progress></span></div>
        <ol class="dash-ot-paper" data-take-questions></ol>
        <div class="dash-modal-actions">
            <span class="dash-muted" data-take-answered></span>
            <button type="button" class="dash-btn dash-btn-primary" data-take-submit>@include('partials.dashboard-icon', ['name' => 'check']) Submit test</button>
        </div>
    </section>

    <section class="dash-panel dash-ot-result" data-test-result hidden>
        <div class="dash-ot-score">
            <strong data-result-score></strong>
            <span data-result-text></span>
        </div>
        <ol class="dash-ot-paper dash-ot-review" data-result-review></ol>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-result-back>Back to tests</button>
        </div>
    </section>
</section>

<dialog class="dash-modal" id="testSubmitConfirm" aria-labelledby="testSubmitTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
        <div>
            <h3 id="testSubmitTitle">Submit this test?</h3>
            <p data-confirm-text>You cannot change answers after submitting.</p>
        </div>
    </div>
    <div class="dash-modal-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-confirm-cancel>Keep working</button>
        <button type="button" class="dash-btn dash-btn-primary" data-confirm-ok>Submit</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/student-online-tests.js') }}?v={{ filemtime(public_path('js/student-online-tests.js')) }}" defer></script>
@endsection

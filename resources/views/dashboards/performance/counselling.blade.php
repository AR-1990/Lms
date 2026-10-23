@extends('layouts.dashboard')

@section('title', 'Counselling')
@section('page_heading', 'Performance')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Counselling</h1>
        <p>Sessions with students, what was agreed and when to meet again. Confidential notes are never shown to the student or parents; the rest appear on their dashboard.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>@include('partials.dashboard-icon', ['name' => 'plus']) New Session</button>
</section>

@include('dashboards.performance.tabs', ['current' => 'admin.performance.counselling'])

<section class="dash-panel" data-counselling data-datatable-url="{{ route('erp.counselling-notes.datatable') }}" data-resource-url="{{ url('/api/erp/counselling-notes') }}" data-students-url="{{ route('erp.counselling-students') }}"
    @if ($data['prefill']) data-prefill-student="{{ $data['prefill']['id'] }}" data-prefill-name="{{ $data['prefill']['name'] }}" data-prefill-alert="{{ $data['prefill']['alert'] }}" @endif>
    <div class="dash-panel-head">
        <div>
            <h2>Sessions</h2>
            <p>Follow-ups due today are sent to the counsellor every morning.</p>
        </div>
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Follow-up</span>
                <select data-filter="follow_up">
                    <option value="">All sessions</option>
                    <option value="due">Follow-up due</option>
                    <option value="upcoming">Follow-up coming up</option>
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Concern</span>
                <select data-filter="concern_id">
                    <option value="">Any concern</option>
                    @foreach ($data['concerns'] as $concern)<option value="{{ $concern->id }}">{{ $concern->name }}</option>@endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Date</th>
                    <th>Concern</th>
                    <th class="dash-hide-md">Notes</th>
                    <th>Counsellor</th>
                    <th>Follow-up</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="counsellingModal" aria-labelledby="counsellingModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
            <div>
                <h3 id="counsellingModalTitle" data-modal-title>New session</h3>
                <p data-alert-note>Notes are confidential unless you switch it off.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            <div class="dash-field dash-field-wide">
                <label for="counsellingStudent">Student</label>
                <input type="search" id="counsellingStudent" data-student-search placeholder="Type a name or admission no…" autocomplete="off" required>
                <input type="hidden" name="student_id">
                <input type="hidden" name="performance_alert_id">
                <ul class="dash-holder-results" data-student-results></ul>
                <p class="dash-field-error" data-error-for="student_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'session_date', 'label' => 'Session date', 'type' => 'date', 'required' => true, 'id' => 'counsellingDate'])
            <div class="dash-field">
                <label for="counsellingCounsellor">Counsellor</label>
                <select id="counsellingCounsellor" name="counsellor_id">
                    @foreach ($data['counsellors'] as $counsellor)<option value="{{ $counsellor['id'] }}">{{ $counsellor['name'] }}</option>@endforeach
                </select>
                <p class="dash-field-error" data-error-for="counsellor_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="counsellingConcern">Concern</label>
                <select id="counsellingConcern" name="concern_id">
                    <option value="">—</option>
                    @foreach ($data['concerns'] as $concern)<option value="{{ $concern->id }}">{{ $concern->name }}</option>@endforeach
                </select>
                <p class="dash-field-error" data-error-for="concern_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'follow_up_on', 'label' => 'Meet again on', 'type' => 'date', 'id' => 'counsellingFollowUp'])
            @include('dashboards.partials.field', ['name' => 'notes', 'label' => 'What was discussed', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'counsellingNotes'])
            @include('dashboards.partials.field', ['name' => 'action_plan', 'label' => 'Action plan', 'type' => 'textarea', 'wide' => true, 'id' => 'counsellingPlan', 'placeholder' => 'What the student, teachers and parents will do'])
            <label class="dash-switch">
                <input type="checkbox" name="parents_met">
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Parents were met</span>
            </label>
            <label class="dash-switch">
                <input type="checkbox" name="is_confidential" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Confidential (hide from student and parents)</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-modal-wide" id="counsellingView" aria-labelledby="counsellingViewTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'users'])</span>
        <div><h3 id="counsellingViewTitle" data-view-title>Session</h3><p data-view-sub></p></div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll" data-view-body></div>
    <div class="dash-modal-actions" data-view-actions></div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-counselling.js') }}?v={{ filemtime(public_path('js/admin-counselling.js')) }}" defer></script>
@endsection

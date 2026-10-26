@extends('layouts.dashboard')

@section('title', 'Exams')
@section('page_heading', 'Exams')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Exams</h1>
        <p>Draft → scheduled (date sheet) → admit cards → marks entry → review → published. Open an exam to run each step.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        New Exam
    </button>
</section>

@include('dashboards.exams.tabs', ['current' => 'admin.exams.index'])

<section class="dash-panel" data-exams data-datatable-url="{{ route('erp.exams.datatable') }}" data-resource-url="{{ url('/api/erp/exams') }}" data-api-url="{{ url('/api/erp') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Term</span>
                <select data-filter="exam_term_id">
                    <option value="">All terms</option>
                    @foreach ($data['terms'] as $term)
                        <option value="{{ $term->id }}">{{ $term->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Status</span>
                <select data-filter="status">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Exam::STATUSES as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    <th>Exam</th>
                    <th class="dash-hide-md">Papers</th>
                    <th>Status</th>
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="examModal" aria-labelledby="examModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'sparkles'])</span>
            <div>
                <h3 id="examModalTitle" data-modal-title>New exam</h3>
                <p>One exam per class per term. The exam fee is optional.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.select', ['name' => 'exam_term_id', 'label' => 'Term', 'options' => $data['terms'], 'required' => true])
            @include('dashboards.partials.select', ['name' => 'class_id', 'label' => 'Class', 'options' => $data['classes'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true, 'placeholder' => 'Mid Term — Grade 5'])
            @include('dashboards.partials.select', ['name' => 'exam_type_id', 'label' => 'Type', 'options' => $data['exam_types']])
            @include('dashboards.partials.select', ['name' => 'grading_scale_id', 'label' => 'Grading scale', 'options' => $data['scales'], 'empty' => 'Default scale'])
            @include('dashboards.partials.field', ['name' => 'marks_entry_deadline', 'label' => 'Marks due by', 'type' => 'date'])
            @include('dashboards.partials.select', ['name' => 'fee_head_id', 'label' => 'Exam fee head', 'options' => $data['fee_heads'], 'empty' => 'No exam fee'])
            @include('dashboards.partials.field', ['name' => 'exam_fee_amount', 'label' => 'Exam fee (Rs)', 'type' => 'number', 'step' => '0.01', 'min' => 0])
            @include('dashboards.partials.field', ['name' => 'fee_due_date', 'label' => 'Fee due on', 'type' => 'date'])
            @include('dashboards.partials.field', ['name' => 'min_attendance_percent', 'label' => 'Minimum attendance %', 'type' => 'number', 'min' => 0, 'max' => 100])
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="require_fee_clearance" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Admit card only when fees are cleared</span>
            </label>
            @include('dashboards.partials.field', ['name' => 'instructions', 'label' => 'Instructions (printed on admit cards)', 'type' => 'textarea', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="examDetail" aria-labelledby="examDetailTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'sparkles'])</span>
        <div>
            <h3 id="examDetailTitle" data-detail-title>Exam</h3>
            <p data-detail-subtitle></p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>

    <div class="dash-step-buttons" data-step-buttons></div>

    <section class="dash-inline-form">
        <h4>Date sheet</h4>
        <div class="dash-line-items">
            <div data-papers></div>
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-add-paper>@include('partials.dashboard-icon', ['name' => 'plus']) Add paper</button>
            <p class="dash-field-error" data-paper-error hidden></p>
        </div>
        <div class="dash-inline-actions"><button type="button" class="dash-btn dash-btn-primary dash-btn-sm" data-save-papers>Save date sheet</button></div>
        <template data-paper-template>
            <div class="dash-line-item dash-line-item-paper" data-paper>
                <select data-paper-field="subject_id" aria-label="Subject">
                    @foreach ($data['curriculum'] as $subject)
                        <option value="{{ $subject->id }}" data-class-id="{{ $subject->class_id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
                <input type="date" data-paper-field="exam_date" aria-label="Date">
                <input type="time" data-paper-field="starts_at" aria-label="Starts at">
                <input type="number" min="0" placeholder="Theory" data-paper-field="theory_max_marks" aria-label="Theory max marks">
                <input type="number" min="0" placeholder="Practical" data-paper-field="practical_max_marks" aria-label="Practical max marks">
                <input type="number" min="0" placeholder="Pass" data-paper-field="pass_marks" aria-label="Pass marks">
                <button type="button" class="dash-icon-btn" data-remove-paper aria-label="Remove paper">@include('partials.dashboard-icon', ['name' => 'close'])</button>
            </div>
        </template>
    </section>

    <section class="dash-inline-form">
        <h4>Fees &amp; admit cards</h4>
        <div class="dash-step-buttons">
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-action="fee-challans">Exam fee challans</button>
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-action="admit-cards">Issue / refresh admit cards</button>
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-print-admit>Print admit cards</button>
        </div>
        <div class="dash-grid-wrap"><table class="dash-table dash-mini-table"><tbody data-admit-rows></tbody></table></div>
    </section>

    <section class="dash-inline-form">
        <h4>Seating plan</h4>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="seatPaper">Paper</label>
                <select id="seatPaper" data-seat-paper></select>
            </div>
            <div class="dash-field">
                <span class="dash-label">Halls</span>
                <div class="dash-checks-inline">
                    @foreach ($data['halls'] as $hall)
                        <label class="dash-check"><input type="checkbox" value="{{ $hall->id }}" data-hall checked> {{ $hall->name }} ({{ $hall->capacity }})</label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="dash-inline-actions"><button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-make-seating>Make seating plan</button></div>
        <div class="dash-grid-wrap"><table class="dash-table dash-mini-table"><tbody data-seat-rows></tbody></table></div>
    </section>

    <section class="dash-inline-form">
        <h4>Results</h4>
        <div class="dash-step-buttons">
            <label class="dash-select"><span class="dash-visually-hidden">Section</span>
                <select data-result-section>
                    <option value="">Whole class</option>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
            </label>
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-tabulate>Show tabulation</button>
            <button type="button" class="dash-btn dash-btn-primary dash-btn-sm" data-publish>Publish results</button>
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-remarks>Remarks</button>
            <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-report-cards>Print result cards</button>
        </div>
        <div class="dash-grid-wrap"><table class="dash-table dash-mini-table" data-tabulation></table></div>
    </section>
</dialog>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="remarksModal" aria-labelledby="remarksTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'pen'])</span>
        <div>
            <h3 id="remarksTitle">Result card remarks</h3>
            <p>Printed on each student's result card. Results must be published first.</p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-form-scroll">
        <div class="dash-grid-wrap"><table class="dash-table dash-remarks-table">
            <thead><tr><th>Student</th><th>Class teacher's remarks</th><th>Principal's remarks</th></tr></thead>
            <tbody data-remarks-rows></tbody>
        </table></div>
    </div>
    <div class="dash-modal-actions">
        <button type="button" class="dash-btn dash-btn-ghost" data-remarks-fill>Fill empty from grade</button>
        <button type="button" class="dash-btn dash-btn-primary" data-remarks-save>@include('partials.dashboard-icon', ['name' => 'check']) Save remarks</button>
    </div>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-exams.js') }}?v={{ filemtime(public_path('js/admin-exams.js')) }}" defer></script>
@endsection

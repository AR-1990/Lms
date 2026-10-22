@extends('layouts.dashboard')

@section('title', 'Marks Entry')
@section('page_heading', 'Exams')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Marks Entry</h1>
        <p>Pick the exam paper and section, enter marks, save as you go, then submit. Changes after submitting need a reason.</p>
    </div>
</section>

@include('dashboards.exams.tabs', ['current' => 'admin.exams.marks'])

<section class="dash-panel" data-marks data-sheet-url="{{ route('erp.marks.sheet') }}" data-save-url="{{ route('erp.marks.store') }}" data-approve-url="{{ route('erp.marks.approve') }}" data-manager="{{ $data['is_manager'] ? '1' : '0' }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Paper</span>
                <select data-paper>
                    @forelse ($data['exams'] as $exam)
                        @foreach ($exam->subjects as $paper)
                            @if ($data['is_manager'] || $data['assignments']->contains('subject_id', $paper->subject_id))
                                <option value="{{ $paper->id }}" data-class-id="{{ $exam->class_id }}" data-subject-id="{{ $paper->subject_id }}">{{ $exam->name }} — {{ $paper->subject?->name }}</option>
                            @endif
                        @endforeach
                    @empty
                        <option value="">No exams open for marks</option>
                    @endforelse
                </select>
            </label>
            <label class="dash-select">
                <span class="dash-visually-hidden">Section</span>
                <select data-section>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}" data-subjects="{{ $data['assignments']->where('section_id', $section->id)->pluck('subject_id')->implode(' ') }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <p class="dash-register-note" data-marks-note hidden></p>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-register">
            <thead>
                <tr>
                    <th>Student</th>
                    <th class="dash-col-num">Theory</th>
                    <th class="dash-col-num" data-practical-head>Practical</th>
                    <th>Absent</th>
                    <th>Grade</th>
                    <th class="dash-hide-md">Remarks</th>
                </tr>
            </thead>
            <tbody data-marks-rows><tr><td colspan="6" class="dash-muted">Choose a paper and section.</td></tr></tbody>
        </table>
    </div>
    <div class="dash-register-foot">
        <div class="dash-field dash-marks-reason">
            <label for="marksReason">Reason for change <span class="dash-optional">needed after submitting</span></label>
            <input id="marksReason" data-reason maxlength="1000">
        </div>
        <div class="dash-head-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-save-marks="0">Save draft</button>
            <button type="button" class="dash-btn dash-btn-primary" data-save-marks="1">@include('partials.dashboard-icon', ['name' => 'check']) Submit</button>
            @if ($data['is_manager'])
                <button type="button" class="dash-btn dash-btn-primary" data-approve-marks>Approve</button>
            @endif
        </div>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-exams.js') }}?v={{ filemtime(public_path('js/admin-exams.js')) }}" defer></script>
@endsection

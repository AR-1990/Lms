@extends('layouts.dashboard')

@section('title', 'Timetable')
@section('page_heading', 'Academics')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Timetable</h1>
        <p>Click a cell to set the subject, teacher and room. A teacher cannot take two classes in the same period.</p>
    </div>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.timetable'])

<section class="dash-panel" data-timetable data-section-url="{{ url('/api/erp/timetable/sections') }}" data-slots-url="{{ url('/api/erp/timetable-slots') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select">
                <span class="dash-visually-hidden">Section</span>
                <select data-section>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table dash-timetable">
            <thead>
                <tr>
                    <th>Period</th>
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                        <th>{{ $day }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody data-timetable-body>
                @foreach ($data['periods'] as $period)
                    <tr>
                        <th scope="row"><span class="dash-cell-title">{{ $period->name }}</span><span class="dash-muted">{{ substr($period->starts_at, 0, 5) }}–{{ substr($period->ends_at, 0, 5) }}</span></th>
                        @foreach (range(1, 6) as $day)
                            <td>
                                @if ($period->is_break)
                                    <div class="dash-slot-break">{{ $period->name }}</div>
                                @else
                                    <button type="button" class="dash-slot" data-slot data-day="{{ $day }}" data-period="{{ $period->id }}">—</button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="slotModal" aria-labelledby="slotTitle">
    <form data-slot-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'grid'])</span>
            <div>
                <h3 id="slotTitle" data-slot-title>Period</h3>
                <p>Subjects come from the class curriculum.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="slotSubject">Subject</label>
                <select id="slotSubject" name="subject_id">
                    <option value="">Free period</option>
                    @foreach ($data['curriculum'] as $subject)
                        <option value="{{ $subject->id }}" data-class-id="{{ $subject->class_id }}">{{ $subject->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="subject_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="slotTeacher">Teacher <span class="dash-optional">optional</span></label>
                <select id="slotTeacher" name="teacher_id">
                    <option value="">—</option>
                    @foreach ($data['teachers'] as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->first_name }} {{ $teacher->last_name }} ({{ $teacher->employee_code }})</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="teacher_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'room', 'label' => 'Room', 'id' => 'slotRoom', 'wide' => true])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-clear-slot>Clear period</button>
            <button type="submit" class="dash-btn dash-btn-primary">@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-schedule.js') }}?v={{ filemtime(public_path('js/admin-schedule.js')) }}" defer></script>
@endsection

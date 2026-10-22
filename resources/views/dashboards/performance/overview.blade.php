@extends('layouts.dashboard')

@section('title', 'Performance')
@section('page_heading', 'Performance')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Performance Tracker</h1>
        <p>Each student's results, attendance, homework and behaviour for the term, with "at risk" flags from your alert rules. Open a student for trends, skills and goals.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-ghost" data-refresh>@include('partials.dashboard-icon', ['name' => 'trending-up']) Recalculate</button>
</section>

@include('dashboards.performance.tabs', ['current' => 'admin.performance.overview'])

<section class="dash-panel" data-performance="overview" data-api-url="{{ url('/api/erp/performance') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Section</span>
                <select data-section>
                    @forelse ($data['sections'] as $section)
                        <option value="{{ $section->id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @empty
                        <option value="">No sections</option>
                    @endforelse
                </select>
            </label>
            <label class="dash-select"><span class="dash-visually-hidden">Term</span>
                <select data-term>
                    @foreach ($data['terms'] as $term)
                        <option value="{{ $term->id }}">{{ $term->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </div>
    <div class="dash-grid-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th class="dash-col-num">Average</th>
                    <th class="dash-col-num">Attendance</th>
                    <th class="dash-col-num dash-hide-md">Homework</th>
                    <th class="dash-col-num dash-hide-md">Rank</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody data-rows><tr><td colspan="6" class="dash-muted">Loading…</td></tr></tbody>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="studentPerformance" aria-labelledby="perfTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'trending-up'])</span>
        <div><h3 id="perfTitle" data-detail-title>Student</h3><p data-detail-subtitle></p></div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <div class="dash-detail-body" data-detail-body></div>
    <form class="dash-inline-form" data-goal-form novalidate>
        <h4>Set a goal</h4>
        <div class="dash-form-grid dash-form-grid-3">
            @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Goal', 'required' => true, 'id' => 'goalTitle', 'placeholder' => 'Reach 80% in Maths'])
            @include('dashboards.partials.select', ['name' => 'subject_id', 'label' => 'Subject', 'options' => $data['subjects'], 'empty' => 'General', 'id' => 'goalSubject'])
            @include('dashboards.partials.field', ['name' => 'target_date', 'label' => 'By', 'type' => 'date', 'id' => 'goalDate'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-inline-actions"><button type="submit" class="dash-btn dash-btn-ghost">Add goal</button></div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-performance.js') }}?v={{ filemtime(public_path('js/admin-performance.js')) }}" defer></script>
@endsection

@extends('layouts.dashboard')

@section('title', 'Skill Ratings')
@section('page_heading', 'Performance')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Skill Ratings</h1>
        <p>Rate each student's co-scholastic skills once a term. Ratings appear on the student's performance page and the parent app.</p>
    </div>
</section>

@include('dashboards.performance.tabs', ['current' => 'admin.performance.skills'])

<section class="dash-panel" data-performance="skills" data-api-url="{{ url('/api/erp/performance') }}">
    <div class="dash-panel-head">
        <div class="dash-filters-inline">
            <label class="dash-select"><span class="dash-visually-hidden">Section</span>
                <select data-section>
                    @foreach ($data['sections'] as $section)
                        <option value="{{ $section->id }}">{{ $section->class_name }} {{ $section->name }}</option>
                    @endforeach
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
    <select hidden data-rating-template>
        <option value="">—</option>
        @foreach ($data['ratings'] as $rating)
            <option value="{{ $rating->id }}">{{ $rating->name }}</option>
        @endforeach
    </select>
    <div class="dash-grid-wrap"><table class="dash-table dash-register" data-skill-table></table></div>
    <div class="dash-register-foot">
        <p class="dash-muted">Only changed ratings are saved.</p>
        <button type="button" class="dash-btn dash-btn-primary" data-save-skills>@include('partials.dashboard-icon', ['name' => 'check']) Save Ratings</button>
    </div>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-performance.js') }}?v={{ filemtime(public_path('js/admin-performance.js')) }}" defer></script>
@endsection

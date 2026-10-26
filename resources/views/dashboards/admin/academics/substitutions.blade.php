@extends('layouts.dashboard')

@section('title', 'Substitutions')
@section('page_heading', 'Academics')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Substitutions</h1>
        <p>Periods of teachers on leave or marked absent, with the teachers who are free in each period (fewest periods that day first). Pick one to cover; they are told and see it in today's schedule.</p>
    </div>
    <label class="dash-field dash-subs-date">
        <span class="dash-visually-hidden">Date</span>
        <input type="date" data-subs-date>
    </label>
</section>

@include('dashboards.admin.academics.tabs', ['current' => 'admin.academics.substitutions'])

<div data-substitutions data-url="{{ route('erp.substitutions.index') }}" data-save-url="{{ route('erp.substitutions.store') }}" data-delete-url="{{ url('/api/erp/substitutions') }}">
    <p class="dash-muted" data-subs-empty hidden>No teacher is away on this day.</p>
    <div class="dash-subs" data-subs-list></div>
</div>
@endsection

@section('extra_js')
<script src="{{ asset('js/substitutions.js') }}?v={{ filemtime(public_path('js/substitutions.js')) }}" defer></script>
@endsection

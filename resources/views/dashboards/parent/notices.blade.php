@extends('layouts.dashboard')

@section('title', 'Notices')
@section('page_heading', 'School Notices')

@section('content')
<section class="dash-page-intro">
    <h1>Notices</h1>
    <p>Official updates from academics, finance, and campus life.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Total Notices</span>
        <strong class="dash-stat-value">{{ count($data['notices']) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">High Priority</span>
        <strong class="dash-stat-value">{{ collect($data['notices'])->where('priority', 'High')->count() }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Finance</span>
        <strong class="dash-stat-value">{{ collect($data['notices'])->where('type', 'Finance')->count() }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Events</span>
        <strong class="dash-stat-value">{{ collect($data['notices'])->where('type', 'Events')->count() }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Notice Board</h2>
    </div>
    <ul class="dash-list">
        @foreach ($data['notices'] as $notice)
            <li>
                <div>
                    <strong>{{ $notice['title'] }}</strong>
                    <span class="dash-muted">{{ $notice['date'] }} · {{ $notice['type'] }} · {{ $notice['priority'] }} priority</span>
                    <span class="dash-muted" style="margin-top: 0.35rem;">{{ $notice['body'] }}</span>
                </div>
                <span class="dash-badge {{ $notice['priority'] === 'High' ? 'dash-badge-warn' : '' }}">{{ $notice['type'] }}</span>
            </li>
        @endforeach
    </ul>
</section>
@endsection

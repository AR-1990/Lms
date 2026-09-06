@extends('layouts.dashboard')

@section('title', 'Gradebook')
@section('page_heading', 'Faculty Gradebook')

@section('content')
@php $summary = $data['summary']; @endphp

<section class="dash-page-intro">
    <h1>Gradebook</h1>
    <p>Track assessments, submissions, and published results.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Open</span>
        <strong class="dash-stat-value">{{ $summary['open_assessments'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Drafts</span>
        <strong class="dash-stat-value">{{ $summary['drafts'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Published</span>
        <strong class="dash-stat-value">{{ $summary['published'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Avg Score</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['avg_score'] }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Assessments</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Assessment</th>
                    <th>Class</th>
                    <th>Due</th>
                    <th>Submissions</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['assessments'] as $item)
                    <tr>
                        <td>{{ $item['title'] }}</td>
                        <td>{{ $item['class'] }}</td>
                        <td>{{ $item['due'] }}</td>
                        <td>{{ $item['submissions'] }}</td>
                        <td>
                            @php
                                $badge = match ($item['status']) {
                                    'Published' => 'dash-badge-ok',
                                    'Draft' => 'dash-badge-warn',
                                    default => '',
                                };
                            @endphp
                            <span class="dash-badge {{ $badge }}">{{ $item['status'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection

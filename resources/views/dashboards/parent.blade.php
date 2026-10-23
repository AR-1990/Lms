@extends('layouts.dashboard')

@section('title', 'Parent Dashboard')
@section('page_heading', 'Family Overview')

@section('content')
@php
    $summary = $data['summary'];
    $children = $data['children'];
    $notices = $data['recent_notices'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $data['parent']['name'] ?? $user->name }}</h1>
    <p>{{ $data['parent'] ? 'Attendance, results, fees and notices for your children' : 'Your account is not linked to a student yet. Please contact the school office.' }}</p>
</section>

@include('partials.sticky-notes', ['limit' => 8])

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Children</span>
        <strong class="dash-stat-value">{{ $summary['children'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Present Today</span>
        <strong class="dash-stat-value">{{ $summary['present_today'] }} / {{ $summary['children'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Fees Outstanding</span>
        <strong class="dash-stat-value dash-stat-value-sm">Rs {{ number_format($summary['fee_outstanding'] / 100) }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Open Vouchers</span>
        <strong class="dash-stat-value">{{ $summary['open_invoices'] }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>My Children</h2>
        <a class="dash-btn dash-btn-ghost" href="{{ route('parent.children') }}">Details</a>
    </div>
    @include('dashboards.parent.children-table', ['children' => $children])
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Latest Notices</h2>
        <a class="dash-btn dash-btn-ghost" href="{{ route('notices') }}">All notices</a>
    </div>
    @if ($notices === [])
        <p class="dash-muted">No notices yet.</p>
    @else
        <ul class="dash-list">
            @foreach ($notices as $notice)
                <li>
                    <strong>{{ $notice['title'] }}</strong>
                    <span class="dash-muted">{{ $notice['category']['name'] ?? '' }} {{ $notice['publish_at'] ? '· '.\Illuminate\Support\Carbon::parse($notice['publish_at'])->format('d M Y') : '' }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection

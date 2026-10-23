@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')
@section('page_heading', 'Dashboard')

@section('content')
@php
    $overview = $data['overview'];
    $status = $data['system_status'];
    $signups = $data['monthly_signups'];
    $roles = $data['roles_breakdown'];
    $tones = ['orange', 'blue', 'emerald', 'violet', 'rose', 'sky', 'amber'];
    $toneColors = ['orange' => '#d4962a', 'blue' => '#3d6ea6', 'emerald' => '#5b8f62', 'violet' => '#8466ad', 'rose' => '#b85c46', 'sky' => '#2e7d81', 'amber' => '#c9a227'];
    $quote = $data['daily_quote'] ?? null;
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $userTotal = max($overview['total_users'], 1);
    $signupMax = max(max(array_column($signups, 'count')), 1);

    $donutStops = [];
    $donutCursor = 0;
    foreach ($roles as $index => $role) {
        $share = $role->users_count / $userTotal * 100;
        $color = $toneColors[$tones[$index % count($tones)]];
        $donutStops[] = $color.' '.round($donutCursor, 2).'% '.round($donutCursor + $share, 2).'%';
        $donutCursor += $share;
    }
    $donutGradient = $donutStops === [] ? '#e5e7eb 0% 100%' : implode(', ', $donutStops);

    $kpis = [
        ['label' => 'Total Users', 'value' => $overview['total_users'], 'icon' => 'users', 'tone' => 'orange', 'foot' => '+'.$overview['new_this_month'].' this month'],
        ['label' => 'Teachers', 'value' => $overview['total_teachers'], 'icon' => 'teacher', 'tone' => 'blue', 'foot' => 'Faculty accounts'],
        ['label' => 'Students', 'value' => $overview['total_students'], 'icon' => 'student', 'tone' => 'emerald', 'foot' => 'Enrolled learners'],
        ['label' => 'Roles', 'value' => $overview['total_roles'], 'icon' => 'key', 'tone' => 'violet', 'foot' => 'Access profiles'],
    ];

    $quickActions = [
        ['label' => 'Add User', 'hint' => 'Create an account', 'icon' => 'user-plus', 'tone' => 'blue', 'url' => route('admin.users.create')],
        ['label' => 'Roles', 'hint' => 'Access & permissions', 'icon' => 'shield', 'tone' => 'violet', 'url' => route('admin.roles')],
        ['label' => 'Reports', 'hint' => 'Headcount & KPIs', 'icon' => 'chart', 'tone' => 'emerald', 'url' => route('admin.reports')],
        ['label' => 'Settings', 'hint' => 'School profile', 'icon' => 'settings', 'tone' => 'rose', 'url' => route('admin.settings')],
        ['label' => 'Website', 'hint' => 'Open public site', 'icon' => 'globe', 'tone' => 'sky', 'url' => route('home')],
    ];
@endphp

<section class="dash-welcome">
    <div class="dash-welcome-main">
        <h1 class="dash-welcome-greeting">
            <span class="dash-wave" aria-hidden="true">👋</span>
            <span class="dash-welcome-word">{{ $greeting }},</span>
            <span class="dash-welcome-word dash-welcome-name">
                {{ $user->name }}
                <svg class="dash-scribble" viewBox="0 0 120 8" preserveAspectRatio="none" aria-hidden="true"><path d="M2 5.5C18 2 30 7 46 4.5S78 2 92 4.8 112 6 118 3"/></svg>
            </span>
        </h1>
        <p class="dash-welcome-sub">
            <span class="dash-pulse-dot" aria-hidden="true"></span>
            System {{ strtolower($status['status']) }} · here is what is happening across {{ $status['school_name'] }} today.
        </p>
        @if ($quote)
            <blockquote class="dash-quote">
                <p>&ldquo;{{ $quote['text'] }}&rdquo;</p>
                @if ($quote['by'])
                    <cite>{{ $quote['by'] }}</cite>
                @endif
            </blockquote>
        @endif
    </div>

    <div class="dash-welcome-side">
        <div class="dash-clock" data-live-clock data-timezone="{{ $status['timezone'] }}">
            @include('partials.dashboard-icon', ['name' => 'clock'])
            <span class="dash-clock-text">
                <strong data-clock-time>{{ now()->format('h:i A') }}</strong>
                <span data-clock-date>{{ now()->format('l, d F') }}</span>
            </span>
        </div>
        <span class="dash-welcome-stat">
            <strong>{{ $overview['new_this_month'] }}</strong>
            new {{ Str::plural('account', $overview['new_this_month']) }} this month
        </span>
        <a href="{{ route('admin.users.create') }}" class="dash-btn dash-btn-primary">
            @include('partials.dashboard-icon', ['name' => 'user-plus'])
            Add User
        </a>
    </div>
</section>

@include('partials.sticky-notes', ['limit' => 8])

<section class="dash-stats" aria-label="Key metrics">
    @foreach ($kpis as $kpi)
        <article class="dash-kpi dash-tone-{{ $kpi['tone'] }}">
            <span class="dash-kpi-icon">@include('partials.dashboard-icon', ['name' => $kpi['icon']])</span>
            <span class="dash-kpi-label">{{ $kpi['label'] }}</span>
            <strong class="dash-kpi-value" data-count-up="{{ $kpi['value'] }}">{{ number_format($kpi['value']) }}</strong>
            <span class="dash-kpi-foot">{{ $kpi['foot'] }}</span>
            <span class="dash-kpi-ghost" aria-hidden="true">@include('partials.dashboard-icon', ['name' => $kpi['icon']])</span>
        </article>
    @endforeach
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Account Growth</h2>
                <p>New portal accounts over the last 6 months.</p>
            </div>
            <span class="dash-badge dash-badge-brand">
                @include('partials.dashboard-icon', ['name' => 'trending-up'])
                {{ $overview['total_users'] }} total
            </span>
        </div>
        <div class="dash-chart-bars" role="img" aria-label="New accounts per month">
            @foreach ($signups as $month)
                <div @class(['dash-chart-col', 'is-current' => $loop->last])>
                    <span class="dash-chart-value">{{ $month['count'] }}</span>
                    <span class="dash-chart-bar" style="--h: {{ round($month['count'] / $signupMax, 3) }}; --i: {{ $loop->index }}"></span>
                    <span class="dash-chart-label">{{ $month['label'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Role Mix</h2>
                <p>How accounts are spread across roles.</p>
            </div>
        </div>
        <div class="dash-donut-wrap">
            <div class="dash-donut" style="--donut: conic-gradient({{ $donutGradient }})">
                <div class="dash-donut-center">
                    <strong data-count-up="{{ $overview['total_users'] }}">{{ $overview['total_users'] }}</strong>
                    <span>users</span>
                </div>
            </div>
            <ul class="dash-legend">
                @foreach ($roles as $role)
                    <li class="dash-tone-{{ $tones[$loop->index % count($tones)] }}">
                        <span class="dash-legend-dot" aria-hidden="true"></span>
                        <span class="dash-legend-name">{{ $role->name }}</span>
                        <strong>{{ $role->users_count }}</strong>
                        <span class="dash-legend-share">{{ round($role->users_count / $userTotal * 100) }}%</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
</div>

<section class="dash-quick" aria-label="Quick actions">
    @foreach ($quickActions as $action)
        <a href="{{ $action['url'] }}" class="dash-quick-tile dash-tone-{{ $action['tone'] }}">
            <span class="dash-quick-icon">@include('partials.dashboard-icon', ['name' => $action['icon']])</span>
            <span class="dash-quick-text">
                <strong>{{ $action['label'] }}</strong>
                <span>{{ $action['hint'] }}</span>
            </span>
            @include('partials.dashboard-icon', ['name' => 'arrow-right', 'class' => 'dash-quick-arrow'])
        </a>
    @endforeach
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Recent Users</h2>
                <p>Latest accounts added to the portal.</p>
            </div>
            <a href="{{ route('admin.users') }}" class="dash-panel-link">
                View all
                @include('partials.dashboard-icon', ['name' => 'arrow-right'])
            </a>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th class="dash-hide-sm">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['recent_users'] as $recent)
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.show', $recent) }}" class="dash-person">
                                    <span class="dash-person-avatar dash-tone-{{ $tones[$recent->id % count($tones)] }}" aria-hidden="true">{{ strtoupper(substr($recent->name, 0, 1)) }}</span>
                                    <span>
                                        <span class="dash-person-name">{{ $recent->name }}</span>
                                        <span class="dash-person-meta">{{ $recent->email }}</span>
                                    </span>
                                </a>
                            </td>
                            <td><span class="dash-badge dash-badge-brand">{{ $recent->roles->first()->name ?? 'Unassigned' }}</span></td>
                            <td class="dash-hide-sm">{{ $recent->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="dash-empty">
                                    @include('partials.dashboard-icon', ['name' => 'inbox'])
                                    <strong>No users yet</strong>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>System Status</h2>
                <p>Runtime environment.</p>
            </div>
            <span class="dash-badge dash-badge-ok">{{ ucfirst($status['status']) }}</span>
        </div>
        <ul class="dash-status-list">
            <li class="dash-tone-sky">
                <span class="dash-status-icon">@include('partials.dashboard-icon', ['name' => 'clock'])</span>
                <span>Timezone</span><strong>{{ $status['timezone'] }}</strong>
            </li>
            <li class="dash-tone-emerald">
                <span class="dash-status-icon">@include('partials.dashboard-icon', ['name' => 'trending-up'])</span>
                <span>Currency</span><strong>{{ $status['currency'] ?? 'Not set' }}</strong>
            </li>
            <li class="dash-tone-rose">
                <span class="dash-status-icon">@include('partials.dashboard-icon', ['name' => 'server'])</span>
                <span>Laravel</span><strong>{{ $status['laravel_version'] }}</strong>
            </li>
            <li class="dash-tone-violet">
                <span class="dash-status-icon">@include('partials.dashboard-icon', ['name' => 'activity'])</span>
                <span>PHP</span><strong>{{ $status['php_version'] }}</strong>
            </li>
            <li class="dash-tone-orange">
                <span class="dash-status-icon">@include('partials.dashboard-icon', ['name' => 'key'])</span>
                <span>API</span><strong>v{{ $status['api_version'] }}</strong>
            </li>
        </ul>
    </section>
</div>
@endsection

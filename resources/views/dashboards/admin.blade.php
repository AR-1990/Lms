@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')
@section('page_heading', 'Dashboard')

@section('content')
@php
    $overview = $data['overview'];
    $status = $data['system_status'];
    $roles = $data['roles_breakdown'];
    $tones = ['orange', 'blue', 'emerald', 'violet', 'rose', 'sky', 'amber'];
    $toneColors = ['orange' => '#d4962a', 'blue' => '#3d6ea6', 'emerald' => '#5b8f62', 'violet' => '#8466ad', 'rose' => '#b85c46', 'sky' => '#2e7d81', 'amber' => '#c9a227'];
    $quote = $data['daily_quote'] ?? null;
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $userTotal = max($overview['total_users'], 1);

    $donutStops = [];
    $donutCursor = 0;
    foreach ($roles as $index => $role) {
        $share = $role->users_count / $userTotal * 100;
        $color = $toneColors[$tones[$index % count($tones)]];
        $donutStops[] = $color.' '.round($donutCursor, 2).'% '.round($donutCursor + $share, 2).'%';
        $donutCursor += $share;
    }
    $donutGradient = $donutStops === [] ? '#e5e7eb 0% 100%' : implode(', ', $donutStops);

    $quickActions = [
        ['label' => 'Fee Challans', 'hint' => 'Issue & collect fees', 'icon' => 'hash', 'tone' => 'emerald', 'url' => route('accounts.fees.challans')],
        ['label' => 'Receipts', 'hint' => 'Payments received', 'icon' => 'check', 'tone' => 'sky', 'url' => route('accounts.fees.receipts')],
        ['label' => 'Expenses', 'hint' => 'Record & pay bills', 'icon' => 'book', 'tone' => 'rose', 'url' => route('accounts.expenses')],
        ['label' => 'Payroll', 'hint' => 'Monthly salaries', 'icon' => 'users', 'tone' => 'violet', 'url' => route('admin.hr.payroll')],
        ['label' => 'Reports', 'hint' => 'Finance, fees, staff', 'icon' => 'chart', 'tone' => 'amber', 'url' => route('admin.reports')],
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
            Fees, pending dues and spending across {{ $status['school_name'] }}, live from today's records.
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
        <a href="{{ route('accounts.fees.challans') }}" class="dash-btn dash-btn-primary">
            @include('partials.dashboard-icon', ['name' => 'hash'])
            Fee Challans
        </a>
    </div>
</section>

<div class="dash-fin" data-finance data-url="{{ route('erp.admin.finance') }}" data-reports="{{ route('admin.reports') }}" data-receipts="{{ route('accounts.fees.receipts') }}" data-expenses="{{ route('accounts.expenses') }}" aria-busy="true">
    {{-- Headline figures: filled by js/admin-dashboard.js from /api/erp/admin/finance --}}
    <section class="dash-fin-kpis" aria-label="Money this month">
        <article class="dash-fin-kpi dash-tone-emerald" data-kpi="collected">
            <header><span class="dash-fin-kpi-icon">@include('partials.dashboard-icon', ['name' => 'trending-up'])</span><span>Collected this month</span><span class="dash-fin-change" data-kpi-change></span></header>
            <strong data-kpi-value>—</strong>
            <p data-kpi-foot>&nbsp;</p>
            <svg class="dash-fin-spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true" data-kpi-spark></svg>
        </article>
        <article class="dash-fin-kpi dash-tone-amber" data-kpi="pending">
            <header><span class="dash-fin-kpi-icon">@include('partials.dashboard-icon', ['name' => 'clock'])</span><span>Pending fees</span></header>
            <strong data-kpi-value>—</strong>
            <p data-kpi-foot>&nbsp;</p>
            <div class="dash-fin-meter" aria-hidden="true"><span data-kpi-meter></span></div>
        </article>
        <article class="dash-fin-kpi dash-tone-rose" data-kpi="expenses">
            <header><span class="dash-fin-kpi-icon">@include('partials.dashboard-icon', ['name' => 'book'])</span><span>Spent this month</span><span class="dash-fin-change" data-kpi-change></span></header>
            <strong data-kpi-value>—</strong>
            <p data-kpi-foot>&nbsp;</p>
            <svg class="dash-fin-spark" viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true" data-kpi-spark></svg>
        </article>
        <article class="dash-fin-kpi dash-tone-sky" data-kpi="today">
            <header><span class="dash-fin-kpi-icon">@include('partials.dashboard-icon', ['name' => 'check'])</span><span>Collected today</span></header>
            <strong data-kpi-value>—</strong>
            <p data-kpi-foot>&nbsp;</p>
            <div class="dash-fin-meter" aria-hidden="true"><span data-kpi-meter></span></div>
        </article>
    </section>

    <section class="dash-fin-chips" aria-label="More figures" data-chips></section>

    <section class="dash-panel dash-fin-trend">
        <div class="dash-panel-head">
            <div>
                <h2>Fee collection vs spending</h2>
                <p>Fees received against expenses and salaries, month by month. Click a legend item to hide it.</p>
            </div>
            <div class="dash-fin-toggle" role="group" aria-label="Period">
                <button type="button" data-months="6">6 months</button>
                <button type="button" data-months="12" class="is-active">12 months</button>
            </div>
        </div>
        <div class="dash-fin-legend" data-trend-legend></div>
        <div class="dash-fin-chart" data-trend-chart></div>
        <div class="dash-fin-net" data-trend-net></div>
    </section>

    <div class="dash-fin-grid">
        <section class="dash-panel">
            <div class="dash-panel-head">
                <div>
                    <h2>Pending fees by age</h2>
                    <p>How long overdue challans have been waiting.</p>
                </div>
                <a class="dash-panel-link" href="{{ route('admin.reports') }}?report=fee-defaulters">Defaulters @include('partials.dashboard-icon', ['name' => 'arrow-right'])</a>
            </div>
            <div class="dash-fin-donut" data-ageing></div>
        </section>

        <section class="dash-panel">
            <div class="dash-panel-head">
                <div>
                    <h2>Daily collection</h2>
                    <p>Fees received each day this month.</p>
                </div>
            </div>
            <div class="dash-fin-chart dash-fin-area" data-daily></div>
        </section>
    </div>

    <div class="dash-fin-grid dash-fin-grid-3">
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Pending by class</h2><p>Where the money is stuck.</p></div></div>
            <ul class="dash-fin-bars" data-bars="pending_by_class"></ul>
        </section>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Paid by</h2><p>Payment methods this month.</p></div></div>
            <ul class="dash-fin-bars" data-bars="methods"></ul>
        </section>
        <section class="dash-panel">
            <div class="dash-panel-head"><div><h2>Spending by category</h2><p>Expenses this month.</p></div></div>
            <ul class="dash-fin-bars" data-bars="expense_categories"></ul>
        </section>
    </div>

    <div class="dash-fin-grid">
        <section class="dash-panel">
            <div class="dash-panel-head">
                <div><h2>Latest receipts</h2><p>Fees just received.</p></div>
                <a class="dash-panel-link" href="{{ route('accounts.fees.receipts') }}">All receipts @include('partials.dashboard-icon', ['name' => 'arrow-right'])</a>
            </div>
            <ul class="dash-fin-list" data-list="recent_receipts"></ul>
        </section>
        <section class="dash-panel">
            <div class="dash-panel-head">
                <div><h2>Largest dues</h2><p>Students who owe the most.</p></div>
                <a class="dash-panel-link" href="{{ route('admin.reports') }}?report=fee-defaulters">Full list @include('partials.dashboard-icon', ['name' => 'arrow-right'])</a>
            </div>
            <ul class="dash-fin-list" data-list="top_defaulters"></ul>
        </section>
    </div>
</div>

@include('partials.attendance-qr')

@include('partials.sticky-notes', ['limit' => 8])

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
                <h2>Role Mix</h2>
                <p>{{ number_format($overview['total_users']) }} portal accounts · {{ $overview['new_this_month'] }} new this month.</p>
            </div>
            <a href="{{ route('admin.users') }}" class="dash-panel-link">Users @include('partials.dashboard-icon', ['name' => 'arrow-right'])</a>
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
</div>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-dashboard.js') }}?v={{ filemtime(public_path('js/admin-dashboard.js')) }}" defer></script>
@endsection

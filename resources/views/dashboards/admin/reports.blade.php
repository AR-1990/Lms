@extends('layouts.dashboard')

@section('title', 'Reports')
@section('page_heading', 'Operational Reports')

@section('content')
<section class="dash-page-intro">
    <h1>Reports</h1>
    <p>Campus KPIs and scheduled operational report packs.</p>
</section>

@if (count($data['kpis']) > 0)
    <section class="dash-stats">
        @foreach ($data['kpis'] as $kpi)
            <article class="dash-stat">
                <span class="dash-stat-label">{{ $kpi['label'] }}</span>
                <strong class="dash-stat-value dash-stat-value-sm">{{ $kpi['value'] }}</strong>
            </article>
        @endforeach
    </section>
@endif

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Report Packs</h2>
                <p>Generated operational reports</p>
            </div>
        </div>
        @if (count($data['reports']) > 0)
            <div class="dash-table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Report</th>
                            <th>Period</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['reports'] as $report)
                            <tr>
                                <td>{{ $report['name'] }}</td>
                                <td>{{ $report['period'] }}</td>
                                <td>{{ $report['owner'] }}</td>
                                <td>
                                    <span class="dash-badge {{ $report['status'] === 'Ready' ? 'dash-badge-ok' : 'dash-badge-warn' }}">
                                        {{ $report['status'] }}
                                    </span>
                                </td>
                                <td>{{ $report['updated'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="dash-empty">
                @include('partials.dashboard-icon', ['name' => 'chart'])
                <strong>No report packs yet</strong>
                <span>Reports will appear here once academic and finance modules start producing data.</span>
            </div>
        @endif
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Role Headcount</h2>
                <p>Share of all {{ number_format($data['overview']['total_users']) }} users</p>
            </div>
        </div>
        <ul class="dash-bars">
            @foreach ($data['roles_breakdown'] as $role)
                <li>
                    <div class="dash-bar-row">
                        <span>{{ $role->name }}</span>
                        <strong>{{ $role->users_count }}</strong>
                    </div>
                    <div class="dash-bar-track">
                        <div class="dash-bar-fill" style="--bar: {{ round($role->users_count / max($data['overview']['total_users'], 1), 3) }}"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection

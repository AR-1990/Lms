@extends('layouts.dashboard')

@section('title', 'Reports')
@section('page_heading', 'Operational Reports')

@section('content')
<section class="dash-page-intro">
    <h1>Reports</h1>
    <p>Campus KPIs and scheduled operational report packs.</p>
</section>

<section class="dash-stats">
    @foreach ($data['kpis'] as $kpi)
        <article class="dash-stat">
            <span class="dash-stat-label">{{ $kpi['label'] }}</span>
            <strong class="dash-stat-value dash-stat-value-sm">{{ $kpi['value'] }}</strong>
        </article>
    @endforeach
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Report Packs</h2>
        </div>
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
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Role Headcount</h2>
        </div>
        <ul class="dash-list">
            @foreach ($data['roles_breakdown'] as $role)
                <li>
                    <span>{{ $role->name }}</span>
                    <strong>{{ $role->users_count }}</strong>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection

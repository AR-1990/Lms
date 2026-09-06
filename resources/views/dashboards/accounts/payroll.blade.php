@extends('layouts.dashboard')

@section('title', 'Payroll')
@section('page_heading', 'Staff Payroll')

@section('content')
@php $summary = $data['summary']; @endphp

<section class="dash-page-intro">
    <h1>Payroll</h1>
    <p>{{ $summary['cycle'] }} payroll cycle overview.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Cycle</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['cycle'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Headcount</span>
        <strong class="dash-stat-value">{{ $summary['headcount'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Net Payable</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['net_payable'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Status</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $summary['status'] }}</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Payroll Register</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Gross</th>
                    <th>Deductions</th>
                    <th>Net</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $row)
                    <tr>
                        <td>{{ $row['employee'] }}</td>
                        <td>{{ $row['department'] }}</td>
                        <td>{{ $row['gross'] }}</td>
                        <td>{{ $row['deductions'] }}</td>
                        <td>{{ $row['net'] }}</td>
                        <td>
                            <span class="dash-badge {{ $row['status'] === 'Approved' ? 'dash-badge-ok' : 'dash-badge-warn' }}">
                                {{ $row['status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection

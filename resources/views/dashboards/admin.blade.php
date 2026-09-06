@extends('layouts.dashboard')

@section('title', 'Admin Dashboard')
@section('page_heading', 'System Overview')

@section('content')
@php
    $overview = $data['overview'];
    $recentUsers = $data['recent_users'];
    $roles = $data['roles_breakdown'];
    $status = $data['system_status'];
@endphp

<section class="dash-page-intro">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Campus ERP overview and role distribution</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Total Users</span>
        <strong class="dash-stat-value">{{ $overview['total_users'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Teachers</span>
        <strong class="dash-stat-value">{{ $overview['total_teachers'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Students</span>
        <strong class="dash-stat-value">{{ $overview['total_students'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Roles</span>
        <strong class="dash-stat-value">{{ $overview['total_roles'] }}</strong>
    </article>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Recent Users</h2>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentUsers as $recent)
                        <tr>
                            <td>{{ $recent->name }}</td>
                            <td>{{ $recent->email }}</td>
                            <td>
                                <span class="dash-badge">{{ $recent->roles->first()->name ?? '—' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Roles Breakdown</h2>
        </div>
        <ul class="dash-list">
            @foreach ($roles as $role)
                <li>
                    <span>{{ $role->name }}</span>
                    <strong>{{ $role->users_count }}</strong>
                </li>
            @endforeach
        </ul>
        <div class="dash-status-row">
            <span>System Status</span>
            <span class="dash-badge dash-badge-ok">{{ ucfirst($status['status']) }}</span>
        </div>
    </section>
</div>
@endsection

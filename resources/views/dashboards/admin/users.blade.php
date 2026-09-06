@extends('layouts.dashboard')

@section('title', 'Users')
@section('page_heading', 'User Directory')

@section('content')
<section class="dash-page-intro">
    <h1>Users</h1>
    <p>Manage portal accounts and assigned roles across campus.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Total Users</span>
        <strong class="dash-stat-value">{{ $data['counts']['total'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Active Roles</span>
        <strong class="dash-stat-value">{{ $data['counts']['active_roles'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Directory</span>
        <strong class="dash-stat-value dash-stat-value-sm">Live</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Source</span>
        <strong class="dash-stat-value dash-stat-value-sm">ERP Users</strong>
    </article>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>All Accounts</h2>
    </div>
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['users'] as $account)
                    <tr>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->email }}</td>
                        <td>
                            <span class="dash-badge">{{ $account->roles->first()->name ?? 'Unassigned' }}</span>
                        </td>
                        <td>{{ $account->created_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection

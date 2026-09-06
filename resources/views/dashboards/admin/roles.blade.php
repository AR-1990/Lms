@extends('layouts.dashboard')

@section('title', 'Roles')
@section('page_heading', 'Roles & Permissions')

@section('content')
<section class="dash-page-intro">
    <h1>Roles</h1>
    <p>Review system roles, assigned users, and permission coverage.</p>
</section>

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Roles</span>
        <strong class="dash-stat-value">{{ $data['counts']['roles'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Permissions</span>
        <strong class="dash-stat-value">{{ $data['counts']['permissions'] }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Groups</span>
        <strong class="dash-stat-value">{{ $data['permission_groups']->count() }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Access Model</span>
        <strong class="dash-stat-value dash-stat-value-sm">RBAC</strong>
    </article>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Role Directory</h2>
        </div>
        <div class="dash-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Users</th>
                        <th>Permissions</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['roles'] as $role)
                        <tr>
                            <td>
                                <strong>{{ $role->name }}</strong>
                                <span class="dash-muted">{{ $role->slug }}</span>
                            </td>
                            <td>{{ $role->users_count }}</td>
                            <td>{{ $role->permissions->count() }}</td>
                            <td>
                                <span class="dash-badge {{ $role->is_system ? 'dash-badge-ok' : '' }}">
                                    {{ $role->is_system ? 'System' : 'Custom' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <h2>Permission Groups</h2>
        </div>
        <ul class="dash-list">
            @foreach ($data['permission_groups'] as $group => $permissions)
                <li>
                    <div>
                        <strong>{{ ucfirst($group) }}</strong>
                        <span class="dash-muted">{{ $permissions->pluck('name')->join(', ') }}</span>
                    </div>
                    <strong>{{ $permissions->count() }}</strong>
                </li>
            @endforeach
        </ul>
    </section>
</div>
@endsection

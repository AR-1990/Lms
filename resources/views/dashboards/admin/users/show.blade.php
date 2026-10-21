@extends('layouts.dashboard')

@section('title', $data['account']->name)
@section('page_heading', 'User Profile')

@section('content')
@php
    $account = $data['account'];
    $permissionGroups = $account->roles
        ->flatMap->permissions
        ->unique('id')
        ->sortBy('name')
        ->groupBy('group');
@endphp

<a href="{{ route('admin.users') }}" class="dash-back-link">
    @include('partials.dashboard-icon', ['name' => 'arrow-left'])
    Back to users
</a>

<section class="dash-card dash-profile">
    <div class="dash-profile-cover" aria-hidden="true"></div>
    <div class="dash-profile-body">
        <span class="dash-profile-avatar" aria-hidden="true">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
        <div class="dash-profile-info">
            <h1>{{ $account->name }}</h1>
            <p>
                @include('partials.dashboard-icon', ['name' => 'mail'])
                {{ $account->email }}
            </p>
            <div class="dash-badges">
                @forelse ($account->roles as $accountRole)
                    <span class="dash-badge dash-badge-brand">{{ $accountRole->name }}</span>
                @empty
                    <span class="dash-badge">Unassigned</span>
                @endforelse
            </div>
        </div>
        <div class="dash-profile-actions">
            <a href="{{ route('admin.users.edit', $account) }}" class="dash-btn dash-btn-primary">
                @include('partials.dashboard-icon', ['name' => 'pen'])
                Edit
            </a>
            @unless ($account->is($user))
                <form method="POST" action="{{ route('admin.users.destroy', $account) }}" data-confirm-delete data-confirm-name="{{ $account->name }}" data-erase-target=".dash-profile">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dash-btn dash-btn-danger">
                        @include('partials.dashboard-icon', ['name' => 'eraser'])
                        Delete
                    </button>
                </form>
            @endunless
        </div>
    </div>
</section>

<div class="dash-grid-2">
    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Access &amp; Permissions</h2>
                <p>Everything this account can do, through its roles.</p>
            </div>
            <span class="dash-badge dash-badge-brand">{{ $permissionGroups->flatten()->count() }} permissions</span>
        </div>
        @if ($permissionGroups->isEmpty())
            <div class="dash-empty">
                @include('partials.dashboard-icon', ['name' => 'shield'])
                <strong>No permissions</strong>
                <span>Assign a role to grant access.</span>
            </div>
        @else
            <ul class="dash-list">
                @foreach ($permissionGroups as $group => $permissions)
                    <li>
                        <div>
                            <strong>{{ ucfirst($group) }}</strong>
                            <div class="dash-chips">
                                @foreach ($permissions as $permission)
                                    <span class="dash-chip">{{ $permission->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="dash-panel">
        <div class="dash-panel-head">
            <div>
                <h2>Account Details</h2>
                <p>Record information.</p>
            </div>
        </div>
        <ul class="dash-kv">
            <li><span>User ID</span><strong>#{{ $account->id }}</strong></li>
            <li><span>Member since</span><strong>{{ $account->created_at?->format('d M Y') }}</strong></li>
            <li><span>Last updated</span><strong>{{ $account->updated_at?->diffForHumans() }}</strong></li>
            <li>
                <span>Email verified</span>
                <strong>
                    @if ($account->email_verified_at)
                        <span class="dash-badge dash-badge-ok">Verified</span>
                    @else
                        <span class="dash-badge dash-badge-warn">Pending</span>
                    @endif
                </strong>
            </li>
        </ul>
    </section>
</div>
@endsection

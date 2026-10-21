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
                @unless ($account->is_active)
                    <span class="dash-badge dash-badge-system">Disabled</span>
                @endunless
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
                <button
                    type="button"
                    class="dash-btn dash-btn-danger"
                    data-user-delete
                    data-name="{{ $account->name }}"
                    data-delete-url="{{ route('erp.users.destroy', $account) }}"
                    data-redirect="{{ route('admin.users') }}"
                >
                    @include('partials.dashboard-icon', ['name' => 'eraser'])
                    Delete
                </button>
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
                <p>Sign-in and record information.</p>
            </div>
        </div>
        <ul class="dash-kv">
            <li><span>User ID</span><strong>#{{ $account->id }}</strong></li>
            <li><span>Username</span><strong>{{ $account->username ?? '—' }}</strong></li>
            <li><span>Phone</span><strong>{{ $account->phone ?? '—' }}</strong></li>
            <li><span>Campus</span><strong>{{ $account->campus?->name ?? '—' }}</strong></li>
            <li>
                <span>Status</span>
                <strong>
                    @if ($account->is_active)
                        <span class="dash-badge dash-badge-ok">Active</span>
                    @else
                        <span class="dash-badge dash-badge-system">Disabled</span>
                    @endif
                </strong>
            </li>
            <li><span>Last login</span><strong>{{ $account->last_login_at?->diffForHumans() ?? 'Never' }}</strong></li>
            <li><span>Member since</span><strong>{{ $account->created_at?->format('d M Y') }}</strong></li>
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

<section
    class="dash-panel"
    data-documents
    data-url="{{ route('erp.attachments.index') }}"
    data-owner="users"
    data-owner-id="{{ $account->id }}"
>
    <div class="dash-panel-head">
        <div>
            <h2>Documents</h2>
            <p>CNIC, certificates, photos and other files. Links to open a file expire after 10 minutes.</p>
        </div>
        <div class="dash-filters-inline">
            <span class="dash-badge" data-doc-count>0 files</span>
            <button type="button" class="dash-btn dash-btn-primary" data-doc-add>
                @include('partials.dashboard-icon', ['name' => 'upload'])
                Upload
            </button>
        </div>
    </div>
    <div class="dash-panel-body">
        <ul class="dash-docs" data-doc-list></ul>
        <div class="dash-empty" data-doc-empty>
            @include('partials.dashboard-icon', ['name' => 'paperclip'])
            <strong>No documents yet</strong>
            <span>Upload a file to keep it with this account.</span>
        </div>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="documentModal" aria-labelledby="documentModalTitle">
    <form data-document-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'paperclip'])</span>
            <div>
                <h3 id="documentModalTitle">Upload document</h3>
                <p>Images, PDF, Word or Excel — up to 10 MB.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <label class="dash-file-drop dash-field-wide" data-file-drop>
                <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" required>
                @include('partials.dashboard-icon', ['name' => 'upload'])
                <strong data-file-name>Choose a file or drop it here</strong>
                <span class="dash-muted">The original file is kept private.</span>
            </label>
            <p class="dash-field-error dash-field-wide" data-error-for="file" hidden></p>

            <div class="dash-field">
                <label for="documentType">Document type <span class="dash-optional">optional</span></label>
                <select id="documentType" name="document_type_id">
                    <option value="">Not specified</option>
                    @foreach ($data['documentTypes'] as $documentType)
                        <option value="{{ $documentType->id }}">{{ $documentType->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="document_type_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="documentTitle">Title <span class="dash-optional">optional</span></label>
                <input id="documentTitle" name="title" maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="title" hidden></p>
            </div>
            <div class="dash-field">
                <label for="documentNumber">Document number <span class="dash-optional">optional</span></label>
                <input id="documentNumber" name="document_number" maxlength="100" autocomplete="off">
                <p class="dash-field-error" data-error-for="document_number" hidden></p>
            </div>
            <div class="dash-field">
                <label for="documentExpires">Expires on <span class="dash-optional">optional</span></label>
                <input id="documentExpires" name="expires_at" type="date">
                <p class="dash-field-error" data-error-for="expires_at" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="documentRemarks">Remarks <span class="dash-optional">optional</span></label>
                <textarea id="documentRemarks" name="remarks" rows="2" maxlength="1000"></textarea>
                <p class="dash-field-error" data-error-for="remarks" hidden></p>
            </div>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'upload']) Upload</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/data-grid.js') }}?v={{ filemtime(public_path('js/data-grid.js')) }}" defer></script>
<script src="{{ asset('js/admin-users.js') }}?v={{ filemtime(public_path('js/admin-users.js')) }}" defer></script>
@endsection

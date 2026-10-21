@php
    $isEditing = isset($account);
    $selectedRoles = old('roles', $isEditing ? $account->roles->pluck('slug')->all() : []);
@endphp

<form method="POST" action="{{ $isEditing ? route('admin.users.update', $account) : route('admin.users.store') }}">
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <div class="settings-section">
        <div class="settings-section-title">
            <h3>Profile Information</h3>
            <p>The name and email address used to sign in to the portal.</p>
        </div>
        <div class="settings-section-body">
            <div class="settings-card">
                <div class="settings-fields">
                    <div class="settings-field settings-field-wide">
                        <label for="name">Full Name</label>
                        <input id="name" name="name" type="text" @class(['settings-input', 'is-invalid' => $errors->has('name')]) value="{{ old('name', $account->name ?? '') }}" required autofocus autocomplete="name">
                        @error('name') <p class="settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="settings-field settings-field-wide">
                        <label for="email">Email Address</label>
                        <input id="email" name="email" type="email" @class(['settings-input', 'is-invalid' => $errors->has('email')]) value="{{ old('email', $account->email ?? '') }}" required autocomplete="email">
                        @error('email') <p class="settings-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="settings-divider" aria-hidden="true"></div>

    <div class="settings-section">
        <div class="settings-section-title">
            <h3>Sign-in &amp; Campus</h3>
            <p>Users can sign in with their email, username or phone number. A disabled account cannot sign in anywhere.</p>
        </div>
        <div class="settings-section-body">
            <div class="settings-card">
                <div class="settings-fields">
                    <div class="settings-field">
                        <label for="username">Username <span class="dash-optional">optional</span></label>
                        <input id="username" name="username" type="text" maxlength="50" @class(['settings-input', 'is-invalid' => $errors->has('username')]) value="{{ old('username', $account->username ?? '') }}" autocomplete="off" placeholder="e.g. PIA-2026-0012">
                        @error('username') <p class="settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="settings-field">
                        <label for="phone">Phone <span class="dash-optional">optional</span></label>
                        <input id="phone" name="phone" type="tel" maxlength="30" @class(['settings-input', 'is-invalid' => $errors->has('phone')]) value="{{ old('phone', $account->phone ?? '') }}" autocomplete="off" placeholder="03XX XXXXXXX">
                        @error('phone') <p class="settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="settings-field">
                        <label for="campus_id">Campus <span class="dash-optional">optional</span></label>
                        <select id="campus_id" name="campus_id" @class(['settings-input', 'is-invalid' => $errors->has('campus_id')])>
                            <option value="">No campus</option>
                            @foreach ($campuses as $campus)
                                <option value="{{ $campus->id }}" @selected((string) old('campus_id', $account->campus_id ?? '') === (string) $campus->id)>{{ $campus->name }} ({{ $campus->code }})</option>
                            @endforeach
                        </select>
                        @error('campus_id') <p class="settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="settings-field">
                        <span class="settings-label">Account status</span>
                        <input type="hidden" name="is_active" value="0">
                        <label class="dash-switch">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active ?? true))>
                            <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                            <span>Active — can sign in</span>
                        </label>
                        @error('is_active') <p class="settings-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="settings-divider" aria-hidden="true"></div>

    <div class="settings-section">
        <div class="settings-section-title">
            <h3>{{ $isEditing ? 'Change Password' : 'Password' }}</h3>
            <p>{{ $isEditing ? 'Leave both fields blank to keep the current password.' : 'At least 8 characters. Share it with the user securely.' }}</p>
        </div>
        <div class="settings-section-body">
            <div class="settings-card">
                <div class="settings-fields">
                    <div class="settings-field">
                        <label for="password">{{ $isEditing ? 'New Password' : 'Password' }}</label>
                        <input id="password" name="password" type="password" @class(['settings-input', 'is-invalid' => $errors->has('password')]) @required(! $isEditing) autocomplete="new-password">
                        @error('password') <p class="settings-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="settings-field">
                        <label for="password_confirmation">Confirm Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="settings-input" @required(! $isEditing) autocomplete="new-password">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="settings-divider" aria-hidden="true"></div>

    <div class="settings-section">
        <div class="settings-section-title">
            <h3>Roles</h3>
            <p>Roles decide which portals and pages this account can open.</p>
        </div>
        <div class="settings-section-body">
            <div class="settings-card settings-card-with-actions">
                <fieldset class="dash-role-grid">
                    <legend class="dash-visually-hidden">Roles</legend>
                    @foreach ($roles as $role)
                        <label class="dash-role-option">
                            <input type="checkbox" name="roles[]" value="{{ $role->slug }}" @checked(in_array($role->slug, $selectedRoles, true))>
                            <span class="dash-role-check" aria-hidden="true">@include('partials.dashboard-icon', ['name' => 'check'])</span>
                            <span class="dash-role-text">
                                <span class="dash-role-name">{{ $role->name }}</span>
                                @if ($role->description)
                                    <span class="dash-role-desc">{{ $role->description }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </fieldset>
                @error('roles') <p class="settings-error">{{ $message }}</p> @enderror
                @error('roles.*') <p class="settings-error">{{ $message }}</p> @enderror
            </div>
            <div class="settings-actions">
                <a href="{{ $isEditing ? route('admin.users.show', $account) : route('admin.users') }}" class="dash-btn dash-btn-ghost">Cancel</a>
                <button type="submit" class="dash-btn dash-btn-primary">
                    @include('partials.dashboard-icon', ['name' => $isEditing ? 'pen' : 'plus'])
                    {{ $isEditing ? 'Save Changes' : 'Create User' }}
                </button>
            </div>
        </div>
    </div>
</form>

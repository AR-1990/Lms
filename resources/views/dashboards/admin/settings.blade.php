@extends('layouts.dashboard')

@section('title', 'System Settings')
@section('page_heading', 'System Settings')

@section('extra_css')
<link rel="stylesheet" href="{{ asset('css/admin-settings.css') }}">
@endsection

@section('content')
<div class="settings-shell">
    <section class="settings-hero">
        <h1>System Settings</h1>
        <p>Configure school identity, regional timezone, and billing currency from one clean control panel. These values will feed receipts, challans, branding, and future modules.</p>
    </section>

    @if (session('status'))
        <section class="settings-alert">
            {{ session('status') }}
        </section>
    @endif

    @if ($errors->any())
        <section class="dash-panel settings-panel">
            <div class="dash-panel-head">
                <h2>Please review the highlighted fields</h2>
            </div>
            <ul class="dash-list">
                @foreach ($errors->all() as $error)
                    <li><span>{{ $error }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($data['countries']->isEmpty() || $data['regions']->isEmpty() || $data['currencies']->isEmpty())
        <section class="settings-help settings-empty">
            Reference dropdown data is still missing. Default countries, regions, and currencies are being prepared for this screen.
        </section>
    @endif

    <section class="dash-stats">
        <article class="dash-stat">
            <span class="dash-stat-label">Timezone</span>
            <div class="settings-stat-note">
                <strong class="dash-stat-value dash-stat-value-sm">{{ $data['settings']['timezone'] ?? config('app.timezone') }}</strong>
                <small>Runtime server timezone</small>
            </div>
        </article>
        <article class="dash-stat">
            <span class="dash-stat-label">Currency</span>
            <div class="settings-stat-note">
                <strong class="dash-stat-value dash-stat-value-sm">{{ $data['settings']['currency']['code'] ?? 'Not Set' }}</strong>
                <small>Receipt and billing base</small>
            </div>
        </article>
        <article class="dash-stat">
            <span class="dash-stat-label">Region</span>
            <div class="settings-stat-note">
                <strong class="dash-stat-value dash-stat-value-sm">{{ $data['settings']['region']['name'] ?? 'Not Set' }}</strong>
                <small>Selected operational region</small>
            </div>
        </article>
        <article class="dash-stat">
            <span class="dash-stat-label">Logo</span>
            <div class="settings-stat-note">
                <strong class="dash-stat-value dash-stat-value-sm">{{ $data['settings']['logo']['file_name'] ?? 'Pending' }}</strong>
                <small>Brand asset status</small>
            </div>
        </article>
    </section>

    <div class="settings-grid">
        <section class="dash-panel settings-panel">
            <div class="dash-panel-head">
                <h2>School Profile</h2>
            </div>

            <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="settings-form">
                @csrf
                @method('PUT')

                <div class="settings-field">
                    <label for="school_name">School Name</label>
                    <input
                        id="school_name"
                        name="school_name"
                        type="text"
                        class="settings-input"
                        value="{{ old('school_name', $data['settings']['school_name']) }}"
                        required
                    >
                </div>

                <div class="settings-field">
                    <label for="school_address">School Address</label>
                    <textarea
                        id="school_address"
                        name="school_address"
                        class="settings-textarea"
                        required
                    >{{ old('school_address', $data['settings']['school_address']) }}</textarea>
                </div>

                <div class="settings-field">
                    <label for="school_phone">Phone Number</label>
                    <input
                        id="school_phone"
                        name="school_phone"
                        type="text"
                        class="settings-input"
                        value="{{ old('school_phone', $data['settings']['school_phone']) }}"
                        required
                    >
                </div>

                <div class="settings-field">
                    <label for="country_id">Country</label>
                    <select id="country_id" name="country_id" class="settings-select" required>
                        <option value="" disabled @selected((int) old('country_id', $data['settings']['country']['id'] ?? 0) === 0)>Select country</option>
                        @foreach ($data['countries'] as $country)
                            <option value="{{ $country->id }}" @selected((int) old('country_id', $data['settings']['country']['id'] ?? 0) === $country->id)>
                                {{ $country->name }} ({{ $country->iso2 }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="settings-field">
                    <label for="region_id">Region</label>
                    <select id="region_id" name="region_id" class="settings-select" required>
                        <option value="" disabled @selected((int) old('region_id', $data['settings']['region']['id'] ?? 0) === 0)>Select region</option>
                        @foreach ($data['regions'] as $region)
                            <option value="{{ $region->id }}" @selected((int) old('region_id', $data['settings']['region']['id'] ?? 0) === $region->id)>
                                {{ $region->name }} - {{ $region->timezone }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="settings-field">
                    <label for="currency_id">Currency</label>
                    <select id="currency_id" name="currency_id" class="settings-select" required>
                        <option value="" disabled @selected((int) old('currency_id', $data['settings']['currency']['id'] ?? 0) === 0)>Select currency</option>
                        @foreach ($data['currencies'] as $currency)
                            <option value="{{ $currency->id }}" @selected((int) old('currency_id', $data['settings']['currency']['id'] ?? 0) === $currency->id)>
                                {{ $currency->name }} ({{ $currency->code }}) {{ $currency->symbol }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="settings-field">
                    <label for="logo">School Logo</label>
                    <input id="logo" name="logo" type="file" class="settings-input" accept="image/*">
                </div>

                <div class="settings-actions">
                    <button type="submit" class="settings-submit">Save Settings</button>
                </div>
            </form>
        </section>

        <section class="dash-panel settings-panel">
            <div class="dash-panel-head">
                <h2>Current Preview</h2>
            </div>

            <div class="settings-logo">
                @if ($data['settings']['logo']['data_uri'])
                    <img src="{{ $data['settings']['logo']['data_uri'] }}" alt="School logo preview">
                @else
                    <strong>No logo uploaded yet</strong>
                @endif
            </div>

            <ul class="settings-meta">
                <li>
                    <span>School</span>
                    <strong>{{ $data['settings']['school_name'] ?? 'Not Set' }}</strong>
                </li>
                <li>
                    <span>Phone</span>
                    <strong>{{ $data['settings']['school_phone'] ?? 'Not Set' }}</strong>
                </li>
                <li>
                    <span>Country</span>
                    <strong>{{ $data['settings']['country']['name'] ?? 'Not Set' }}</strong>
                </li>
                <li>
                    <span>Region</span>
                    <strong>{{ $data['settings']['region']['name'] ?? 'Not Set' }}</strong>
                </li>
                <li>
                    <span>Server Timezone</span>
                    <strong>{{ $data['settings']['timezone'] ?? config('app.timezone') }}</strong>
                </li>
                <li>
                    <span>Currency</span>
                    <strong>{{ $data['settings']['currency']['name'] ?? 'Not Set' }}</strong>
                </li>
            </ul>

            <div class="settings-help settings-help-offset">
                The selected region timezone is applied at runtime, and the school profile data can be reused in future receipt, challan, and branding modules.
            </div>
        </section>
    </div>
</div>
@endsection

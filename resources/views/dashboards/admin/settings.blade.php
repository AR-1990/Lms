@extends('layouts.dashboard')

@section('title', 'System Settings')
@section('page_heading', 'System Settings')

@section('extra_css')
<style>
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.5rem;
    }

    .settings-form {
        display: grid;
        gap: 1rem;
    }

    .settings-field {
        display: grid;
        gap: 0.45rem;
    }

    .settings-field label {
        color: #0f172a;
        font-size: 0.9rem;
        font-weight: 700;
    }

    .settings-input,
    .settings-select,
    .settings-textarea {
        width: 100%;
        border: 1px solid #d7deeb;
        border-radius: 14px;
        background: #fff;
        color: #0f172a;
        padding: 0.9rem 1rem;
    }

    .settings-textarea {
        min-height: 110px;
        resize: vertical;
    }

    .settings-actions {
        display: flex;
        justify-content: flex-end;
    }

    .settings-submit {
        border: 0;
        border-radius: 999px;
        background: #0f172a;
        color: #fff;
        cursor: pointer;
        font-weight: 700;
        padding: 0.9rem 1.4rem;
    }

    .settings-help,
    .settings-alert {
        border-radius: 16px;
        padding: 1rem 1.1rem;
    }

    .settings-help {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .settings-alert {
        background: #ecfdf5;
        color: #047857;
    }

    .settings-logo {
        align-items: center;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 18px;
        display: flex;
        justify-content: center;
        min-height: 180px;
        overflow: hidden;
        padding: 1rem;
    }

    .settings-logo img {
        max-height: 150px;
        object-fit: contain;
    }

    .settings-meta {
        display: grid;
        gap: 0.85rem;
        margin-top: 1rem;
    }

    .settings-meta li {
        align-items: center;
        display: flex;
        justify-content: space-between;
    }

    @media (max-width: 900px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<section class="dash-page-intro">
    <h1>System Settings</h1>
    <p>Configure school identity, timezone region, and billing currency for future receipts and branding.</p>
</section>

@if (session('status'))
    <section class="settings-alert">
        {{ session('status') }}
    </section>
@endif

@if ($errors->any())
    <section class="dash-panel">
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

<section class="dash-stats">
    <article class="dash-stat">
        <span class="dash-stat-label">Timezone</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $data['settings']['timezone'] ?? config('app.timezone') }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Currency</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $data['settings']['currency']['code'] ?? 'Not Set' }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Region</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $data['settings']['region']['name'] ?? 'Not Set' }}</strong>
    </article>
    <article class="dash-stat">
        <span class="dash-stat-label">Logo</span>
        <strong class="dash-stat-value dash-stat-value-sm">{{ $data['settings']['logo']['file_name'] ?? 'Pending' }}</strong>
    </article>
</section>

<div class="settings-grid">
    <section class="dash-panel">
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

    <section class="dash-panel">
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

        <div class="settings-help" style="margin-top: 1rem;">
            Selected region ki timezone runtime par apply hogi, aur school profile data future receipt, challan, aur branding modules mein reuse ki ja sakti hai.
        </div>
    </section>
</div>
@endsection

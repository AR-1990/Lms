@extends('layouts.dashboard')

@section('title', 'System Settings')
@section('page_heading', 'System Settings')

@section('extra_css')
<link rel="stylesheet" href="{{ asset('css/admin-settings.css') }}?v={{ filemtime(public_path('css/admin-settings.css')) }}">
@endsection

@section('content')
@php
    $settings = $data['settings'];
@endphp

<div class="settings-shell">
    @if ($data['countries']->isEmpty() || $data['regions']->isEmpty() || $data['currencies']->isEmpty())
        <div class="settings-notice">
            Reference dropdown data is still missing. Default countries, regions, and currencies are being prepared for this screen.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="settings-section">
            <div class="settings-section-title">
                <h3>School Profile</h3>
                <p>The school identity used on receipts, challans and portal branding.</p>
            </div>

            <div class="settings-section-body">
                <div class="settings-card">
                    <div class="settings-fields">
                        <div class="settings-field settings-field-wide">
                            <label for="school_name">School Name</label>
                            <input id="school_name" name="school_name" type="text" @class(['settings-input', 'is-invalid' => $errors->has('school_name')]) value="{{ old('school_name', $settings['school_name']) }}" required autocomplete="organization">
                            @error('school_name') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field settings-field-wide">
                            <label for="school_address">School Address</label>
                            <textarea id="school_address" name="school_address" @class(['settings-input', 'settings-textarea', 'is-invalid' => $errors->has('school_address')]) required>{{ old('school_address', $settings['school_address']) }}</textarea>
                            @error('school_address') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="school_phone">Phone Number</label>
                            <input id="school_phone" name="school_phone" type="text" @class(['settings-input', 'is-invalid' => $errors->has('school_phone')]) value="{{ old('school_phone', $settings['school_phone']) }}" required autocomplete="tel">
                            @error('school_phone') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="school_tagline">Tagline</label>
                            <input id="school_tagline" name="school_tagline" type="text" @class(['settings-input', 'is-invalid' => $errors->has('school_tagline')]) value="{{ old('school_tagline', $settings['school_tagline']) }}">
                            @error('school_tagline') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="admissions_phone">Admissions phone</label>
                            <input id="admissions_phone" name="admissions_phone" type="text" @class(['settings-input', 'is-invalid' => $errors->has('admissions_phone')]) value="{{ old('admissions_phone', $settings['admissions_phone']) }}">
                            @error('admissions_phone') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="whatsapp_number">WhatsApp number</label>
                            <input id="whatsapp_number" name="whatsapp_number" type="text" @class(['settings-input', 'is-invalid' => $errors->has('whatsapp_number')]) value="{{ old('whatsapp_number', $settings['whatsapp_number']) }}">
                            @error('whatsapp_number') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="contact_email">Contact email</label>
                            <input id="contact_email" name="contact_email" type="email" @class(['settings-input', 'is-invalid' => $errors->has('contact_email')]) value="{{ old('contact_email', $settings['contact_email']) }}">
                            @error('contact_email') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="admissions_email">Admissions email</label>
                            <input id="admissions_email" name="admissions_email" type="email" @class(['settings-input', 'is-invalid' => $errors->has('admissions_email')]) value="{{ old('admissions_email', $settings['admissions_email']) }}">
                            @error('admissions_email') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="office_hours">Office hours</label>
                            <input id="office_hours" name="office_hours" type="text" @class(['settings-input', 'is-invalid' => $errors->has('office_hours')]) value="{{ old('office_hours', $settings['office_hours']) }}">
                            @error('office_hours') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="visiting_hours">Visiting hours</label>
                            <input id="visiting_hours" name="visiting_hours" type="text" @class(['settings-input', 'is-invalid' => $errors->has('visiting_hours')]) value="{{ old('visiting_hours', $settings['visiting_hours']) }}">
                            @error('visiting_hours') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field settings-field-wide">
                            <label for="footer_text">Footer text</label>
                            <textarea id="footer_text" name="footer_text" @class(['settings-input', 'settings-textarea', 'is-invalid' => $errors->has('footer_text')])>{{ old('footer_text', $settings['footer_text']) }}</textarea>
                            @error('footer_text') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="logo">School Logo</label>
                            <input id="logo" name="logo" type="file" @class(['settings-input', 'settings-file', 'is-invalid' => $errors->has('logo')]) accept="image/*">
                            @error('logo') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-divider" aria-hidden="true"></div>

        <div class="settings-section">
            <div class="settings-section-title">
                <h3>Region &amp; Billing</h3>
                <p>The selected region's timezone is applied at runtime, and the currency drives all fee amounts.</p>
            </div>

            <div class="settings-section-body">
                <div class="settings-card settings-card-with-actions">
                    <div class="settings-fields">
                        <div class="settings-field">
                            <label for="country_id">Country</label>
                            <select id="country_id" name="country_id" @class(['settings-input', 'is-invalid' => $errors->has('country_id')]) required>
                                <option value="" disabled @selected((int) old('country_id', $settings['country']['id'] ?? 0) === 0)>Select country</option>
                                @foreach ($data['countries'] as $country)
                                    <option value="{{ $country->id }}" @selected((int) old('country_id', $settings['country']['id'] ?? 0) === $country->id)>
                                        {{ $country->name }} ({{ $country->iso2 }})
                                    </option>
                                @endforeach
                            </select>
                            @error('country_id') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field">
                            <label for="region_id">Region</label>
                            <select id="region_id" name="region_id" @class(['settings-input', 'is-invalid' => $errors->has('region_id')]) required>
                                <option value="" disabled @selected((int) old('region_id', $settings['region']['id'] ?? 0) === 0)>Select region</option>
                                @foreach ($data['regions'] as $region)
                                    <option value="{{ $region->id }}" @selected((int) old('region_id', $settings['region']['id'] ?? 0) === $region->id)>
                                        {{ $region->name }} - {{ $region->timezone }}
                                    </option>
                                @endforeach
                            </select>
                            @error('region_id') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="settings-field settings-field-wide">
                            <label for="currency_id">Currency</label>
                            <select id="currency_id" name="currency_id" @class(['settings-input', 'is-invalid' => $errors->has('currency_id')]) required>
                                <option value="" disabled @selected((int) old('currency_id', $settings['currency']['id'] ?? 0) === 0)>Select currency</option>
                                @foreach ($data['currencies'] as $currency)
                                    <option value="{{ $currency->id }}" @selected((int) old('currency_id', $settings['currency']['id'] ?? 0) === $currency->id)>
                                        {{ $currency->name }} ({{ $currency->code }}) {{ $currency->symbol }}
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id') <p class="settings-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    @if (session('status'))
                        <span class="settings-saved" role="status">Saved.</span>
                    @endif
                    <button type="submit" class="dash-btn dash-btn-primary">Save</button>
                </div>
            </div>
        </div>
    </form>

    <div class="settings-divider" aria-hidden="true"></div>

    <div class="settings-section">
        <div class="settings-section-title">
            <h3>Current Preview</h3>
            <p>How the saved settings currently resolve across the system.</p>
        </div>

        <div class="settings-section-body">
            <div class="settings-card">
                <div class="settings-preview">
                    <div class="settings-logo">
                        @if ($settings['logo']['data_uri'])
                            <img src="{{ $settings['logo']['data_uri'] }}" alt="School logo preview">
                        @else
                            <span>No logo uploaded yet</span>
                        @endif
                    </div>

                    <ul class="settings-meta">
                        <li><span>School</span><strong>{{ $settings['school_name'] ?? 'Not Set' }}</strong></li>
                        <li><span>Phone</span><strong>{{ $settings['school_phone'] ?? 'Not Set' }}</strong></li>
                        <li><span>Country</span><strong>{{ $settings['country']['name'] ?? 'Not Set' }}</strong></li>
                        <li><span>Region</span><strong>{{ $settings['region']['name'] ?? 'Not Set' }}</strong></li>
                        <li><span>Server Timezone</span><strong>{{ $settings['timezone'] ?? config('app.timezone') }}</strong></li>
                        <li><span>Currency</span><strong>{{ $settings['currency']['name'] ?? 'Not Set' }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

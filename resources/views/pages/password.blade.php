@extends('layouts.school')

@section('title', $mode === 'reset' ? 'Set a new password' : 'Forgot password')

@section('content')
{{-- Forgot / reset password: both forms post to the same API the mobile apps use (/api/forgot-password, /api/reset-password). --}}
<div class="login-page-wrapper">
    <div class="login-bg-mesh"></div>

    <div class="login-split-container login-single">
        <div class="login-form-panel">
            <div class="login-card-header">
                <a href="{{ route('home') }}" class="login-brand-logo">
                    <div class="login-brand-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    </div>
                    <div style="font-family: var(--font-heading); font-size: 1.2rem; font-weight: 900; color: var(--midnight); line-height: 1;">{{ $settings->school_name }}</div>
                </a>

                <h2 style="font-size: 1.85rem; font-weight: 900; color: var(--midnight); margin-bottom: 0.35rem;">{{ $mode === 'reset' ? 'Set a new password' : 'Forgot your password?' }}</h2>
                <p style="font-size: 0.9rem; color: #64748b; line-height: 1.5;">
                    {{ $mode === 'reset' ? 'Choose a new password of at least 8 characters. You will be signed out of every phone and browser.' : 'Enter your ID, email or phone number. If your account has an email address, we will send you a link to set a new password.' }}
                </p>
            </div>

            <form data-password-form data-mode="{{ $mode }}" data-url="{{ $mode === 'reset' ? route('api.password.update') : route('api.password.email') }}" data-login="{{ route('login') }}" novalidate>
                @csrf
                <div class="login-alert" data-password-error hidden></div>
                <div class="login-alert login-alert-ok" data-password-ok hidden></div>

                @if ($mode === 'reset')
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="form-group">
                        <label class="form-label" for="resetEmail">Email</label>
                        <input type="email" id="resetEmail" name="email" class="form-control" value="{{ $email }}" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="resetPassword">New password</label>
                        <input type="password" id="resetPassword" name="password" class="form-control" minlength="8" required autocomplete="new-password" autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="resetPasswordConfirm">Repeat new password</label>
                        <input type="password" id="resetPasswordConfirm" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password">
                    </div>
                @else
                    <div class="form-group">
                        <label class="form-label" for="forgotIdentifier">ID / Email / Phone</label>
                        <input type="text" id="forgotIdentifier" name="identifier" class="form-control" required autofocus autocomplete="username">
                    </div>
                @endif

                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; box-shadow: var(--shadow-amber);" data-password-submit>
                    <span>{{ $mode === 'reset' ? 'Save new password' : 'Send reset link' }}</span>
                </button>
            </form>

            <p style="margin-top: 1.5rem; text-align: center; font-size: 0.875rem;">
                <a href="{{ route('login') }}" style="color: var(--primary); font-weight: 700;">&larr; Back to sign in</a>
                @if ($settings->school_phone)
                    <span style="display: block; margin-top: 0.5rem; color: #64748b;">No email on your account? Call the school office on {{ $settings->school_phone }}.</span>
                @endif
            </p>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const form = document.querySelector('[data-password-form]');
    const error = form.querySelector('[data-password-error]');
    const ok = form.querySelector('[data-password-ok]');
    const button = form.querySelector('[data-password-submit]');

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        error.hidden = true;
        ok.hidden = true;
        button.disabled = true;

        const body = {};
        new FormData(form).forEach(function (value, key) { if (key !== '_token') { body[key] = value; } });

        fetch(form.dataset.url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json();
        }).then(function (envelope) {
            if (!envelope.success) {
                const fields = envelope.errors && envelope.errors.fields ? envelope.errors.fields : {};
                const first = Object.keys(fields)[0];
                error.textContent = first ? fields[first][0] : envelope.message;
                error.hidden = false;

                return;
            }

            ok.textContent = envelope.message;
            ok.hidden = false;

            if (form.dataset.mode === 'reset') {
                setTimeout(function () { window.location.href = form.dataset.login; }, 2500);
            } else {
                form.reset();
            }
        }).catch(function () {
            error.textContent = 'Something went wrong. Please try again.';
            error.hidden = false;
        }).finally(function () {
            button.disabled = false;
        });
    });
})();
</script>
@endsection

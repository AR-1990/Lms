@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')
@php($highlights = $page->section('highlights'))

<div class="login-page-wrapper">
    <div class="login-bg-mesh"></div>

    <div class="login-split-container">
        <!-- Left showcase (Website → Pages → Sign In) -->
        <div class="login-showcase-panel">
            @if ($page->hero_image_path)
                <div class="login-showcase-bg" style="background-image: url('{{ \App\Models\WebsitePage::imageUrl($page->hero_image_path) }}');"></div>
            @endif

            <div class="login-showcase-content">
                @if ($page->hero_pill)
                    <div class="login-showcase-badge">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>{{ $page->hero_pill }}</span>
                    </div>
                @endif

                <h1 class="login-showcase-title">{{ \App\Models\WebsitePage::highlight($page->hero_title) }}</h1>

                @if ($page->hero_text)
                    <p style="color: rgba(255, 255, 255, 0.85); font-size: 1.1rem; line-height: 1.7; max-width: 500px;">{{ $page->hero_text }}</p>
                @endif

                @if ($highlights && $highlights->items->isNotEmpty())
                    <div class="login-features-list">
                        @foreach ($highlights->items as $item)
                            <div class="login-feature-item">
                                <div class="login-feature-icon">@include('partials.site-icon', ['name' => $item->icon, 'size' => 20])</div>
                                <h4 style="color: #ffffff; font-size: 1rem; margin-bottom: 0.25rem;">{{ $item->title }}</h4>
                                <p style="color: rgba(255,255,255,0.7); font-size: 0.825rem; margin-bottom: 0;">{{ $item->body }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div style="position: relative; z-index: 5; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.12); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: rgba(255,255,255,0.75);">
                <span>&copy; {{ date('Y') }} {{ $settings->school_name }}</span>
                <a href="{{ route('home') }}" style="color: #fde047; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">&larr; Back to Website</a>
            </div>
        </div>

        <!-- Sign-in form: one form for everyone; the account decides the portal. -->
        <div class="login-form-panel">
            <div class="login-card-header">
                <a href="{{ route('home') }}" class="login-brand-logo">
                    <div class="login-brand-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    </div>
                    <div>
                        <div style="font-family: var(--font-heading); font-size: 1.2rem; font-weight: 900; color: var(--midnight); line-height: 1;">{{ $settings->school_name }}</div>
                        @if ($settings->school_tagline)
                            <div style="font-size: 0.68rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.1em;">{{ $settings->school_tagline }}</div>
                        @endif
                    </div>
                </a>

                <h2 style="font-size: 1.85rem; font-weight: 900; color: var(--midnight); margin-bottom: 0.35rem;">{{ $highlights?->title ?: 'Sign In' }}</h2>
                @if ($highlights?->description)
                    <p style="font-size: 0.9rem; color: #64748b; line-height: 1.5;">{{ $highlights->description }}</p>
                @endif
            </div>

            <form id="portalLoginForm" action="{{ route('login.submit') }}" method="POST">
                @csrf

                @if ($errors->any())
                    <div class="login-alert">{{ $errors->first() }}</div>
                @endif

                <div class="form-group">
                    <label class="form-label" for="loginUsernameInput">ID / Email</label>
                    <input type="text" id="loginUsernameInput" name="username" class="form-control" value="{{ old('username') }}" required autofocus autocomplete="username">
                </div>

                <div class="form-group">
                    <label class="form-label" for="loginPasswordInput">Password</label>
                    <div style="position: relative;">
                        <input type="password" id="loginPasswordInput" name="password" class="form-control" required autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Show or hide password">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #57534e; cursor: pointer;">
                        <input type="checkbox" name="remember" style="accent-color: var(--primary); width: 16px; height: 16px;">
                        <span>Keep me signed in</span>
                    </label>
                    <a href="{{ route('password.request') }}" style="font-size: 0.85rem; font-weight: 700; color: var(--primary);">Forgot password?</a>
                </div>

                <button type="submit" id="loginSubmitBtn" class="btn btn-primary btn-lg" style="width: 100%; box-shadow: var(--shadow-amber);">
                    <span>Sign In</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>

            @if ($highlights?->cta_label)
                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); text-align: center;">
                    <a href="{{ \App\Models\WebsitePage::link($highlights->cta_url) }}" class="btn btn-sm btn-outline" style="width: 100%;">{{ $highlights->cta_label }} &rarr;</a>
                </div>
            @endif
        </div>
    </div>
</div>

@endsection

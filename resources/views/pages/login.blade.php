@extends('layouts.school')

@section('title', 'Pinnacle Portal Sign In | Student LMS, Parent Accounts & Faculty')
@section('meta_description', 'Secure login gateway for Pinnacle International Academy. Access Student LMS, Parent fee accounting, Faculty gradebooks, and Admin portals.')

@section('content')

<div class="login-page-wrapper">
    <div class="login-bg-mesh"></div>

    <div class="login-split-container">
        <!-- Left Showcase Side -->
        <div class="login-showcase-panel">
            <div class="login-showcase-bg" style="background-image: url('{{ asset('images/facility-library.jpg') }}');"></div>

            <div class="login-showcase-content">
                <div class="login-showcase-badge">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>256-Bit SSL Encrypted Campus Network</span>
                </div>

                <h1 class="login-showcase-title">
                    Digital Learning & <br><span class="text-amber-gradient">Academic Portal</span>
                </h1>

                <p style="color: rgba(255, 255, 255, 0.85); font-size: 1.1rem; line-height: 1.7; max-width: 500px;">
                    Empowering students, parents, and faculty with unified digital classrooms, 1-Link fee settlements, real-time analytics, and seamless collaboration.
                </p>

                <!-- Key Feature Highlights Grid -->
                <div class="login-features-list">
                    <div class="login-feature-item">
                        <div class="login-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10M6 10h10"/></svg>
                        </div>
                        <h4 style="color: #ffffff; font-size: 1rem; margin-bottom: 0.25rem;">Smart LMS & Quizzes</h4>
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.825rem; margin-bottom: 0;">Interactive video lectures & auto-graded tests.</p>
                    </div>

                    <div class="login-feature-item">
                        <div class="login-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                        </div>
                        <h4 style="color: #ffffff; font-size: 1rem; margin-bottom: 0.25rem;">1-Link Fee Payments</h4>
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.825rem; margin-bottom: 0;">Instant online voucher clearance & receipts.</p>
                    </div>

                    <div class="login-feature-item">
                        <div class="login-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                        </div>
                        <h4 style="color: #ffffff; font-size: 1rem; margin-bottom: 0.25rem;">Live RFID Attendance</h4>
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.825rem; margin-bottom: 0;">Instant SMS & in-app alerts on campus entry.</p>
                    </div>

                    <div class="login-feature-item">
                        <div class="login-feature-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <h4 style="color: #ffffff; font-size: 1rem; margin-bottom: 0.25rem;">Parent-Teacher Desk</h4>
                        <p style="color: rgba(255,255,255,0.7); font-size: 0.825rem; margin-bottom: 0;">Direct communication & progress reviews.</p>
                    </div>
                </div>
            </div>

            <div style="position: relative; z-index: 5; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.12); display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: rgba(255,255,255,0.75);">
                <span>&copy; {{ date('Y') }} Pinnacle International Academy</span>
                <a href="{{ route('home') }}" style="color: #fde047; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem;">
                    &larr; Back to Main Website
                </a>
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="login-form-panel">
            <div class="login-card-header">
                <a href="{{ route('home') }}" class="login-brand-logo">
                    <div class="login-brand-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                        </svg>
                    </div>
                    <div>
                        <div style="font-family: var(--font-heading); font-size: 1.2rem; font-weight: 900; color: var(--midnight); line-height: 1;">PINNACLE</div>
                        <div style="font-size: 0.68rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.1em;">Unified Portal Access</div>
                    </div>
                </a>

                <h2 style="font-size: 1.85rem; font-weight: 900; color: var(--midnight); margin-bottom: 0.35rem;">Sign In to Your Account</h2>
                <p id="loginRoleDescription" style="font-size: 0.9rem; color: #64748b; line-height: 1.5;">
                    Access class schedule, video lectures, homework submissions, and quiz reports.
                </p>
            </div>

            <!-- Role Selector Tabs -->
            <div class="role-selector-tabs" role="tablist">
                <button type="button" class="role-chip {{ ($role ?? 'student') === 'student' ? 'active' : '' }}" data-role="student" role="tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    <span>Student</span>
                </button>

                <button type="button" class="role-chip {{ ($role ?? '') === 'parent' ? 'active' : '' }}" data-role="parent" role="tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Parent</span>
                </button>

                <button type="button" class="role-chip {{ ($role ?? '') === 'teacher' ? 'active' : '' }}" data-role="teacher" role="tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><circle cx="12" cy="10" r="2"/></svg>
                    <span>Faculty</span>
                </button>

                <button type="button" class="role-chip {{ ($role ?? '') === 'accounts' ? 'active' : '' }}" data-role="accounts" role="tab">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                    <span>Accounts</span>
                </button>
            </div>

            <!-- Single-Click Quick Autofill Helper -->
            <div class="demo-credentials-box">
                <div style="font-size: 0.8rem; font-weight: 800; color: #9a3412; display: flex; align-items: center; gap: 0.4rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span>Instant Demo Autofill (Click to Test):</span>
                </div>
                <div class="demo-chips-group">
                    <button type="button" class="demo-chip-btn" data-fill-role="student">👨‍🎓 Student Demo</button>
                    <button type="button" class="demo-chip-btn" data-fill-role="parent">👨‍👩‍👧 Parent Demo</button>
                    <button type="button" class="demo-chip-btn" data-fill-role="teacher">👩‍🏫 Teacher Demo</button>
                    <button type="button" class="demo-chip-btn" data-fill-role="accounts">💼 Accounts Demo</button>
                </div>
            </div>

            <!-- Interactive Login Form -->
            <form id="portalLoginForm" action="{{ route('login.submit') }}" method="POST">
                @csrf
                <input type="hidden" name="role" id="loginRoleInput" value="{{ old('role', $role ?? 'student') }}">

                @if ($errors->any())
                    <div class="login-alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="form-group">
                    <label class="form-label" for="loginUsernameInput" id="loginUsernameLabel">Student Roll No / ID</label>
                    <input type="text" id="loginUsernameInput" name="username" class="form-control" placeholder="e.g. student@lms.test" value="{{ old('username', 'student@lms.test') }}" required autofocus>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                        <label class="form-label" for="loginPasswordInput" style="margin-bottom: 0;">Password / PIN</label>
                        <a href="javascript:void(0)" onclick="alert('For password resets, please contact the IT Administration office at it.support@pinnacle.edu.pk or +92 300 1234567.')" style="font-size: 0.8rem; color: var(--primary); font-weight: 700;">
                            Forgot Password?
                        </a>
                    </div>
                    <div style="position: relative;">
                        <input type="password" id="loginPasswordInput" name="password" class="form-control" placeholder="••••••••" value="password" required>
                        <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #57534e; cursor: pointer;">
                        <input type="checkbox" name="remember" checked style="accent-color: var(--primary); width: 16px; height: 16px;">
                        <span>Keep me signed in</span>
                    </label>
                    <span style="font-size: 0.775rem; color: #94a3b8; font-weight: 600;">v2.4 Active</span>
                </div>

                <button type="submit" id="loginSubmitBtn" class="btn btn-primary btn-lg" style="width: 100%; box-shadow: var(--shadow-amber);">
                    <span>Sign In to Portal</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>

            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); text-align: center;">
                <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.75rem;">
                    New student or looking for admission enrollment?
                </p>
                <a href="{{ route('admissions') }}" class="btn btn-sm btn-outline" style="width: 100%;">
                    Apply For Fresh Admission 2026-27 &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

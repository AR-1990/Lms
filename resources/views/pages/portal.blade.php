@extends('layouts.school')

@section('title', 'Learning Management & Accounts Portal | Pinnacle Academy')
@section('meta_description', 'Access Pinnacle Academy digital ecosystem. Student LMS, Parent Fee & Grade Portal, Teacher Gradebook, Accounts, and Administration Gateways.')

@section('content')

<!-- Portals Hero Banner -->
<section class="page-hero-banner" style="background-image: linear-gradient(135deg, rgba(28,25,23,0.94) 0%, rgba(28,25,23,0.82) 100%), url('{{ asset('images/hero-robotics.jpg') }}');">
    <div class="container">
        <div class="hero-banner-content">
            <div class="slide-pill">
                <span class="slide-pill-dot"></span>
                <span>Pinnacle Cloud Ecosystem &bull; LMS & Accounts</span>
            </div>
            <h1 class="hero-banner-title">
                Unified Learning & <span class="text-amber-gradient">Accounts Gateway</span>
            </h1>
            <p class="hero-banner-desc">
                Secure digital portals connecting students, parents, educators, and accountants. Access lesson modules, fee billing, grade cards, and attendance records.
            </p>
        </div>
    </div>
</section>

<!-- 5 Portal Gateways -->
<section class="section section-white">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Select Your Access Gateway</span>
            <h2 class="section-title">One Platform, Seamless Digital Experience</h2>
            <p class="section-desc">Choose your designated portal below to sign in with your verified institutional credentials.</p>
        </div>

        <div class="grid-3">
            <!-- 1. Student LMS Portal -->
            <div class="portal-card" data-reveal="fade-up">
                <div class="portal-icon" style="background: var(--primary-light); color: var(--primary);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <h3 class="card-title">Student LMS Portal</h3>
                <p class="card-body" style="margin-bottom: 1.5rem;">
                    Access digital video lectures, homework submissions, STEM coding assignments, quiz results, and online class schedules.
                </p>
                <div style="margin-top: auto;">
                    <a href="{{ route('login', ['role' => 'student']) }}" class="btn btn-primary" style="width: 100%;">
                        Student Sign In &rarr;
                    </a>
                </div>
            </div>

            <!-- 2. Parent & Fee Accounts Portal -->
            <div class="portal-card" data-reveal="fade-up" style="transition-delay: 0.15s;">
                <div class="portal-icon" style="background: var(--amber-light); color: var(--amber);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h3 class="card-title">Parent & Fee Portal</h3>
                <p class="card-body" style="margin-bottom: 1.5rem;">
                    Download monthly fee challans, view live 1-Link online fee payments, inspect daily attendance, and message teachers directly.
                </p>
                <div style="margin-top: auto;">
                    <a href="{{ route('login', ['role' => 'parent']) }}" class="btn btn-primary" style="width: 100%;">
                        Parent Login & Billing &rarr;
                    </a>
                </div>
            </div>

            <!-- 3. Teacher & Staff Workstation -->
            <div class="portal-card" data-reveal="fade-up" style="transition-delay: 0.3s;">
                <div class="portal-icon" style="background: var(--emerald-light); color: var(--emerald);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h10"/><path d="M7 12h10"/><path d="M7 17h10"/></svg>
                </div>
                <h3 class="card-title">Teacher Gradebook</h3>
                <p class="card-body" style="margin-bottom: 1.5rem;">
                    Upload exam marks, manage lesson plans, mark RFID attendance, post digital homework, and schedule parent conferences.
                </p>
                <div style="margin-top: auto;">
                    <a href="{{ route('login', ['role' => 'teacher']) }}" class="btn btn-primary" style="width: 100%;">
                        Faculty Sign In &rarr;
                    </a>
                </div>
            </div>

            <!-- 4. Fee & Financial Accounts -->
            <div class="portal-card" data-reveal="fade-up">
                <div class="portal-icon" style="background: var(--primary-light); color: var(--primary);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <h3 class="card-title">Accounts & Bursar</h3>
                <p class="card-body" style="margin-bottom: 1.5rem;">
                    Institutional payroll, fee reconciliation, discount ledger, vendor invoicing, 1-Link bank deposits, and audit reporting.
                </p>
                <div style="margin-top: auto;">
                    <a href="{{ route('login', ['role' => 'accounts']) }}" class="btn btn-primary" style="width: 100%;">
                        Accounts Sign In &rarr;
                    </a>
                </div>
            </div>

            <!-- 5. Admin & Principal Desk -->
            <div class="portal-card" data-reveal="fade-up" style="transition-delay: 0.15s;">
                <div class="portal-icon" style="background: #f5f3ff; color: #7c3aed;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <h3 class="card-title">Principal & Admin Desk</h3>
                <p class="card-body" style="margin-bottom: 1.5rem;">
                    High-level analytics, admission approvals, staff payroll clearance, timetable builder, and whole-school notification broadcast.
                </p>
                <div style="margin-top: auto;">
                    <a href="{{ route('login', ['role' => 'accounts']) }}" class="btn btn-primary" style="width: 100%;">
                        Administration Sign In &rarr;
                    </a>
                </div>
            </div>

            <!-- 6. Helpdesk & Support -->
            <div class="portal-card" data-reveal="fade-up" style="transition-delay: 0.3s;">
                <div class="portal-icon" style="background: #fef2f2; color: #ef4444;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                </div>
                <h3 class="card-title">IT & LMS Helpdesk</h3>
                <p class="card-body" style="margin-bottom: 1.5rem;">
                    Forgot your student ID or password? Submit an account reset request or consult our portal user manual.
                </p>
                <div style="margin-top: auto;">
                    <a href="{{ route('contact') }}" class="btn btn-outline" style="width: 100%;">
                        Contact IT Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

<!-- Top Information Bar -->
<div class="topbar">
    <div class="container topbar-wrapper">
        <div class="topbar-info">
            <div class="topbar-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                <a href="tel:+923001234567">+92 300 1234567</a>
            </div>
            <div class="topbar-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                <a href="mailto:admissions@pinnacle.edu.pk">admissions@pinnacle.edu.pk</a>
            </div>
            <div class="topbar-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>Mon - Fri: 8:00 AM - 3:30 PM</span>
            </div>
        </div>

        <div class="topbar-actions">
            <a href="{{ route('login') }}" class="topbar-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Portal Sign In</span>
            </a>
        </div>
    </div>
</div>

<!-- Main Sticky Navigation Bar -->
<header class="site-header" id="mainHeader">
    <div class="container nav-container">
        <!-- School Brand Identity -->
        <a href="{{ route('home') }}" class="brand" aria-label="Pinnacle International Academy Home">
            <div class="brand-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                </svg>
            </div>
            <div class="brand-text">
                <span class="brand-name">PINNACLE</span>
                <span class="brand-tagline">International Academy</span>
            </div>
        </a>

        <!-- Desktop Navigation Menu -->
        <nav aria-label="Primary Navigation">
            <ul class="nav-menu">
                <li><a href="{{ route('home') }}" class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">Home</a></li>
                <li><a href="{{ route('about') }}" class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}">About Us</a></li>
                <li><a href="{{ route('academics') }}" class="nav-link {{ request()->routeIs('academics') ? 'active' : '' }}">Academics</a></li>
                <li><a href="{{ route('admissions') }}" class="nav-link {{ request()->routeIs('admissions') ? 'active' : '' }}">Admissions</a></li>
                <li><a href="{{ route('campus') }}" class="nav-link {{ request()->routeIs('campus') ? 'active' : '' }}">Campus Life</a></li>
                <li><a href="{{ route('news') }}" class="nav-link {{ request()->routeIs('news') ? 'active' : '' }}">News & Events</a></li>
                <li><a href="{{ route('contact') }}" class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}">Contact</a></li>
                <li><a href="{{ route('portal') }}" class="nav-link {{ request()->routeIs('portal') ? 'active' : '' }}">Portals</a></li>
            </ul>
        </nav>

        <!-- Right Call to Actions -->
        <div class="nav-actions">
            <a href="{{ route('login') }}" class="btn btn-outline btn-sm btn-portal-desktop">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Sign In</span>
            </a>
            <a href="{{ route('admissions') }}" class="btn btn-primary btn-sm">
                Apply Now
            </a>
            <button class="menu-toggle" id="mobileMenuToggle" aria-label="Toggle Navigation Menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
            </button>
        </div>
    </div>
</header>

<!-- Mobile Navigation Drawer -->
<div class="mobile-overlay" id="mobileOverlay"></div>
<div class="mobile-drawer" id="mobileDrawer">
    <div class="drawer-header">
        <div class="brand">
            <div class="brand-icon" style="width: 38px; height: 38px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            </div>
            <div class="brand-text">
                <span class="brand-name" style="font-size: 1.15rem;">PINNACLE</span>
            </div>
        </div>
        <button class="drawer-close" id="drawerCloseBtn" aria-label="Close Navigation">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>

    <ul class="drawer-menu">
        <li><a href="{{ route('home') }}" class="drawer-link {{ request()->routeIs('home') ? 'active' : '' }}">Home</a></li>
        <li><a href="{{ route('about') }}" class="drawer-link {{ request()->routeIs('about') ? 'active' : '' }}">About Us</a></li>
        <li><a href="{{ route('academics') }}" class="drawer-link {{ request()->routeIs('academics') ? 'active' : '' }}">Academics</a></li>
        <li><a href="{{ route('admissions') }}" class="drawer-link {{ request()->routeIs('admissions') ? 'active' : '' }}">Admissions</a></li>
        <li><a href="{{ route('campus') }}" class="drawer-link {{ request()->routeIs('campus') ? 'active' : '' }}">Campus Life</a></li>
        <li><a href="{{ route('news') }}" class="drawer-link {{ request()->routeIs('news') ? 'active' : '' }}">News & Events</a></li>
        <li><a href="{{ route('contact') }}" class="drawer-link {{ request()->routeIs('contact') ? 'active' : '' }}">Contact</a></li>
        <li><a href="{{ route('portal') }}" class="drawer-link {{ request()->routeIs('portal') ? 'active' : '' }}">Portals Hub</a></li>
        <li><a href="{{ route('login') }}" class="drawer-link {{ request()->routeIs('login') ? 'active' : '' }}" style="color: var(--primary);">Portal Sign In &rarr;</a></li>
    </ul>

    <div style="margin-top: auto; padding-top: 2rem;">
        <a href="{{ route('admissions') }}" class="btn btn-primary" style="width: 100%;">Apply For Admission</a>
    </div>
</div>

<header class="dash-topbar">
    <button type="button" class="dash-menu-btn" id="dashMenuToggle" aria-label="Toggle menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
    </button>

    <div class="dash-topbar-title">
        @yield('page_heading', 'Dashboard')
    </div>

    <div class="dash-topbar-user">
        <div class="dash-user-meta">
            <div class="dash-user-name">{{ $user->name }}</div>
            <div class="dash-user-email">{{ $user->email }}</div>
        </div>
        <div class="dash-user-avatar" aria-hidden="true">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
    </div>
</header>

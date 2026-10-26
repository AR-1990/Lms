<aside class="dash-sidebar" id="dashSidebar" aria-label="Sidebar">
    <div class="dash-sidebar-head">
        <a href="{{ route('dashboard') }}" class="dash-brand" aria-label="Dashboard home">
            <span class="dash-brand-mark">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z" opacity=".7"/></svg>
            </span>
            <span class="dash-brand-text">
                <span class="dash-brand-name">Pinnacle</span>
                <span class="dash-brand-role">{{ $navigation['role_label'] }}</span>
            </span>
        </a>
        <button type="button" class="dash-icon-btn dash-collapse-btn" data-sidebar-collapse aria-controls="dashSidebar" aria-label="Collapse sidebar">
            @include('partials.dashboard-icon', ['name' => 'collapse'])
        </button>
    </div>

    <nav class="dash-nav" aria-label="Dashboard navigation">
        <span class="dash-nav-heading">Menu</span>
        @foreach ($navigation['links'] as $link)
            <a
                href="{{ $link['url'] }}"
                @class(['dash-nav-link', 'is-active' => $link['active']])
                @if ($link['active']) aria-current="page" @endif
                data-tooltip="{{ $link['label'] }}"
            >
                <span class="dash-nav-tile dash-tone-{{ $link['tone'] }}">
                    @include('partials.dashboard-icon', ['name' => $link['icon'], 'class' => 'dash-nav-icon'])
                </span>
                <span class="dash-nav-label">{{ $link['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="dash-sidebar-foot">
        <a href="{{ route('guide') }}" class="dash-nav-link {{ ($active ?? null) === 'guide' ? 'is-active' : '' }}" data-tooltip="User Guide" @if (($active ?? null) === 'guide') aria-current="page" @endif>
            <span class="dash-nav-tile dash-tone-amber">
                @include('partials.dashboard-icon', ['name' => 'book', 'class' => 'dash-nav-icon'])
            </span>
            <span class="dash-nav-label">User Guide</span>
        </a>

        <a href="{{ route('home') }}" class="dash-nav-link" data-tooltip="Main Website">
            <span class="dash-nav-tile dash-tone-sky">
                @include('partials.dashboard-icon', ['name' => 'globe', 'class' => 'dash-nav-icon'])
            </span>
            <span class="dash-nav-label">Main Website</span>
        </a>

        <div class="dash-dropdown" data-dropdown>
            <button type="button" class="dash-account" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false">
                <span class="dash-avatar" aria-hidden="true">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <span class="dash-account-meta">
                    <span class="dash-account-name">{{ $user->name }}</span>
                    <span class="dash-account-email">{{ $user->email }}</span>
                </span>
                @include('partials.dashboard-icon', ['name' => 'chevron-down', 'class' => 'dash-account-chevron'])
            </button>

            <div class="dash-dropdown-panel" role="menu" data-dropdown-panel>
                <div class="dash-dropdown-header">Manage Account</div>
                <a href="{{ route('home') }}" class="dash-dropdown-link" role="menuitem">
                    @include('partials.dashboard-icon', ['name' => 'globe'])
                    Main Website
                </a>
                <div class="dash-dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dash-dropdown-link dash-dropdown-link-danger" role="menuitem">
                        @include('partials.dashboard-icon', ['name' => 'logout'])
                        Log Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>

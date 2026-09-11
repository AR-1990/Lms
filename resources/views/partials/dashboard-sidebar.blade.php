<aside class="dash-sidebar" id="dashSidebar">
    <div class="dash-sidebar-brand">
        <div class="dash-brand-mark">P</div>
        <div>
            <div class="dash-brand-name">Pinnacle</div>
            <div class="dash-brand-role" id="dashRoleLabel">{{ $sidebar['role_label'] }}</div>
        </div>
    </div>

    <nav
        class="dash-nav"
        aria-label="Dashboard navigation"
        id="dashNav"
        data-sidebar-markup='@json($sidebar["markup"])'
    ></nav>

    <div class="dash-sidebar-footer">
        <a href="{{ route('home') }}" class="dash-nav-link">Main Website</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="dash-logout-btn">Sign Out</button>
        </form>
    </div>
</aside>

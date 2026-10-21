<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Portal Dashboard') | Pinnacle Academy</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ea580c'><path d='M12 3L1 9l11 6 9-4.91V17h2V9L12 3z'/></svg>">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    @yield('extra_css')
    <script src="{{ asset('js/dashboard.js') }}" defer></script>
    <script type="speculationrules">
        {"prefetch": [{"where": {"and": [{"href_matches": "/*"}, {"not": {"selector_matches": "[data-no-prefetch]"}}]}, "eagerness": "moderate"}]}
    </script>
</head>
<body @class(['dash-body', 'is-sidebar-collapsed' => request()->cookie('dash_sidebar') === 'collapsed'])>
    <a href="#dashContent" class="dash-skip-link">Skip to content</a>
    <div class="dash-progress" aria-hidden="true"></div>

    <div class="dash-app">
        @include('partials.dashboard-navigation')
        <div class="dash-backdrop" data-sidebar-close aria-hidden="true"></div>

        <div class="dash-column">
            @foreach (['status' => 'success', 'error' => 'danger'] as $flashKey => $bannerStyle)
                @if (session($flashKey))
                    <div class="dash-banner dash-banner-{{ $bannerStyle }}" role="status" data-banner>
                        <div class="dash-banner-inner">
                            <span class="dash-banner-icon">
                                @include('partials.dashboard-icon', ['name' => $bannerStyle === 'success' ? 'check' : 'alert'])
                            </span>
                            <p class="dash-banner-text">{{ session($flashKey) }}</p>
                            <button type="button" class="dash-banner-close" data-banner-close aria-label="Dismiss">
                                @include('partials.dashboard-icon', ['name' => 'close'])
                            </button>
                        </div>
                    </div>
                @endif
            @endforeach

            <header class="dash-header">
                <div class="dash-container dash-header-inner">
                    <button type="button" class="dash-icon-btn dash-menu-btn" data-sidebar-toggle aria-controls="dashSidebar" aria-expanded="false" aria-label="Open menu">
                        @include('partials.dashboard-icon', ['name' => 'menu'])
                    </button>
                    <div class="dash-header-text">
                        <span class="dash-header-crumb">{{ $navigation['role_label'] }}</span>
                        <h2 class="dash-header-title">@yield('page_heading', 'Dashboard')</h2>
                    </div>
                    <span class="dash-header-meta">
                        @include('partials.dashboard-icon', ['name' => 'calendar'])
                        {{ now()->format('l, d M Y') }}
                    </span>
                </div>
            </header>

            <main class="dash-main" id="dashContent" tabindex="-1">
                <div class="dash-container dash-content">
                    @yield('content')
                </div>
            </main>

            <footer class="dash-footer">
                <div class="dash-container dash-footer-inner">
                    <p>&copy; {{ now()->year }} Pinnacle International Academy. All rights reserved.</p>
                    <nav class="dash-footer-links" aria-label="Footer">
                        <a href="{{ route('home') }}">Main Website</a>
                        <a href="{{ route('contact') }}">Support</a>
                        <span class="dash-footer-made">
                            Crafted with
                            @include('partials.dashboard-icon', ['name' => 'heart'])
                            for campus teams
                        </span>
                    </nav>
                </div>
            </footer>
        </div>
    </div>

    <dialog class="dash-modal" id="dashConfirmModal" aria-labelledby="dashConfirmTitle" aria-describedby="dashConfirmMessage">
        <div class="dash-modal-body">
            <span class="dash-modal-icon">@include('partials.dashboard-icon', ['name' => 'eraser'])</span>
            <div>
                <h3 id="dashConfirmTitle">Erase this user?</h3>
                <p id="dashConfirmMessage" data-confirm-message>This account will be permanently deleted. This cannot be undone.</p>
            </div>
        </div>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-confirm-cancel>Cancel</button>
            <button type="button" class="dash-btn dash-btn-danger" data-confirm-accept>
                @include('partials.dashboard-icon', ['name' => 'eraser'])
                Erase
            </button>
        </div>
    </dialog>

    <div id="toastContainer" class="dash-toast-stack" aria-live="polite"></div>
    @yield('extra_js')
</body>
</html>

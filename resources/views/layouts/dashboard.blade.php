<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1d2b45">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth<meta name="user-id" content="{{ auth()->id() }}">@endauth
    <title>@yield('title', 'Portal Dashboard') | Pinnacle Academy</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%231d2b45'><path d='M12 3L1 9l11 6 9-4.91V17h2V9L12 3z'/></svg>">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|fraunces:500,600,600i&display=swap">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
    @yield('extra_css')
    <script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}" defer></script>
    <script>
        // A skipped page turn (fast clicks, back button) is harmless; keep it out of the console.
        function quietTransition(transition) {
            transition.ready.catch(function () {});
            transition.updateCallbackDone.catch(function () {});
        }

        window.addEventListener('pageswap', function (event) {
            if (event.viewTransition) {
                quietTransition(event.viewTransition);
            }
        });

        window.addEventListener('pagereveal', function (event) {
            if (event.viewTransition) {
                quietTransition(event.viewTransition);
                document.documentElement.classList.add('is-page-turn');
            }
        });
    </script>
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
                        <svg class="dash-scribble" viewBox="0 0 120 8" preserveAspectRatio="none" aria-hidden="true"><path d="M2 5.5C18 2 30 7 46 4.5S78 2 92 4.8 112 6 118 3"/></svg>
                    </div>
                    <div class="dash-jump" data-jump data-url="{{ url('/api/erp/jump-to') }}">
                        <label class="dash-jump-box">
                            @include('partials.dashboard-icon', ['name' => 'search'])
                            <span class="dash-visually-hidden">Jump to a page</span>
                            <input type="search" placeholder="Jump to…" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="dashJumpList" data-jump-input>
                            <kbd aria-hidden="true">Ctrl K</kbd>
                        </label>
                        <ul class="dash-jump-list" id="dashJumpList" role="listbox" data-jump-list hidden></ul>
                    </div>
                    @if (($navigation['campuses'] ?? collect())->count() > 1)
                        <label class="dash-select dash-campus-pick">
                            <span class="dash-visually-hidden">Campus</span>
                            <select data-campus-pick>
                                <option value="">All campuses</option>
                                @foreach ($navigation['campuses'] as $campus)
                                    <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <span class="dash-header-meta">
                        @include('partials.dashboard-icon', ['name' => 'calendar'])
                        {{ now()->format('l, d M Y') }}
                    </span>
                    <div class="dash-bell" data-bell data-summary-url="{{ url('/api/erp/notifications/summary') }}" data-read-url="{{ url('/api/erp/notifications') }}" data-all-url="{{ route('notices') }}">
                        <button type="button" class="dash-icon-btn dash-bell-btn" data-bell-toggle aria-expanded="false" aria-controls="dashBellPanel" aria-label="Notifications">
                            @include('partials.dashboard-icon', ['name' => 'bell'])
                            <span class="dash-bell-count" data-bell-count hidden>0</span>
                        </button>
                        <div class="dash-bell-panel" id="dashBellPanel" data-bell-panel hidden>
                            <div class="dash-bell-head">
                                <strong>Notifications</strong>
                                <button type="button" class="dash-link-btn" data-bell-sound aria-pressed="true">🔔 Sound on</button>
                                <button type="button" class="dash-link-btn" data-bell-read-all>Mark all read</button>
                            </div>
                            <ul class="dash-bell-list" data-bell-list><li class="dash-muted">Loading…</li></ul>
                            <a class="dash-bell-foot" href="{{ route('notices') }}">All notices</a>
                        </div>
                    </div>
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
                <h3 id="dashConfirmTitle">Erase this record?</h3>
                <p id="dashConfirmMessage" data-confirm-message>This record will be erased.</p>
            </div>
        </div>
        <div class="dash-field dash-confirm-reason">
            <label for="dashConfirmReason">Reason <span class="dash-optional">an administrator approves deletions</span></label>
            <textarea id="dashConfirmReason" rows="2" maxlength="500" placeholder="Why is this being deleted?" data-confirm-reason></textarea>
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
    @include('partials.realtime')
    <script src="{{ asset('js/notification-bell.js') }}?v={{ filemtime(public_path('js/notification-bell.js')) }}" defer></script>
    @yield('extra_js')
</body>
</html>

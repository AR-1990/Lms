{{-- Tab strip between the screens of one area. Expects $tabs: [route name => [label, icon, permission(s) or null]] and $current (route name). Tabs the user cannot open are hidden. --}}
<nav class="dash-section-tabs" aria-label="{{ $label ?? 'Sections' }}">
    @foreach ($tabs as $routeName => [$tabLabel, $tabIcon, $tabPermission])
        @continue($tabPermission !== null && ! auth()->user()->hasPermission($tabPermission))
        <a href="{{ route($routeName) }}" @class(['dash-section-tab', 'is-active' => $routeName === $current]) @if ($routeName === $current) aria-current="page" @endif>
            @include('partials.dashboard-icon', ['name' => $tabIcon])
            <span>{{ $tabLabel }}</span>
        </a>
    @endforeach
</nav>

{{-- WebSocket client (Laravel Reverb) for live screens: tasks and chat. Sets window.PinnacleRealtime.echo when the server settings exist. --}}
<div hidden data-realtime
    data-key="{{ config('reverb.apps.apps.0.key') }}"
    data-host="{{ config('reverb.apps.apps.0.options.host') }}"
    data-port="{{ config('reverb.apps.apps.0.options.port') }}"
    data-scheme="{{ config('reverb.apps.apps.0.options.scheme') }}"
    data-auth-url="{{ url('/api/broadcasting/auth') }}"></div>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@2.1.6/dist/echo.iife.js" defer></script>
<script src="{{ asset('js/realtime.js') }}?v={{ filemtime(public_path('js/realtime.js')) }}" defer></script>

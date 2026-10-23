/**
 * Connects Laravel Echo to the Reverb WebSocket server. Screens use window.PinnacleRealtime.echo when it is
 * not null; without a running server they keep working and simply refresh on their own actions.
 */
(function () {
    'use strict';

    const settings = document.querySelector('[data-realtime]');
    const csrf = document.querySelector('meta[name="csrf-token"]');

    window.PinnacleRealtime = { echo: null };

    if (!settings || !settings.dataset.key || !window.Echo || !window.Pusher) {
        return;
    }

    const EchoClient = window.Echo.default || window.Echo;
    const secure = settings.dataset.scheme === 'https';

    window.PinnacleRealtime.echo = new EchoClient({
        broadcaster: 'reverb',
        key: settings.dataset.key,
        // A loopback host only works on the server itself; from a LAN IP or domain, use the page's host.
        wsHost: settings.dataset.host && !/^(127\.0\.0\.1|localhost|::1)$/.test(settings.dataset.host) ? settings.dataset.host : window.location.hostname,
        wsPort: Number(settings.dataset.port) || 80,
        wssPort: Number(settings.dataset.port) || 443,
        forceTLS: secure,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: settings.dataset.authUrl,
        auth: { headers: { 'X-CSRF-TOKEN': csrf ? csrf.content : '', 'Accept': 'application/json' } }
    });
})();

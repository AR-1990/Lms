/**
 * Dashboard "My attendance QR": loads /api/erp/my-attendance-qr and shows it; tap to enlarge for the kiosk camera.
 */
(function () {
    'use strict';

    const panel = document.querySelector('[data-my-qr]');
    const ui = window.PinnacleDashboard;

    if (!panel || !ui) {
        return;
    }

    const full = document.getElementById('myQrFull');

    ui.api('GET', panel.dataset.url).then(function (envelope) {
        const qr = envelope.data;

        if (!qr) {
            return;
        }

        const src = 'data:image/png;base64,' + qr.qr;
        panel.querySelector('[data-my-qr-image]').src = src;
        full.querySelector('[data-my-qr-full]').src = src;
        panel.querySelector('[data-my-qr-name]').textContent = qr.holder_name || '';
        panel.querySelector('[data-my-qr-card]').textContent = qr.card_no;
        panel.querySelector('[data-my-qr-valid]').textContent = qr.valid_until ? new Date(qr.valid_until + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
        panel.hidden = false;
    }).catch(function () {});

    panel.querySelector('[data-my-qr-zoom]').addEventListener('click', function () { full.showModal(); });
    full.addEventListener('click', function (event) {
        if (event.target === full || event.target.closest('[data-modal-close]')) {
            full.close();
        }
    });
})();

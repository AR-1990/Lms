/**
 * Notices page: open a notice to read it in full (marks it read), and confirm "I have read this".
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-notices]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui) {
        return;
    }

    function markRead(notice, acknowledge) {
        return ui.api('POST', root.dataset.readUrl + '/' + notice.dataset.notice + '/read', { acknowledge: acknowledge, device: 'web' }).then(function (envelope) {
            notice.classList.remove('is-unread');

            return envelope;
        });
    }

    function open(notice) {
        const body = notice.querySelector('[data-notice-body]');
        const button = notice.querySelector('[data-notice-toggle]');
        const opening = body.hidden;

        body.hidden = !opening;
        button.textContent = opening ? 'Show less' : 'Read more';
        button.setAttribute('aria-expanded', String(opening));

        if (opening && notice.classList.contains('is-unread')) {
            markRead(notice, false).catch(function () {});
        }
    }

    root.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-notice-toggle]');
        const ack = event.target.closest('[data-notice-ack]');

        if (toggle) {
            open(toggle.closest('[data-notice]'));
        }

        if (ack) {
            ack.disabled = true;
            markRead(ack.closest('[data-notice]'), true)
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    ack.outerHTML = '<span class="dash-badge dash-badge-ok">Confirmed</span>';
                })
                .catch(function () {
                    ack.disabled = false;
                });
        }
    });

    // Arriving from the bell opens that notice.
    const target = root.dataset.openId ? root.querySelector('[data-notice="' + root.dataset.openId + '"]') : null;

    if (target) {
        open(target);
        target.scrollIntoView({ block: 'center' });
    }
})();

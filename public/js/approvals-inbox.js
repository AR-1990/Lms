/**
 * Approvals Inbox: one panel per module with its waiting items; Approve / Reject call the exact endpoint the
 * API gives for each item (the same calls the mobile apps make).
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-inbox]');

    if (!root || !window.PinnacleDashboard) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const rejectDialog = document.getElementById('inboxReject');
    const rejectForm = rejectDialog.querySelector('form');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    const items = {};
    let rejecting = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function money(paisa) {
        return 'Rs ' + Math.round(paisa / 100).toLocaleString('en-PK');
    }

    function render(data) {
        const total = document.querySelector('[data-inbox-total]');
        total.textContent = data.total ? data.total + ' waiting' : 'All clear';
        total.className = 'dash-badge ' + (data.total ? 'dash-badge-warn' : 'dash-badge-ok');

        const sections = data.sections.filter(function (section) { return section.count > 0; });

        root.innerHTML = sections.length ? sections.map(function (section) {
            return '<section class="dash-panel"><div class="dash-panel-head"><div><h2>' + esc(section.label) + '</h2><p>' + section.count + ' waiting' + (section.count > section.items.length ? ' (showing the oldest ' + section.items.length + ')' : '') + '</p></div></div>'
                + '<ul class="dash-list dash-inbox-list">' + section.items.map(function (item) {
                    const key = section.key + ':' + item.id;
                    items[key] = item;

                    return '<li data-item="' + esc(key) + '"><div><strong>' + esc(item.title) + '</strong>'
                        + '<span class="dash-muted">' + esc([item.detail, item.requested_by ? 'by ' + item.requested_by : null, item.requested_at ? dateFormat.format(new Date(item.requested_at)) : null].filter(Boolean).join(' · ')) + '</span></div>'
                        + '<div class="dash-inbox-actions">' + (item.amount ? '<span class="dash-inbox-amount">' + esc(money(item.amount)) + '</span>' : '')
                        + '<button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-reject>Reject</button>'
                        + '<button type="button" class="dash-btn dash-btn-primary dash-btn-sm" data-approve>Approve</button></div></li>';
                }).join('') + '</ul></section>';
        }).join('') : '<section class="dash-panel"><p class="dash-muted dash-family-empty">Nothing is waiting for you. 🎉</p></section>';
    }

    function load() {
        return ui.api('GET', root.dataset.url).then(function (envelope) { render(envelope.data); }).catch(function () {
            root.innerHTML = '<p class="dash-muted">Could not load the inbox.</p>';
        });
    }

    function act(item, action, extra) {
        return ui.api(action.method, action.url, Object.assign({}, action.body || {}, extra || {})).then(function (envelope) {
            ui.toast(envelope.message);
            load();
        });
    }

    root.addEventListener('click', function (event) {
        const row = event.target.closest('[data-item]');

        if (!row) {
            return;
        }

        const item = items[row.dataset.item];

        if (event.target.closest('[data-approve]')) {
            event.target.closest('[data-approve]').disabled = true;
            act(item, item.approve).catch(function (envelope) {
                ui.toast((envelope && envelope.errors && envelope.errors.summary) || (envelope && envelope.message) || 'Could not approve.', 'error');
                load();
            });
        }

        if (event.target.closest('[data-reject]')) {
            if (!item.reject.reason_field) {
                act(item, item.reject).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not reject.', 'error'); });

                return;
            }

            rejecting = item;
            rejectForm.reset();
            rejectForm.querySelector('[data-form-error]').hidden = true;
            rejectDialog.querySelector('[data-reject-title]').textContent = item.title;
            rejectForm.elements.reason.required = !item.reject.reason_optional;
            rejectDialog.showModal();
        }
    });

    rejectDialog.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () { rejectDialog.close(); });
    });

    rejectForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const reason = rejectForm.elements.reason.value.trim();
        const error = rejectForm.querySelector('[data-form-error]');
        const extra = {};
        extra[rejecting.reject.reason_field] = reason;

        act(rejecting, rejecting.reject, extra).then(function () { rejectDialog.close(); }).catch(function (envelope) {
            error.textContent = (envelope && envelope.errors && envelope.errors.summary) || (envelope && envelope.message) || 'Could not reject.';
            error.hidden = false;
        });
    });

    load();
})();

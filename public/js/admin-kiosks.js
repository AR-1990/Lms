/**
 * Attendance → Gate Kiosks: grid, add / edit popup, and the one-time link popup (after adding or "New link").
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-kiosks]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const esc = window.PinnacleGrid.escape;
    const linkDialog = document.getElementById('kioskLink');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

    function display(render) {
        return function (value, type, row) { return type === 'display' ? render(value, row) : value; };
    }

    function showLink(url) {
        linkDialog.querySelector('[data-kiosk-url]').value = url;
        linkDialog.querySelector('[data-open-url]').href = url;
        linkDialog.showModal();
    }

    kit.wireDialog(linkDialog);

    linkDialog.querySelector('[data-copy-url]').addEventListener('click', function () {
        const field = linkDialog.querySelector('[data-kiosk-url]');
        field.select();

        (navigator.clipboard ? navigator.clipboard.writeText(field.value) : Promise.reject()).then(function () {
            ui.toast('Link copied.');
        }).catch(function () {
            document.execCommand('copy');
            ui.toast('Link copied.');
        });
    });

    const mounted = kit.mount({
        root: root,
        modal: document.getElementById('kioskModal'),
        order: [[0, 'asc']],
        titles: { create: 'Add kiosk', edit: 'Edit kiosk' },
        searchPlaceholder: 'Search kiosks…',
        emptyText: 'No kiosks yet.',
        deletable: false,
        nameOf: function (row) { return row.name; },
        columns: [
            { data: 'name', render: display(function (value, row) { return '<span class="dash-cell-title">' + esc(value) + '</span><button type="button" class="dash-link-btn" data-renew="' + row.id + '">New link</button>'; }) },
            { data: 'campus_name', name: 'campus_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: 'Any campus' },
            { data: 'scan_count', searchable: false, className: 'dash-col-num' },
            { data: 'last_used_at', searchable: false, render: display(function (value) { return value ? esc(dateFormat.format(new Date(value))) : '<span class="dash-muted">Not yet</span>'; }) },
            { data: 'is_active', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">On</span>' : '<span class="dash-badge">Off</span>'; }) }
        ],
        fill: function (form, row) {
            kit.fillForm(form, row, { name: 'text', campus_id: 'int', is_active: 'bool' }, { is_active: true });
        },
        payload: function (form) {
            return kit.readForm(form, { name: 'text', campus_id: 'int', is_active: 'bool' });
        },
        afterSave: function (data) {
            if (data && data.url) {
                showLink(data.url);
            }
        }
    });

    root.addEventListener('click', function (event) {
        const renew = event.target.closest('[data-renew]');

        if (!renew) {
            return;
        }

        ui.api('POST', root.dataset.resourceUrl + '/' + renew.dataset.renew + '/renew').then(function (envelope) {
            ui.toast(envelope.message);
            showLink(envelope.data.url);
            mounted.refresh();
        }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not make a new link.', 'error'); });
    });
})();

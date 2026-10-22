/**
 * Approvals queue: deletions and purchases waiting for an administrator, plus the decided history.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const root = document.querySelector('[data-approvals]');

    if (!ui || !kit || !window.PinnacleGrid || !root) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    const TONES = { pending: 'dash-badge-warn', approved: 'dash-badge-ok', rejected: 'dash-badge-danger' };
    const dialog = document.getElementById('approvalModal');
    const form = dialog.querySelector('form');
    const filters = root.querySelectorAll('[data-filter]');
    let current = null;

    function when(value) {
        return value ? dateFormat.format(new Date(value)) : '';
    }

    function badge(status) {
        return '<span class="dash-badge ' + (TONES[status] || 'dash-badge-system') + '">' + esc(status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';
    }

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function what(row) {
        return row.request_type === 'delete' ? 'Delete ' + row.record_type : 'Purchase';
    }

    const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
        url: root.dataset.datatableUrl,
        order: [[0, 'desc']],
        searchPlaceholder: 'Search record or reason…',
        emptyText: 'Nothing waiting for approval.',
        params: function () {
            const values = {};

            filters.forEach(function (field) {
                values[field.dataset.filter] = field.value;
            });

            return values;
        },
        columns: [
            { data: 'subject_label', name: 'subject_label', render: display(function (value, row) {
                return '<span class="dash-cell-title">' + esc(value) + '</span><span class="dash-muted">' + esc(what(row)) + (row.amount ? ' · ' + money(row.amount) : '') + '</span>';
            }) },
            { data: 'requested_at', searchable: false, className: 'dash-hide-md', render: display(function (value, row) {
                return '<span class="dash-cell-title">' + esc(row.requested_by_name || '—') + '</span><span class="dash-muted">' + esc(when(value)) + '</span>';
            }) },
            { data: 'reason', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '', render: display(function (value) { return esc(value || '—'); }) },
            { data: 'status', searchable: false, render: display(function (value, row) {
                return badge(value) + (row.decided_by_name ? '<span class="dash-muted">by ' + esc(row.decided_by_name) + '</span>' : '');
            }) },
            { data: null, orderable: false, searchable: false, className: 'dash-col-actions', render: display(function (value, row) {
                return '<div class="dash-actions"><button type="button" class="dash-action dash-action-view" data-open data-tooltip="Open" aria-label="Open ' + esc(row.subject_label) + '">'
                    + '<svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></button></div>';
            }) }
        ]
    });

    filters.forEach(function (field) {
        field.addEventListener('change', function () {
            grid.ajax.reload();
        });
    });

    kit.wireDialog(dialog);

    root.querySelector('[data-grid]').addEventListener('click', function (event) {
        const button = event.target.closest('[data-open]');

        if (!button) {
            return;
        }

        current = grid.row(button.closest('tr')).data();
        form.reset();
        kit.clearErrors(form);
        dialog.querySelector('[data-detail-title]').textContent = what(current) + ': ' + current.subject_label;
        dialog.querySelector('[data-detail-subtitle]').textContent = 'Module: ' + current.module.replace(/_/g, ' ');
        dialog.querySelector('[data-detail-body]').innerHTML = '<ul class="dash-kv">'
            + '<li><span>Status</span><strong>' + badge(current.status) + '</strong></li>'
            + '<li><span>Requested by</span><strong>' + esc((current.requested_by_name || '—') + ' · ' + when(current.requested_at)) + '</strong></li>'
            + (current.amount ? '<li><span>Estimated amount</span><strong>' + money(current.amount) + '</strong></li>' : '')
            + '<li><span>Reason</span><strong>' + esc(current.reason || '—') + '</strong></li>'
            + (current.decided_by_name ? '<li><span>Decided by</span><strong>' + esc(current.decided_by_name + ' · ' + when(current.decided_at)) + '</strong></li>' : '')
            + (current.decision_note ? '<li><span>Note</span><strong>' + esc(current.decision_note) + '</strong></li>' : '')
            + '</ul>';

        const pending = current.status === 'pending';
        dialog.querySelector('[data-decision-fields]').hidden = !pending;
        dialog.querySelector('[data-decision-actions]').hidden = !pending;
        dialog.showModal();
    });

    dialog.querySelector('[data-decision-actions]').addEventListener('click', function (event) {
        const button = event.target.closest('[data-decision]');

        if (!button || !current) {
            return;
        }

        kit.submitForm(form, 'POST', root.dataset.resourceUrl + '/' + current.id + '/' + button.dataset.decision, kit.readForm(form, { decision_note: 'text' }))
            .then(function () {
                dialog.close();
                grid.ajax.reload(null, false);
            })
            .catch(function () {});
    });
})();

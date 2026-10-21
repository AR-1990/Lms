/**
 * Activity log screen: filters, Yajra grid (newest first), and a details modal
 * showing the before / after value of every changed field.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-activity-log]');

    if (!root || !window.DataTable || !window.PinnacleGrid || !window.PinnacleDashboard) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const esc = window.PinnacleGrid.escape;
    const modal = document.getElementById('activityModal');
    const filters = root.querySelectorAll('[data-filter]');
    const actionTones = {
        created: 'emerald',
        updated: 'blue',
        deleted: 'rose',
        restored: 'violet',
        login: 'sky',
        logout: 'amber',
        login_failed: 'rose'
    };
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    function filterValues() {
        const values = {};

        filters.forEach(function (field) {
            values[field.dataset.filter] = field.value;
        });

        return values;
    }

    function relativeTime(date) {
        const seconds = Math.round((Date.now() - date.getTime()) / 1000);

        if (seconds < 60) {
            return 'just now';
        }

        const units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60]];
        const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

        for (let i = 0; i < units.length; i += 1) {
            if (seconds >= units[i][1]) {
                return formatter.format(-Math.floor(seconds / units[i][1]), units[i][0]);
            }
        }

        return 'just now';
    }

    const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
        url: root.dataset.datatableUrl,
        order: [[0, 'desc']],
        pageLength: 25,
        searchPlaceholder: 'Search who, what, or record…',
        emptyText: 'No activity recorded yet.',
        params: filterValues,
        columns: [
            {
                data: 'created_at',
                render: function (value, type) {
                    if (type !== 'display' || !value) {
                        return value;
                    }

                    const date = new Date(value);

                    return '<span class="dash-cell-title" title="' + esc(dateFormat.format(date)) + '">' + esc(relativeTime(date)) + '</span>'
                        + '<span class="dash-muted">' + esc(dateFormat.format(date)) + '</span>';
                }
            },
            {
                data: 'user_name',
                render: function (value, type, row) {
                    if (type !== 'display') {
                        return value;
                    }

                    return '<span class="dash-cell-title">' + esc(row.user_name || 'System') + '</span>'
                        + '<span class="dash-muted">' + esc(row.platform || '') + (row.ip_address ? ' · ' + esc(row.ip_address) : '') + '</span>';
                }
            },
            {
                data: 'module',
                render: function (value, type, row) {
                    return type === 'display' ? '<span class="dash-badge dash-badge-brand">' + esc(row.module_label) + '</span>' : value;
                }
            },
            {
                data: 'action',
                render: function (value, type, row) {
                    if (type !== 'display') {
                        return value;
                    }

                    return '<span class="dash-badge dash-action-badge dash-tone-' + (actionTones[row.action] || 'amber') + '">' + esc(row.action_label) + '</span>';
                }
            },
            {
                data: 'description',
                className: 'dash-col-wrap',
                render: function (value, type) {
                    return type === 'display' ? esc(value) : value;
                }
            },
            {
                data: 'changes_count',
                orderable: false,
                searchable: false,
                className: 'dash-col-actions',
                render: function (value, type, row) {
                    if (type !== 'display') {
                        return value;
                    }

                    return '<button type="button" class="dash-action dash-action-view" data-details data-tooltip="' + (row.changes_count ? row.changes_count + ' changes' : 'Details') + '" aria-label="View details">'
                        + '<svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/></svg>'
                        + '</button>';
                }
            }
        ]
    });

    filters.forEach(function (field) {
        field.addEventListener('change', function () {
            grid.ajax.reload();
        });
    });

    root.querySelector('[data-filters-reset]').addEventListener('click', function () {
        filters.forEach(function (field) {
            field.value = '';
        });
        grid.search('');
        grid.ajax.reload();
    });

    /* ---------- Details modal ---------- */

    function renderDetails(log) {
        const when = dateFormat.format(new Date(log.created_at));
        const body = modal.querySelector('[data-detail-body]');
        let html = '<p class="dash-detail-description">' + esc(log.description) + '</p>'
            + '<ul class="dash-kv">'
            + '<li><span>Who</span><strong>' + esc(log.user_name || 'System') + '</strong></li>'
            + '<li><span>Module</span><strong>' + esc(log.module) + '</strong></li>'
            + '<li><span>Record</span><strong>' + esc(log.subject_label || '—') + (log.subject_type ? ' <span class="dash-muted">' + esc(log.subject_type) + ' #' + esc(log.subject_id) + '</span>' : '') + '</strong></li>'
            + '<li><span>Platform</span><strong>' + esc(log.platform || '—') + '</strong></li>'
            + '<li><span>IP address</span><strong>' + esc(log.ip_address || '—') + '</strong></li>'
            + '</ul>';

        if (log.changes && log.changes.length) {
            html += '<h4 class="dash-detail-heading">Changed fields</h4><div class="dash-table-wrap"><table class="dash-table dash-changes-table"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody>';
            log.changes.forEach(function (change) {
                html += '<tr><td><strong>' + esc(change.field) + '</strong></td>'
                    + '<td class="dash-change-old">' + (change.old_value === null ? '<span class="dash-muted">empty</span>' : esc(change.old_value)) + '</td>'
                    + '<td class="dash-change-new">' + (change.new_value === null ? '<span class="dash-muted">empty</span>' : esc(change.new_value)) + '</td></tr>';
            });
            html += '</tbody></table></div>';
        } else {
            html += '<p class="dash-muted dash-detail-empty">No field changes recorded for this action.</p>';
        }

        modal.querySelector('[data-detail-title]').textContent = log.action.replace(/_/g, ' ').replace(/^\w/, function (letter) { return letter.toUpperCase(); });
        modal.querySelector('[data-detail-meta]').textContent = when;
        body.innerHTML = html;
    }

    root.querySelector('[data-grid]').addEventListener('click', function (event) {
        const button = event.target.closest('[data-details]');

        if (!button) {
            return;
        }

        const row = grid.row(button.closest('tr')).data();
        button.disabled = true;

        ui.api('GET', root.dataset.showUrl + '/' + row.id)
            .then(function (envelope) {
                renderDetails(envelope.data);
                modal.showModal();
            })
            .catch(function (envelope) {
                ui.toast((envelope && envelope.message) || 'Could not load the details.', 'error');
            })
            .finally(function () {
                button.disabled = false;
            });
    });

    modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            modal.close();
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.close();
        }
    });
})();

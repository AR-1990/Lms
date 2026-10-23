/**
 * HR → Appraisals screens: criteria, cycles (summary popup with Launch / Close) and every appraisal of a
 * cycle (opened in the shared appraisal popup from js/appraisal-form.js).
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-appraisals]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const APPLIES = { all: 'All staff', teaching: 'Teaching staff', non_teaching: 'Non-teaching staff' };
    const STATUS = {
        draft: ['Draft', 'dash-badge-system'], open: ['Open', 'dash-badge-warn'], closed: ['Closed', 'dash-badge-ok'],
        self_review: ['Self-review', 'dash-badge-warn'], submitted: ['Awaiting acknowledgement', 'dash-badge-system'], acknowledged: ['Acknowledged', 'dash-badge-ok']
    };

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function day(value) {
        return value ? dateFormat.format(new Date(value)) : '—';
    }

    function titleCell(title, sub) {
        return '<strong>' + esc(title || '—') + '</strong>' + (sub ? '<span class="dash-muted dash-cell-sub">' + esc(sub) + '</span>' : '');
    }

    function score(value) {
        return value === null || value === undefined ? '—' : Number(value).toFixed(2);
    }

    function statusBadge(status, row) {
        // A cycle row has its own "draft"; an appraisal "draft" means it is with the reviewer.
        const meta = status === 'draft' && row && row.employee_name !== undefined ? ['With reviewer', 'dash-badge-warn'] : (STATUS[status] || [status, '']);

        return '<span class="dash-badge ' + meta[1] + '">' + esc(meta[0]) + '</span>';
    }

    function filterParams() {
        const params = {};

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            params[select.dataset.filter] = select.value;
        });

        return params;
    }

    function closeOnButtons(dialog) {
        dialog.querySelectorAll('[data-modal-close]').forEach(function (button) { button.onclick = function () { dialog.close(); }; });
    }

    /* ---------- Criteria ---------- */

    function criteria() {
        const SPEC = { name: 'text', description: 'text', weight_percent: 'number', applies_to: 'text', sort_order: 'int', is_active: 'bool' };

        kit.mount({
            root: root,
            modal: document.getElementById('criterionModal'),
            order: [[3, 'asc']],
            titles: { create: 'Add criterion', edit: 'Edit criterion' },
            emptyText: 'No criteria yet.',
            nameOf: function (row) { return row.name; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.description); }) },
                { data: 'weight_percent', searchable: false, render: display(function (value) { return esc(Number(value) + '%'); }) },
                { data: 'applies_to', render: display(function (value) { return esc(APPLIES[value] || value); }) },
                { data: 'sort_order', searchable: false },
                { data: 'is_active', render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { applies_to: 'all', is_active: true, weight_percent: 10 }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });
    }

    /* ---------- Cycles ---------- */

    function cycles() {
        const SPEC = { name: 'text', starts_on: 'text', ends_on: 'text', department_id: 'int', self_review: 'bool' };
        const view = document.getElementById('cycleView');
        let mounted = null;

        function bars(rows, label, value, total) {
            return rows.length ? '<ul class="dash-bars">' + rows.map(function (row) {
                return '<li><div class="dash-bar-row"><span>' + esc(row[label]) + '</span><strong>' + esc(String(row[value])) + '</strong></div>'
                    + '<div class="dash-bar-track"><div class="dash-bar-fill" style="--bar: ' + Math.min(1, Number(row[value]) / (total || 1)).toFixed(3) + '"></div></div></li>';
            }).join('') + '</ul>' : '<p class="dash-muted">Nothing scored yet.</p>';
        }

        function show(summary) {
            const done = (summary.by_status.submitted || 0) + (summary.by_status.acknowledged || 0);
            const facts = [
                ['Status', statusBadge(summary.status)],
                ['Period', esc(day(summary.starts_on) + ' – ' + day(summary.ends_on))],
                ['Staff', esc(String(summary.total))],
                ['Self-review', esc(String(summary.by_status.self_review || 0))],
                ['With reviewer', esc(String(summary.by_status.draft || 0))],
                ['Submitted', esc(String(summary.by_status.submitted || 0))],
                ['Acknowledged', esc(String(summary.by_status.acknowledged || 0))],
                ['Average score', esc(score(summary.average_score))]
            ];

            view.querySelector('[data-view-title]').textContent = summary.name;
            view.querySelector('[data-view-sub]').textContent = summary.total ? done + ' of ' + summary.total + ' done' + (summary.without_reviewer ? ' · ' + summary.without_reviewer + ' without a reviewer' : '') : (summary.self_review ? 'Staff rate themselves first' : 'Reviewers score directly');
            view.querySelector('[data-view-body]').innerHTML = '<dl class="dash-asset-facts">' + facts.map(function (fact) { return '<div><dt>' + fact[0] + '</dt><dd>' + fact[1] + '</dd></div>'; }).join('') + '</dl>'
                + (summary.total ? '<h4>Average by department (out of 5)</h4>' + bars(summary.by_department, 'department', 'average', 5)
                    + '<h4>Ratings</h4>' + bars(summary.by_rating, 'rating', 'count', done) : '<p class="dash-muted dash-appraisal-hint">Launch creates one appraisal per current employee' + (summary.department_id ? ' of this department' : '') + ' and notifies them and their reviewers.</p>');

            view.querySelector('[data-view-actions]').innerHTML = '<button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>'
                + (summary.total ? '<a class="dash-btn dash-btn-ghost" href="' + esc(root.dataset.appraisalsPage + '?cycle=' + summary.id) + '">See appraisals</a>' : '')
                + (summary.status === 'open' ? '<button type="button" class="dash-btn dash-btn-ghost" data-cycle-action="launch">Add new staff</button><button type="button" class="dash-btn dash-btn-danger" data-cycle-action="close">Close cycle</button>' : '')
                + (summary.status === 'draft' ? '<button type="button" class="dash-btn dash-btn-primary" data-cycle-action="launch">Launch</button>' : '');
            closeOnButtons(view);

            view.querySelectorAll('[data-cycle-action]').forEach(function (button) {
                button.onclick = function () {
                    const action = button.dataset.cycleAction;

                    if (action === 'close' && !window.confirm('Close ' + summary.name + '? Unfinished appraisals can no longer be scored.')) {
                        return;
                    }

                    button.disabled = true;
                    ui.api('POST', root.dataset.resourceUrl + '/' + summary.id + '/' + action).then(function (envelope) {
                        ui.toast(envelope.message);
                        mounted.refresh();

                        return action === 'close' ? ui.api('GET', root.dataset.resourceUrl + '/' + summary.id) : envelope;
                    }).then(function (envelope) { show(envelope.data); })
                        .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'That did not work.'), 'error'); })
                        .finally(function () { button.disabled = false; });
                };
            });

            if (!view.open) {
                view.showModal();
            }
        }

        mounted = kit.mount({
            root: root,
            modal: document.getElementById('cycleModal'),
            order: [[1, 'desc']],
            titles: { create: 'New cycle', edit: 'Edit cycle' },
            emptyText: 'No cycles yet.',
            nameOf: function (row) { return row.name; },
            canEdit: function (row) { return row.status === 'closed' ? 'Closed' : true; },
            canDelete: function (row) { return row.status === 'draft' ? true : 'Only a draft can be deleted'; },
            onView: function (row) {
                ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { show(envelope.data); })
                    .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The cycle could not be opened.'), 'error'); });
            },
            afterSave: function (cycle) {
                if (cycle && cycle.status === 'draft') {
                    ui.api('GET', root.dataset.resourceUrl + '/' + cycle.id).then(function (envelope) { show(envelope.data); }).catch(function () {});
                }
            },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.department_name + (row.self_review ? ' · self-review' : '')); }) },
                { data: 'ends_on', render: display(function (value, row) { return esc(day(row.starts_on) + ' – ' + day(value)); }) },
                { data: 'appraisals_count', searchable: false },
                { data: 'done_count', searchable: false, render: display(function (value, row) {
                    const percent = row.appraisals_count ? Math.round(value * 100 / row.appraisals_count) : 0;

                    return row.appraisals_count ? '<div class="dash-board-progress"><span style="width:' + percent + '%"></span></div><span class="dash-muted dash-cell-sub">' + esc(value + ' of ' + row.appraisals_count) + '</span>' : '<span class="dash-muted">Not launched</span>';
                }) },
                { data: 'average_score', searchable: false, render: display(function (value) { return esc(score(value)); }) },
                { data: 'status', render: display(function (value) { return statusBadge(value); }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { self_review: true }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });
    }

    /* ---------- Appraisals ---------- */

    function appraisals() {
        const mounted = kit.mount({
            root: root,
            modal: null,
            order: [[0, 'asc']],
            titles: {},
            emptyText: 'No appraisals here.',
            searchPlaceholder: 'Search employee…',
            editable: false,
            deletable: false,
            params: filterParams,
            nameOf: function (row) { return row.employee_name; },
            onView: function (row) {
                if (window.PinnacleAppraisal) {
                    window.PinnacleAppraisal.open(row.id, function () { mounted.grid.ajax.reload(null, false); });
                }
            },
            columns: [
                { data: 'employee_name', render: display(function (value, row) { return titleCell(value, row.employee_code); }) },
                { data: 'department_name', orderable: false, defaultContent: '—' },
                { data: 'reviewer_name', orderable: false, render: display(function (value) { return value ? esc(value) : '<span class="dash-badge dash-badge-danger">Not set</span>'; }) },
                { data: 'self_score', searchable: false, render: display(function (value) { return esc(score(value)); }) },
                { data: 'overall_score', searchable: false, render: display(function (value) { return '<strong>' + esc(score(value)) + '</strong>'; }) },
                { data: 'rating_name', orderable: false, defaultContent: '—' },
                { data: 'status', render: display(statusBadge) }
            ]
        });

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            select.addEventListener('change', function () { mounted.grid.ajax.reload(); });
        });
    }

    ({ criteria: criteria, cycles: cycles, appraisals: appraisals }[root.dataset.appraisals] || function () {})();
})();

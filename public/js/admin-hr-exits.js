/**
 * HR → Staff Exits: grid + "Start exit" popup; the detail popup ticks each clearance area, works out the
 * settlement live (extra lines included), pays it, and opens the experience letter once settled.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-exits]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const detail = document.getElementById('exitDetail');
    const settleForm = detail.querySelector('[data-settle-form]');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const TONES = { clearance: 'dash-badge-warn', settled: 'dash-badge-ok', cancelled: 'dash-badge-system' };
    const TYPES = { resignation: 'Resignation', termination: 'Termination', retirement: 'Retirement', contract_end: 'Contract end' };
    let current = null;
    let previewTimer = null;

    function today() {
        return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    }

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function headline(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
    }

    const mounted = kit.mount({
        root: root,
        modal: document.getElementById('exitModal'),
        order: [[1, 'desc']],
        titles: { create: 'Start exit', edit: 'Start exit' },
        searchPlaceholder: 'Search employee…',
        emptyText: 'No staff exits yet.',
        editable: false,
        deletable: false,
        params: function () { return { status: root.querySelector('[data-filter="status"]').value }; },
        nameOf: function (row) { return row.employee_name; },
        onView: openExit,
        columns: [
            { data: 'employee_name', name: 'employee_name', orderable: false, render: display(function (value, row) { return '<span class="dash-cell-title">' + esc(value || '—') + '</span><span class="dash-muted">' + esc([row.employee_code, row.designation_name].filter(Boolean).join(' · ')) + '</span>'; }) },
            { data: 'last_working_day', searchable: false, render: display(function (value, row) { return '<span class="dash-cell-title">' + esc(dateFormat.format(new Date(value))) + '</span><span class="dash-muted">' + esc([TYPES[row.exit_type], row.reason_name].filter(Boolean).join(' · ')) + '</span>'; }) },
            { data: 'cleared_count', searchable: false, orderable: false, className: 'dash-hide-md', render: display(function (value, row) { return esc(value + ' / ' + row.clearances_count + ' cleared'); }) },
            { data: 'final_settlement_amount', searchable: false, className: 'dash-col-num', render: display(function (value) { return value === null ? '—' : money(value); }) },
            { data: 'status', searchable: false, render: display(function (value) { return '<span class="dash-badge ' + (TONES[value] || '') + '">' + esc(value === 'clearance' ? 'In clearance' : headline(value)) + '</span>'; }) }
        ],
        fill: function (form) {
            form.elements.exit_type.value = 'resignation';
        },
        payload: function (form) {
            return kit.readForm(form, { employee_id: 'int', exit_type: 'text', reason_id: 'int', notice_date: 'text', last_working_day: 'text', remarks: 'text' });
        }
    });

    root.querySelector('[data-filter="status"]').addEventListener('change', function () { mounted.refresh(); });
    kit.wireDialog(detail);

    /* ---------- Detail ---------- */

    function extraLines() {
        return Array.prototype.map.call(detail.querySelectorAll('[data-extra-line]'), function (row) {
            return {
                label: row.querySelector('[name="label"]').value.trim(),
                type: row.querySelector('[name="type"]').value,
                amount: Math.round((Number(row.querySelector('[name="amount"]').value) || 0) * 100)
            };
        }).filter(function (line) { return line.label !== '' && line.amount > 0; });
    }

    function renderSettlement(settlement) {
        detail.querySelector('[data-lines]').innerHTML = settlement.lines.map(function (line) {
            return '<tr><td>' + esc(line.label) + (line.source === 'manual' ? ' <span class="dash-muted">added</span>' : '') + '</td><td class="dash-col-num">' + (line.type === 'deduction' ? '−' : '') + money(line.amount) + '</td></tr>';
        }).join('') + '<tr class="dash-exit-total"><td><strong>Net to pay</strong></td><td class="dash-col-num"><strong>' + money(settlement.net) + '</strong></td></tr>';

        detail.querySelector('[data-assets]').textContent = settlement.assets_held.length
            ? 'Still holding: ' + settlement.assets_held.map(function (asset) { return asset.name + ' (' + asset.asset_tag + ')'; }).join(', ')
            : 'No school assets held.';

        const blocking = detail.querySelector('[data-blocking]');
        blocking.hidden = settlement.blocking.length === 0;
        blocking.textContent = settlement.blocking.join(' ');
        detail.querySelector('[data-pay]').disabled = settlement.blocking.length > 0;
    }

    function preview() {
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(function () {
            ui.api('POST', root.dataset.resourceUrl + '/' + current.id + '/preview', { lines: extraLines() }).then(function (envelope) {
                renderSettlement(envelope.data);
            }).catch(function () {});
        }, 300);
    }

    function render(data) {
        const exit = data.exit;
        const open = exit.status === 'clearance';
        current = exit;

        detail.querySelector('[data-detail-title]').textContent = exit.employee ? (exit.employee.first_name + ' ' + (exit.employee.last_name || '')).trim() : 'Exit';
        detail.querySelector('[data-detail-subtitle]').textContent = [TYPES[exit.exit_type], exit.reason ? exit.reason.name : null, 'last day ' + dateFormat.format(new Date(exit.last_working_day))].filter(Boolean).join(' · ');
        detail.querySelector('[data-letter]').hidden = exit.status !== 'settled';

        detail.querySelector('[data-clearances]').innerHTML = exit.clearances.map(function (clearance) {
            return '<li class="dash-leave-check' + (clearance.cleared ? ' is-done' : '') + '"><span class="dash-leave-mark" aria-hidden="true">' + (clearance.cleared ? '✓' : '!') + '</span>'
                + '<div><strong>' + esc(clearance.area ? clearance.area.name : 'Area') + '</strong><span class="dash-muted">'
                + esc(clearance.cleared ? 'Cleared by ' + (clearance.cleared_by ? clearance.cleared_by.name : '') : 'Waiting') + '</span>'
                + (open ? '<button type="button" class="dash-link-btn" data-clear="' + clearance.id + '" data-value="' + (clearance.cleared ? 0 : 1) + '">' + (clearance.cleared ? 'Undo' : 'Mark cleared') + '</button>' : '')
                + '</div></li>';
        }).join('') || '<li class="dash-muted">No clearance areas are set up (Lookups → Exit clearance area).</li>';

        settleForm.hidden = !open;

        if (open) {
            renderSettlement(data.settlement);
        } else {
            detail.querySelector('[data-lines]').innerHTML = exit.lines.map(function (line) {
                return '<tr><td>' + esc(line.label) + '</td><td class="dash-col-num">' + (line.type === 'deduction' ? '−' : '') + money(line.amount) + '</td></tr>';
            }).join('') + (exit.final_settlement_amount !== null ? '<tr class="dash-exit-total"><td><strong>Paid on ' + esc(dateFormat.format(new Date(exit.settlement_paid_on))) + '</strong></td><td class="dash-col-num"><strong>' + money(exit.final_settlement_amount) + '</strong></td></tr>' : '');
            detail.querySelector('[data-assets]').textContent = '';
        }
    }

    function openExit(row) {
        ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) {
            settleForm.reset();
            kit.clearErrors(settleForm);
            detail.querySelector('[data-extra]').innerHTML = '';
            settleForm.elements.paid_on.value = today();
            render(envelope.data);
            detail.showModal();
        }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not open the exit.', 'error'); });
    }

    function reload() {
        return ui.api('GET', root.dataset.resourceUrl + '/' + current.id).then(function (envelope) {
            render(envelope.data);
            mounted.refresh();
        });
    }

    detail.addEventListener('click', function (event) {
        const clear = event.target.closest('[data-clear]');
        const remove = event.target.closest('[data-remove-line]');

        if (clear) {
            ui.api('PUT', root.dataset.clearanceUrl + '/' + clear.dataset.clear, { cleared: clear.dataset.value === '1' }).then(function (envelope) {
                ui.toast(envelope.message);
                reload().then(preview);
            }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not update.', 'error'); });
        }

        if (remove) {
            remove.closest('[data-extra-line]').remove();
            preview();
        }
    });

    detail.querySelector('[data-add-line]').addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'dash-exit-line';
        row.setAttribute('data-extra-line', '');
        row.innerHTML = '<input name="label" placeholder="Gratuity, bonus, notice-period deduction…" aria-label="What for">'
            + '<select name="type" aria-label="Earning or deduction"><option value="earning">Pay</option><option value="deduction">Deduct</option></select>'
            + '<input name="amount" type="number" min="1" step="1" placeholder="Rs" aria-label="Amount in rupees">'
            + '<button type="button" class="dash-icon-btn" data-remove-line aria-label="Remove line">×</button>';
        detail.querySelector('[data-extra]').appendChild(row);
        row.querySelector('input').focus();
    });

    detail.querySelector('[data-extra]').addEventListener('input', preview);

    settleForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const body = kit.readForm(settleForm, { payment_method_id: 'int', paid_on: 'text', exit_interview_notes: 'text' });
        body.lines = extraLines();

        kit.submitForm(settleForm, 'POST', root.dataset.resourceUrl + '/' + current.id + '/settle', body).then(function (envelope) {
            render({ exit: envelope.data, settlement: null });
            mounted.refresh();
        }).catch(function () {});
    });

    detail.querySelector('[data-cancel-exit]').addEventListener('click', function () {
        ui.api('POST', root.dataset.resourceUrl + '/' + current.id + '/cancel').then(function (envelope) {
            ui.toast(envelope.message);
            detail.close();
            mounted.refresh();
        }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not cancel.', 'error'); });
    });

    detail.querySelector('[data-letter]').addEventListener('click', function () {
        ui.openFile(root.dataset.resourceUrl + '/' + current.id + '/experience-letter', 'Preparing the letter…').catch(function () {});
    });
})();

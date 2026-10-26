/**
 * Fees → Online Payments: payments families sent with a screenshot. Open one to see the screenshot and
 * approve it (a receipt is made) or reject it with the reason. Runs on /api/erp/fees/payment-proofs.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-online-payments]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const esc = window.PinnacleGrid.escape;
    const dialog = document.getElementById('proofReview');
    const statusFilter = root.querySelector('[data-filter="status"]');
    const rejectBox = dialog.querySelector('[data-proof-reject]');
    const reason = dialog.querySelector('#proofReason');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const badges = { pending: '<span class="dash-badge dash-badge-warn">To check</span>', approved: '<span class="dash-badge dash-badge-ok">Approved</span>', rejected: '<span class="dash-badge dash-badge-danger">Rejected</span>' };
    let current = null;

    function money(paisa) {
        return 'Rs ' + (paisa / 100).toLocaleString('en-PK', { maximumFractionDigits: 2 });
    }

    function display(render) {
        return function (value, type, row) { return type === 'display' ? render(value, row) : value; };
    }

    function open(row) {
        const shot = root.dataset.resourceUrl + '/' + row.id + '/screenshot';
        const image = dialog.querySelector('[data-proof-image]');
        const fileLink = dialog.querySelector('[data-proof-file]');

        current = row;
        dialog.querySelector('[data-proof-title]').textContent = money(row.amount) + ' — ' + (row.student_name || '');
        dialog.querySelector('[data-proof-sub]').textContent = (row.method_name || '') + ' · ' + row.reference_no;
        dialog.querySelector('[data-proof-link]').href = shot;
        image.hidden = false;
        fileLink.hidden = true;
        image.onerror = function () { image.hidden = true; fileLink.hidden = false; };
        image.src = shot;
        dialog.querySelector('[data-proof-facts]').innerHTML = [
            ['Student', esc(row.student_name) + ' · ' + esc(row.admission_no || '')],
            ['Paid by', esc(row.method_name)],
            ['Transaction ID', '<strong>' + esc(row.reference_no) + '</strong>'],
            ['Paid on', esc(dateFormat.format(new Date(row.paid_on)))],
            ['Amount', '<strong>' + money(row.amount) + '</strong>'],
            ['Challan', esc(row.invoice_no || 'Oldest unpaid challans')],
            ['Payer', esc(row.payer_name || '—')],
            ['Note', esc(row.note || '—')],
            ['Status', badges[row.status] + (row.reject_reason ? ' ' + esc(row.reject_reason) : '')]
        ].map(function (fact) { return '<dt>' + fact[0] + '</dt><dd>' + fact[1] + '</dd>'; }).join('');
        rejectBox.hidden = true;
        reason.value = '';
        dialog.querySelector('[data-proof-actions]').hidden = row.status !== 'pending';
        dialog.showModal();
    }

    const mounted = kit.mount({
        root: root,
        order: [],
        searchPlaceholder: 'Search student or transaction…',
        emptyText: 'Nothing here.',
        editable: false,
        deletable: false,
        nameOf: function (row) { return row.reference_no; },
        onView: open,
        params: function () { return { status: statusFilter.value }; },
        columns: [
            { data: 'student_name', name: 'student_name', orderable: false, render: display(function (value, row) { return '<span class="dash-cell-title">' + esc(value) + '</span><span class="dash-cell-sub">' + esc(row.admission_no || '') + '</span>'; }) },
            { data: 'method_name', name: 'payment_methods.name' },
            { data: 'reference_no', name: 'payment_proofs.reference_no', className: 'dash-hide-md' },
            { data: 'paid_on', name: 'payment_proofs.paid_on', searchable: false, render: display(function (value) { return esc(dateFormat.format(new Date(value))); }) },
            { data: 'amount', name: 'payment_proofs.amount', searchable: false, className: 'dash-col-num', render: display(money) },
            { data: 'status', name: 'payment_proofs.status', searchable: false, render: display(function (value) { return badges[value] || esc(value); }) }
        ]
    });

    kit.wireDialog(dialog);
    statusFilter.addEventListener('change', mounted.refresh);

    function decide(action, body, button) {
        button.disabled = true;
        ui.api('POST', root.dataset.resourceUrl + '/' + current.id + '/' + action, body).then(function (envelope) {
            ui.toast(envelope.message);
            dialog.close();
            mounted.refresh();
        }).catch(function (envelope) {
            ui.toast((envelope && envelope.message) || 'Could not save.', 'error');
        }).finally(function () { button.disabled = false; });
    }

    dialog.querySelector('[data-proof-approve]').addEventListener('click', function (event) {
        decide('approve', {}, event.currentTarget);
    });

    dialog.querySelector('[data-proof-reject-btn]').addEventListener('click', function (event) {
        if (rejectBox.hidden) {
            rejectBox.hidden = false;
            reason.focus();

            return;
        }

        if (!reason.value.trim()) {
            ui.toast('Write why it is rejected; the family will see it.', 'error');
            reason.focus();

            return;
        }

        decide('reject', { reason: reason.value.trim() }, event.currentTarget);
    });
})();

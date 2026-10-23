/**
 * Expenses (draft → approve → pay, cancel / reverse, bill scan), expense categories, vendor payments with
 * what is owed to each vendor, and bank reconciliation sheets. Amounts travel in paisa.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-expenses]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const canApprove = root.dataset.canApprove === '1';
    const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const TONES = { draft: 'dash-badge-warn', approved: 'dash-badge-brand', paid: 'dash-badge-ok', posted: 'dash-badge-ok', completed: 'dash-badge-ok', cancelled: 'dash-badge-system', reversed: 'dash-badge-system' };

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function badge(value) {
        return '<span class="dash-badge ' + (TONES[value] || '') + '">' + esc(String(value || '').replace(/^\w/, function (letter) { return letter.toUpperCase(); })) + '</span>';
    }

    function day(value) {
        return value ? dateFormat.format(new Date(value)) : '—';
    }

    function titleCell(title, sub) {
        return '<strong>' + esc(title || '—') + '</strong>' + (sub ? '<span class="dash-muted dash-cell-sub">' + esc(sub) + '</span>' : '');
    }

    function filterParams() {
        const params = {};

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            params[select.dataset.filter] = select.value;
        });

        return params;
    }

    function closeOnButtons(modal) {
        modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
    }

    function askReason(question) {
        const reason = window.prompt(question + ' (at least 10 characters; it goes on the voucher)');

        return reason && reason.trim().length >= 10 ? reason.trim() : (reason ? (ui.toast('The reason needs at least 10 characters.', 'error'), null) : null);
    }

    /* ---------- Expenses ---------- */

    function expenses() {
        const SPEC = { expense_category_id: 'int', vendor_id: 'int', expense_date: 'text', reference_no: 'text', amount: 'money', tax_amount: 'money', description: 'text' };
        const view = document.getElementById('expenseView');
        const payModal = document.getElementById('payModal');
        const payForm = payModal.querySelector('form');
        let current = null;

        const mounted = kit.mount({
            root: root,
            modal: document.getElementById('expenseModal'),
            order: [[2, 'desc']],
            titles: { create: 'Record expense', edit: 'Edit expense' },
            emptyText: 'No expenses yet.',
            searchPlaceholder: 'Search number or description…',
            params: filterParams,
            nameOf: function (row) { return row.expense_no; },
            canEdit: function (row) { return row.status === 'draft' ? true : 'Only drafts change'; },
            canDelete: function (row) { return row.status === 'draft' ? true : 'Cancel it instead'; },
            onView: function (row) { open(row.id); },
            loadRow: function (row) { return ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; }); },
            columns: [
                { data: 'expense_no', render: display(function (value, row) { return titleCell(value, row.description); }) },
                { data: 'category_name', orderable: false, render: display(function (value, row) { return titleCell(value, row.vendor_name); }) },
                { data: 'expense_date', render: display(function (value) { return esc(day(value)); }) },
                { data: 'amount', searchable: false, render: display(function (value, row) { return esc(money(row.total)) + (row.tax_amount ? '<span class="dash-muted dash-cell-sub">incl. tax ' + esc(money(row.tax_amount)) + '</span>' : ''); }) },
                { data: 'status', render: display(function (value) { return badge(value); }) },
                { data: 'voucher_no', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { expense_date: today }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });

        function files() {
            ui.api('GET', root.dataset.attachmentsUrl + '?owner=expenses&owner_id=' + current.id).then(function (envelope) {
                view.querySelector('[data-view-files]').innerHTML = envelope.data.map(function (file) {
                    return '<li><a href="' + esc(file.url) + '" target="_blank" rel="noopener">' + esc(file.original_name) + '</a><span class="dash-muted">' + esc(file.size_label) + '</span></li>';
                }).join('') || '<li class="dash-muted">No bill attached yet.</li>';
            }).catch(function () {});
        }

        function open(id) {
            ui.api('GET', root.dataset.resourceUrl + '/' + id).then(function (envelope) {
                current = envelope.data;

                const facts = [
                    ['Status', badge(current.status)], ['Category', esc(current.category_name)], ['Vendor', esc(current.vendor_name || '—')], ['Bill date', esc(day(current.expense_date))],
                    ['Amount', esc(money(current.amount))], ['Tax', esc(money(current.tax_amount))], ['Total', '<strong>' + esc(money(current.total)) + '</strong>'],
                    ['Approved by', esc(current.approver_name || '—')], ['Paid', esc(current.paid_on ? day(current.paid_on) + ' · ' + (current.payment_method_name || '') : '—')], ['Voucher', esc(current.voucher_no || '—')]
                ];

                view.querySelector('[data-view-title]').textContent = current.expense_no;
                view.querySelector('[data-view-sub]').textContent = (current.description || '') + (current.cancel_reason ? ' · cancelled: ' + current.cancel_reason : '');
                view.querySelector('[data-view-facts]').innerHTML = facts.map(function (fact) { return '<div><dt>' + fact[0] + '</dt><dd>' + fact[1] + '</dd></div>'; }).join('');
                view.querySelector('[data-view-actions]').innerHTML = '<button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>'
                    + (current.status === 'draft' && canApprove ? '<button type="button" class="dash-btn dash-btn-primary" data-expense-action="approve">Approve</button>' : '')
                    + (current.status === 'approved' ? '<button type="button" class="dash-btn dash-btn-primary" data-expense-action="pay">Pay</button>' : '')
                    + (current.status !== 'cancelled' ? '<button type="button" class="dash-btn dash-btn-danger" data-expense-action="cancel">' + (current.status === 'paid' ? 'Reverse' : 'Cancel') + '</button>' : '');
                closeOnButtons(view);
                files();

                if (!view.open) {
                    view.showModal();
                }
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The expense could not be opened.'), 'error'); });
        }

        view.querySelector('[data-view-actions]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-expense-action]');

            if (!button) {
                return;
            }

            if (button.dataset.expenseAction === 'approve') {
                ui.api('POST', root.dataset.resourceUrl + '/' + current.id + '/approve').then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); open(current.id); })
                    .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not approve.'), 'error'); });
            }

            if (button.dataset.expenseAction === 'pay') {
                payForm.reset();
                kit.clearErrors(payForm);
                kit.fillForm(payForm, null, { paid_on: 'text', payment_method_id: 'int', reference_no: 'text' }, { paid_on: today, payment_method_id: current.payment_method_id });
                payModal.querySelector('[data-modal-sub]').textContent = current.expense_no + ' · ' + money(current.total);
                payModal.showModal();
            }

            if (button.dataset.expenseAction === 'cancel') {
                const reason = askReason((current.status === 'paid' ? 'Why reverse ' : 'Why cancel ') + current.expense_no + '?');

                if (reason) {
                    ui.api('POST', root.dataset.resourceUrl + '/' + current.id + '/cancel', { reason: reason }).then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); open(current.id); })
                        .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not cancel.'), 'error'); });
                }
            }
        });

        view.querySelector('[data-view-upload]').addEventListener('change', function (event) {
            const file = event.target.files[0];

            if (!file) {
                return;
            }

            const body = new FormData();
            body.append('owner', 'expenses');
            body.append('owner_id', current.id);
            body.append('file', file);
            body.append('title', 'Bill ' + current.expense_no);
            ui.api('POST', root.dataset.attachmentsUrl, body).then(function (envelope) { ui.toast(envelope.message); files(); })
                .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The file was not uploaded.'), 'error'); });
            event.target.value = '';
        });

        closeOnButtons(payModal);
        payForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(payForm, 'POST', root.dataset.resourceUrl + '/' + current.id + '/pay', kit.readForm(payForm, { paid_on: 'text', payment_method_id: 'int', reference_no: 'text' })).then(function () {
                payModal.close();
                mounted.refresh();
                open(current.id);
            }).catch(function () {});
        });

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            select.addEventListener('change', function () { mounted.grid.ajax.reload(); });
        });
    }

    /* ---------- Categories ---------- */

    function categories() {
        const SPEC = { name: 'text', parent_id: 'int', expense_account_id: 'int', is_active: 'bool' };

        kit.mount({
            root: root,
            modal: document.getElementById('categoryModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add category', edit: 'Edit category' },
            emptyText: 'No categories yet.',
            nameOf: function (row) { return row.name; },
            canDelete: function (row) { return row.expenses_count > 0 ? 'Has expenses' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.parent_name); }) },
                { data: 'account_name', orderable: false, defaultContent: '—' },
                { data: 'expenses_count', searchable: false },
                { data: 'is_active', render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { is_active: true }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });
    }

    /* ---------- Vendor payments ---------- */

    function vendorPayments() {
        const SPEC = { vendor_id: 'int', paid_on: 'text', amount: 'money', payment_method_id: 'int', reference_no: 'text', remarks: 'text' };
        const modal = document.getElementById('vendorPayModal');
        const form = modal.querySelector('form');
        const balancesPanel = document.querySelector('[data-vendor-balances]');
        const balances = window.PinnacleGrid.create(balancesPanel.querySelector('[data-balance-grid]'), {
            url: balancesPanel.dataset.url,
            order: [[2, 'desc']],
            emptyText: 'No vendors yet.',
            exportName: 'vendor-balances',
            columns: [
                { data: 'name' },
                { data: 'phone', defaultContent: '—' },
                { data: 'owed', searchable: false, render: display(function (value) { return '<span class="dash-badge ' + (value > 0 ? 'dash-badge-warn' : 'dash-badge-ok') + '">' + esc(money(value)) + '</span>'; }) }
            ]
        });

        function showOwed() {
            const line = form.querySelector('[data-owed]');

            line.textContent = '';

            if (!form.elements.vendor_id.value) {
                return;
            }

            ui.api('GET', root.dataset.resourceUrl.replace('vendor-payments', 'vendors') + '/' + form.elements.vendor_id.value + '/owed').then(function (envelope) {
                line.textContent = 'Owed to this vendor: ' + money(envelope.data.owed);
            }).catch(function () {});
        }

        form.elements.vendor_id.addEventListener('change', showOwed);

        const mounted = kit.mount({
            root: root,
            modal: modal,
            order: [[2, 'desc']],
            titles: { create: 'Pay vendor', edit: 'Pay vendor' },
            emptyText: 'No vendor payments yet.',
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.payment_no; },
            onView: function (row) {
                if (row.status !== 'posted' || !canApprove) {
                    ui.toast(row.status === 'posted' ? 'Only an approver can reverse a payment.' : 'This payment is reversed.');

                    return;
                }

                const reason = askReason('Why reverse ' + row.payment_no + '?');

                if (reason) {
                    ui.api('POST', root.dataset.resourceUrl + '/' + row.id + '/reverse', { reason: reason }).then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); balances.ajax.reload(null, false); })
                        .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not reverse.'), 'error'); });
                }
            },
            afterSave: function () { balances.ajax.reload(null, false); },
            columns: [
                { data: 'payment_no', render: display(function (value, row) { return titleCell(value, row.reference_no); }) },
                { data: 'vendor_name', orderable: false },
                { data: 'paid_on', render: display(function (value) { return esc(day(value)); }) },
                { data: 'amount', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'payment_method_name', orderable: false, defaultContent: '—' },
                { data: 'status', render: display(function (value) { return badge(value); }) },
                { data: 'voucher_no', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' }
            ],
            fill: function (formElement) { kit.fillForm(formElement, null, SPEC, { paid_on: today }); showOwed(); },
            payload: function (formElement) { return kit.readForm(formElement, SPEC); }
        });
    }

    /* ---------- Bank reconciliation ---------- */

    function reconciliation() {
        const SPEC = { bank_account_id: 'int', statement_date: 'text', statement_balance: 'money', remarks: 'text' };
        const sheet = document.getElementById('reconSheet');
        const rows = sheet.querySelector('[data-sheet-rows]');
        let current = null;

        function render() {
            const locked = current.status !== 'draft';
            const totals = [
                ['Statement balance', current.statement_balance], ['+ Deposits not on statement', current.uncleared_deposits], ['− Payments not on statement', current.uncleared_payments],
                ['= Adjusted statement', current.adjusted_statement], ['Book balance', current.book_balance], ['Difference', current.difference]
            ];

            sheet.querySelector('[data-sheet-title]').textContent = current.bank_label;
            sheet.querySelector('[data-sheet-sub]').innerHTML = badge(current.status) + ' statement of ' + esc(day(current.statement_date));
            sheet.querySelector('[data-sheet-totals]').innerHTML = totals.map(function (row, index) {
                return '<div><dt>' + esc(row[0]) + '</dt><dd>' + (index === 5 ? '<span class="dash-badge ' + (row[1] === 0 ? 'dash-badge-ok' : 'dash-badge-danger') + '">' + esc(money(row[1])) + '</span>' : esc(money(row[1]))) + '</dd></div>';
            }).join('');
            rows.innerHTML = current.lines.map(function (line) {
                return '<tr data-line="' + line.id + '"><td><input type="checkbox" data-cleared' + (line.cleared ? ' checked' : '') + (locked ? ' disabled' : '') + ' aria-label="Cleared"></td><td>' + esc(day(line.entry_date)) + '</td><td>' + esc(line.voucher_no) + '</td><td>' + esc(line.narration || '') + '</td>'
                    + '<td>' + (line.debit ? esc(money(line.debit)) : '') + '</td><td>' + (line.credit ? esc(money(line.credit)) : '') + '</td></tr>';
            }).join('') || '<tr><td colspan="6" class="dash-muted">No lines to clear up to this date.</td></tr>';
            sheet.querySelector('[data-sheet-actions]').hidden = locked;
        }

        function open(id) {
            ui.api('GET', root.dataset.resourceUrl + '/' + id).then(function (envelope) {
                current = envelope.data;
                render();

                if (!sheet.open) {
                    sheet.showModal();
                }
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not open.'), 'error'); });
        }

        function save() {
            const cleared = Array.prototype.filter.call(rows.querySelectorAll('[data-line]'), function (row) { return row.querySelector('[data-cleared]').checked; })
                .map(function (row) { return { journal_line_id: Number(row.dataset.line) }; });

            return ui.api('PUT', root.dataset.resourceUrl + '/' + current.id, { cleared: cleared }).then(function (envelope) {
                current = envelope.data;
                render();
                mounted.refresh();

                return envelope;
            });
        }

        const mounted = kit.mount({
            root: root,
            modal: document.getElementById('reconModal'),
            order: [[1, 'desc']],
            titles: { create: 'New reconciliation', edit: 'New reconciliation' },
            emptyText: 'No reconciliations yet.',
            editable: false,
            nameOf: function (row) { return row.bank_label + ' ' + row.statement_date; },
            canDelete: function (row) { return row.status === 'draft' ? true : 'Completed'; },
            onView: function (row) { open(row.id); },
            afterSave: function (record) { open(record.id); },
            columns: [
                { data: 'bank_label', orderable: false },
                { data: 'statement_date', render: display(function (value) { return esc(day(value)); }) },
                { data: 'statement_balance', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'book_balance', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'status', render: display(function (value) { return badge(value); }) },
                { data: 'reconciler_name', orderable: false, className: 'dash-hide-md', defaultContent: '—' }
            ],
            fill: function (form) { kit.fillForm(form, null, SPEC, { statement_date: today }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });

        closeOnButtons(sheet);
        rows.addEventListener('change', function () {
            const open = Array.prototype.filter.call(rows.querySelectorAll('[data-line]'), function (row) { return !row.querySelector('[data-cleared]').checked; });

            current.uncleared_deposits = 0;
            current.uncleared_payments = 0;
            open.forEach(function (row) {
                const line = current.lines.find(function (entry) { return entry.id === Number(row.dataset.line); });

                current.uncleared_deposits += line.debit;
                current.uncleared_payments += line.credit;
            });
            current.lines.forEach(function (line) { line.cleared = !open.some(function (row) { return Number(row.dataset.line) === line.id; }); });
            current.adjusted_statement = current.statement_balance + current.uncleared_deposits - current.uncleared_payments;
            current.difference = current.book_balance - current.adjusted_statement;
            render();
        });
        sheet.querySelector('[data-sheet-save]').addEventListener('click', function () {
            save().then(function (envelope) { ui.toast(envelope.message); }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not save.'), 'error'); });
        });
        sheet.querySelector('[data-sheet-complete]').addEventListener('click', function () {
            save().then(function () {
                return ui.api('POST', root.dataset.resourceUrl + '/' + current.id + '/complete');
            }).then(function (envelope) {
                ui.toast(envelope.message);
                current = envelope.data;
                render();
                mounted.refresh();
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not complete.'), 'error'); });
        });
    }

    ({ expenses: expenses, 'expense-categories': categories, 'vendor-payments': vendorPayments, 'bank-reconciliation': reconciliation }[root.dataset.expenses] || function () {})();
})();

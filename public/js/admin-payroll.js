/**
 * HR leave, payroll and loan screens (one file, picked by data-payroll): leave queue, leave types,
 * salary components and tax slabs, salary structures, payroll runs and payslips, loans.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const root = document.querySelector('[data-payroll]');

    if (!ui || !kit || !window.PinnacleGrid || !root) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const screen = root.dataset.payroll;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const monthFormat = new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' });
    const rupees = function (paisa) { return 'Rs ' + Math.round((Number(paisa) || 0) / 100).toLocaleString('en-PK'); };
    const BADGES = { pending: 'dash-badge-warn', approved: 'dash-badge-ok', rejected: 'dash-badge-danger', cancelled: 'dash-badge-system', draft: 'dash-badge-system', posted: 'dash-badge-ok', paid: 'dash-badge-ok', active: 'dash-badge-warn', closed: 'dash-badge-system', final: 'dash-badge-ok' };

    function display(render) {
        return function (value, type, row) { return type === 'display' ? render(value, row) : value; };
    }

    function titleCell(title, subtitle) {
        return '<span class="dash-cell-title">' + esc(title) + '</span>' + (subtitle ? '<span class="dash-muted">' + esc(subtitle) + '</span>' : '');
    }

    function badge(status) {
        return '<span class="dash-badge ' + (BADGES[status] || '') + '">' + esc(String(status).charAt(0).toUpperCase() + String(status).slice(1)) + '</span>';
    }

    function filterParams() {
        const values = {};

        root.querySelectorAll('[data-filter]').forEach(function (field) { values[field.dataset.filter] = field.value; });

        return values;
    }

    function wireFilters(grid) {
        root.querySelectorAll('[data-filter]').forEach(function (field) {
            field.addEventListener('change', function () { grid.ajax.reload(); });
        });
    }

    function post(url, body, busyText) {
        const hide = busyText ? ui.busy(busyText) : function () {};

        return ui.api('POST', url, body).then(function (envelope) {
            ui.toast(envelope.message);

            return envelope;
        }).catch(function (envelope) {
            ui.toast(kit.messageOf(envelope, 'That did not work.'), 'error');

            return Promise.reject(envelope);
        }).finally(hide);
    }

    /* A small "reason" popup for rejecting leave or loans. */
    function askReason(callback) {
        const dialog = document.getElementById('decideModal');
        const form = dialog.querySelector('form');

        form.reset();
        kit.clearErrors(form);
        dialog.showModal();
        form.onsubmit = function (event) {
            event.preventDefault();
            callback(form.elements.remarks.value.trim(), form, dialog);
        };
    }

    document.querySelectorAll('dialog').forEach(function (dialog) {
        dialog.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { dialog.close(); });
        });
    });

    /* ---------- Leave queue ---------- */

    if (screen === 'leave') {
        const SPEC = { employee_id: 'int', leave_type_id: 'int', from_date: 'text', to_date: 'text', is_half_day: 'bool', reason: 'text' };
        const mounted = kit.mount({
            root: root,
            modal: document.getElementById('leaveModal'),
            order: [[0, 'asc']],
            titles: { create: 'Apply for staff', edit: 'Apply for staff' },
            emptyText: 'No leave requests.',
            params: filterParams,
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.employee_name; },
            columns: [
                { data: 'employee_name', name: 'employee_id', render: display(function (value, row) { return titleCell(value, row.employee_code); }) },
                { data: 'from_date', searchable: false, render: display(function (value, row) {
                    return titleCell(row.leave_type_name + ' · ' + row.days + ' day' + (row.days === 1 ? '' : 's'), dateFormat.format(new Date(value)) + (row.to_date !== value ? ' – ' + dateFormat.format(new Date(row.to_date)) : '') + (row.is_half_day ? ' (half day)' : ''));
                }) },
                { data: 'reason', orderable: false, className: 'dash-hide-md', render: display(function (value, row) { return esc(value) + (row.review_remarks ? '<span class="dash-muted">HR: ' + esc(row.review_remarks) + '</span>' : ''); }) },
                { data: 'status', searchable: false, render: display(function (value, row) {
                    let actions = '';

                    if (row.status === 'pending') {
                        actions = '<button type="button" class="dash-link-btn" data-decide="' + row.id + '" data-approve="1">Approve</button> <button type="button" class="dash-link-btn" data-decide="' + row.id + '" data-approve="0">Reject</button>';
                    } else if (row.status === 'approved') {
                        actions = '<button type="button" class="dash-link-btn" data-cancel="' + row.id + '">Cancel</button>';
                    }

                    return badge(value) + (row.approved_by_name ? '<span class="dash-muted">' + esc(row.approved_by_name) + '</span>' : '') + (actions ? '<div class="dash-row-actions">' + actions + '</div>' : '');
                }) }
            ],
            fill: function (form) { kit.fillForm(form, null, SPEC, {}); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });

        wireFilters(mounted.grid);
        root.querySelector('[data-grid]').addEventListener('click', function (event) {
            const decide = event.target.closest('[data-decide]');
            const cancel = event.target.closest('[data-cancel]');
            const base = root.dataset.resourceUrl + '/';

            if (decide && decide.dataset.approve === '1') {
                post(base + decide.dataset.decide + '/decide', { approve: true }).then(mounted.refresh).catch(function () {});
            } else if (decide) {
                askReason(function (remarks, form, dialog) {
                    ui.api('POST', base + decide.dataset.decide + '/decide', { approve: false, remarks: remarks }).then(function (envelope) {
                        ui.toast(envelope.message);
                        dialog.close();
                        mounted.refresh();
                    }).catch(function (envelope) { kit.showErrors(form, envelope); });
                });
            }

            if (cancel) {
                post(base + cancel.dataset.cancel + '/cancel').then(mounted.refresh).catch(function () {});
            }
        });
    }

    /* ---------- Leave types ---------- */

    if (screen === 'leave-types') {
        const SPEC = { name: 'text', code: 'text', days_per_year: 'number', max_carry_forward: 'number', applicable_gender_id: 'int', sort_order: 'int', is_paid: 'bool', carry_forward: 'bool', requires_document: 'bool', is_active: 'bool' };

        kit.mount({
            root: root,
            modal: document.getElementById('payrollModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add leave type', edit: 'Edit leave type' },
            emptyText: 'No leave types yet.',
            nameOf: function (row) { return row.name; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.code); }) },
                { data: 'days_per_year', searchable: false, render: display(function (value, row) { return row.is_paid ? esc(String(value)) : 'No limit'; }) },
                { data: 'is_paid', orderable: false, searchable: false, className: 'dash-hide-md', render: display(function (value, row) {
                    return esc([value ? 'Paid' : 'Unpaid (deducted)', row.carry_forward ? 'carries forward' + (row.max_carry_forward ? ' up to ' + row.max_carry_forward : '') : null, row.requires_document ? 'needs a document' : null, row.gender_name ? 'only ' + row.gender_name : null].filter(Boolean).join(' · '));
                }) },
                { data: 'is_active', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { is_paid: true, is_active: true, days_per_year: 10 }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });
    }

    /* ---------- Salary components + tax slabs ---------- */

    if (screen === 'components') {
        const SPEC = { name: 'text', code: 'text', type: 'text', calculation: 'text', default_value: 'money', account_id: 'int', sort_order: 'int', is_taxable: 'bool', is_active: 'bool' };

        kit.mount({
            root: root,
            modal: document.getElementById('payrollModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add component', edit: 'Edit component' },
            emptyText: 'No components.',
            nameOf: function (row) { return row.name; },
            canDelete: function (row) { return row.is_system ? 'Built in' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.code + ' · ' + row.type + (row.is_system ? ' · built in' : '')); }) },
                { data: 'default_value', searchable: false, render: display(function (value, row) {
                    if (row.is_system) {
                        return '<span class="dash-muted">calculated</span>';
                    }

                    return row.calculation === 'percent_of_basic' ? esc((value / 100).toFixed(2)) + '% of basic' : rupees(value);
                }) },
                { data: 'account_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'is_active', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { type: 'earning', calculation: 'fixed', is_taxable: true, is_active: true }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });

        const slabs = document.querySelector('[data-tax-slabs]');
        const yearSelect = slabs.querySelector('[data-slab-year]');
        const rows = slabs.querySelector('[data-slab-rows]');

        const loadSlabs = function () {
            return ui.api('GET', slabs.dataset.url + '?fiscal_year_id=' + yearSelect.value).then(function (envelope) {
                rows.innerHTML = envelope.data.length ? envelope.data.map(function (slab) {
                    return '<tr><td>' + rupees(slab.min_income) + '</td><td>' + (slab.max_income ? rupees(slab.max_income) : 'and above') + '</td><td>' + rupees(slab.fixed_tax) + '</td><td>' + esc(String(slab.rate_percent)) + '%</td>'
                        + '<td><button type="button" class="dash-link-btn" data-slab-delete="' + slab.id + '">Remove</button></td></tr>';
                }).join('') : '<tr><td colspan="5" class="dash-muted">No slabs: payroll deducts no income tax for this year.</td></tr>';
            });
        };

        yearSelect.addEventListener('change', loadSlabs);
        slabs.querySelector('[data-slab-add]').addEventListener('click', function () {
            const value = function (name) { return slabs.querySelector('[data-slab-field="' + name + '"]').value; };
            const body = {
                fiscal_year_id: Number(yearSelect.value),
                min_income: Math.round(Number(value('min_income') || 0) * 100),
                max_income: value('max_income') === '' ? null : Math.round(Number(value('max_income')) * 100),
                fixed_tax: Math.round(Number(value('fixed_tax') || 0) * 100),
                rate_percent: Number(value('rate_percent') || 0)
            };

            ui.api('POST', slabs.dataset.url, body).then(function (envelope) {
                ui.toast(envelope.message);
                slabs.querySelectorAll('[data-slab-field]').forEach(function (input) { input.value = ''; });
                loadSlabs();
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The slab was not saved.'), 'error'); });
        });
        rows.addEventListener('click', function (event) {
            const remove = event.target.closest('[data-slab-delete]');

            if (remove) {
                ui.eraseRecord('this tax slab', remove.closest('tr'), slabs.dataset.url + '/' + remove.dataset.slabDelete).then(loadSlabs);
            }
        });
        loadSlabs();
    }

    /* ---------- Salary structures ---------- */

    if (screen === 'salaries') {
        const modal = document.getElementById('salaryModal');
        const form = modal.querySelector('form');
        let employeeId = null;
        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[0, 'asc']],
            searchPlaceholder: 'Search name or code…',
            emptyText: 'No current employees.',
            columns: [
                { data: 'first_name', render: display(function (value, row) { return titleCell(row.full_name, row.employee_code); }) },
                { data: 'designation_name', orderable: false, searchable: false, className: 'dash-hide-md', render: display(function (value, row) { return titleCell(value || '—', row.department_name); }) },
                { data: 'current_basic', searchable: false, render: display(function (value) { return value === null ? '<span class="dash-badge dash-badge-warn">Not set</span>' : rupees(value); }) },
                { data: 'current_gross', searchable: false, render: display(function (value) { return value === null ? '—' : rupees(value); }) },
                { data: null, orderable: false, searchable: false, className: 'dash-col-actions', render: display(function (value, row) {
                    return '<button type="button" class="dash-action dash-action-edit" data-salary="' + row.id + '" data-tooltip="Salary" aria-label="Salary of ' + esc(row.full_name) + '">✎</button>';
                }) }
            ]
        });

        root.querySelector('[data-grid]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-salary]');

            if (!button) {
                return;
            }

            employeeId = button.dataset.salary;
            ui.api('GET', root.dataset.api + '/employees/' + employeeId + '/salary-structure').then(function (envelope) {
                const structure = envelope.data.structure;
                const items = structure ? structure.items : [];

                form.reset();
                kit.clearErrors(form);
                modal.querySelector('[data-salary-title]').textContent = envelope.data.employee.name + ' · ' + envelope.data.employee.employee_code;
                modal.querySelector('[data-salary-current]').textContent = structure
                    ? 'Current since ' + dateFormat.format(new Date(structure.effective_from)) + ': basic ' + rupees(structure.basic_salary) + ', gross ' + rupees(structure.gross_salary) + '. Save from a new date to change it.'
                    : 'No salary yet. Leave an amount empty to use the component default.';
                form.elements.effective_from.value = new Date().toISOString().slice(0, 10);
                form.elements.basic_salary.value = structure ? (structure.basic_salary / 100).toFixed(2) : '';
                form.querySelectorAll('[data-item-on]').forEach(function (box) {
                    const item = items.find(function (entry) { return String(entry.salary_component_id) === box.dataset.itemOn; });

                    box.checked = Boolean(item);
                    form.querySelector('[data-item-amount="' + box.dataset.itemOn + '"]').value = item ? (item.amount / 100).toFixed(2) : '';
                });
                modal.querySelector('[data-salary-history]').innerHTML = envelope.data.increments.length ? envelope.data.increments.map(function (increment) {
                    return '<li><span>' + esc(dateFormat.format(new Date(increment.effective_from))) + '</span><strong>' + rupees(increment.old_basic) + ' → ' + rupees(increment.new_basic) + (increment.increment_percent !== null ? ' (' + increment.increment_percent + '%)' : '') + '</strong></li>';
                }).join('') : '<li><span class="dash-muted">No increments yet.</span><strong></strong></li>';
                modal.showModal();
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not open the salary.'), 'error'); });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = {
                effective_from: form.elements.effective_from.value,
                basic_salary: Math.round(Number(form.elements.basic_salary.value || 0) * 100),
                remarks: form.elements.remarks.value.trim() || null,
                items: Array.prototype.filter.call(form.querySelectorAll('[data-item-on]'), function (box) { return box.checked; }).map(function (box) {
                    const amount = form.querySelector('[data-item-amount="' + box.dataset.itemOn + '"]').value;

                    return { salary_component_id: Number(box.dataset.itemOn), amount: amount === '' ? null : Math.round(Number(amount) * 100) };
                })
            };

            kit.submitForm(form, 'POST', root.dataset.api + '/employees/' + employeeId + '/salary-structure', body).then(function () {
                modal.close();
                grid.ajax.reload(null, false);
            }).catch(function () {});
        });
    }

    /* ---------- Payroll runs ---------- */

    if (screen === 'runs') {
        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[0, 'desc']],
            searchPlaceholder: 'Search…',
            emptyText: 'No payroll yet. Pick a month and press Generate.',
            columns: [
                { data: 'month', searchable: false, render: display(function (value, row) { return '<a class="dash-cell-title" href="' + esc(root.dataset.runUrl + '/' + row.id) + '">' + esc(monthFormat.format(new Date(value))) + '</a>'; }) },
                { data: 'status', searchable: false, render: display(badge) },
                { data: 'employee_count', searchable: false, className: 'dash-hide-md' },
                { data: 'total_gross', searchable: false, className: 'dash-hide-md', render: display(rupees) },
                { data: 'total_net', searchable: false, render: display(rupees) }
            ]
        });

        document.querySelector('[data-generate-form]').addEventListener('submit', function (event) {
            event.preventDefault();
            post(root.dataset.api + '/payroll-runs/generate', { month: event.target.elements.month.value }, 'Calculating payslips…').then(function (envelope) {
                window.location.href = root.dataset.runUrl + '/' + envelope.data.id;
            }).catch(function () { grid.ajax.reload(); });
        });
    }

    /* ---------- One payroll run ---------- */

    if (screen === 'payslips') {
        const runUrl = root.dataset.api + '/payroll-runs/' + root.dataset.runId;
        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[0, 'asc']],
            searchPlaceholder: 'Search…',
            emptyText: 'No payslips: give staff a salary under Salaries, then regenerate.',
            columns: [
                { data: 'employee_name', name: 'employee_id', render: display(function (value, row) { return titleCell(value, row.employee_code); }) },
                { data: 'working_days', searchable: false, className: 'dash-hide-md', render: display(function (value, row) {
                    return titleCell(row.present_days + ' / ' + value + ' present', [row.leave_days ? row.leave_days + ' leave' : null, row.unpaid_leave_days ? row.unpaid_leave_days + ' unpaid' : null, row.absent_days ? row.absent_days + ' absent' : null].filter(Boolean).join(' · '));
                }) },
                { data: 'gross', searchable: false, className: 'dash-hide-md', render: display(rupees) },
                { data: 'deductions', searchable: false, className: 'dash-hide-md', render: display(rupees) },
                { data: 'net', searchable: false, render: display(function (value) { return '<strong>' + rupees(value) + '</strong>'; }) },
                { data: null, orderable: false, searchable: false, className: 'dash-col-actions', render: display(function (value, row) {
                    return '<button type="button" class="dash-action dash-action-view" data-print-slip="' + row.id + '" data-tooltip="Payslip PDF" aria-label="Payslip of ' + esc(row.employee_name) + '">🖨</button>';
                }) }
            ]
        });

        root.querySelector('[data-grid]').addEventListener('click', function (event) {
            const print = event.target.closest('[data-print-slip]');

            if (print) {
                ui.openFile(runUrl + '/pdf?ids[]=' + print.dataset.printSlip, 'Preparing the payslip…').catch(function () {});
            }
        });

        document.querySelector('[data-run-print]').addEventListener('click', function () {
            ui.openFile(runUrl + '/pdf', 'Preparing payslips…').catch(function () {});
        });

        const reload = function () { window.location.reload(); };

        document.querySelectorAll('[data-run-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                const action = button.dataset.runAction;

                if (action === 'regenerate') {
                    post(root.dataset.api + '/payroll-runs/generate', { month: root.dataset.runMonth }, 'Recalculating payslips…').then(reload).catch(function () {});
                } else if (action === 'approve') {
                    post(runUrl + '/approve').then(reload).catch(function () {});
                } else if (action === 'post') {
                    post(runUrl + '/post', undefined, 'Posting the salary journal…').then(reload).catch(function () {});
                } else if (action === 'pay') {
                    const dialog = document.getElementById('payModal');

                    dialog.querySelector('form').elements.paid_on.value = new Date().toISOString().slice(0, 10);
                    dialog.showModal();
                } else if (action === 'reverse') {
                    document.getElementById('reverseModal').showModal();
                }
            });
        });

        const payForm = document.querySelector('[data-pay-form]');

        payForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(payForm, 'POST', runUrl + '/pay', kit.readForm(payForm, { payment_method_id: 'int', paid_on: 'text', reference: 'text' })).then(reload).catch(function () {});
        });

        const reverseForm = document.querySelector('[data-reverse-form]');

        reverseForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(reverseForm, 'POST', runUrl + '/reverse', { reason: reverseForm.elements.reason.value.trim() }).then(reload).catch(function () {});
        });
    }

    /* ---------- Loans ---------- */

    if (screen === 'loans') {
        const SPEC = { employee_id: 'int', loan_type_id: 'int', amount: 'money', instalments: 'int', start_month: 'text', reason: 'text' };
        const canApprove = root.dataset.canApprove === '1';
        let disbursing = null;
        const mounted = kit.mount({
            root: root,
            modal: document.getElementById('loanModal'),
            order: [[0, 'asc']],
            titles: { create: 'New loan', edit: 'Loan' },
            emptyText: 'No loans.',
            params: filterParams,
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.employee_name; },
            columns: [
                { data: 'employee_name', name: 'employee_id', render: display(function (value, row) { return titleCell(value, [row.employee_code, row.loan_type_name].filter(Boolean).join(' · ')); }) },
                { data: 'amount', searchable: false, render: display(function (value, row) { return titleCell(rupees(value), row.status === 'active' ? rupees(row.outstanding) + ' left' : (row.reason || '')); }) },
                { data: 'instalments', searchable: false, className: 'dash-hide-md', render: display(function (value, row) {
                    return esc(value + ' × ' + rupees(row.instalment_amount)) + '<span class="dash-muted">from ' + esc(monthFormat.format(new Date(row.start_month))) + '</span>';
                }) },
                { data: 'status', searchable: false, render: display(function (value, row) {
                    let actions = '';

                    if (row.status === 'pending' && canApprove) {
                        actions = '<button type="button" class="dash-link-btn" data-loan-decide="' + row.id + '" data-approve="1">Approve</button> <button type="button" class="dash-link-btn" data-loan-decide="' + row.id + '" data-approve="0">Reject</button>';
                    } else if (row.status === 'approved') {
                        actions = '<button type="button" class="dash-link-btn" data-disburse="' + row.id + '">Pay out</button>';
                    }

                    return badge(value) + (actions ? '<div class="dash-row-actions">' + actions + '</div>' : '');
                }) }
            ],
            fill: function (form) { kit.fillForm(form, null, SPEC, { instalments: 3 }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });

        wireFilters(mounted.grid);

        root.querySelector('[data-grid]').addEventListener('click', function (event) {
            const decide = event.target.closest('[data-loan-decide]');
            const disburse = event.target.closest('[data-disburse]');
            const base = root.dataset.resourceUrl + '/';

            if (decide && decide.dataset.approve === '1') {
                post(base + decide.dataset.loanDecide + '/decide', { approve: true }).then(mounted.refresh).catch(function () {});
            } else if (decide) {
                askReason(function (remarks, form, dialog) {
                    ui.api('POST', base + decide.dataset.loanDecide + '/decide', { approve: false, remarks: remarks }).then(function (envelope) {
                        ui.toast(envelope.message);
                        dialog.close();
                        mounted.refresh();
                    }).catch(function (envelope) { kit.showErrors(form, envelope); });
                });
            }

            if (disburse) {
                disbursing = disburse.dataset.disburse;
                document.querySelector('[data-disburse-form]').elements.issued_on.value = new Date().toISOString().slice(0, 10);
                document.getElementById('disburseModal').showModal();
            }
        });

        const disburseForm = document.querySelector('[data-disburse-form]');

        disburseForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(disburseForm, 'POST', root.dataset.resourceUrl + '/' + disbursing + '/disburse', kit.readForm(disburseForm, { payment_method_id: 'int', issued_on: 'text' })).then(function () {
                document.getElementById('disburseModal').close();
                mounted.refresh();
            }).catch(function () {});
        });
    }
})();

/**
 * Fee screens: challans (manual, monthly run, waive / cancel), receipts (receive / reverse),
 * fee structures, fee heads, concession types and student concessions. Amounts are typed in
 * rupees and sent in paisa; everything goes through /api/erp/fees.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const TONES = { paid: 'dash-badge-ok', approved: 'dash-badge-ok', active: 'dash-badge-ok', posted: 'dash-badge-brand', partially_paid: 'dash-badge-warn', pending: 'dash-badge-warn', overdue: 'dash-badge-danger', reversed: 'dash-badge-danger', rejected: 'dash-badge-danger' };

    function headline(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
    }

    function badge(status) {
        return '<span class="dash-badge ' + (TONES[status] || 'dash-badge-system') + '">' + esc(headline(status)) + '</span>';
    }

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function titleCell(title, subtitle) {
        return '<span class="dash-cell-title">' + esc(title) + '</span>' + (subtitle ? '<span class="dash-muted">' + esc(subtitle) + '</span>' : '');
    }

    function shortDate(value) {
        return value ? esc(dateFormat.format(new Date(value))) : '—';
    }

    function today() {
        const now = new Date();

        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    }

    function valueText(calculation, value) {
        return calculation === 'percent' ? (Number(value) / 100) + '%' : money(value);
    }

    function kv(items) {
        return '<ul class="dash-kv">' + items.filter(function (item) {
            return item[1] !== null && item[1] !== undefined && item[1] !== '';
        }).map(function (item) {
            return '<li><span>' + esc(item[0]) + '</span><strong>' + (item[2] ? item[1] : esc(item[1])) + '</strong></li>';
        }).join('') + '</ul>';
    }

    function filterReader(root) {
        return function () {
            const values = {};

            root.querySelectorAll('[data-filter]').forEach(function (field) {
                values[field.dataset.filter] = field.value;
            });

            return values;
        };
    }

    function mountFilters(root, grid) {
        root.querySelectorAll('[data-filter]').forEach(function (field) {
            field.addEventListener('change', function () {
                grid.ajax.reload();
            });
        });
    }

    /**
     * Search-then-pick student field. onPick(studentId) runs when a student is chosen.
     */
    function wirePicker(form, studentsUrl, onPick) {
        const search = form.querySelector('[data-student-search]');
        const select = form.querySelector('[data-student-select]');
        let timer = null;

        if (!search) {
            return;
        }

        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                const term = search.value.trim();

                if (term.length < 2) {
                    return;
                }

                ui.api('GET', studentsUrl + '?search=' + encodeURIComponent(term)).then(function (envelope) {
                    const students = envelope.data.items;

                    select.innerHTML = '<option value="">' + (students.length ? 'Pick a student' : 'No student matches') + '</option>' + students.map(function (student) {
                        const place = student.class_name ? ' · ' + student.class_name + ' ' + (student.section_name || '') : '';

                        return '<option value="' + student.id + '">' + esc(student.full_name + ' · ' + student.admission_no + place) + '</option>';
                    }).join('');

                    if (students.length === 1) {
                        select.value = students[0].id;
                        select.dispatchEvent(new Event('change'));
                    }
                });
            }, 250);
        });

        if (onPick) {
            select.addEventListener('change', function () {
                onPick(select.value);
            });
        }
    }

    function resetPicker(form, label) {
        const search = form.querySelector('[data-student-search]');

        if (search) {
            search.value = '';
            form.querySelector('[data-student-select]').innerHTML = '<option value="">' + esc(label || 'Search, then pick a student') + '</option>';
        }
    }

    /**
     * Small popup form (generate, copy, waive …): wire close + submit, resolve to the envelope.
     */
    function wirePopupForm(dialog, form, request) {
        kit.wireDialog(dialog);
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            request(form).catch(function () {});
        });
    }

    const root = document.querySelector('[data-fees]');

    if (!root) {
        return;
    }

    const screen = root.dataset.fees;
    const resourceUrl = root.dataset.resourceUrl;
    const modal = document.getElementById('feesModal');
    const form = modal ? modal.querySelector('[data-resource-form]') : null;

    /* ---------- Challans ---------- */

    if (screen === 'challans') {
        const detail = document.getElementById('challanModal');
        const generateDialog = document.getElementById('generateModal');
        const generateForm = generateDialog.querySelector('form');
        const waiveForm = detail.querySelector('[data-waive-form]');
        const cancelForm = detail.querySelector('[data-cancel-form]');

        const mounted = kit.mount({
            root: root,
            modal: modal,
            order: [[2, 'desc']],
            titles: { create: 'Manual challan', edit: 'Manual challan' },
            searchPlaceholder: 'Search challan no, student or applicant…',
            emptyText: 'No challans yet.',
            params: filterReader(root),
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.invoice_no; },
            onView: openChallan,
            columns: [
                { data: 'payer_name', name: 'payer_name', orderable: false, render: display(function (value, row) { return titleCell(value, row.invoice_no + (row.payer_ref ? ' · ' + row.payer_ref : '')); }) },
                { data: 'invoice_type', searchable: false, className: 'dash-hide-md', render: display(function (value, row) { return titleCell(headline(value), row.billing_month); }) },
                { data: 'due_date', searchable: false, className: 'dash-hide-md', render: display(shortDate) },
                { data: 'total', searchable: false, className: 'dash-col-num', render: display(money) },
                { data: 'balance', searchable: false, className: 'dash-col-num', render: display(money) },
                { data: 'status', searchable: false, render: display(function (value, row) {
                    return (value === 'posted' || value === 'partially_paid') && row.due_date < today() ? badge('overdue') : badge(value);
                }) }
            ],
            fill: function (challanForm) {
                resetPicker(challanForm);
                challanForm.elements.issue_date.value = today();
                challanForm.elements.due_date.value = today();
            },
            payload: function (challanForm) {
                const body = kit.readForm(challanForm, { student_id: 'int', issue_date: 'text', due_date: 'text', notes: 'text' });

                body.items = Array.prototype.map.call(challanForm.querySelectorAll('[data-line]'), function (line) {
                    const field = function (name) { return line.querySelector('[data-line-field="' + name + '"]').value.trim(); };

                    return { fee_head_id: Number(field('fee_head_id')) || null, amount: field('amount') ? Math.round(parseFloat(field('amount')) * 100) : null, description: field('description') || null };
                }).filter(function (item) {
                    return item.fee_head_id || item.amount;
                });

                return body;
            }
        });

        mountFilters(root, mounted.grid);
        wirePicker(form, root.dataset.studentsUrl);
        kit.wireDialog(detail);

        document.querySelector('[data-open-generate]').addEventListener('click', function () {
            generateForm.reset();
            kit.clearErrors(generateForm);
            generateForm.elements.billing_month.value = today().slice(0, 7);
            generateDialog.showModal();
        });

        wirePopupForm(generateDialog, generateForm, function () {
            const hide = ui.busy('Generating monthly challans…');

            return kit.submitForm(generateForm, 'POST', resourceUrl + '/generate', kit.readForm(generateForm, { academic_session_id: 'int', class_id: 'int', billing_month: 'text' }))
                .then(function () {
                    generateDialog.close();
                    mounted.refresh();
                })
                .finally(hide);
        });

        function openChallan(row) {
            ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) {
                renderChallan(envelope.data);

                if (!detail.open) {
                    detail.showModal();
                }
            });
        }

        function renderChallan(invoice) {
            const open = invoice.status === 'posted' || invoice.status === 'partially_paid';
            const lines = invoice.items.map(function (item) {
                return '<tr><td>' + titleCell(item.fee_head, item.description) + '</td><td class="dash-col-num">' + money(item.amount) + '</td>'
                    + '<td class="dash-col-num">' + (item.concession_amount ? '− ' + money(item.concession_amount) : '—') + '</td><td class="dash-col-num">' + money(item.net_amount) + '</td></tr>';
            }).join('');
            const payments = invoice.payments.map(function (payment) {
                return '<li><span>' + esc(payment.receipt_no) + ' · ' + shortDate(payment.received_on) + '</span><strong>' + money(payment.amount) + ' ' + badge(payment.status) + '</strong></li>';
            }).join('');
            const waivers = invoice.waivers.map(function (waiver) {
                return '<li><span>' + esc(waiver.reason) + '</span><strong>' + money(waiver.amount) + (waiver.approved_by ? ' <span class="dash-muted">' + esc(waiver.approved_by) + '</span>' : '') + '</strong></li>';
            }).join('');

            detail.dataset.id = invoice.id;
            detail.querySelector('[data-detail-title]').textContent = invoice.invoice_no + ' — ' + invoice.payer_name;
            detail.querySelector('[data-detail-subtitle]').textContent = headline(invoice.invoice_type) + (invoice.billing_month ? ' · ' + invoice.billing_month : '') + (invoice.voucher_no ? ' · voucher ' + invoice.voucher_no : '');
            detail.querySelector('[data-detail-body]').innerHTML = kv([
                ['Status', badge(invoice.status), true],
                ['Issued', shortDate(invoice.issue_date), true],
                ['Due', shortDate(invoice.due_date), true],
                ['Cancelled because', invoice.cancelled_reason],
                ['Cancelled by', invoice.cancelled_by_name ? esc(invoice.cancelled_by_name) + ' · ' + shortDate(invoice.cancelled_at) : null, true]
            ]) + '<div class="dash-grid-wrap"><table class="dash-table dash-mini-table"><thead><tr><th>Line</th><th class="dash-col-num">Amount</th><th class="dash-col-num">Concession</th><th class="dash-col-num">Net</th></tr></thead><tbody>' + lines + '</tbody></table></div>'
                + kv([
                    ['Total', money(invoice.total)],
                    ['Waived', invoice.waived_total ? money(invoice.waived_total) : null],
                    ['Paid', money(invoice.paid_total)],
                    ['Balance', '<strong>' + money(invoice.balance) + '</strong>' + (invoice.balance > 0 && invoice.status !== 'cancelled' ? ' <a class="dash-link-btn" href="' + esc(root.dataset.receiptsUrl + '?' + (invoice.student_id ? 'student=' + invoice.student_id : 'application=' + invoice.admission_application_id)) + '">Receive</a>' : ''), true]
                ])
                + (payments ? '<h4 class="dash-detail-heading">Payments</h4><ul class="dash-kv">' + payments + '</ul>' : '')
                + (waivers ? '<h4 class="dash-detail-heading">Write-offs</h4><ul class="dash-kv">' + waivers + '</ul>' : '');

            waiveForm.hidden = !open;
            cancelForm.hidden = !open || invoice.paid_total > 0 || invoice.waived_total > 0;
            waiveForm.reset();
            cancelForm.reset();
            kit.clearErrors(waiveForm);
            kit.clearErrors(cancelForm);
        }

        [[waiveForm, '/waive', { amount: 'money', reason: 'text' }], [cancelForm, '/cancel', { reason: 'text' }]].forEach(function (setup) {
            setup[0].addEventListener('submit', function (event) {
                event.preventDefault();
                kit.submitForm(setup[0], 'POST', resourceUrl + '/' + detail.dataset.id + setup[1], kit.readForm(setup[0], setup[2]))
                    .then(function (envelope) {
                        renderChallan(envelope.data);
                        mounted.refresh();
                    })
                    .catch(function () {});
            });
        });
    }

    /* ---------- Receipts ---------- */

    if (screen === 'receipts') {
        const detail = document.getElementById('receiptModal');
        const reverseForm = detail.querySelector('[data-reverse-form]');
        const invoiceList = form.querySelector('[data-open-invoices]');
        const applicantNote = form.querySelector('[data-applicant]');
        const picker = form.querySelector('[data-student-picker]');
        let applicationId = null;

        const mounted = kit.mount({
            root: root,
            modal: modal,
            order: [[2, 'desc']],
            titles: { create: 'Receive payment', edit: 'Receive payment' },
            searchPlaceholder: 'Search receipt no, student or applicant…',
            emptyText: 'No receipts yet.',
            params: filterReader(root),
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.receipt_no; },
            onView: openReceipt,
            columns: [
                { data: 'payer_name', name: 'payer_name', orderable: false, render: display(function (value, row) { return titleCell(value, row.receipt_no + (row.payer_ref ? ' · ' + row.payer_ref : '')); }) },
                { data: 'method_name', orderable: false, searchable: false, className: 'dash-hide-md', render: display(function (value, row) { return titleCell(value || '—', row.reference_no); }) },
                { data: 'received_on', searchable: false, className: 'dash-hide-md', render: display(shortDate) },
                { data: 'amount', searchable: false, className: 'dash-col-num', render: display(function (value, row) { return money(value) + (row.advance_amount > 0 ? '<span class="dash-muted">' + money(row.advance_amount) + ' advance</span>' : ''); }) },
                { data: 'status', searchable: false, render: display(badge) }
            ],
            fill: function (receiptForm) {
                applicationId = null;
                picker.hidden = false;
                applicantNote.hidden = true;
                resetPicker(receiptForm);
                invoiceList.innerHTML = '<li class="dash-muted">Pick a student to see their open challans.</li>';
                receiptForm.elements.received_on.value = today();
                toggleMethodFields();
            },
            payload: function (receiptForm) {
                const body = kit.readForm(receiptForm, { payment_method_id: 'int', amount: 'money', received_on: 'text', reference_no: 'text', cheque_bank_id: 'int', cheque_date: 'text', remarks: 'text' });
                const ticked = Array.prototype.map.call(invoiceList.querySelectorAll('input:checked'), function (box) { return Number(box.value); });

                if (applicationId) {
                    body.admission_application_id = applicationId;
                } else {
                    body.student_id = Number(receiptForm.elements.student_id.value) || null;
                }

                if (ticked.length) {
                    body.invoice_ids = ticked;
                }

                return body;
            }
        });

        mountFilters(root, mounted.grid);
        kit.wireDialog(detail);
        wirePicker(form, root.dataset.studentsUrl, function (studentId) {
            loadOpenInvoices(studentId ? 'student_id=' + studentId : null);
        });

        function loadOpenInvoices(query) {
            if (!query) {
                invoiceList.innerHTML = '<li class="dash-muted">Pick a student to see their open challans.</li>';

                return Promise.resolve([]);
            }

            return ui.api('GET', root.dataset.invoicesUrl + '?status=unpaid&per_page=50&' + query).then(function (envelope) {
                const invoices = envelope.data.items;

                invoiceList.innerHTML = invoices.length ? invoices.map(function (invoice) {
                    return '<li><label class="dash-check"><input type="checkbox" value="' + invoice.id + '" data-balance="' + invoice.balance + '"> '
                        + esc(invoice.invoice_no + ' · ' + headline(invoice.invoice_type) + (invoice.billing_month ? ' ' + invoice.billing_month : '')) + ' <strong>' + money(invoice.balance) + '</strong></label></li>';
                }).join('') : '<li class="dash-muted">Nothing due. A payment now is kept as advance.</li>';
                setAmount();

                return invoices;
            });
        }

        function setAmount() {
            const boxes = Array.prototype.slice.call(invoiceList.querySelectorAll('input[type="checkbox"]'));
            const ticked = boxes.filter(function (box) { return box.checked; });
            const due = (ticked.length ? ticked : boxes).reduce(function (sum, box) { return sum + Number(box.dataset.balance); }, 0);

            form.elements.amount.value = due > 0 ? (due / 100).toFixed(2) : '';
        }

        function toggleMethodFields() {
            const method = form.elements.payment_method_id.selectedOptions[0];
            const needsBank = method && method.dataset.requiresBank === '1';

            form.querySelectorAll('[data-bank-field]').forEach(function (field) {
                field.hidden = !needsBank;
            });
        }

        invoiceList.addEventListener('change', setAmount);
        form.elements.payment_method_id.addEventListener('change', toggleMethodFields);

        // Arriving from a challan or an application: open the popup with that payer already chosen.
        const params = new URLSearchParams(window.location.search);
        const payerQuery = params.get('student') ? 'student_id=' + Number(params.get('student')) : (params.get('application') ? 'admission_application_id=' + Number(params.get('application')) : null);

        if (payerQuery) {
            mounted.open(null);
            loadOpenInvoices(payerQuery).then(function (invoices) {
                if (params.get('application')) {
                    applicationId = Number(params.get('application'));
                    picker.hidden = true;
                    applicantNote.hidden = false;
                    applicantNote.textContent = 'Applicant: ' + (invoices[0] ? invoices[0].payer_name + ' (' + invoices[0].payer_ref + ')' : '#' + applicationId);
                } else if (invoices[0]) {
                    form.elements.student_id.innerHTML = '<option value="' + invoices[0].student_id + '">' + esc(invoices[0].payer_name + ' · ' + invoices[0].payer_ref) + '</option>';
                }
            });
            window.history.replaceState(null, '', window.location.pathname);
        }

        function openReceipt(row) {
            ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) {
                renderReceipt(envelope.data);
                detail.showModal();
            });
        }

        function renderReceipt(payment) {
            const allocations = payment.allocations.map(function (allocation) {
                return '<li><span>' + esc(allocation.invoice_no + ' · ' + headline(allocation.invoice_type) + (allocation.billing_month ? ' ' + allocation.billing_month : '')) + '</span><strong>' + money(allocation.amount) + '</strong></li>';
            }).join('');

            detail.dataset.id = payment.id;
            detail.querySelector('[data-detail-title]').textContent = payment.receipt_no + ' — ' + payment.payer_name;
            detail.querySelector('[data-detail-subtitle]').textContent = (payment.payment_method || '') + (payment.voucher_no ? ' · voucher ' + payment.voucher_no : '');
            detail.querySelector('[data-detail-body]').innerHTML = kv([
                ['Status', badge(payment.status), true],
                ['Amount', money(payment.amount)],
                ['Kept as advance', payment.advance_amount > 0 ? money(payment.advance_amount) : null],
                ['Received on', shortDate(payment.received_on), true],
                ['Reference', payment.reference_no],
                ['Cheque', payment.cheque_status ? headline(payment.cheque_status) : null],
                ['Received by', payment.received_by],
                ['Reversed because', payment.reversal_reason],
                ['Reversed by', payment.reversed_by_name ? esc(payment.reversed_by_name) + ' · ' + shortDate(payment.reversed_at) : null, true],
                ['Remarks', payment.remarks]
            ]) + (allocations ? '<h4 class="dash-detail-heading">Paid against</h4><ul class="dash-kv">' + allocations + '</ul>' : '');

            reverseForm.hidden = payment.status !== 'posted';
            reverseForm.reset();
            kit.clearErrors(reverseForm);
        }

        reverseForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(reverseForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/reverse', kit.readForm(reverseForm, { reason: 'text', cheque_bounced: 'bool' }))
                .then(function (envelope) {
                    renderReceipt(envelope.data);
                    mounted.refresh();
                })
                .catch(function () {});
        });
    }

    /* ---------- Fee structures ---------- */

    if (screen === 'structures') {
        const lines = form.querySelector('[data-lines]');
        const template = form.querySelector('[data-line-template]');
        const copyDialog = document.getElementById('copyModal');
        const copyForm = copyDialog.querySelector('form');

        function addLine(item) {
            const line = template.content.firstElementChild.cloneNode(true);
            const field = function (name) { return line.querySelector('[data-line-field="' + name + '"]'); };

            if (item) {
                field('fee_head_id').value = item.fee_head_id;
                field('charge_on').value = item.charge_on;
                field('amount').value = (item.amount / 100).toFixed(2);
                field('due_day').value = item.due_day || '';
                field('is_mandatory').checked = Boolean(item.is_mandatory);
                field('applies_to_new_students_only').checked = Boolean(item.applies_to_new_students_only);
                line.querySelectorAll('[data-month]').forEach(function (box) {
                    box.checked = item.months.indexOf(Number(box.value)) !== -1;
                });
            } else {
                field('charge_on').value = 'recurring';
            }

            lines.appendChild(line);
        }

        const mounted = kit.mount({
            root: root,
            modal: modal,
            order: [[0, 'asc']],
            titles: { create: 'Add structure', edit: 'Edit structure' },
            searchPlaceholder: 'Search class…',
            emptyText: 'No fee structures for this session.',
            params: filterReader(root),
            nameOf: function (row) { return row.name; },
            loadRow: function (row) {
                return ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; });
            },
            columns: [
                { data: 'class_name', name: 'class_name', render: display(function (value, row) { return titleCell(value, row.name); }) },
                { data: 'items_count', searchable: false, className: 'dash-hide-md dash-col-num' },
                { data: 'monthly_total', searchable: false, className: 'dash-col-num', render: display(money) },
                { data: 'admission_total', searchable: false, className: 'dash-hide-md dash-col-num', render: display(money) },
                { data: 'is_active', searchable: false, render: display(function (value) { return badge(value ? 'active' : 'inactive'); }) }
            ],
            fill: function (structureForm, row) {
                kit.fillForm(structureForm, row, { academic_session_id: 'int', class_id: 'int', name: 'text', is_active: 'bool' }, {
                    academic_session_id: root.querySelector('[data-filter="academic_session_id"]').value,
                    is_active: true
                });
                lines.innerHTML = '';
                (row ? row.items : [null]).forEach(addLine);
            },
            payload: function (structureForm) {
                const body = kit.readForm(structureForm, { academic_session_id: 'int', class_id: 'int', name: 'text', is_active: 'bool' });

                body.items = Array.prototype.map.call(lines.querySelectorAll('[data-line]'), function (line, position) {
                    const field = function (name) { return line.querySelector('[data-line-field="' + name + '"]'); };

                    return {
                        fee_head_id: Number(field('fee_head_id').value),
                        charge_on: field('charge_on').value,
                        amount: field('amount').value ? Math.round(parseFloat(field('amount').value) * 100) : null,
                        due_day: field('due_day').value ? Number(field('due_day').value) : null,
                        is_mandatory: field('is_mandatory').checked,
                        applies_to_new_students_only: field('applies_to_new_students_only').checked,
                        sort_order: position,
                        months: Array.prototype.filter.call(line.querySelectorAll('[data-month]'), function (box) { return box.checked; }).map(function (box) { return Number(box.value); })
                    };
                });

                return body;
            }
        });

        mountFilters(root, mounted.grid);

        form.querySelector('[data-add-line]').addEventListener('click', function () {
            addLine(null);
        });

        lines.addEventListener('click', function (event) {
            const remove = event.target.closest('[data-remove-line]');

            if (remove) {
                remove.closest('[data-line]').remove();
            }
        });

        document.querySelector('[data-open-copy]').addEventListener('click', function () {
            copyForm.reset();
            kit.clearErrors(copyForm);
            copyDialog.showModal();
        });

        wirePopupForm(copyDialog, copyForm, function () {
            return kit.submitForm(copyForm, 'POST', resourceUrl + '/copy', kit.readForm(copyForm, { from_session_id: 'int', to_session_id: 'int' }))
                .then(function () {
                    copyDialog.close();
                    mounted.refresh();
                });
        });
    }

    /* ---------- Fee heads & concession types ---------- */

    const SIMPLE = {
        heads: {
            titles: { create: 'Add fee head', edit: 'Edit fee head' },
            spec: { name: 'text', code: 'text', income_account_id: 'int', frequency_id: 'int', sort_order: 'int', is_refundable: 'bool', is_concession_allowed: 'bool', is_active: 'bool' },
            defaults: { is_concession_allowed: true, is_active: true },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.code); }) },
                { data: 'income_account_label', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'frequency_name', orderable: false, searchable: false, defaultContent: '—' },
                { data: 'is_active', searchable: false, render: display(function (value) { return badge(value ? 'active' : 'inactive'); }) }
            ]
        },
        'concession-types': {
            titles: { create: 'Add concession type', edit: 'Edit concession type' },
            spec: { name: 'text', calculation: 'text', default_value: 'money', expense_account_id: 'int', requires_approval: 'bool', is_active: 'bool' },
            defaults: { calculation: 'percent', requires_approval: true, is_active: true },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.requires_approval ? 'Needs approval' : 'Auto-approved'); }) },
                { data: 'default_value', searchable: false, render: display(function (value, row) { return esc(valueText(row.calculation, value)); }) },
                { data: 'expense_account_label', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—', render: display(function (value) { return esc(value || '5400 Fee Concessions'); }) },
                { data: 'is_active', searchable: false, render: display(function (value) { return badge(value ? 'active' : 'inactive'); }) }
            ]
        }
    };

    if (SIMPLE[screen]) {
        const setup = SIMPLE[screen];

        kit.mount({
            root: root,
            modal: modal,
            order: [[0, 'asc']],
            titles: setup.titles,
            emptyText: 'Nothing here yet.',
            nameOf: function (row) { return row.name; },
            columns: setup.columns,
            fill: function (simpleForm, row) {
                kit.fillForm(simpleForm, row, setup.spec, setup.defaults);
            },
            payload: function (simpleForm) {
                return kit.readForm(simpleForm, setup.spec);
            }
        });
    }

    /* ---------- Student concessions ---------- */

    if (screen === 'concessions') {
        const decision = document.getElementById('decisionModal');

        const mounted = kit.mount({
            root: root,
            modal: modal,
            order: [[3, 'desc']],
            titles: { create: 'Grant concession', edit: 'Grant concession' },
            searchPlaceholder: 'Search student…',
            emptyText: 'No concessions yet.',
            params: filterReader(root),
            editable: false,
            nameOf: function (row) { return row.type_name + ' — ' + row.student_name; },
            onView: function (row) {
                decision.dataset.id = row.id;
                decision.querySelector('[data-detail-title]').textContent = row.student_name;
                decision.querySelector('[data-detail-subtitle]').textContent = row.admission_no + ' · ' + row.type_name;
                decision.querySelector('[data-detail-body]').innerHTML = kv([
                    ['Status', badge(row.status), true],
                    ['Value', valueText(row.calculation, row.value)],
                    ['Applies to', row.fee_head_name],
                    ['Valid', shortDate(row.valid_from) + ' – ' + (row.valid_to ? shortDate(row.valid_to) : 'open'), true],
                    ['Reason', row.reason]
                ]);
                decision.querySelector('[data-decision-actions]').hidden = row.status !== 'pending';
                decision.showModal();
            },
            columns: [
                { data: 'student_name', name: 'student_name', orderable: false, render: display(function (value, row) { return titleCell(value, row.admission_no); }) },
                { data: 'type_name', orderable: false, searchable: false, render: display(function (value, row) { return titleCell(value, row.fee_head_name); }) },
                { data: 'value', searchable: false, render: display(function (value, row) { return esc(valueText(row.calculation, value)); }) },
                { data: 'valid_from', searchable: false, className: 'dash-hide-md', render: display(function (value, row) { return shortDate(value) + ' – ' + (row.valid_to ? shortDate(row.valid_to) : 'open'); }) },
                { data: 'status', searchable: false, render: display(badge) }
            ],
            fill: function (concessionForm) {
                resetPicker(concessionForm);
                concessionForm.elements.valid_from.value = today();
            },
            payload: function (concessionForm) {
                return kit.readForm(concessionForm, { student_id: 'int', concession_type_id: 'int', fee_head_id: 'int', calculation: 'text', value: 'money', valid_from: 'text', valid_to: 'text', reason: 'text' });
            }
        });

        mountFilters(root, mounted.grid);
        wirePicker(form, root.dataset.studentsUrl);
        kit.wireDialog(decision);

        decision.querySelector('[data-decision-actions]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-decision]');

            if (!button) {
                return;
            }

            ui.api('POST', resourceUrl + '/' + decision.dataset.id + '/decision', { decision: button.dataset.decision })
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    decision.close();
                    mounted.refresh();
                })
                .catch(function (envelope) {
                    ui.toast(kit.messageOf(envelope, 'Could not save the decision.'), 'error');
                });
        });
    }
})();

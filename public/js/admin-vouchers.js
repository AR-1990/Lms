/**
 * Vouchers screen: Yajra grid, a paper popup to write / edit draft vouchers line by line,
 * and a detail popup to post, reverse or delete — all through /api/erp/ledger/vouchers.
 * The form takes rupees; the API receives paisa.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-vouchers]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const messageOf = window.PinnacleResource.messageOf;
    const resourceUrl = root.dataset.resourceUrl;
    const canDraft = root.dataset.canDraft === '1';
    const canPost = root.dataset.canPost === '1';
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const filters = Array.prototype.slice.call(root.querySelectorAll('[data-filter]'));
    const STATUS_BADGES = { draft: 'dash-badge-warn', posted: 'dash-badge-ok', reversed: 'dash-badge-system' };

    const editor = document.getElementById('voucherModal');
    const form = editor.querySelector('[data-voucher-form]');
    const linesBox = form.querySelector('[data-lines]');
    const lineTemplate = form.querySelector('[data-line-template]');
    const viewer = document.getElementById('voucherViewModal');
    let editing = null;

    const ICONS = {
        book: '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
        pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        eraser: '<path d="m7 21-4.3-4.3a2.4 2.4 0 0 1 0-3.4l9.6-9.6a2.4 2.4 0 0 1 3.4 0l5.6 5.6a2.4 2.4 0 0 1 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/>'
    };

    function icon(name) {
        return '<svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    }

    function statusBadge(status) {
        return '<span class="dash-badge ' + (STATUS_BADGES[status] || '') + '">' + esc(status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';
    }

    function toPaisa(value) {
        return value === '' || value === null ? 0 : Math.round(parseFloat(value) * 100);
    }

    function today() {
        const now = new Date();

        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    }

    /* ---------- Grid ---------- */

    const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
        url: root.dataset.datatableUrl,
        order: [[0, 'desc']],
        searchPlaceholder: 'Search voucher no or narration…',
        emptyText: 'No vouchers yet.',
        params: function () {
            const values = {};

            filters.forEach(function (field) {
                values[field.dataset.filter] = field.value;
            });

            return values;
        },
        columns: [
            { data: 'entry_date', render: function (value, type) { return type === 'display' ? esc(dateFormat.format(new Date(value))) : value; } },
            {
                data: 'voucher_no',
                render: function (value, type, row) {
                    return type === 'display'
                        ? '<span class="dash-number-preview">' + esc(value) + '</span>' + (row.is_automatic ? '<span class="dash-muted">Automatic</span>' : '')
                        : value;
                }
            },
            { data: 'narration', orderable: false, className: 'dash-hide-md', defaultContent: '', render: function (value, type) { return type === 'display' ? esc(value || '—') : value; } },
            { data: 'total_debit', searchable: false, className: 'dash-col-num dash-nowrap', render: function (value, type) { return type === 'display' ? money(value) : value; } },
            { data: 'status', searchable: false, render: function (value, type) { return type === 'display' ? statusBadge(value) : value; } },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'dash-col-actions',
                render: function (value, type, row) {
                    if (type !== 'display') {
                        return '';
                    }

                    const isDraft = row.status === 'draft';
                    let html = '<div class="dash-actions"><button type="button" class="dash-action dash-action-view" data-view data-tooltip="Open" aria-label="Open ' + esc(row.voucher_no) + '">' + icon('book') + '</button>';

                    if (canDraft) {
                        html += isDraft
                            ? '<button type="button" class="dash-action dash-action-edit" data-edit data-tooltip="Edit draft">' + icon('pen') + '</button>'
                            : '<span class="dash-action dash-action-edit is-disabled" data-tooltip="Posted — reverse instead">' + icon('pen') + '</span>';
                        html += isDraft
                            ? '<button type="button" class="dash-action dash-action-delete" data-delete data-tooltip="Delete draft">' + icon('eraser') + '</button>'
                            : '<span class="dash-action dash-action-delete is-disabled" data-tooltip="Posted vouchers stay">' + icon('eraser') + '</span>';
                    }

                    return html + '</div>';
                }
            }
        ]
    });

    filters.forEach(function (field) {
        field.addEventListener('change', function () {
            grid.ajax.reload();
        });
    });

    function refresh() {
        grid.ajax.reload(null, false);
    }

    /* ---------- Editor ---------- */

    function addLine(line) {
        const row = lineTemplate.content.firstElementChild.cloneNode(true);

        if (line) {
            row.querySelector('[name="account_id"]').value = line.account_id;
            row.querySelector('[name="debit"]').value = line.debit ? (line.debit / 100).toFixed(2) : '';
            row.querySelector('[name="credit"]').value = line.credit ? (line.credit / 100).toFixed(2) : '';
            row.querySelector('[name="description"]').value = line.description || '';
        }

        linesBox.appendChild(row);
    }

    function lineRows() {
        return Array.prototype.slice.call(linesBox.querySelectorAll('[data-line]'));
    }

    function updateTotals() {
        let debit = 0;
        let credit = 0;

        lineRows().forEach(function (row) {
            debit += toPaisa(row.querySelector('[name="debit"]').value);
            credit += toPaisa(row.querySelector('[name="credit"]').value);
        });

        form.querySelector('[data-total="debit"]').textContent = money(debit);
        form.querySelector('[data-total="credit"]').textContent = money(credit);

        const difference = form.querySelector('[data-difference]');
        difference.textContent = debit === credit ? (debit > 0 ? 'Balanced' : '') : 'Off by ' + money(Math.abs(debit - credit));
        difference.classList.toggle('is-balanced', debit === credit && debit > 0);
    }

    function clearErrors() {
        form.querySelectorAll('[data-error-for]').forEach(function (element) {
            element.hidden = true;
        });
        form.querySelectorAll('.is-invalid').forEach(function (element) {
            element.classList.remove('is-invalid');
        });
        form.querySelector('[data-form-error]').hidden = true;
    }

    function showErrors(envelope) {
        const fields = (envelope && envelope.errors && envelope.errors.fields) || {};
        const linesError = form.querySelector('[data-error-for="lines"]');
        const lineMessages = [];

        Object.keys(fields).forEach(function (key) {
            const match = key.match(/^lines\.(\d+)\.(\w+)$/);

            if (match) {
                const row = lineRows()[Number(match[1])];
                const input = row && row.querySelector('[name="' + (match[2] === 'credit' ? 'debit' : match[2]) + '"]');

                if (input) {
                    input.classList.add('is-invalid');
                }

                lineMessages.push('Line ' + (Number(match[1]) + 1) + ': ' + fields[key][0]);

                return;
            }

            const target = form.querySelector('[data-error-for="' + key + '"]');

            if (target) {
                target.textContent = fields[key][0];
                target.hidden = false;
            }
        });

        if (lineMessages.length) {
            linesError.textContent = lineMessages.join(' ');
            linesError.hidden = false;
        }

        if (!Object.keys(fields).length) {
            const general = form.querySelector('[data-form-error]');
            general.textContent = messageOf(envelope, 'Could not save the voucher.');
            general.hidden = false;
        }
    }

    function openEditor(entry) {
        editing = entry || null;
        form.reset();
        clearErrors();
        linesBox.innerHTML = '';

        form.querySelector('[data-voucher-title]').textContent = entry ? 'Edit draft ' + entry.voucher_no : 'New voucher';
        form.elements.voucher_type_id.disabled = Boolean(entry);

        if (entry) {
            form.elements.voucher_type_id.value = entry.voucher_type.id;
            form.elements.entry_date.value = entry.entry_date;
            form.elements.narration.value = entry.narration || '';
            entry.lines.forEach(addLine);
        } else {
            const journal = Array.prototype.find.call(form.elements.voucher_type_id.options, function (option) {
                return option.text.indexOf('JV ') === 0;
            });

            if (journal) {
                form.elements.voucher_type_id.value = journal.value;
            }

            form.elements.entry_date.value = today();
            addLine();
            addLine();
        }

        updateTotals();
        editor.showModal();
    }

    form.querySelector('[data-line-add]').addEventListener('click', function () {
        addLine();
        updateTotals();
    });

    linesBox.addEventListener('click', function (event) {
        const remove = event.target.closest('[data-line-remove]');

        if (remove && lineRows().length > 2) {
            remove.closest('[data-line]').remove();
            updateTotals();
        } else if (remove) {
            ui.toast('A voucher needs at least two lines.', 'error');
        }
    });

    linesBox.addEventListener('input', function (event) {
        const row = event.target.closest('[data-line]');

        event.target.classList.remove('is-invalid');

        // A line is either a debit or a credit: typing in one side clears the other.
        if (row && event.target.value !== '' && (event.target.name === 'debit' || event.target.name === 'credit')) {
            row.querySelector('[name="' + (event.target.name === 'debit' ? 'credit' : 'debit') + '"]').value = '';
        }

        updateTotals();
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearErrors();

        const body = {
            entry_date: form.elements.entry_date.value,
            narration: form.elements.narration.value.trim() || null,
            lines: lineRows().map(function (row) {
                return {
                    account_id: Number(row.querySelector('[name="account_id"]').value) || null,
                    debit: toPaisa(row.querySelector('[name="debit"]').value),
                    credit: toPaisa(row.querySelector('[name="credit"]').value),
                    description: row.querySelector('[name="description"]').value.trim() || null
                };
            })
        };

        if (!editing) {
            body.voucher_type_id = Number(form.elements.voucher_type_id.value);
        }

        const submit = form.querySelector('[data-submit]');
        submit.disabled = true;

        (editing ? ui.api('PUT', resourceUrl + '/' + editing.id, body) : ui.api('POST', resourceUrl, body))
            .then(function (envelope) {
                editor.close();
                ui.toast(envelope.message + ' ' + envelope.data.voucher_no);
                refresh();
                openViewer(envelope.data);
            })
            .catch(showErrors)
            .finally(function () {
                submit.disabled = false;
            });
    });

    /* ---------- Viewer: post / reverse / delete ---------- */

    function openViewer(entry) {
        const reverseBox = viewer.querySelector('[data-reverse-box]');
        const actions = viewer.querySelector('[data-view-actions]');
        const meta = [
            ['Type', entry.voucher_type ? entry.voucher_type.name : '—'],
            ['Date', dateFormat.format(new Date(entry.entry_date))],
            ['Fiscal year', entry.fiscal_year || '—'],
            ['Status', null],
            ['Posted by', entry.posted_by || '—']
        ];

        if (entry.reverses) {
            meta.push(['Reverses', entry.reverses.voucher_no]);
        }

        if (entry.reversed_by) {
            meta.push(['Reversed by', entry.reversed_by.voucher_no + (entry.reversal_reason ? ' — ' + entry.reversal_reason : '')]);
            meta.push(['Reversed on', (entry.reversed_by_name || '—') + (entry.reversed_at ? ' · ' + new Date(entry.reversed_at).toLocaleString('en-GB') : '')]);
        }

        viewer.querySelector('[data-view-title]').textContent = entry.voucher_no;
        viewer.querySelector('[data-view-subtitle]').textContent = entry.narration || 'No narration';
        viewer.querySelector('[data-view-body]').innerHTML =
            '<ul class="dash-kv">' + meta.map(function (item) {
                return '<li><span>' + esc(item[0]) + '</span><strong>' + (item[1] === null ? statusBadge(entry.status) : esc(item[1])) + '</strong></li>';
            }).join('') + '</ul>'
            + '<div class="dash-table-wrap"><table class="dash-table dash-voucher-table"><thead><tr><th>Account</th><th class="dash-col-num">Debit</th><th class="dash-col-num">Credit</th></tr></thead><tbody>'
            + entry.lines.map(function (line) {
                return '<tr><td>' + esc(line.account) + (line.description ? '<span class="dash-muted">' + esc(line.description) + '</span>' : '') + '</td>'
                    + '<td class="dash-col-num">' + (line.debit ? money(line.debit) : '') + '</td>'
                    + '<td class="dash-col-num">' + (line.credit ? money(line.credit) : '') + '</td></tr>';
            }).join('')
            + '</tbody><tfoot><tr><th>Total</th><th class="dash-col-num">' + money(entry.total_debit) + '</th><th class="dash-col-num">' + money(entry.total_credit) + '</th></tr></tfoot></table></div>';

        reverseBox.hidden = true;
        viewer.querySelector('[data-reverse-error]').hidden = true;
        viewer.querySelector('[data-reverse-reason]').value = '';

        let buttons = '<button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>';

        if (entry.status === 'draft' && canDraft) {
            buttons += '<button type="button" class="dash-btn dash-btn-ghost" data-act="edit">' + icon('pen') + ' Edit</button>';
        }

        if (entry.status === 'draft' && canPost) {
            buttons += '<button type="button" class="dash-btn dash-btn-primary" data-act="post">' + icon('book') + ' Post to ledger</button>';
        }

        if (entry.status === 'posted' && canPost) {
            buttons += '<button type="button" class="dash-btn dash-btn-danger" data-act="reverse">Reverse…</button>';
        }

        actions.innerHTML = buttons;
        actions.dataset.entryId = entry.id;
        viewer.entry = entry;

        if (!viewer.open) {
            viewer.showModal();
        }
    }

    function loadAndView(id) {
        ui.api('GET', resourceUrl + '/' + id).then(function (envelope) {
            openViewer(envelope.data);
        }).catch(function (envelope) {
            ui.toast(messageOf(envelope, 'Could not open the voucher.'), 'error');
        });
    }

    viewer.addEventListener('click', function (event) {
        if (event.target === viewer || event.target.closest('[data-modal-close]')) {
            viewer.close();

            return;
        }

        const action = event.target.closest('[data-act]');

        if (!action) {
            return;
        }

        const entry = viewer.entry;

        if (action.dataset.act === 'edit') {
            viewer.close();
            openEditor(entry);

            return;
        }

        if (action.dataset.act === 'reverse' && viewer.querySelector('[data-reverse-box]').hidden) {
            viewer.querySelector('[data-reverse-box]').hidden = false;
            viewer.querySelector('[data-reverse-reason]').focus();
            action.textContent = 'Confirm reversal';

            return;
        }

        action.disabled = true;

        const request = action.dataset.act === 'post'
            ? ui.api('POST', resourceUrl + '/' + entry.id + '/post')
            : ui.api('POST', resourceUrl + '/' + entry.id + '/reverse', { reason: viewer.querySelector('[data-reverse-reason]').value.trim() });

        request.then(function (envelope) {
            ui.toast(envelope.message);
            refresh();
            openViewer(envelope.data);
        }).catch(function (envelope) {
            action.disabled = false;

            const fields = envelope && envelope.errors && envelope.errors.fields;

            if (action.dataset.act === 'reverse' && fields && fields.reason) {
                const error = viewer.querySelector('[data-reverse-error]');
                error.textContent = fields.reason[0];
                error.hidden = false;

                return;
            }

            ui.toast(messageOf(envelope, 'Could not complete the action.'), 'error');
        });
    });

    editor.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            editor.close();
        });
    });

    const createButton = document.querySelector('[data-voucher-create]');

    if (createButton) {
        createButton.addEventListener('click', function () {
            openEditor(null);
        });
    }

    root.querySelector('[data-grid]').addEventListener('click', function (event) {
        const button = event.target.closest('[data-view], [data-edit], [data-delete]');

        if (!button) {
            return;
        }

        const tr = button.closest('tr');
        const row = grid.row(tr).data();

        if (button.hasAttribute('data-view')) {
            loadAndView(row.id);
        }

        if (button.hasAttribute('data-edit')) {
            ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) {
                openEditor(envelope.data);
            });
        }

        if (button.hasAttribute('data-delete')) {
            ui.eraseRecord(row.voucher_no, tr, resourceUrl + '/' + row.id).then(function (result) {
                if (result === 'deleted') {
                    refresh();
                }
            });
        }
    });
})();

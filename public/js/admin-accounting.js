/**
 * Accounts screens: chart of accounts, account ledger, trial balance, fiscal years,
 * voucher types, payment methods, bank accounts and vendors — all over /api/erp/ledger.
 * Amounts travel in paisa; forms take rupees and convert.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-accounting]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const filters = Array.prototype.slice.call(root.querySelectorAll('[data-filter]'));

    /* ---------- Helpers ---------- */

    function text(form, name) {
        return form.elements[name].value.trim() || null;
    }

    function number(form, name) {
        const value = form.elements[name].value.trim();

        return value === '' ? null : Number(value);
    }

    function paisa(form, name) {
        const value = form.elements[name].value.trim();

        return value === '' ? null : Math.round(parseFloat(value) * 100);
    }

    function set(form, name, value) {
        form.elements[name].value = value === null || value === undefined ? '' : value;
    }

    function filterValues() {
        const values = {};

        filters.forEach(function (field) {
            values[field.dataset.filter] = field.value;
        });

        return values;
    }

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function badge(on, yes, no) {
        return '<span class="dash-badge ' + (on ? 'dash-badge-ok' : 'dash-badge-system') + '">' + (on ? yes : no) + '</span>';
    }

    function titleCell(title, subtitle) {
        return '<span class="dash-cell-title">' + esc(title) + '</span>' + (subtitle ? '<span class="dash-muted">' + esc(subtitle) + '</span>' : '');
    }

    function signed(balance) {
        if (balance === null || balance === undefined) {
            return '—';
        }

        if (balance === 0) {
            return money(0);
        }

        return balance < 0 ? money(-balance) + ' Cr' : money(balance) + ' Dr';
    }

    function shortDate(value) {
        return value ? esc(dateFormat.format(new Date(value))) : '—';
    }

    const canManage = root.dataset.canManage !== '0';

    /* ---------- Grid + popup screens ---------- */

    const SCREENS = {
        chart: {
            titles: { create: 'Add account', edit: 'Edit account' },
            order: [[0, 'asc']],
            nameOf: function (row) { return row.code + ' ' + row.name; },
            viewUrl: function (row) { return row.is_group ? null : root.dataset.ledgerUrl + '/' + row.id + '/ledger'; },
            canEdit: function () { return canManage ? true : 'View only'; },
            canDelete: function (row) {
                if (!canManage) {
                    return 'View only';
                }

                if (row.is_system) {
                    return 'Built-in account';
                }

                return row.children_count > 0 ? 'Has sub-accounts' : true;
            },
            columns: [
                { data: 'code', className: 'dash-nowrap' },
                {
                    data: 'name',
                    render: display(function (value, row) {
                        const flags = [row.is_group ? 'Group' : null, row.is_cash_account ? 'Cash' : null, row.is_bank_account ? 'Bank' : null].filter(Boolean).join(' · ');

                        return titleCell(row.name, [row.parent_name ? 'Under ' + row.parent_name : null, flags].filter(Boolean).join(' — '));
                    })
                },
                { data: 'account_type_name', orderable: false, searchable: false, className: 'dash-hide-md' },
                { data: 'balance', orderable: false, searchable: false, className: 'dash-col-num dash-nowrap', render: display(function (value, row) { return row.is_group ? '<span class="dash-muted">Heading</span>' : signed(value); }) },
                { data: 'is_active', searchable: false, className: 'dash-hide-md', render: display(function (value) { return badge(value, 'Active', 'Inactive'); }) }
            ],
            fill: function (form, row) {
                set(form, 'account_type_id', row ? row.account_type_id : form.elements.account_type_id.options[0].value);
                syncParentOptions(form);
                set(form, 'parent_id', row && row.parent_id);
                set(form, 'code', row && row.code);
                set(form, 'name', row && row.name);
                set(form, 'opening_balance', row && row.opening_balance ? (row.opening_balance / 100).toFixed(2) : '');
                set(form, 'opening_balance_type', row && row.opening_balance_type);
                set(form, 'description', row && row.description);
                form.elements.is_group.checked = Boolean(row && row.is_group);
                form.elements.is_cash_account.checked = Boolean(row && row.is_cash_account);
                form.elements.is_bank_account.checked = Boolean(row && row.is_bank_account);
                form.elements.is_active.checked = row ? row.is_active : true;
            },
            payload: function (form) {
                return {
                    account_type_id: number(form, 'account_type_id'),
                    parent_id: number(form, 'parent_id'),
                    code: text(form, 'code'),
                    name: text(form, 'name'),
                    opening_balance: paisa(form, 'opening_balance'),
                    opening_balance_type: text(form, 'opening_balance_type'),
                    description: text(form, 'description'),
                    is_group: form.elements.is_group.checked,
                    is_cash_account: form.elements.is_cash_account.checked,
                    is_bank_account: form.elements.is_bank_account.checked,
                    is_active: form.elements.is_active.checked
                };
            }
        },

        'fiscal-years': {
            titles: { create: 'Add fiscal year', edit: 'Edit fiscal year' },
            order: [[1, 'desc']],
            canDelete: function (row) { return row.journal_entries_count > 0 ? 'Has vouchers' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.is_current ? 'Current year' : ''); }) },
                { data: 'starts_on', render: display(shortDate) },
                { data: 'ends_on', render: display(shortDate) },
                { data: 'journal_entries_count', searchable: false, className: 'dash-col-num dash-hide-md' },
                {
                    data: 'status',
                    searchable: false,
                    render: display(function (value, row) {
                        const open = value === 'open';

                        return badge(open, 'Open', 'Closed')
                            + ' <button type="button" class="dash-link-btn" data-year-status="' + (open ? 'close' : 'reopen') + '" data-id="' + row.id + '" data-name="' + esc(row.name) + '">'
                            + (open ? 'Close' : 'Reopen') + '</button>';
                    })
                }
            ],
            fill: function (form, row) {
                set(form, 'name', row && row.name);
                set(form, 'starts_on', row && row.starts_on);
                set(form, 'ends_on', row && row.ends_on);
                form.elements.is_current.checked = Boolean(row && row.is_current);
            },
            payload: function (form) {
                return { name: text(form, 'name'), starts_on: text(form, 'starts_on'), ends_on: text(form, 'ends_on'), is_current: form.elements.is_current.checked };
            }
        },

        'voucher-types': {
            titles: { create: 'Add voucher type', edit: 'Edit voucher type' },
            order: [[0, 'asc']],
            canDelete: function (row) {
                if (row.is_system) {
                    return 'Built-in type';
                }

                return row.journal_entries_count > 0 ? 'Used on vouchers' : true;
            },
            columns: [
                { data: 'code', render: display(function (value) { return '<span class="dash-number-preview">' + esc(value) + '</span>'; }) },
                { data: 'name' },
                { data: 'journal_entries_count', searchable: false, className: 'dash-col-num dash-hide-md' },
                { data: 'is_system', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Built-in</span>' : '<span class="dash-badge">Custom</span>'; }) }
            ],
            fill: function (form, row) {
                set(form, 'name', row && row.name);
                set(form, 'code', row && row.code);
                form.elements.code.readOnly = Boolean(row);
            },
            payload: function (form, row) {
                return row ? { name: text(form, 'name') } : { name: text(form, 'name'), code: text(form, 'code') };
            }
        },

        'payment-methods': {
            titles: { create: 'Add payment method', edit: 'Edit payment method' },
            order: [[0, 'asc']],
            columns: [
                { data: 'sort_order', searchable: false, className: 'dash-col-num' },
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.code + (row.is_online ? ' · Online' : '')); }) },
                { data: 'account_label', orderable: false, searchable: false, className: 'dash-hide-md' },
                {
                    data: 'requires_reference',
                    orderable: false,
                    searchable: false,
                    render: display(function (value, row) {
                        const needs = [row.requires_reference ? 'Reference no' : null, row.requires_bank_details ? 'Bank & date' : null].filter(Boolean);

                        return needs.length ? esc(needs.join(', ')) : '<span class="dash-muted">Nothing extra</span>';
                    })
                },
                { data: 'is_active', searchable: false, render: display(function (value) { return badge(value, 'Active', 'Inactive'); }) }
            ],
            fill: function (form, row) {
                set(form, 'name', row && row.name);
                set(form, 'code', row && row.code);
                set(form, 'account_id', row ? row.account_id : form.elements.account_id.options[0] && form.elements.account_id.options[0].value);
                set(form, 'charges_percent', row && row.charges_percent);
                set(form, 'sort_order', row && row.sort_order);
                form.elements.requires_reference.checked = Boolean(row && row.requires_reference);
                form.elements.requires_bank_details.checked = Boolean(row && row.requires_bank_details);
                form.elements.is_online.checked = Boolean(row && row.is_online);
                form.elements.is_active.checked = row ? row.is_active : true;
            },
            payload: function (form) {
                return {
                    name: text(form, 'name'),
                    code: text(form, 'code'),
                    account_id: number(form, 'account_id'),
                    charges_percent: number(form, 'charges_percent'),
                    sort_order: number(form, 'sort_order'),
                    requires_reference: form.elements.requires_reference.checked,
                    requires_bank_details: form.elements.requires_bank_details.checked,
                    is_online: form.elements.is_online.checked,
                    is_active: form.elements.is_active.checked
                };
            }
        },

        'bank-accounts': {
            titles: { create: 'Add bank account', edit: 'Edit bank account' },
            order: [[0, 'asc']],
            nameOf: function (row) { return row.account_title + ' (' + row.account_number + ')'; },
            columns: [
                { data: 'account_title', render: display(function (value, row) { return titleCell(value, row.is_default_for_fees ? 'Printed on fee challans' : row.branch); }) },
                { data: 'bank_name', orderable: false, searchable: false },
                { data: 'account_number', render: display(function (value, row) { return titleCell(value, row.iban); }) },
                { data: 'account_label', orderable: false, searchable: false, className: 'dash-hide-md' }
            ],
            fill: function (form, row) {
                set(form, 'account_id', row ? row.account_id : form.elements.account_id.options[0] && form.elements.account_id.options[0].value);
                set(form, 'bank_id', row ? row.bank_id : form.elements.bank_id.options[0] && form.elements.bank_id.options[0].value);
                set(form, 'account_title', row && row.account_title);
                set(form, 'account_number', row && row.account_number);
                set(form, 'iban', row && row.iban);
                set(form, 'branch', row && row.branch);
                set(form, 'branch_code', row && row.branch_code);
                form.elements.is_default_for_fees.checked = Boolean(row && row.is_default_for_fees);
            },
            payload: function (form) {
                return {
                    account_id: number(form, 'account_id'),
                    bank_id: number(form, 'bank_id'),
                    account_title: text(form, 'account_title'),
                    account_number: text(form, 'account_number'),
                    iban: text(form, 'iban'),
                    branch: text(form, 'branch'),
                    branch_code: text(form, 'branch_code'),
                    is_default_for_fees: form.elements.is_default_for_fees.checked
                };
            }
        },

        vendors: {
            titles: { create: 'Add vendor', edit: 'Edit vendor' },
            order: [[0, 'asc']],
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.contact_person); }) },
                { data: 'phone', defaultContent: '—', render: display(function (value, row) { return titleCell(value || '—', row.email); }) },
                { data: 'ntn', defaultContent: '—', className: 'dash-hide-md', render: display(function (value) { return esc(value || '—'); }) },
                { data: 'is_active', searchable: false, render: display(function (value) { return badge(value, 'Active', 'Inactive'); }) }
            ],
            fill: function (form, row) {
                ['name', 'contact_person', 'phone', 'email', 'ntn', 'strn', 'bank_name', 'bank_account_no', 'address', 'payable_account_id'].forEach(function (field) {
                    set(form, field, row && row[field]);
                });
                form.elements.is_active.checked = row ? row.is_active : true;
            },
            payload: function (form) {
                const body = { payable_account_id: number(form, 'payable_account_id'), is_active: form.elements.is_active.checked };

                ['name', 'contact_person', 'phone', 'email', 'ntn', 'strn', 'bank_name', 'bank_account_no', 'address'].forEach(function (field) {
                    body[field] = text(form, field);
                });

                return body;
            }
        }
    };

    /* Chart: only offer groups of the chosen account type as parents. */
    function syncParentOptions(form) {
        const typeId = form.elements.account_type_id.value;

        Array.prototype.forEach.call(form.elements.parent_id.options, function (option) {
            option.hidden = option.value !== '' && option.dataset.type !== typeId;
        });

        const selected = form.elements.parent_id.selectedOptions[0];

        if (selected && selected.hidden) {
            form.elements.parent_id.value = '';
        }
    }

    const screen = SCREENS[root.dataset.accounting];

    if (screen && window.PinnacleResource) {
        const modal = document.getElementById('accountingModal');
        const mounted = window.PinnacleResource.mount({
            root: root,
            modal: modal,
            order: screen.order,
            columns: screen.columns,
            titles: screen.titles,
            canEdit: screen.canEdit,
            canDelete: screen.canDelete,
            viewUrl: screen.viewUrl,
            fill: screen.fill,
            payload: screen.payload,
            creatable: canManage,
            searchPlaceholder: 'Search…',
            emptyText: 'Nothing here yet.',
            nameOf: screen.nameOf || function (row) { return row.name; },
            params: filters.length ? filterValues : null
        });

        filters.forEach(function (field) {
            field.addEventListener('change', function () {
                mounted.grid.ajax.reload();
            });
        });

        if (modal && modal.querySelector('[name="account_type_id"]')) {
            const form = modal.querySelector('form');
            form.elements.account_type_id.addEventListener('change', function () {
                syncParentOptions(form);
            });
        }

        root.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-year-status]');

            if (!toggle) {
                return;
            }

            toggle.disabled = true;
            ui.api('POST', root.dataset.resourceUrl + '/' + toggle.dataset.id + '/' + toggle.dataset.yearStatus)
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    mounted.refresh();
                })
                .catch(function (envelope) {
                    toggle.disabled = false;
                    ui.toast(window.PinnacleResource.messageOf(envelope, 'Could not change the year.'), 'error');
                });
        });

        return;
    }

    /* ---------- Account ledger ---------- */

    if (root.dataset.accounting === 'ledger') {
        const summary = document.querySelector('[data-ledger-summary]');
        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[0, 'asc']],
            pageLength: 25,
            searchPlaceholder: 'Search voucher or narration…',
            emptyText: 'No posted entries in this period.',
            params: filterValues,
            columns: [
                { data: 'entry_date', render: display(shortDate) },
                { data: 'voucher_no', render: display(function (value) { return '<span class="dash-number-preview">' + esc(value) + '</span>'; }) },
                { data: 'narration', orderable: false, render: display(function (value, row) { return titleCell(value || '—', row.description); }) },
                { data: 'debit', searchable: false, orderable: false, className: 'dash-col-num dash-nowrap', render: display(function (value) { return value ? money(value) : ''; }) },
                { data: 'credit', searchable: false, orderable: false, className: 'dash-col-num dash-nowrap', render: display(function (value) { return value ? money(value) : ''; }) }
            ]
        });

        grid.on('draw', function () {
            const json = grid.ajax.json();

            if (!json || !json.summary) {
                return;
            }

            summary.querySelector('[data-summary="opening_balance"]').textContent = signed(json.summary.opening_balance);
            summary.querySelector('[data-summary="movement"]').textContent = money(json.summary.total_debit) + ' / ' + money(json.summary.total_credit);
            summary.querySelector('[data-summary="closing_balance"]').textContent = signed(json.summary.closing_balance);
        });

        filters.forEach(function (field) {
            field.addEventListener('change', function () {
                grid.ajax.reload();
            });
        });

        return;
    }

    /* ---------- Trial balance ---------- */

    if (root.dataset.accounting === 'trial-balance') {
        const body = root.querySelector('[data-rows]');
        const status = root.querySelector('[data-balance-status]');
        const asOf = root.querySelector('[data-filter="as_of"]');

        const load = function () {
            ui.api('GET', root.dataset.url + '?as_of=' + encodeURIComponent(asOf.value))
                .then(function (envelope) {
                    const data = envelope.data;

                    root.querySelector('[data-as-of-label]').textContent = dateFormat.format(new Date(data.as_of));
                    status.textContent = data.is_balanced ? 'Debits equal credits — the books balance.' : 'Out of balance by ' + money(Math.abs(data.total_debit - data.total_credit)) + '. Check opening balances.';
                    status.classList.toggle('dash-text-danger', !data.is_balanced);
                    root.querySelector('[data-total="debit"]').textContent = money(data.total_debit);
                    root.querySelector('[data-total="credit"]').textContent = money(data.total_credit);

                    body.innerHTML = data.rows.length ? data.rows.map(function (row) {
                        return '<tr><td><a href="' + esc(root.dataset.ledgerUrl + '/' + row.account_id + '/ledger') + '">' + esc(row.code) + '</a></td>'
                            + '<td>' + esc(row.name) + '</td>'
                            + '<td class="dash-hide-sm">' + esc(row.account_type) + '</td>'
                            + '<td class="dash-col-num">' + (row.debit ? money(row.debit) : '') + '</td>'
                            + '<td class="dash-col-num">' + (row.credit ? money(row.credit) : '') + '</td></tr>';
                    }).join('') : '<tr><td colspan="5" class="dash-muted">No balances on this date.</td></tr>';
                })
                .catch(function (envelope) {
                    ui.toast(window.PinnacleResource ? window.PinnacleResource.messageOf(envelope, 'Could not load.') : 'Could not load.', 'error');
                });
        };

        asOf.addEventListener('change', load);
        load();
    }
})();

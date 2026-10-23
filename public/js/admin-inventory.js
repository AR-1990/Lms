/**
 * Inventory screens (PLAN.md §16): stock items, purchase orders (approve / receive / cancel), direct
 * purchases, issues & sales with returns, transfers, stock counts, the stock ledger and categories.
 * Documents share one "item lines" editor. Quantities are decimal; money travels in paisa.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-inventory]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const screen = root.dataset.inventory;
    const canApprove = root.dataset.canApprove === '1';
    const today = new Date().toISOString().slice(0, 10);
    const TONES = { draft: 'dash-badge-warn', approved: 'dash-badge-brand', partially_received: 'dash-badge-brand', received: 'dash-badge-ok', cancelled: 'dash-badge-system', issued: 'dash-badge-brand', partially_returned: 'dash-badge-warn', returned: 'dash-badge-ok' };

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function label(value) {
        return String(value || '').replace(/_/g, ' ').replace(/^\w/, function (letter) { return letter.toUpperCase(); });
    }

    function badge(value) {
        return '<span class="dash-badge ' + (TONES[value] || '') + '">' + esc(label(value)) + '</span>';
    }

    function day(value) {
        return value ? dateFormat.format(new Date(value)) : '—';
    }

    function qty(value) {
        return String(Math.round(Number(value || 0) * 1000) / 1000);
    }

    function titleCell(title, sub) {
        return '<strong>' + esc(title || '—') + '</strong>' + (sub ? '<span class="dash-muted dash-cell-sub">' + esc(sub) + '</span>' : '');
    }

    function rupees(paisa) {
        return paisa === null || paisa === undefined || paisa === '' ? '' : (Number(paisa) / 100).toFixed(2);
    }

    function paisa(value) {
        return value === '' || value === null || value === undefined ? null : Math.round(parseFloat(value) * 100);
    }

    function filterParams() {
        const params = {};

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            params[select.dataset.filter] = select.value;
        });

        return params;
    }

    function watchFilters(grid) {
        root.querySelectorAll('[data-filter]').forEach(function (select) {
            select.addEventListener('change', function () { grid.ajax.reload(); });
        });
    }

    /* ---------- Item lines editor ---------- */

    function Lines(box, kind, options) {
        const rows = box.querySelector('[data-line-rows]');
        const search = box.querySelector('[data-line-search]');
        const results = box.querySelector('[data-line-results]');
        const columns = Number(box.querySelectorAll('thead th').length);
        let timer = null;

        function empty() {
            const has = rows.querySelector('[data-line]');
            const placeholder = rows.querySelector('[data-line-empty]');

            if (!has && !placeholder) {
                rows.insertAdjacentHTML('beforeend', '<tr class="dash-muted" data-line-empty><td colspan="' + columns + '">No items yet. Search above to add.</td></tr>');
            }

            if (has && placeholder) {
                placeholder.remove();
            }
        }

        function cellsFor(line) {
            const qtyInput = '<input type="number" step="0.001" min="0" data-qty value="' + esc(line.quantity === undefined ? 1 : line.quantity) + '" aria-label="Quantity">';

            if (kind === 'order' || kind === 'receipt') {
                return '<td>' + qtyInput + '</td><td><input type="number" step="0.01" min="0" data-cost value="' + esc(rupees(line.unit_cost)) + '" aria-label="Unit cost"></td><td data-line-total></td>';
            }

            if (kind === 'issue') {
                return '<td>' + qtyInput + '</td><td><input type="number" step="0.01" min="0" data-price value="' + esc(rupees(line.sale_price)) + '" aria-label="Sale price"></td>'
                    + '<td>' + (line.is_returnable ? '<input type="date" data-due aria-label="Return by">' : '<span class="dash-muted">—</span>') + '</td>';
            }

            if (kind === 'count') {
                return '<td>' + esc(qty(line.on_hand)) + '</td><td><input type="number" step="0.001" min="0" data-counted value="' + esc(qty(line.on_hand)) + '" aria-label="Counted"></td>'
                    + '<td><input type="number" step="0.01" min="0" data-cost value="' + esc(rupees(line.average_cost)) + '" aria-label="Unit cost"></td>';
            }

            return '<td>' + qtyInput + '</td>';
        }

        function add(line) {
            if (line.inventory_item_id && rows.querySelector('[data-line][data-item="' + line.inventory_item_id + '"]')) {
                ui.toast('That item is already in the list.');

                return;
            }

            rows.insertAdjacentHTML('beforeend', '<tr data-line data-item="' + esc(line.inventory_item_id || '') + '" data-asset-category="' + esc(line.asset_category_id || '') + '">'
                + '<td>' + (line.asset_category_id ? '<input type="text" data-description value="' + esc(line.description || '') + '" placeholder="Asset description" aria-label="Description"><span class="dash-muted dash-cell-sub">Asset · ' + esc(line.label || '') + '</span>' : titleCell(line.label, line.unit ? 'on hand ' + qty(line.on_hand) + ' ' + line.unit : null)) + '</td>'
                + cellsFor(line)
                + '<td><button type="button" class="dash-icon-btn" data-line-remove aria-label="Remove line">×</button></td></tr>');
            empty();
            totals();
        }

        function totals() {
            rows.querySelectorAll('[data-line]').forEach(function (row) {
                const cell = row.querySelector('[data-line-total]');

                if (cell) {
                    cell.textContent = money(Math.round(Number(row.querySelector('[data-qty]').value || 0) * (paisa(row.querySelector('[data-cost]').value) || 0)));
                }
            });
        }

        function find() {
            const term = search.value.trim();
            const store = options.store ? options.store() : '';

            if (!term) {
                results.innerHTML = '';

                return;
            }

            ui.api('GET', root.dataset.searchUrl + '?term=' + encodeURIComponent(term) + (store ? '&location_id=' + store : '') + (options.sellable && options.sellable() ? '&sellable=1' : '')).then(function (envelope) {
                results.innerHTML = envelope.data.map(function (item, index) {
                    return '<li><button type="button" data-pick="' + index + '">' + esc(item.sku + ' · ' + item.name) + ' <span class="dash-muted">' + esc(qty(item.on_hand) + ' ' + (item.unit || '')) + '</span></button></li>';
                }).join('') || '<li class="dash-muted">No item found.</li>';
                results.found = envelope.data;
            }).catch(function () {});
        }

        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(find, 300);
        });

        results.addEventListener('click', function (event) {
            const pick = event.target.closest('[data-pick]');

            if (!pick) {
                return;
            }

            const item = results.found[Number(pick.dataset.pick)];

            add({ inventory_item_id: item.id, label: item.sku + ' · ' + item.name, unit: item.unit, on_hand: item.on_hand, average_cost: item.average_cost, unit_cost: item.average_cost || item.standard_cost, sale_price: item.sale_price, is_returnable: item.is_returnable });
            search.value = '';
            results.innerHTML = '';
        });

        rows.addEventListener('click', function (event) {
            if (event.target.closest('[data-line-remove]')) {
                event.target.closest('tr').remove();
                empty();
            }
        });

        rows.addEventListener('input', totals);

        return {
            add: add,
            clear: function () {
                rows.innerHTML = '';
                empty();
                search.value = '';
                results.innerHTML = '';
            },
            read: function () {
                return Array.prototype.map.call(rows.querySelectorAll('[data-line]'), function (row) {
                    const line = row.dataset.item ? { inventory_item_id: Number(row.dataset.item) } : { asset_category_id: Number(row.dataset.assetCategory), description: row.querySelector('[data-description]').value.trim() || null };
                    const quantity = row.querySelector('[data-qty]');
                    const cost = row.querySelector('[data-cost]');
                    const price = row.querySelector('[data-price]');
                    const due = row.querySelector('[data-due]');
                    const counted = row.querySelector('[data-counted]');

                    if (quantity) {
                        line.quantity = Number(quantity.value || 0);
                    }

                    if (cost) {
                        line.unit_cost = paisa(cost.value);
                    }

                    if (price && price.value !== '') {
                        line.sale_price = paisa(price.value);
                    }

                    if (due && due.value) {
                        line.due_back_on = due.value;
                    }

                    if (counted) {
                        line.counted_qty = Number(counted.value || 0);
                    }

                    return line;
                });
            }
        };
    }

    /* Recipient finder (issues) */

    function recipients(form) {
        const input = form.querySelector('[data-recipient-search]');
        const list = form.querySelector('[data-recipient-results]');
        let timer = null;

        function find() {
            ui.api('GET', root.dataset.recipientsUrl + '?type=' + encodeURIComponent(form.elements.recipient_type.value) + '&term=' + encodeURIComponent(input.value.trim())).then(function (envelope) {
                list.innerHTML = envelope.data.map(function (holder) {
                    return '<li><button type="button" data-recipient="' + holder.id + '" data-name="' + esc(holder.name) + '">' + esc(holder.name) + (holder.detail ? ' <span class="dash-muted">' + esc(holder.detail) + '</span>' : '') + '</button></li>';
                }).join('') || '<li class="dash-muted">Nobody found.</li>';
            }).catch(function () {});
        }

        input.addEventListener('input', function () {
            form.elements.recipient_id.value = '';
            clearTimeout(timer);
            timer = setTimeout(find, 300);
        });
        form.elements.recipient_type.addEventListener('change', function () {
            form.elements.recipient_id.value = '';
            input.value = '';
            list.innerHTML = '';
        });
        list.addEventListener('click', function (event) {
            const pick = event.target.closest('[data-recipient]');

            if (pick) {
                form.elements.recipient_id.value = pick.dataset.recipient;
                input.value = pick.dataset.name;
                list.innerHTML = '';
            }
        });

        return { clear: function () { input.value = ''; list.innerHTML = ''; } };
    }

    function closeOnButtons(modal) {
        modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
    }

    /* ---------- Screens ---------- */

    function stock() {
        const SPEC = { name: 'text', item_category_id: 'int', sku: 'text', unit_id: 'int', brand: 'text', size: 'text', color: 'text', barcode: 'text', reorder_level: 'number', reorder_quantity: 'number', standard_cost: 'money', sale_price: 'money', description: 'text', is_sellable: 'bool', is_returnable: 'bool', is_active: 'bool' };
        const mounted = kit.mount({
            root: root,
            modal: document.getElementById('itemModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add item', edit: 'Edit item' },
            emptyText: 'No items yet.',
            searchPlaceholder: 'Search name or code…',
            params: filterParams,
            nameOf: function (row) { return row.name; },
            loadRow: function (row) { return ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; }); },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, [row.sku, row.brand, row.size, row.color].filter(Boolean).join(' · ')); }) },
                { data: 'category_name', orderable: false, defaultContent: '—' },
                { data: 'on_hand', searchable: false, render: display(function (value, row) { return '<span class="dash-badge ' + (row.is_low ? 'dash-badge-danger' : 'dash-badge-ok') + '">' + esc(qty(value) + ' ' + (row.unit_name || '')) + '</span>'; }) },
                { data: 'reorder_level', searchable: false, className: 'dash-hide-md', render: display(function (value) { return esc(qty(value)); }) },
                { data: 'standard_cost', searchable: false, className: 'dash-hide-md', render: display(function (value, row) { return esc(money(value)) + (row.is_sellable ? '<span class="dash-muted dash-cell-sub">sells at ' + esc(money(row.sale_price)) + '</span>' : ''); }) },
                { data: 'is_active', render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { is_active: true, reorder_level: 0 }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });

        watchFilters(mounted.grid);
    }

    function categories() {
        const SPEC = { name: 'text', parent_id: 'int', inventory_account_id: 'int', expense_account_id: 'int', sale_fee_head_id: 'int', is_active: 'bool' };

        kit.mount({
            root: root,
            modal: document.getElementById('categoryModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add category', edit: 'Edit category' },
            emptyText: 'No categories yet.',
            nameOf: function (row) { return row.name; },
            canDelete: function (row) { return row.items_count > 0 ? 'Has items' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.parent_name); }) },
                { data: 'expense_account_name', orderable: false, className: 'dash-hide-md', defaultContent: 'Default (5600)' },
                { data: 'sale_fee_head_name', orderable: false, defaultContent: 'Not sold' },
                { data: 'items_count', searchable: false },
                { data: 'is_active', render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { is_active: true }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });
    }

    function orders() {
        const SPEC = { vendor_id: 'int', location_id: 'int', order_date: 'text', expected_date: 'text', purchase_request_id: 'int', tax: 'money', discount: 'money', terms: 'text' };
        const modal = document.getElementById('orderModal');
        const lines = Lines(modal.querySelector('[data-lines]'), 'order', { store: function () { return modal.querySelector('form').elements.location_id.value; } });
        const view = document.getElementById('orderView');
        const receiveModal = document.getElementById('receiveModal');
        const receiveForm = receiveModal.querySelector('form');
        let current = null;

        modal.querySelector('[data-asset-line]').addEventListener('change', function (event) {
            const select = event.target;

            if (select.value) {
                lines.add({ asset_category_id: Number(select.value), label: select.options[select.selectedIndex].text, quantity: 1 });
                select.value = '';
            }
        });

        const mounted = kit.mount({
            root: root,
            modal: modal,
            order: [[2, 'desc']],
            titles: { create: 'New purchase order', edit: 'Edit purchase order' },
            emptyText: 'No purchase orders yet.',
            searchPlaceholder: 'Search order no…',
            params: filterParams,
            nameOf: function (row) { return row.po_no; },
            canEdit: function (row) { return row.status === 'draft' ? true : 'Only drafts change'; },
            canDelete: function (row) { return row.status === 'draft' ? true : 'Cancel it instead'; },
            onView: function (row) { openOrder(row.id); },
            loadRow: function (row) { return ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; }); },
            columns: [
                { data: 'po_no', render: display(function (value, row) { return titleCell(value, [row.items_count + ' line(s)', row.request_no].filter(Boolean).join(' · ')); }) },
                { data: 'vendor_name', orderable: false },
                { data: 'order_date', render: display(function (value, row) { return titleCell(day(value), row.expected_date ? 'by ' + day(row.expected_date) : null); }) },
                { data: 'total', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'status', render: display(function (value) { return badge(value); }) }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row, SPEC, { order_date: today });
                lines.clear();
                (row ? row.items : []).forEach(function (line) {
                    lines.add({ inventory_item_id: line.inventory_item_id, asset_category_id: line.asset_category_id, label: line.label, description: line.description, quantity: line.quantity, unit_cost: line.unit_cost });
                });
            },
            payload: function (form) { return Object.assign(kit.readForm(form, SPEC), { items: lines.read() }); }
        });

        function openOrder(id) {
            ui.api('GET', root.dataset.resourceUrl + '/' + id).then(function (envelope) {
                current = envelope.data;
                view.querySelector('[data-view-title]').textContent = current.po_no + ' · ' + current.vendor_name;
                view.querySelector('[data-view-sub]').innerHTML = badge(current.status) + ' ' + esc(day(current.order_date)) + (current.approver_name ? ' · approved by ' + esc(current.approver_name) : '') + (current.cancel_reason ? ' · ' + esc(current.cancel_reason) : '');
                view.querySelector('[data-view-body]').innerHTML = '<table class="dash-table"><thead><tr><th>Item</th><th>Ordered</th><th>Received</th><th>Unit cost</th><th>Total</th></tr></thead><tbody>'
                    + current.items.map(function (line) { return '<tr><td>' + titleCell(line.description, line.label) + '</td><td>' + esc(qty(line.quantity)) + '</td><td>' + esc(qty(line.received_quantity)) + '</td><td>' + esc(money(line.unit_cost)) + '</td><td>' + esc(money(line.line_total)) + '</td></tr>'; }).join('')
                    + '</tbody><tfoot><tr><th colspan="4">Total (incl. tax ' + esc(money(current.tax)) + ', discount ' + esc(money(current.discount)) + ')</th><th>' + esc(money(current.total)) + '</th></tr></tfoot></table>'
                    + (current.receipts.length ? '<p class="dash-muted">Received: ' + current.receipts.map(function (receipt) { return esc(receipt.grn_no + ' (' + day(receipt.received_on) + ')'); }).join(', ') + '</p>' : '');
                view.querySelector('[data-view-actions]').innerHTML = '<button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>'
                    + (current.status === 'draft' && canApprove ? '<button type="button" class="dash-btn dash-btn-primary" data-order-action="approve">Approve</button>' : '')
                    + (current.status === 'approved' || current.status === 'partially_received' ? '<button type="button" class="dash-btn dash-btn-primary" data-order-action="receive">Receive goods</button>' : '')
                    + (current.status === 'draft' || current.status === 'approved' ? '<button type="button" class="dash-btn dash-btn-danger" data-order-action="cancel">Cancel order</button>' : '');
                closeOnButtons(view);

                if (!view.open) {
                    view.showModal();
                }
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The order could not be opened.'), 'error'); });
        }

        view.querySelector('[data-view-actions]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-order-action]');

            if (!button) {
                return;
            }

            const action = button.dataset.orderAction;

            if (action === 'approve') {
                ui.api('POST', root.dataset.resourceUrl + '/' + current.id + '/approve').then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); openOrder(current.id); })
                    .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not approve.'), 'error'); });
            }

            if (action === 'cancel') {
                const reason = window.prompt('Why cancel ' + current.po_no + '? (at least 10 characters)');

                if (reason) {
                    ui.api('POST', root.dataset.resourceUrl + '/' + current.id + '/cancel', { reason: reason }).then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); openOrder(current.id); })
                        .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not cancel.'), 'error'); });
                }
            }

            if (action === 'receive') {
                receiveForm.reset();
                kit.clearErrors(receiveForm);
                receiveForm.elements.received_on.value = today;
                receiveForm.elements.location_id.value = current.location_id || receiveForm.elements.location_id.value;
                receiveModal.querySelector('[data-modal-sub]').textContent = current.po_no + ' · ' + current.vendor_name;
                receiveModal.querySelector('[data-receive-rows]').innerHTML = current.items.map(function (line) {
                    return '<tr data-order-line="' + line.id + '"><td>' + titleCell(line.description, line.label) + '</td><td>' + esc(qty(line.quantity)) + '</td><td>' + esc(qty(line.received_quantity)) + '</td>'
                        + '<td><input type="number" step="0.001" min="0" max="' + esc(line.remaining) + '" value="' + esc(qty(line.remaining)) + '" data-receive-qty aria-label="Receive now"></td></tr>';
                }).join('');
                receiveModal.showModal();
            }
        });

        closeOnButtons(receiveModal);
        receiveForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = kit.readForm(receiveForm, { location_id: 'int', received_on: 'text', vendor_invoice_no: 'text', vendor_invoice_date: 'text' });

            body.purchase_order_id = current.id;
            body.items = Array.prototype.map.call(receiveModal.querySelectorAll('[data-order-line]'), function (row) {
                return { purchase_order_item_id: Number(row.dataset.orderLine), quantity: Number(row.querySelector('[data-receive-qty]').value || 0) };
            });
            kit.submitForm(receiveForm, 'POST', root.dataset.resourceUrl.replace('purchase-orders', 'goods-receipts'), body).then(function () {
                receiveModal.close();
                mounted.refresh();
                openOrder(current.id);
            }).catch(function () {});
        });
    }

    function receipts() {
        const SPEC = { vendor_id: 'int', location_id: 'int', received_on: 'text', vendor_invoice_no: 'text' };
        const modal = document.getElementById('receiptModal');
        const lines = Lines(modal.querySelector('[data-lines]'), 'receipt', { store: function () { return modal.querySelector('form').elements.location_id.value; } });

        kit.mount({
            root: root,
            modal: modal,
            order: [[4, 'desc']],
            titles: { create: 'Direct purchase', edit: 'Direct purchase' },
            emptyText: 'Nothing received yet.',
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.grn_no; },
            columns: [
                { data: 'grn_no', render: display(function (value, row) { return titleCell(value, row.vendor_invoice_no ? 'invoice ' + row.vendor_invoice_no : null); }) },
                { data: 'vendor_name', orderable: false },
                { data: 'po_no', orderable: false, className: 'dash-hide-md', defaultContent: 'Direct' },
                { data: 'location_name', orderable: false },
                { data: 'received_on', render: display(function (value) { return esc(day(value)); }) },
                { data: 'total', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'voucher_no', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' }
            ],
            fill: function (form) { kit.fillForm(form, null, SPEC, { received_on: today }); lines.clear(); },
            payload: function (form) { return Object.assign(kit.readForm(form, SPEC), { items: lines.read() }); }
        });
    }

    function issues() {
        const SPEC = { location_id: 'int', issued_on: 'text', recipient_type: 'text', recipient_id: 'int', purpose: 'text', is_sale: 'bool', due_date: 'text', received_signature: 'bool' };
        const modal = document.getElementById('issueModal');
        const form = modal.querySelector('form');
        const lines = Lines(modal.querySelector('[data-lines]'), 'issue', { store: function () { return form.elements.location_id.value; }, sellable: function () { return form.elements.is_sale.checked; } });
        const finder = recipients(form);
        const view = document.getElementById('issueView');
        const returnForm = view.querySelector('form');
        let current = null;

        function saleFields() {
            form.elements.due_date.closest('.dash-field').hidden = !form.elements.is_sale.checked;
        }

        form.elements.is_sale.addEventListener('change', function () {
            if (form.elements.is_sale.checked) {
                form.elements.recipient_type.value = 'student';
                form.elements.recipient_type.dispatchEvent(new Event('change'));
            }

            saleFields();
        });

        const mounted = kit.mount({
            root: root,
            modal: modal,
            order: [[3, 'desc']],
            titles: { create: 'Issue stock', edit: 'Issue stock' },
            emptyText: 'Nothing issued yet.',
            searchPlaceholder: 'Search issue no…',
            editable: false,
            deletable: false,
            params: filterParams,
            nameOf: function (row) { return row.issue_no; },
            onView: function (row) { openIssue(row.id); },
            columns: [
                { data: 'issue_no', render: display(function (value, row) { return titleCell(value, (row.is_sale ? 'Sale' : 'Issue') + ' · ' + row.items_count + ' item(s)' + (row.purpose ? ' · ' + row.purpose : '')); }) },
                { data: 'recipient_name', orderable: false, render: display(function (value, row) { return titleCell(value, label(row.recipient_type)); }) },
                { data: 'location_name', orderable: false },
                { data: 'issued_on', render: display(function (value) { return esc(day(value)); }) },
                { data: 'total_cost', searchable: false, render: display(function (value, row) { return esc(money(value)) + (row.is_sale ? '<span class="dash-muted dash-cell-sub">sold ' + esc(money(row.sale_total)) + (row.invoice_no ? ' · ' + esc(row.invoice_no) : '') + '</span>' : ''); }) },
                { data: 'status', render: display(function (value) { return badge(value); }) }
            ],
            fill: function (formElement) {
                kit.fillForm(formElement, null, SPEC, { issued_on: today, recipient_type: 'employee' });
                lines.clear();
                finder.clear();
                saleFields();
            },
            payload: function (formElement) { return Object.assign(kit.readForm(formElement, SPEC), { items: lines.read() }); }
        });

        function openIssue(id) {
            ui.api('GET', root.dataset.resourceUrl + '/' + id).then(function (envelope) {
                current = envelope.data;

                const returnable = !current.is_sale && current.status !== 'returned';

                view.querySelector('[data-view-title]').textContent = current.issue_no + ' · ' + (current.recipient_name || '');
                view.querySelector('[data-view-sub]').innerHTML = badge(current.status) + ' ' + esc(day(current.issued_on) + ' · ' + current.location_name + (current.invoice_no ? ' · challan ' + current.invoice_no : '') + (current.voucher_no ? ' · ' + current.voucher_no : ''));
                view.querySelector('[data-return-rows]').innerHTML = current.items.map(function (line) {
                    const left = Math.max(0, line.quantity - line.returned_quantity);

                    return '<tr data-issue-line="' + line.id + '"><td>' + titleCell(line.sku + ' · ' + line.name, line.due_back_on ? 'return by ' + day(line.due_back_on) : null) + '</td><td>' + esc(qty(line.quantity)) + '</td><td>' + esc(qty(line.returned_quantity)) + '</td>'
                        + '<td>' + (returnable && left > 0 ? '<input type="number" step="0.001" min="0" max="' + esc(left) + '" value="0" data-return-qty aria-label="Return now">' : '—') + '</td></tr>';
                }).join('');
                view.querySelector('[data-return-fields]').hidden = !returnable;
                view.querySelector('[data-return-submit]').hidden = !returnable;
                returnForm.elements.returned_on.value = today;
                kit.clearErrors(returnForm);

                if (!view.open) {
                    view.showModal();
                }
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The issue could not be opened.'), 'error'); });
        }

        closeOnButtons(view);
        returnForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = kit.readForm(returnForm, { returned_on: 'text', condition_id: 'int' });

            body.items = Array.prototype.map.call(view.querySelectorAll('[data-issue-line]'), function (row) {
                const input = row.querySelector('[data-return-qty]');

                return { stock_issue_item_id: Number(row.dataset.issueLine), quantity: input ? Number(input.value || 0) : 0 };
            }).filter(function (line) { return line.quantity > 0; });
            kit.submitForm(returnForm, 'POST', root.dataset.resourceUrl + '/' + current.id + '/return', body).then(function () {
                mounted.refresh();
                openIssue(current.id);
            }).catch(function () {});
        });

        watchFilters(mounted.grid);
    }

    function transfers() {
        const SPEC = { from_location_id: 'int', to_location_id: 'int', transferred_on: 'text', remarks: 'text' };
        const modal = document.getElementById('transferModal');
        const lines = Lines(modal.querySelector('[data-lines]'), 'transfer', { store: function () { return modal.querySelector('form').elements.from_location_id.value; } });

        kit.mount({
            root: root,
            modal: modal,
            order: [[3, 'desc']],
            titles: { create: 'Move stock', edit: 'Move stock' },
            emptyText: 'No transfers yet.',
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.transfer_no; },
            columns: [
                { data: 'transfer_no', render: display(function (value, row) { return titleCell(value, row.remarks); }) },
                { data: 'from_name', orderable: false },
                { data: 'to_name', orderable: false },
                { data: 'transferred_on', render: display(function (value) { return esc(day(value)); }) },
                { data: 'items_count', searchable: false }
            ],
            fill: function (form) { kit.fillForm(form, null, SPEC, { transferred_on: today }); lines.clear(); },
            payload: function (form) { return Object.assign(kit.readForm(form, SPEC), { items: lines.read() }); }
        });
    }

    function counts() {
        const SPEC = { location_id: 'int', adjusted_on: 'text', reason_id: 'int', remarks: 'text' };
        const modal = document.getElementById('countModal');
        const form = modal.querySelector('form');
        const lines = Lines(modal.querySelector('[data-lines]'), 'count', { store: function () { return form.elements.location_id.value; } });

        form.elements.location_id.addEventListener('change', function () {
            lines.clear();
            ui.toast('Store changed: add the items again so the system quantities match this store.');
        });

        kit.mount({
            root: root,
            modal: modal,
            order: [[2, 'desc']],
            titles: { create: 'Stock count', edit: 'Stock count' },
            emptyText: 'No counts yet.',
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.adjustment_no; },
            columns: [
                { data: 'adjustment_no', render: display(function (value, row) { return titleCell(value, row.remarks); }) },
                { data: 'location_name', orderable: false },
                { data: 'adjusted_on', render: display(function (value) { return esc(day(value)); }) },
                { data: 'reason_name', orderable: false, defaultContent: 'Count' },
                { data: 'items_count', searchable: false },
                { data: 'approver_name', orderable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'voucher_no', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' }
            ],
            fill: function (formElement) { kit.fillForm(formElement, null, SPEC, { adjusted_on: today }); lines.clear(); },
            payload: function (formElement) { return Object.assign(kit.readForm(formElement, SPEC), { items: lines.read() }); }
        });
    }

    function ledger() {
        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[0, 'desc']],
            params: filterParams,
            searchPlaceholder: 'Search item…',
            emptyText: 'No stock movements yet.',
            exportName: 'stock-ledger',
            columns: [
                { data: 'moved_on', render: display(function (value) { return esc(day(value)); }) },
                { data: 'item_label', orderable: false },
                { data: 'location_name', orderable: false },
                { data: 'movement_type', render: display(function (value) { return esc(label(value)); }) },
                { data: 'quantity', searchable: false, render: display(function (value) { return '<span class="dash-badge ' + (value < 0 ? 'dash-badge-danger' : 'dash-badge-ok') + '">' + esc((value > 0 ? '+' : '') + qty(value)) + '</span>'; }) },
                { data: 'unit_cost', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'balance_after', searchable: false, render: display(function (value) { return esc(qty(value)); }) },
                { data: 'document', orderable: false, searchable: false, defaultContent: '—' }
            ]
        });

        watchFilters(grid);
    }

    ({ stock: stock, categories: categories, orders: orders, receipts: receipts, issues: issues, transfers: transfers, counts: counts, ledger: ledger }[screen] || function () {})();
})();

/**
 * HR purchase requests: item lines with estimated prices, sent to an administrator for approval.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const root = document.querySelector('[data-purchases]');

    if (!ui || !kit || !window.PinnacleGrid || !root) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const TONES = { pending: 'dash-badge-warn', approved: 'dash-badge-ok', rejected: 'dash-badge-danger' };
    const modal = document.getElementById('purchaseModal');
    const form = modal.querySelector('form');
    const lines = form.querySelector('[data-lines]');
    const template = form.querySelector('[data-line-template]');
    const FIELDS = { department_id: 'int', request_date: 'text', needed_by: 'text', purpose: 'text' };

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function field(line, name) {
        return line.querySelector('[data-line-field="' + name + '"]');
    }

    function addLine(item) {
        const line = template.content.firstElementChild.cloneNode(true);

        if (item) {
            field(line, 'item_name').value = item.item_name;
            field(line, 'category_id').value = item.category_id || '';
            field(line, 'quantity').value = item.quantity;
            field(line, 'unit_id').value = item.unit_id || '';
            field(line, 'estimated_unit_price').value = (item.estimated_unit_price / 100).toFixed(2);
        }

        lines.appendChild(line);
        updateTotal();
    }

    function readLines() {
        return Array.prototype.map.call(lines.querySelectorAll('[data-line]'), function (line) {
            const price = field(line, 'estimated_unit_price').value;

            return {
                item_name: field(line, 'item_name').value.trim() || null,
                category_id: Number(field(line, 'category_id').value) || null,
                quantity: field(line, 'quantity').value ? Number(field(line, 'quantity').value) : null,
                unit_id: Number(field(line, 'unit_id').value) || null,
                estimated_unit_price: price ? Math.round(parseFloat(price) * 100) : 0
            };
        });
    }

    function updateTotal() {
        const total = readLines().reduce(function (sum, item) {
            return sum + Math.round((item.quantity || 0) * item.estimated_unit_price);
        }, 0);

        form.querySelector('[data-total]').textContent = money(total);
    }

    const mounted = kit.mount({
        root: root,
        modal: modal,
        order: [[0, 'desc']],
        titles: { create: 'New purchase request', edit: 'Edit purchase request' },
        searchPlaceholder: 'Search request no or purpose…',
        emptyText: 'No purchase requests yet.',
        params: function () {
            const values = {};

            root.querySelectorAll('[data-filter]').forEach(function (filter) {
                values[filter.dataset.filter] = filter.value;
            });

            return values;
        },
        nameOf: function (row) { return row.request_no; },
        canEdit: function (row) { return row.status === 'pending' ? true : 'Already ' + row.status; },
        canDelete: function (row) { return row.status === 'approved' ? 'Approved' : true; },
        loadRow: function (row) {
            return ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; });
        },
        columns: [
            { data: 'request_no', name: 'request_no', render: display(function (value, row) {
                return '<span class="dash-cell-title">' + esc(value) + '</span><span class="dash-muted">' + esc(row.purpose || '') + '</span>';
            }) },
            { data: 'department_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' },
            { data: 'estimated_total', searchable: false, className: 'dash-col-num', render: display(function (value, row) {
                return money(value) + '<span class="dash-muted">' + row.items_count + ' item' + (row.items_count === 1 ? '' : 's') + '</span>';
            }) },
            { data: 'status', searchable: false, render: display(function (value, row) {
                return '<span class="dash-badge ' + TONES[value] + '">' + esc(value.charAt(0).toUpperCase() + value.slice(1)) + '</span>'
                    + (row.decided_by_name ? '<span class="dash-muted">by ' + esc(row.decided_by_name) + '</span>' : '');
            }) }
        ],
        fill: function (purchaseForm, row) {
            kit.fillForm(purchaseForm, row, FIELDS);
            lines.innerHTML = '';
            (row ? row.items : [null]).forEach(addLine);
        },
        payload: function (purchaseForm) {
            return Object.assign(kit.readForm(purchaseForm, FIELDS), { items: readLines() });
        }
    });

    root.querySelectorAll('[data-filter]').forEach(function (filter) {
        filter.addEventListener('change', function () {
            mounted.grid.ajax.reload();
        });
    });

    form.querySelector('[data-add-line]').addEventListener('click', function () {
        addLine(null);
    });

    lines.addEventListener('click', function (event) {
        const remove = event.target.closest('[data-remove-line]');

        if (remove) {
            remove.closest('[data-line]').remove();
            updateTotal();
        }
    });

    lines.addEventListener('input', updateTotal);
})();

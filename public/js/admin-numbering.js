/**
 * Document numbering screen — edit-only grid via PinnacleResource and /api/erp/number-sequences.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-numbering]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const modal = document.getElementById('numberingModal');
    const form = modal.querySelector('[data-resource-form]');
    const year = new Date().getFullYear();

    function formatPreview() {
        const prefix = form.elements.prefix.value.trim();
        const padding = Math.min(Math.max(parseInt(form.elements.padding.value, 10) || 1, 1), 10);
        const next = Math.max(parseInt(form.elements.next_number.value, 10) || 1, 1);

        modal.querySelector('[data-preview]').textContent = prefix
            + (form.elements.include_year.checked ? year + '-' : '')
            + String(next).padStart(padding, '0');
    }

    form.addEventListener('input', formatPreview);
    form.addEventListener('change', formatPreview);

    window.PinnacleResource.mount({
        root: root,
        modal: modal,
        creatable: false,
        deletable: false,
        order: [[0, 'asc']],
        pageLength: 25,
        searchPlaceholder: 'Search documents…',
        titles: { create: 'Edit number format', edit: 'Edit number format' },
        nameOf: function (row) {
            return row.label;
        },
        columns: [
            {
                data: 'label',
                render: function (value, type, row) {
                    return type === 'display'
                        ? '<span class="dash-cell-title">' + esc(row.label) + '</span><span class="dash-muted">' + esc(row.type) + '</span>'
                        : value;
                }
            },
            {
                data: 'preview',
                orderable: false,
                searchable: false,
                render: function (value, type) {
                    return type === 'display' ? '<code class="dash-number-preview">' + esc(value) + '</code>' : value;
                }
            },
            { data: 'prefix', render: function (value, type) { return type === 'display' ? esc(value || '—') : value; } },
            { data: 'padding', searchable: false, className: 'dash-col-num' },
            {
                data: 'reset_yearly',
                searchable: false,
                render: function (value, type) {
                    return type === 'display' ? '<span class="dash-badge ' + (value ? 'dash-badge-brand' : 'dash-badge-system') + '">' + (value ? 'Every year' : 'Never') + '</span>' : value;
                }
            }
        ],
        fill: function (formElement, row) {
            formElement.elements.label.value = row.label;
            formElement.elements.prefix.value = row.prefix || '';
            formElement.elements.padding.value = row.padding;
            formElement.elements.next_number.value = row.next_number;
            formElement.elements.next_number.min = row.next_number;
            formElement.elements.include_year.checked = row.include_year;
            formElement.elements.reset_yearly.checked = row.reset_yearly;
            modal.querySelector('[data-next-hint]').textContent = 'Can only move forward from ' + row.next_number + '.';
            formatPreview();
        },
        payload: function (formElement) {
            return {
                label: formElement.elements.label.value.trim(),
                prefix: formElement.elements.prefix.value.trim() || null,
                padding: Number(formElement.elements.padding.value),
                next_number: Number(formElement.elements.next_number.value),
                include_year: formElement.elements.include_year.checked,
                reset_yearly: formElement.elements.reset_yearly.checked
            };
        }
    });
})();

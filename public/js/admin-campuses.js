/**
 * Campuses screen — grid + paper popup via PinnacleResource and /api/erp/campuses.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-campuses]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const statusFilter = root.querySelector('[data-status-filter]');

    const screen = window.PinnacleResource.mount({
        root: root,
        modal: document.getElementById('campusModal'),
        order: [[0, 'asc']],
        searchPlaceholder: 'Search campuses…',
        emptyText: 'No campuses yet.',
        titles: { create: 'Add campus', edit: 'Edit campus' },
        params: function () {
            return { is_active: statusFilter.value };
        },
        nameOf: function (row) {
            return row.name;
        },
        canDelete: function (row) {
            return row.users_count > 0 ? 'Has users' : true;
        },
        columns: [
            {
                data: 'name',
                render: function (value, type, row) {
                    return type === 'display'
                        ? '<span class="dash-cell-title">' + esc(row.name) + '</span><span class="dash-muted">' + esc(row.code) + '</span>'
                        : value;
                }
            },
            { data: 'city', defaultContent: '—', render: function (value, type) { return type === 'display' ? esc(value || '—') : value; } },
            {
                data: 'phone',
                orderable: false,
                render: function (value, type, row) {
                    return type === 'display'
                        ? '<span>' + esc(row.phone || '—') + '</span>' + (row.email ? '<span class="dash-muted">' + esc(row.email) + '</span>' : '')
                        : value;
                }
            },
            { data: 'users_count', searchable: false, className: 'dash-col-num' },
            {
                data: 'is_active',
                searchable: false,
                render: function (value, type) {
                    return type === 'display'
                        ? '<span class="dash-badge ' + (value ? 'dash-badge-ok' : 'dash-badge-system') + '">' + (value ? 'Active' : 'Inactive') + '</span>'
                        : value;
                }
            }
        ],
        fill: function (form, row) {
            form.elements.name.value = row ? row.name : '';
            form.elements.code.value = row ? row.code : '';
            form.elements.address.value = row && row.address ? row.address : '';
            form.elements.city.value = row && row.city ? row.city : '';
            form.elements.phone.value = row && row.phone ? row.phone : '';
            form.elements.email.value = row && row.email ? row.email : '';
            form.elements.is_active.checked = row ? row.is_active : true;
        },
        payload: function (form) {
            const value = function (name) {
                return form.elements[name].value.trim() || null;
            };

            return {
                name: form.elements.name.value.trim(),
                code: form.elements.code.value.trim().toUpperCase(),
                address: value('address'),
                city: value('city'),
                phone: value('phone'),
                email: value('email'),
                is_active: form.elements.is_active.checked
            };
        }
    });

    statusFilter.addEventListener('change', function () {
        screen.grid.ajax.reload();
    });
})();

/**
 * Roles screen — grid + paper popup with a grouped permission matrix, via /api/erp/roles.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-roles]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const modal = document.getElementById('roleModal');
    const form = modal.querySelector('[data-resource-form]');
    const matrix = form.querySelector('[data-perm-matrix]');
    const locked = form.querySelector('[data-perm-locked]');
    const countLabel = form.querySelector('[data-perm-count]');
    const permissionBoxes = Array.prototype.slice.call(matrix.querySelectorAll('input[name="permissions"]'));
    const groupBoxes = Array.prototype.slice.call(matrix.querySelectorAll('[data-perm-group]'));
    const totalPermissions = Number(root.dataset.totalPermissions);
    let editingAdmin = false;

    function refreshGroups() {
        groupBoxes.forEach(function (groupBox) {
            const members = permissionBoxes.filter(function (box) {
                return box.dataset.group === groupBox.dataset.permGroup;
            });
            const checked = members.filter(function (box) {
                return box.checked;
            }).length;

            groupBox.checked = checked === members.length;
            groupBox.indeterminate = checked > 0 && checked < members.length;
        });

        const selected = permissionBoxes.filter(function (box) {
            return box.checked;
        }).length;

        countLabel.textContent = editingAdmin ? 'All' : selected + ' of ' + totalPermissions + ' selected';
    }

    matrix.addEventListener('change', function (event) {
        const groupBox = event.target.closest('[data-perm-group]');

        if (groupBox) {
            permissionBoxes.forEach(function (box) {
                if (box.dataset.group === groupBox.dataset.permGroup) {
                    box.checked = groupBox.checked;
                }
            });
        }

        refreshGroups();
    });

    window.PinnacleResource.mount({
        root: root,
        modal: modal,
        order: [[0, 'asc']],
        searchPlaceholder: 'Search roles…',
        emptyText: 'No roles yet.',
        titles: { create: 'Add role', edit: 'Edit role' },
        nameOf: function (row) {
            return row.name;
        },
        canDelete: function (row) {
            if (row.is_system) {
                return 'Built-in role';
            }

            return row.users_count > 0 ? 'Assigned to users' : true;
        },
        columns: [
            {
                data: 'name',
                render: function (value, type, row) {
                    return type === 'display'
                        ? '<span class="dash-cell-title">' + esc(row.name) + '</span><span class="dash-muted">' + esc(row.description || row.slug) + '</span>'
                        : value;
                }
            },
            { data: 'users_count', searchable: false, className: 'dash-col-num' },
            {
                data: 'permissions_count',
                searchable: false,
                render: function (value, type, row) {
                    if (type !== 'display') {
                        return value;
                    }

                    return row.is_admin
                        ? '<span class="dash-badge dash-badge-brand">Full access</span>'
                        : '<span class="dash-perm-meter" style="--fill:' + Math.round((value / Math.max(totalPermissions, 1)) * 100) + '%"><span></span></span> ' + esc(value) + ' / ' + totalPermissions;
                }
            },
            {
                data: 'is_system',
                searchable: false,
                className: 'dash-hide-md',
                render: function (value, type) {
                    return type === 'display'
                        ? '<span class="dash-badge ' + (value ? 'dash-badge-ok' : '') + '">' + (value ? 'Built-in' : 'Custom') + '</span>'
                        : value;
                }
            }
        ],
        fill: function (form, row) {
            editingAdmin = Boolean(row && row.is_admin);
            form.elements.name.value = row ? row.name : '';
            form.elements.slug.value = row ? row.slug : '';
            form.elements.slug.readOnly = Boolean(row && row.is_system);
            form.elements.description.value = row && row.description ? row.description : '';

            const granted = row ? row.permission_slugs : [];

            permissionBoxes.forEach(function (box) {
                box.checked = editingAdmin || granted.indexOf(box.value) !== -1;
                box.disabled = editingAdmin;
            });
            groupBoxes.forEach(function (box) {
                box.disabled = editingAdmin;
            });

            matrix.classList.toggle('is-locked', editingAdmin);
            locked.hidden = !editingAdmin;
            refreshGroups();
        },
        payload: function (form, row) {
            const body = {
                name: form.elements.name.value.trim(),
                description: form.elements.description.value.trim() || null
            };
            const slug = form.elements.slug.value.trim();

            if (slug) {
                body.slug = slug;
            }

            if (!(row && row.is_admin)) {
                body.permissions = permissionBoxes.filter(function (box) {
                    return box.checked;
                }).map(function (box) {
                    return box.value;
                });
            }

            return body;
        }
    });
})();

/**
 * Lookups screen: list filter, Yajra grid, add / edit modal, active toggle, delete.
 * All reads and writes go through the /api/erp/lookups endpoints.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-lookups]');

    if (!root || !window.DataTable || !window.PinnacleGrid || !window.PinnacleDashboard) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const esc = window.PinnacleGrid.escape;
    const resourceUrl = root.dataset.resourceUrl;
    const modal = document.getElementById('lookupModal');
    const form = modal.querySelector('[data-lookup-form]');
    const typeList = root.querySelector('[data-type-list]');
    const statusFilter = root.querySelector('[data-status-filter]');
    let currentType = '';
    let editing = null;

    const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
        url: root.dataset.datatableUrl,
        order: [[1, 'asc'], [2, 'asc']],
        searchPlaceholder: 'Search options…',
        emptyText: 'No options in this list yet. Add the first one.',
        params: function () {
            return { type: currentType, is_active: statusFilter.value };
        },
        columns: [
            {
                data: 'name',
                render: function (value, type, row) {
                    if (type !== 'display') {
                        return value;
                    }

                    return '<span class="dash-cell-title">' + esc(row.name) + '</span>'
                        + (row.code ? '<span class="dash-muted">' + esc(row.code) + '</span>' : '')
                        + (row.is_system ? '<span class="dash-badge dash-badge-system">Built-in</span>' : '');
                }
            },
            {
                data: 'type',
                render: function (value, type, row) {
                    return type === 'display' ? '<span class="dash-badge dash-badge-brand">' + esc(row.type_label) + '</span>' : value;
                }
            },
            { data: 'sort_order', className: 'dash-col-num' },
            {
                data: 'is_active',
                render: function (value, type, row) {
                    if (type !== 'display') {
                        return value;
                    }

                    return '<button type="button" class="dash-switch dash-switch-inline" data-toggle-active role="switch" aria-checked="' + (row.is_active ? 'true' : 'false') + '" aria-label="' + (row.is_active ? 'Deactivate ' : 'Activate ') + esc(row.name) + '">'
                        + '<span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>'
                        + '<span class="dash-switch-text">' + (row.is_active ? 'Active' : 'Inactive') + '</span></button>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'dash-col-actions',
                render: function (value, type, row) {
                    const eraser = row.is_system
                        ? '<span class="dash-action dash-action-delete is-disabled" data-tooltip="Built-in" aria-label="Built-in options cannot be deleted">' + icon('eraser') + '</span>'
                        : '<button type="button" class="dash-action dash-action-delete" data-delete data-tooltip="Delete" aria-label="Delete ' + esc(row.name) + '">' + icon('eraser') + '</button>';

                    return '<div class="dash-actions">'
                        + '<button type="button" class="dash-action dash-action-edit" data-edit data-tooltip="Edit" aria-label="Edit ' + esc(row.name) + '">' + icon('pen') + '</button>'
                        + eraser
                        + '</div>';
                }
            }
        ]
    });

    function icon(name) {
        const paths = {
            pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
            eraser: '<path d="m7 21-4.3-4.3a2.4 2.4 0 0 1 0-3.4l9.6-9.6a2.4 2.4 0 0 1 3.4 0l5.6 5.6a2.4 2.4 0 0 1 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/>'
        };

        return '<svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths[name] + '</svg>';
    }

    function messageOf(envelope, fallback) {
        if (envelope && envelope.errors && envelope.errors.summary) {
            return envelope.errors.summary;
        }

        return (envelope && envelope.message) || fallback;
    }

    function rowFrom(element) {
        const tr = element.closest('tr');

        return { tr: tr, data: grid.row(tr).data() };
    }

    function payloadFrom(row, overrides) {
        return Object.assign({
            type: row.type,
            name: row.name,
            code: row.code,
            description: row.description,
            sort_order: row.sort_order,
            is_active: row.is_active
        }, overrides);
    }

    function refresh() {
        grid.ajax.reload(null, false);
        refreshTypes();
    }

    /* ---------- List (type) filter ---------- */

    function selectType(type, label) {
        currentType = type;
        typeList.querySelectorAll('[data-type-filter]').forEach(function (button) {
            button.classList.toggle('is-active', button.dataset.typeFilter === type);
        });
        root.querySelector('[data-grid-title]').textContent = type ? label : 'All lists';
        root.querySelector('[data-grid-subtitle]').textContent = type ? 'Options in the ' + label + ' list.' : 'Every option across all lists.';
        grid.column(1).visible(!type);
        grid.ajax.reload();
    }

    typeList.addEventListener('click', function (event) {
        const button = event.target.closest('[data-type-filter]');

        if (button) {
            selectType(button.dataset.typeFilter, button.querySelector('.dash-type-name').textContent);
        }
    });

    root.querySelector('[data-type-search]').addEventListener('input', function (event) {
        const term = event.target.value.trim().toLowerCase();

        typeList.querySelectorAll('[data-type-filter]').forEach(function (button) {
            const matches = button.dataset.typeFilter === '' || button.textContent.toLowerCase().indexOf(term) !== -1;
            button.parentElement.hidden = !matches;
        });
    });

    statusFilter.addEventListener('change', function () {
        grid.ajax.reload();
    });

    function refreshTypes() {
        ui.api('GET', root.dataset.typesUrl).then(function (envelope) {
            const types = envelope.data;
            const total = types.reduce(function (sum, item) { return sum + item.total; }, 0);
            let html = '<li><button type="button" class="dash-type-item' + (currentType === '' ? ' is-active' : '') + '" data-type-filter="">'
                + '<span class="dash-type-name">All lists</span><span class="dash-type-count">' + total + '</span></button></li>';

            types.forEach(function (item) {
                html += '<li><button type="button" class="dash-type-item' + (currentType === item.type ? ' is-active' : '') + '" data-type-filter="' + esc(item.type) + '">'
                    + '<span class="dash-type-name">' + esc(item.label) + '</span>'
                    + '<span class="dash-type-count">' + item.active + '/' + item.total + '</span></button></li>';
            });

            typeList.innerHTML = html;
            root.querySelector('[data-types-count]').textContent = types.length;
            root.querySelector('[data-options-count]').textContent = total;
            modal.querySelector('[data-type-datalist]').innerHTML = types.map(function (item) {
                return '<option value="' + esc(item.type) + '">' + esc(item.label) + '</option>';
            }).join('');
        }).catch(function () {});
    }

    /* ---------- Add / edit modal ---------- */

    function clearErrors() {
        form.querySelectorAll('[data-error-for]').forEach(function (element) {
            element.hidden = true;
            element.textContent = '';
        });
        form.querySelectorAll('.is-invalid').forEach(function (element) {
            element.classList.remove('is-invalid');
        });
        form.querySelector('[data-form-error]').hidden = true;
    }

    function openModal(row) {
        editing = row || null;
        clearErrors();
        form.reset();

        form.elements.type.value = row ? row.type : currentType;
        form.elements.name.value = row ? row.name : '';
        form.elements.code.value = row && row.code ? row.code : '';
        form.elements.sort_order.value = row ? row.sort_order : 0;
        form.elements.description.value = row && row.description ? row.description : '';
        form.elements.is_active.checked = row ? row.is_active : true;
        form.elements.type.readOnly = Boolean(row && row.is_system);

        modal.querySelector('[data-modal-title]').textContent = row ? 'Edit option' : 'Add option';
        modal.querySelector('[data-submit-label]').textContent = row ? 'Save Changes' : 'Save Option';
        modal.querySelector('[data-type-hint]').textContent = row && row.is_system
            ? 'Built-in options stay in their list.'
            : 'Pick an existing list or type a new key in lowercase_with_underscores.';

        modal.showModal();
        (row || currentType ? form.elements.name : form.elements.type).focus();
    }

    function closeModal() {
        modal.close();
        editing = null;
    }

    function showErrors(envelope) {
        const fields = envelope && envelope.errors && envelope.errors.fields ? envelope.errors.fields : null;

        if (!fields) {
            const general = form.querySelector('[data-form-error]');
            general.textContent = (envelope && envelope.message) || 'Something went wrong. Try again.';
            general.hidden = false;

            return;
        }

        Object.keys(fields).forEach(function (field) {
            const target = form.querySelector('[data-error-for="' + field + '"]');
            const input = form.elements[field];

            if (target) {
                target.textContent = fields[field][0];
                target.hidden = false;
            }

            if (input && input.classList) {
                input.classList.add('is-invalid');
            }
        });

        const firstInvalid = form.querySelector('.is-invalid');

        if (firstInvalid) {
            firstInvalid.focus();
        }
    }

    root.ownerDocument.querySelector('[data-lookup-create]').addEventListener('click', function () {
        openModal(null);
    });

    modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    form.addEventListener('input', function (event) {
        const error = form.querySelector('[data-error-for="' + event.target.name + '"]');

        if (error) {
            error.hidden = true;
            event.target.classList.remove('is-invalid');
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearErrors();

        const submit = form.querySelector('[data-submit]');
        const sortOrder = form.elements.sort_order.value.trim();
        const body = {
            type: form.elements.type.value.trim(),
            name: form.elements.name.value.trim(),
            code: form.elements.code.value.trim() || null,
            description: form.elements.description.value.trim() || null,
            sort_order: sortOrder === '' ? null : Number(sortOrder),
            is_active: form.elements.is_active.checked
        };

        submit.disabled = true;

        const request = editing
            ? ui.api('PUT', resourceUrl + '/' + editing.id, body)
            : ui.api('POST', resourceUrl, body);

        request.then(function (envelope) {
            closeModal();
            ui.toast(envelope.message);
            refresh();
        }).catch(showErrors).finally(function () {
            submit.disabled = false;
        });
    });

    /* ---------- Row actions ---------- */

    root.querySelector('[data-grid]').addEventListener('click', function (event) {
        const editButton = event.target.closest('[data-edit]');
        const deleteButton = event.target.closest('[data-delete]');
        const toggleButton = event.target.closest('[data-toggle-active]');

        if (editButton) {
            openModal(rowFrom(editButton).data);
        }

        if (toggleButton) {
            const row = rowFrom(toggleButton).data;
            toggleButton.disabled = true;

            ui.api('PUT', resourceUrl + '/' + row.id, payloadFrom(row, { is_active: !row.is_active }))
                .then(function (envelope) {
                    ui.toast(row.name + (envelope.data.is_active ? ' is now active' : ' is now inactive'));
                    refresh();
                })
                .catch(function (envelope) {
                    ui.toast(messageOf(envelope, 'Could not update the option.'), 'error');
                    toggleButton.disabled = false;
                });
        }

        if (deleteButton) {
            const target = rowFrom(deleteButton);

            ui.confirm(target.data.name).then(function (accepted) {
                if (!accepted) {
                    return;
                }

                ui.erase(target.tr)
                    .then(function () {
                        return ui.api('DELETE', resourceUrl + '/' + target.data.id);
                    })
                    .then(function (envelope) {
                        ui.toast(envelope.message);
                        refresh();
                    })
                    .catch(function (envelope) {
                        target.tr.classList.remove('is-erasing');
                        ui.toast(messageOf(envelope, 'Could not delete the option.'), 'error');
                    });
            });
        }
    });
})();

/**
 * Shared CRUD screen: Yajra grid + paper popup form + edit / delete actions,
 * all through the module's /api/erp endpoints. Each screen only describes its
 * columns and how to fill / read its form.
 *
 * PinnacleResource.mount({
 *   root,                      // element with data-datatable-url and data-resource-url
 *   modal,                     // <dialog> containing form[data-resource-form]
 *   columns, order, params, searchPlaceholder, emptyText,
 *   fill(form, row|null),      // put a row's values into the form (null = new)
 *   payload(form),             // read the form into the request body
 *   titles: { create, edit },  // popup titles
 *   nameOf(row),               // name used in toasts and the delete confirmation
 *   canEdit(row) / canDelete(row) → true or a reason string shown as tooltip,
 *   viewUrl(row) / editUrl(row) // optional: open full pages instead of the popup
 *   creatable                  // false hides "add" (edit-only resources)
 * })
 */
(function () {
    'use strict';

    const ICONS = {
        book: '<path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/>',
        pen: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        eraser: '<path d="m7 21-4.3-4.3a2.4 2.4 0 0 1 0-3.4l9.6-9.6a2.4 2.4 0 0 1 3.4 0l5.6 5.6a2.4 2.4 0 0 1 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/>'
    };

    function icon(name) {
        return '<svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    }

    function messageOf(envelope, fallback) {
        if (envelope && envelope.errors && envelope.errors.summary) {
            return envelope.errors.summary;
        }

        return (envelope && envelope.message) || fallback;
    }

    function mount(config) {
        const ui = window.PinnacleDashboard;
        const esc = window.PinnacleGrid.escape;
        const root = config.root;
        const modal = config.modal;
        const form = modal ? modal.querySelector('[data-resource-form]') : null;
        const resourceUrl = root.dataset.resourceUrl;
        const allowed = function (check, row) {
            return check ? check(row) : true;
        };
        let editing = null;

        const actionsColumn = {
            data: null,
            orderable: false,
            searchable: false,
            className: 'dash-col-actions',
            render: function (value, type, row) {
                if (type !== 'display') {
                    return '';
                }

                const name = esc(config.nameOf(row));
                const canEdit = allowed(config.canEdit, row);
                const canDelete = config.deletable === false ? null : allowed(config.canDelete, row);
                let html = '<div class="dash-actions">';

                if (config.viewUrl) {
                    html += '<a href="' + esc(config.viewUrl(row)) + '" class="dash-action dash-action-view" data-tooltip="View" aria-label="View ' + name + '">' + icon('book') + '</a>';
                }

                if (canEdit !== true) {
                    html += '<span class="dash-action dash-action-edit is-disabled" data-tooltip="' + esc(canEdit) + '">' + icon('pen') + '</span>';
                } else if (config.editUrl) {
                    html += '<a href="' + esc(config.editUrl(row)) + '" class="dash-action dash-action-edit" data-tooltip="Edit" aria-label="Edit ' + name + '">' + icon('pen') + '</a>';
                } else {
                    html += '<button type="button" class="dash-action dash-action-edit" data-edit data-tooltip="Edit" aria-label="Edit ' + name + '">' + icon('pen') + '</button>';
                }

                if (canDelete !== null) {
                    html += canDelete === true
                        ? '<button type="button" class="dash-action dash-action-delete" data-delete data-tooltip="Delete" aria-label="Delete ' + name + '">' + icon('eraser') + '</button>'
                        : '<span class="dash-action dash-action-delete is-disabled" data-tooltip="' + esc(canDelete) + '">' + icon('eraser') + '</span>';
                }

                return html + '</div>';
            }
        };

        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: config.order || [],
            params: config.params,
            pageLength: config.pageLength,
            searchPlaceholder: config.searchPlaceholder,
            emptyText: config.emptyText,
            columns: config.columns.concat([actionsColumn])
        });

        function refresh() {
            grid.ajax.reload(null, false);

            if (config.onChanged) {
                config.onChanged();
            }
        }

        /* ---------- Form popup ---------- */

        function clearErrors() {
            form.querySelectorAll('[data-error-for]').forEach(function (element) {
                element.hidden = true;
                element.textContent = '';
            });
            form.querySelectorAll('.is-invalid').forEach(function (element) {
                element.classList.remove('is-invalid');
            });

            const general = form.querySelector('[data-form-error]');

            if (general) {
                general.hidden = true;
            }
        }

        function showErrors(envelope) {
            const fields = envelope && envelope.errors && envelope.errors.fields ? envelope.errors.fields : null;
            let placed = false;

            if (fields) {
                Object.keys(fields).forEach(function (field) {
                    const key = field.split('.')[0];
                    const target = form.querySelector('[data-error-for="' + key + '"]');
                    const input = form.elements[key];

                    if (target) {
                        target.textContent = fields[field][0];
                        target.hidden = false;
                        placed = true;
                    }

                    if (input && input.classList) {
                        input.classList.add('is-invalid');
                    }
                });
            }

            if (!placed) {
                const general = form.querySelector('[data-form-error]');
                general.textContent = messageOf(envelope, 'Something went wrong. Try again.');
                general.hidden = false;
            }

            const firstInvalid = form.querySelector('.is-invalid');

            if (firstInvalid && firstInvalid.focus) {
                firstInvalid.focus();
            }
        }

        function open(row) {
            editing = row || null;
            form.reset();
            clearErrors();
            config.fill(form, editing);
            modal.querySelector('[data-modal-title]').textContent = editing ? config.titles.edit : config.titles.create;
            modal.showModal();

            const first = form.querySelector('input:not([type="hidden"]):not([readonly]):not([type="checkbox"]), textarea, select');

            if (first) {
                first.focus();
            }
        }

        function close() {
            modal.close();
            editing = null;
        }

        if (form) {
            const createButton = document.querySelector('[data-resource-create]');

            if (createButton && config.creatable !== false) {
                createButton.addEventListener('click', function () {
                    open(null);
                });
            }

            modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
                button.addEventListener('click', close);
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    close();
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
                const body = config.payload(form, editing);
                submit.disabled = true;

                (editing ? ui.api('PUT', resourceUrl + '/' + editing.id, body) : ui.api('POST', resourceUrl, body))
                    .then(function (envelope) {
                        close();
                        ui.toast(envelope.message);
                        refresh();
                    })
                    .catch(showErrors)
                    .finally(function () {
                        submit.disabled = false;
                    });
            });
        }

        /* ---------- Row actions ---------- */

        root.querySelector('[data-grid]').addEventListener('click', function (event) {
            const editButton = event.target.closest('[data-edit]');
            const deleteButton = event.target.closest('[data-delete]');

            if (editButton) {
                open(grid.row(editButton.closest('tr')).data());
            }

            if (deleteButton) {
                const tr = deleteButton.closest('tr');
                const row = grid.row(tr).data();

                ui.confirm(config.nameOf(row)).then(function (accepted) {
                    if (!accepted) {
                        return;
                    }

                    ui.erase(tr)
                        .then(function () {
                            return ui.api('DELETE', resourceUrl + '/' + row.id);
                        })
                        .then(function (envelope) {
                            ui.toast(envelope.message);
                            refresh();
                        })
                        .catch(function (envelope) {
                            tr.classList.remove('is-erasing');
                            ui.toast(messageOf(envelope, 'Could not delete.'), 'error');
                        });
                });
            }
        });

        return { grid: grid, open: open, refresh: refresh };
    }

    window.PinnacleResource = { mount: mount, messageOf: messageOf };
})();

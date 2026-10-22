/**
 * User directory (Yajra grid via /api/erp/users) and the profile page:
 * delete through the API, plus the documents panel (/api/erp/attachments).
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;

    if (!ui || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const messageOf = function (envelope, fallback) {
        if (envelope && envelope.errors && envelope.errors.summary) {
            return envelope.errors.summary;
        }

        return (envelope && envelope.message) || fallback;
    };
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    /* ---------- Directory grid ---------- */

    function mountDirectory(root) {
        const roleFilter = root.querySelector('[data-role-filter]');
        const statusFilter = root.querySelector('[data-status-filter]');
        const pageUrl = root.dataset.pageUrl;

        const screen = window.PinnacleResource.mount({
            root: root,
            modal: null,
            order: [[0, 'asc']],
            searchPlaceholder: 'Search name, email, username, phone…',
            emptyText: 'No users yet.',
            params: function () {
                return { role: roleFilter.value, is_active: statusFilter.value };
            },
            nameOf: function (row) {
                return row.name;
            },
            viewUrl: function (row) {
                return pageUrl + '/' + row.id;
            },
            editUrl: function (row) {
                return pageUrl + '/' + row.id + '/edit';
            },
            canDelete: function (row) {
                return row.is_self ? 'This is you' : true;
            },
            columns: [
                {
                    data: 'name',
                    render: function (value, type, row) {
                        if (type !== 'display') {
                            return value;
                        }

                        return '<a href="' + esc(pageUrl + '/' + row.id) + '" class="dash-person">'
                            + '<span class="dash-person-avatar" aria-hidden="true">' + esc(row.name.charAt(0).toUpperCase()) + '</span>'
                            + '<span><span class="dash-person-name">' + esc(row.name) + '</span>'
                            + '<span class="dash-person-meta">' + esc(row.email) + '</span>'
                            + (row.is_active ? '' : '<span class="dash-badge dash-badge-system dash-show-md">Disabled</span>')
                            + '</span></a>';
                    }
                },
                {
                    data: 'username',
                    defaultContent: '',
                    className: 'dash-nowrap',
                    render: function (value, type, row) {
                        if (type !== 'display') {
                            return value;
                        }

                        return '<span>' + esc(row.username || '—') + '</span>' + (row.phone ? '<span class="dash-muted">' + esc(row.phone) + '</span>' : '');
                    }
                },
                {
                    data: 'roles_list',
                    orderable: false,
                    searchable: false,
                    render: function (value, type) {
                        if (type !== 'display') {
                            return '';
                        }

                        const badges = (value || []).map(function (role) {
                            return '<span class="dash-badge dash-badge-brand">' + esc(role.name) + '</span>';
                        });

                        return '<div class="dash-badges">' + (badges.length ? badges.join('') : '<span class="dash-badge">Unassigned</span>') + '</div>';
                    }
                },
                {
                    data: 'is_active',
                    searchable: false,
                    className: 'dash-hide-md',
                    render: function (value, type) {
                        return type === 'display'
                            ? '<span class="dash-badge ' + (value ? 'dash-badge-ok' : 'dash-badge-system') + '">' + (value ? 'Active' : 'Disabled') + '</span>'
                            : value;
                    }
                },
                {
                    data: 'last_login_at',
                    searchable: false,
                    className: 'dash-hide-md',
                    render: function (value, type) {
                        if (type !== 'display') {
                            return value;
                        }

                        return value ? esc(dateFormat.format(new Date(value))) : '<span class="dash-muted">Never</span>';
                    }
                }
            ]
        });

        [roleFilter, statusFilter].forEach(function (field) {
            field.addEventListener('change', function () {
                screen.grid.ajax.reload();
            });
        });
    }

    /* ---------- Profile: delete ---------- */

    function mountProfileDelete(button) {
        button.addEventListener('click', function () {
            ui.eraseRecord(button.dataset.name, document.querySelector('.dash-profile'), button.dataset.deleteUrl).then(function (result) {
                if (result === 'deleted') {
                    window.location.assign(button.dataset.redirect);
                }
            });
        });
    }

    /* ---------- Profile: documents ---------- */

    const ICONS = {
        file: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        check: '<path d="M20 6 9 17l-5-5"/>',
        eraser: '<path d="m7 21-4.3-4.3a2.4 2.4 0 0 1 0-3.4l9.6-9.6a2.4 2.4 0 0 1 3.4 0l5.6 5.6a2.4 2.4 0 0 1 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/>'
    };

    function icon(name) {
        return '<svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    }

    function mountDocuments(panel) {
        const list = panel.querySelector('[data-doc-list]');
        const empty = panel.querySelector('[data-doc-empty]');
        const count = panel.querySelector('[data-doc-count]');
        const modal = document.getElementById('documentModal');
        const form = modal.querySelector('[data-document-form]');
        const drop = form.querySelector('[data-file-drop]');
        const fileInput = form.elements.file;
        const fileLabel = form.querySelector('[data-file-name]');
        const baseUrl = panel.dataset.url;
        const owner = { owner: panel.dataset.owner, owner_id: panel.dataset.ownerId };
        let documents = [];

        function render() {
            count.textContent = documents.length + (documents.length === 1 ? ' file' : ' files');
            empty.hidden = documents.length > 0;
            list.innerHTML = documents.map(function (doc) {
                const preview = doc.is_image
                    ? '<img class="dash-doc-thumb" src="' + esc(doc.url) + '" alt="" loading="lazy">'
                    : '<span class="dash-doc-icon">' + icon('file') + '</span>';
                const meta = [
                    doc.document_type ? esc(doc.document_type.name) : null,
                    esc(doc.size_label),
                    doc.document_number ? '#' + esc(doc.document_number) : null,
                    doc.expires_at ? (doc.is_expired ? '<span class="dash-badge dash-badge-warn">Expired</span>' : 'Expires ' + esc(dateFormat.format(new Date(doc.expires_at)))) : null,
                    doc.is_verified ? '<span class="dash-badge dash-badge-ok">Verified</span>' : null
                ].filter(Boolean).join('<span aria-hidden="true">·</span>');

                return '<li class="dash-doc" data-id="' + doc.id + '">' + preview
                    + '<div><span class="dash-doc-name">' + esc(doc.title || doc.original_name) + '</span><span class="dash-doc-meta">' + meta + '</span></div>'
                    + '<div class="dash-actions">'
                    + '<a class="dash-action dash-action-view" href="' + esc(doc.url) + '" target="_blank" rel="noopener" data-tooltip="Open" aria-label="Open ' + esc(doc.original_name) + '">' + icon('eye') + '</a>'
                    + '<button type="button" class="dash-action dash-action-verify' + (doc.is_verified ? ' is-on' : '') + '" data-verify data-tooltip="' + (doc.is_verified ? 'Remove verification' : 'Mark verified') + '">' + icon('check') + '</button>'
                    + '<button type="button" class="dash-action dash-action-delete" data-delete data-tooltip="Delete" aria-label="Delete ' + esc(doc.original_name) + '">' + icon('eraser') + '</button>'
                    + '</div></li>';
            }).join('');
        }

        function load() {
            ui.api('GET', baseUrl + '?' + new URLSearchParams(owner).toString())
                .then(function (envelope) {
                    documents = envelope.data;
                    render();
                })
                .catch(function (envelope) {
                    ui.toast(messageOf(envelope, 'Could not load documents.'), 'error');
                });
        }

        function find(element) {
            const id = Number(element.closest('[data-id]').dataset.id);

            return documents.find(function (doc) {
                return doc.id === id;
            });
        }

        function clearErrors() {
            form.querySelectorAll('[data-error-for]').forEach(function (element) {
                element.hidden = true;
            });
            form.querySelector('[data-form-error]').hidden = true;
        }

        function showErrors(envelope) {
            const fields = envelope && envelope.errors && envelope.errors.fields;
            let placed = false;

            Object.keys(fields || {}).forEach(function (field) {
                const target = form.querySelector('[data-error-for="' + field + '"]');

                if (target) {
                    target.textContent = fields[field][0];
                    target.hidden = false;
                    placed = true;
                }
            });

            if (!placed) {
                const general = form.querySelector('[data-form-error]');
                general.textContent = messageOf(envelope, 'Upload failed. Try again.');
                general.hidden = false;
            }
        }

        function showFileName() {
            fileLabel.textContent = fileInput.files.length ? fileInput.files[0].name : 'Choose a file or drop it here';
        }

        panel.querySelector('[data-doc-add]').addEventListener('click', function () {
            form.reset();
            clearErrors();
            showFileName();
            modal.showModal();
        });

        modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                modal.close();
            });
        });

        fileInput.addEventListener('change', showFileName);

        ['dragenter', 'dragover'].forEach(function (name) {
            drop.addEventListener(name, function (event) {
                event.preventDefault();
                drop.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach(function (name) {
            drop.addEventListener(name, function (event) {
                event.preventDefault();
                drop.classList.remove('is-dragging');
            });
        });

        drop.addEventListener('drop', function (event) {
            if (event.dataTransfer.files.length) {
                fileInput.files = event.dataTransfer.files;
                showFileName();
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors();

            const body = new window.FormData(form);
            const submit = form.querySelector('[data-submit]');
            body.append('owner', owner.owner);
            body.append('owner_id', owner.owner_id);
            submit.disabled = true;

            ui.api('POST', baseUrl, body)
                .then(function (envelope) {
                    modal.close();
                    ui.toast(envelope.message);
                    documents.unshift(envelope.data);
                    render();
                })
                .catch(showErrors)
                .finally(function () {
                    submit.disabled = false;
                });
        });

        list.addEventListener('click', function (event) {
            const verifyButton = event.target.closest('[data-verify]');
            const deleteButton = event.target.closest('[data-delete]');

            if (verifyButton) {
                const doc = find(verifyButton);

                ui.api('PATCH', baseUrl + '/' + doc.id + '/verify', { is_verified: !doc.is_verified })
                    .then(function (envelope) {
                        Object.assign(doc, envelope.data);
                        ui.toast(envelope.message);
                        render();
                    })
                    .catch(function (envelope) {
                        ui.toast(messageOf(envelope, 'Could not update.'), 'error');
                    });
            }

            if (deleteButton) {
                const doc = find(deleteButton);
                const item = deleteButton.closest('.dash-doc');

                ui.eraseRecord(doc.title || doc.original_name, item, baseUrl + '/' + doc.id).then(function (result) {
                    if (result === 'deleted') {
                        documents = documents.filter(function (other) {
                            return other.id !== doc.id;
                        });
                        render();
                    }
                });
            }
        });

        load();
    }

    const directory = document.querySelector('[data-users]');
    const deleteButton = document.querySelector('[data-user-delete]');
    const documentsPanel = document.querySelector('[data-documents]');

    if (directory && window.PinnacleResource) {
        mountDirectory(directory);
    }

    if (deleteButton) {
        mountProfileDelete(deleteButton);
    }

    if (documentsPanel) {
        mountDocuments(documentsPanel);
    }
})();

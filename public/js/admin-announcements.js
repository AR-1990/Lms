/**
 * Announcements area: the announcement list (write, publish, schedule, archive, reach), categories,
 * message templates and the delivery log. Data comes from /api/erp/announcements and friends.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    const STATUS_BADGES = { draft: 'dash-badge-system', scheduled: 'dash-badge-warn', published: 'dash-badge-ok', archived: 'dash-badge-system' };
    const AUDIENCE_LABELS = { everyone: 'Everyone', all_students: 'Students', all_parents: 'Parents', all_employees: 'Staff', role: 'Roles', class: 'Classes', section: 'Sections', department: 'Departments', user: 'People' };

    function display(render) {
        return function (value, columnType, row) {
            return columnType === 'display' ? render(value, row) : value;
        };
    }

    function titleCell(title, subtitle) {
        return '<span class="dash-cell-title">' + esc(title) + '</span>' + (subtitle ? '<span class="dash-muted">' + esc(subtitle) + '</span>' : '');
    }

    function filterParams(root) {
        return function () {
            const values = {};

            root.querySelectorAll('[data-filter]').forEach(function (field) {
                values[field.dataset.filter] = field.value;
            });

            return values;
        };
    }

    function wireFilters(root, grid) {
        root.querySelectorAll('[data-filter]').forEach(function (field) {
            field.addEventListener('change', function () {
                grid.ajax.reload();
            });
        });
    }

    // <input type="datetime-local"> wants "YYYY-MM-DDTHH:MM" in local time.
    function toLocalInput(iso) {
        if (!iso) {
            return '';
        }

        const date = new Date(iso);
        const pad = function (value) { return String(value).padStart(2, '0'); };

        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    /* ---------- Announcements ---------- */

    const listRoot = document.querySelector('[data-announcements]');

    if (listRoot) {
        const modal = document.getElementById('announcementModal');
        const statsModal = document.getElementById('announcementStatsModal');
        const SPEC = { title: 'text', category_id: 'int', summary: 'text', body: 'text', is_public: 'bool', is_pinned: 'bool', is_urgent: 'bool', requires_acknowledgement: 'bool', send_push: 'bool', send_sms: 'bool', send_email: 'bool' };

        const setAudiences = function (form, audiences) {
            form.querySelectorAll('[data-audience-group]').forEach(function (box) {
                box.checked = audiences.some(function (row) { return row.audience_type === box.dataset.audienceGroup; });
            });
            form.querySelectorAll('[data-audience-list]').forEach(function (select) {
                Array.prototype.forEach.call(select.options, function (option) {
                    option.selected = audiences.some(function (row) { return row.audience_type === select.dataset.audienceList && String(row.audience_id) === option.value; });
                });
            });
        };

        const readAudiences = function (form) {
            const rows = [];

            form.querySelectorAll('[data-audience-group]:checked').forEach(function (box) {
                rows.push({ audience_type: box.dataset.audienceGroup, audience_id: null });
            });
            form.querySelectorAll('[data-audience-list]').forEach(function (select) {
                Array.prototype.forEach.call(select.selectedOptions, function (option) {
                    rows.push({ audience_type: select.dataset.audienceList, audience_id: Number(option.value) });
                });
            });

            return rows;
        };

        const mounted = kit.mount({
            root: listRoot,
            modal: modal,
            order: [[2, 'desc']],
            titles: { create: 'New announcement', edit: 'Edit announcement' },
            emptyText: 'No announcements yet.',
            searchPlaceholder: 'Search title…',
            params: filterParams(listRoot),
            nameOf: function (row) { return row.title; },
            canEdit: function (row) { return row.status === 'archived' ? 'Archived announcements cannot be changed' : true; },
            onView: function (row) {
                ui.api('GET', listRoot.dataset.resourceUrl + '/' + row.id).then(function (envelope) {
                    const stats = envelope.data.stats;
                    const percent = stats.recipients ? Math.round(stats.read * 100 / stats.recipients) : 0;
                    const tile = function (label, value) {
                        return '<article class="dash-stat"><span class="dash-stat-label">' + esc(label) + '</span><strong class="dash-stat-value">' + esc(String(value)) + '</strong></article>';
                    };

                    statsModal.querySelector('[data-stats-title]').textContent = row.title;
                    statsModal.querySelector('[data-stats-body]').innerHTML = tile('Reaches', stats.recipients) + tile('Opened', stats.read + ' (' + percent + '%)')
                        + (row.requires_acknowledgement ? tile('Confirmed', stats.acknowledged) : '');
                    statsModal.showModal();
                }).catch(function (envelope) {
                    ui.toast(kit.messageOf(envelope, 'Could not load the reach.'), 'error');
                });
            },
            loadRow: function (row) {
                return ui.api('GET', listRoot.dataset.resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; });
            },
            columns: [
                { data: 'title', render: display(function (value, row) {
                    const flags = [row.is_pinned ? 'pinned' : null, row.is_urgent ? 'urgent' : null, row.is_public ? 'on website' : null].filter(Boolean).join(' · ');

                    return '<span class="dash-notice-badge" style="--badge:' + esc(row.category_color || '#1d2b45') + '">' + esc(row.category_name || 'Notice') + '</span> ' + titleCell(value, flags);
                }) },
                { data: 'audience_types', orderable: false, searchable: false, className: 'dash-hide-md', render: display(function (value, row) {
                    const labels = String(value || '').split(', ').filter(Boolean).map(function (type) { return AUDIENCE_LABELS[type] || type; });

                    return esc(labels.join(', ') || (row.is_public ? 'Website only' : '—'));
                }) },
                { data: 'publish_at', searchable: false, className: 'dash-hide-md', render: display(function (value) { return value ? esc(dateFormat.format(new Date(value))) : '—'; }) },
                { data: 'status', searchable: false, render: display(function (value, row) {
                    let html = '<span class="dash-badge ' + (STATUS_BADGES[value] || '') + '">' + esc(value.charAt(0).toUpperCase() + value.slice(1)) + '</span>';

                    if (value === 'draft' || value === 'scheduled') {
                        html += ' <button type="button" class="dash-link-btn" data-publish="' + row.id + '">' + (value === 'draft' ? 'Publish' : 'Publish now') + '</button>';
                    }

                    if (value === 'published' || value === 'scheduled') {
                        html += ' <button type="button" class="dash-link-btn" data-archive="' + row.id + '">Archive</button>';
                    }

                    return html;
                }) },
                { data: 'reads_count', searchable: false, className: 'dash-hide-md', render: display(function (value, row) {
                    return esc(String(value)) + (row.requires_acknowledgement ? ' <span class="dash-muted">(' + esc(String(row.acknowledged_count)) + ' confirmed)</span>' : '');
                }) }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row, SPEC, { send_push: true });
                form.elements.publish_at.value = toLocalInput(row && row.publish_at);
                form.elements.expires_at.value = toLocalInput(row && row.expires_at);
                setAudiences(form, row ? row.audiences || [] : []);
            },
            payload: function (form) {
                const body = kit.readForm(form, SPEC);

                body.publish_at = form.elements.publish_at.value || null;
                body.expires_at = form.elements.expires_at.value || null;
                body.audiences = readAudiences(form);

                return body;
            }
        });

        wireFilters(listRoot, mounted.grid);
        kit.wireDialog(statsModal);

        listRoot.querySelector('[data-grid]').addEventListener('click', function (event) {
            const publish = event.target.closest('[data-publish]');
            const archive = event.target.closest('[data-archive]');

            if (!publish && !archive) {
                return;
            }

            const button = publish || archive;
            const url = listRoot.dataset.resourceUrl + '/' + (publish ? publish.dataset.publish + '/publish' : archive.dataset.archive + '/archive');
            const hide = publish ? ui.busy('Publishing and notifying people…') : function () {};

            button.disabled = true;
            ui.api('POST', url)
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    mounted.refresh();
                })
                .catch(function (envelope) {
                    ui.toast(kit.messageOf(envelope, 'That did not work.'), 'error');
                    button.disabled = false;
                })
                .finally(hide);
        });
    }

    /* ---------- Categories & message templates ---------- */

    const settingsRoot = document.querySelector('[data-communication-settings]');

    if (settingsRoot) {
        const type = settingsRoot.dataset.communicationSettings;
        const modal = document.getElementById('communicationModal');
        const form = modal.querySelector('form');
        const active = function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; };
        const SCREENS = {
            categories: {
                spec: { name: 'text', color: 'text', sort_order: 'int', is_active: 'bool' },
                defaults: { color: '#1d4ed8', is_active: true },
                columns: [
                    { data: 'name', render: display(function (value, row) { return '<span class="dash-notice-badge" style="--badge:' + esc(row.color || '#1d2b45') + '">' + esc(value) + '</span>'; }) },
                    { data: 'announcements_count', searchable: false, className: 'dash-hide-md', render: display(function (value) { return esc(String(value)) + ' announcement' + (value === 1 ? '' : 's'); }) },
                    { data: 'is_active', searchable: false, render: display(active) }
                ]
            },
            templates: {
                spec: { name: 'text', event_key: 'text', channel: 'text', subject: 'text', body: 'text', is_active: 'bool' },
                defaults: { is_active: true },
                columns: [
                    { data: 'name', render: display(function (value, row) { return titleCell(value, row.channel === 'in_app' ? 'Bell (in app)' : row.channel); }) },
                    { data: 'event_label', name: 'event_key', className: 'dash-hide-md', render: display(function (value) { return esc(value); }) },
                    { data: 'is_active', searchable: false, render: display(active) }
                ]
            }
        };
        const screen = SCREENS[type];
        const eventSelect = form.querySelector('[data-template-event]');

        const showPlaceholders = function () {
            if (!eventSelect) {
                return;
            }

            const names = eventSelect.selectedOptions[0].dataset.placeholders.split(' ');

            form.querySelector('[data-template-placeholders]').textContent = 'You can use: ' + names.map(function (name) { return '{' + name + '}'; }).join('  ');
        };

        if (eventSelect) {
            eventSelect.addEventListener('change', showPlaceholders);
        }

        kit.mount({
            root: settingsRoot,
            modal: modal,
            order: [[0, 'asc']],
            titles: { create: 'Add', edit: 'Edit' },
            emptyText: 'Nothing here yet.',
            nameOf: function (row) { return row.name; },
            columns: screen.columns,
            fill: function (form, row) {
                kit.fillForm(form, row, screen.spec, screen.defaults);
                showPlaceholders();
            },
            payload: function (form) {
                return kit.readForm(form, screen.spec);
            }
        });
    }

    /* ---------- Delivery log ---------- */

    const logRoot = document.querySelector('[data-delivery-log]');

    if (logRoot) {
        const STATUS = { queued: 'dash-badge-warn', sent: 'dash-badge-ok', delivered: 'dash-badge-ok', failed: 'dash-badge-danger' };
        const grid = window.PinnacleGrid.create(logRoot.querySelector('[data-grid]'), {
            url: logRoot.dataset.datatableUrl,
            order: [[3, 'desc']],
            searchPlaceholder: 'Search recipient or text…',
            emptyText: 'Nothing has been sent yet.',
            params: filterParams(logRoot),
            columns: [
                { data: 'subject', render: display(function (value, row) { return titleCell(value || row.event_key || 'Message', row.channel + ' · ' + String(row.body || '').slice(0, 90)); }) },
                { data: 'recipient', className: 'dash-hide-md' },
                { data: 'status', searchable: false, render: display(function (value, row) {
                    return '<span class="dash-badge ' + (STATUS[value] || '') + '"' + (row.error ? ' data-tooltip="' + esc(row.error) + '"' : '') + '>' + esc(value) + '</span>';
                }) },
                { data: 'created_at', searchable: false, className: 'dash-hide-md', render: display(function (value) { return esc(dateFormat.format(new Date(value))); }) }
            ]
        });

        wireFilters(logRoot, grid);
    }
})();

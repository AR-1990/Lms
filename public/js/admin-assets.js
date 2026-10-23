/**
 * Asset screens (PLAN.md §15): register with hand-over / take-back / repair / dispose, who holds what,
 * repairs, verification sheets, monthly depreciation, disposals, categories and locations.
 * Data: /api/erp/assets…; amounts travel in paisa and forms take rupees.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-assets]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const screen = root.dataset.assets;
    const today = new Date().toISOString().slice(0, 10);
    const STATUS_TONES = { in_store: 'dash-badge-ok', assigned: 'dash-badge-brand', in_repair: 'dash-badge-warn', lost: 'dash-badge-danger', disposed: 'dash-badge-system', reported: 'dash-badge-warn', in_progress: 'dash-badge-brand', completed: 'dash-badge-ok', draft: 'dash-badge-warn' };

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function label(value) {
        return String(value || '').replace(/_/g, ' ').replace(/^\w/, function (letter) { return letter.toUpperCase(); });
    }

    function badge(value) {
        return '<span class="dash-badge ' + (STATUS_TONES[value] || '') + '">' + esc(label(value)) + '</span>';
    }

    function day(value) {
        return value ? dateFormat.format(new Date(value)) : '—';
    }

    function titleCell(title, sub) {
        return '<strong>' + esc(title || '—') + '</strong>' + (sub ? '<span class="dash-muted dash-cell-sub">' + esc(sub) + '</span>' : '');
    }

    function filterParams() {
        const params = {};

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            params[select.dataset.filter] = select.value;
        });

        return params;
    }

    function closeOnButtons(modal) {
        modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });
    }

    /* ---------- Register ---------- */

    function register() {
        const canManage = root.dataset.canManage === '1';
        const canAssign = root.dataset.canAssign === '1';
        const canDispose = root.dataset.canDispose === '1';
        const details = document.getElementById('assetDetails');
        const SPEC = { name: 'text', asset_category_id: 'int', asset_tag: 'text', serial_no: 'text', brand: 'text', model: 'text', location_id: 'int', condition_id: 'int', vendor_id: 'int', invoice_no: 'text', purchase_date: 'text', purchase_cost: 'money', warranty_expires_on: 'text', depreciation_start_date: 'text', description: 'text', post_purchase: 'bool', payment_method_id: 'int' };
        let current = null;

        const mounted = kit.mount({
            root: root,
            modal: document.getElementById('assetModal'),
            order: [[0, 'desc']],
            titles: { create: 'Register asset', edit: 'Edit asset' },
            emptyText: 'No assets yet.',
            searchPlaceholder: 'Search tag, name or serial…',
            params: filterParams,
            creatable: canManage,
            editable: canManage,
            deletable: canManage,
            nameOf: function (row) { return row.asset_tag + ' ' + row.name; },
            canEdit: function (row) { return row.status === 'disposed' ? 'Disposed assets cannot change' : true; },
            canDelete: function (row) { return row.status === 'assigned' ? 'Take it back first' : true; },
            onView: function (row) { openDetails(row.id); },
            loadRow: function (row) { return ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; }); },
            columns: [
                { data: 'asset_tag', render: display(function (value, row) { return titleCell(value + ' · ' + row.name, [row.brand, row.model, row.serial_no].filter(Boolean).join(' · ')); }) },
                { data: 'category_name', orderable: false, defaultContent: '—' },
                { data: 'holder_name', orderable: false, render: display(function (value, row) { return titleCell(value || row.location_name || 'In store', value ? row.location_name : row.condition_name); }) },
                { data: 'status', render: display(function (value, row) {
                    const next = canAssign && value === 'in_store' ? ['assign', 'Hand over'] : (canAssign && value === 'assigned' ? ['return', 'Take back'] : null);

                    return badge(value) + (next ? ' <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm dash-quick-action" data-quick="' + next[0] + '" data-id="' + row.id + '">' + next[1] + '</button>' : '');
                }) },
                { data: 'current_book_value', searchable: false, className: 'dash-hide-md dash-col-num', render: display(function (value) { return esc(money(value)); }) },
                { data: 'purchase_date', className: 'dash-hide-md', render: display(function (value) { return esc(day(value)); }) }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row, SPEC, { purchase_date: today });
                form.querySelector('[data-post-purchase]').hidden = !!row;
                form.elements.payment_method_id.closest('.dash-field').hidden = !!row;
            },
            payload: function (form, row) {
                const body = kit.readForm(form, SPEC);

                if (row) {
                    delete body.post_purchase;
                    delete body.payment_method_id;
                }

                return body;
            }
        });

        function openDetails(id) {
            return ui.api('GET', root.dataset.resourceUrl + '/' + id).then(function (envelope) {
                current = envelope.data;
                renderDetails();

                if (!details.open) {
                    details.showModal();
                }
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The asset could not be opened.'), 'error'); });
        }

        function renderDetails() {
            const asset = current;
            const facts = [
                ['Status', badge(asset.status)], ['Category', esc(asset.category_name)], ['With', esc(asset.holder_name || '—')], ['Location', esc(asset.location_name || '—')],
                ['Condition', esc(asset.condition_name || '—')], ['Serial', esc(asset.serial_no || '—')], ['Bought', esc(day(asset.purchase_date)) + (asset.vendor_name ? ' · ' + esc(asset.vendor_name) : '')],
                ['Cost', esc(money(asset.purchase_cost))], ['Book value', esc(money(asset.current_book_value))], ['Warranty', esc(day(asset.warranty_expires_on))]
            ];
            const buttons = [];

            details.querySelector('[data-details-title]').textContent = asset.asset_tag + ' · ' + asset.name;
            details.querySelector('[data-details-subtitle]').textContent = [asset.brand, asset.model].filter(Boolean).join(' ') || asset.category_name;
            details.querySelector('[data-details-facts]').innerHTML = facts.map(function (fact) { return '<div><dt>' + fact[0] + '</dt><dd>' + fact[1] + '</dd></div>'; }).join('');

            if (canAssign && asset.status === 'in_store') {
                buttons.push('<button type="button" class="dash-btn dash-btn-primary" data-open-action="assign">Hand over</button>');
            }

            if (canAssign && asset.status === 'assigned') {
                buttons.push('<button type="button" class="dash-btn dash-btn-primary" data-open-action="return">Take back</button>');
            }

            if (canManage && (asset.status === 'in_store' || asset.status === 'assigned')) {
                buttons.push('<button type="button" class="dash-btn dash-btn-ghost" data-open-action="repair">Report repair</button>');
            }

            if (canDispose && (asset.status === 'in_store' || asset.status === 'lost')) {
                buttons.push('<button type="button" class="dash-btn dash-btn-danger" data-open-action="dispose">Dispose</button>');
            }

            details.querySelector('[data-details-actions]').innerHTML = buttons.join('') || '<p class="dash-muted">No actions for this asset.</p>';
            details.querySelector('[data-details-assignments]').innerHTML = asset.assignments.map(function (row) {
                return '<li><strong>' + esc(row.holder || '—') + '</strong> <span class="dash-muted">' + esc(label(row.assignee_type)) + '</span><span>' + esc(day(row.assigned_on)) + ' → ' + esc(row.returned_on ? day(row.returned_on) : 'still held') + '</span>'
                    + (row.remarks ? '<span class="dash-muted">' + esc(row.remarks) + '</span>' : '') + '</li>';
            }).join('') || '<li class="dash-muted">Never handed out.</li>';
            details.querySelector('[data-details-repairs]').innerHTML = asset.maintenances.map(function (row) {
                return '<li><strong>' + esc(row.type || 'Repair') + '</strong> ' + badge(row.status) + '<span>' + esc(day(row.reported_on)) + (row.cost ? ' · ' + esc(money(row.cost)) : '') + '</span>' + (row.description ? '<span class="dash-muted">' + esc(row.description) + '</span>' : '') + '</li>';
            }).join('') || '<li class="dash-muted">No repairs.</li>';
            details.querySelector('[data-details-depreciation]').innerHTML = asset.depreciations.map(function (row) {
                return '<li><strong>' + esc(row.month) + '</strong><span>' + esc(money(row.amount)) + ' · book value ' + esc(money(row.book_value_after)) + '</span></li>';
            }).join('') || '<li class="dash-muted">Not depreciated yet.</li>';
        }

        closeOnButtons(details);

        /* Action popups: hand over, take back, repair, dispose */

        const ACTIONS = {
            assign: { modal: 'assetAssign', url: '/assign', spec: { assignee_type: 'text', assignee_id: 'int', assigned_on: 'text', expected_return_on: 'text', condition_out_id: 'int', acknowledgement_signed: 'bool', remarks: 'text' }, defaults: { assignee_type: 'employee', assigned_on: today } },
            return: { modal: 'assetReturn', url: '/return', spec: { returned_on: 'text', condition_in_id: 'int', location_id: 'int', remarks: 'text' }, defaults: { returned_on: today } },
            repair: { modal: 'assetRepair', url: '/repairs', spec: { maintenance_type_id: 'int', vendor_id: 'int', reported_on: 'text', sent_on: 'text', description: 'text' }, defaults: { reported_on: today } },
            dispose: { modal: 'assetDispose', url: '/dispose', spec: { disposed_on: 'text', method: 'text', sale_amount: 'money', payment_method_id: 'int', reason: 'text' }, defaults: { disposed_on: today, method: 'scrapped' } }
        };

        function openAction(key) {
            const action = ACTIONS[key];
            const modal = document.getElementById(action.modal);
            const form = modal.querySelector('form');

            form.reset();
            kit.clearErrors(form);
            kit.fillForm(form, null, action.spec, action.defaults);
            modal.querySelectorAll('[data-action-asset]').forEach(function (line) {
                line.textContent = current.asset_tag + ' · ' + current.name + (key === 'return' && current.holder_name ? ' — with ' + current.holder_name : '');
            });

            if (key === 'assign') {
                form.querySelector('[data-holder-results]').innerHTML = '';
                form.querySelector('[data-holder-search]').value = '';
            }

            modal.showModal();

            if (key === 'assign') {
                form.querySelector('[data-holder-search]').focus();
            }
        }

        details.querySelector('[data-details-actions]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-open-action]');

            if (button) {
                openAction(button.dataset.openAction);
            }
        });

        // Straight from the grid row: load the asset, then open its next step.
        root.querySelector('[data-grid]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-quick]');

            if (!button) {
                return;
            }

            ui.api('GET', root.dataset.resourceUrl + '/' + button.dataset.id).then(function (envelope) {
                current = envelope.data;
                openAction(button.dataset.quick);
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The asset could not be opened.'), 'error'); });
        });

        Object.keys(ACTIONS).forEach(function (key) {
            const action = ACTIONS[key];
            const modal = document.getElementById(action.modal);
            const form = modal.querySelector('form');

            closeOnButtons(modal);
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                kit.submitForm(form, 'POST', root.dataset.resourceUrl + '/' + current.id + action.url, kit.readForm(form, action.spec)).then(function () {
                    modal.close();
                    mounted.refresh();

                    if (details.open) {
                        openDetails(current.id);
                    }
                }).catch(function () {});
            });
        });

        /* Hand over: find the person, department or room */

        const assignForm = document.getElementById('assetAssign').querySelector('form');
        const results = assignForm.querySelector('[data-holder-results]');
        let searchTimer = null;

        function searchHolders() {
            const term = assignForm.querySelector('[data-holder-search]').value.trim();

            ui.api('GET', root.dataset.resourceUrl + '/holders?type=' + encodeURIComponent(assignForm.elements.assignee_type.value) + '&term=' + encodeURIComponent(term)).then(function (envelope) {
                results.innerHTML = envelope.data.map(function (holder) {
                    return '<li><button type="button" data-holder="' + holder.id + '" data-holder-name="' + esc(holder.name) + '">' + esc(holder.name) + (holder.detail ? ' <span class="dash-muted">' + esc(holder.detail) + '</span>' : '') + '</button></li>';
                }).join('') || '<li class="dash-muted">Nobody found.</li>';
            }).catch(function () {});
        }

        assignForm.querySelector('[data-holder-search]').addEventListener('input', function () {
            assignForm.elements.assignee_id.value = '';
            clearTimeout(searchTimer);
            searchTimer = setTimeout(searchHolders, 300);
        });

        assignForm.elements.assignee_type.addEventListener('change', function () {
            assignForm.elements.assignee_id.value = '';
            assignForm.querySelector('[data-holder-search]').value = '';
            searchHolders();
        });

        results.addEventListener('click', function (event) {
            const pick = event.target.closest('[data-holder]');

            if (pick) {
                assignForm.elements.assignee_id.value = pick.dataset.holder;
                assignForm.querySelector('[data-holder-search]').value = pick.dataset.holderName;
                results.innerHTML = '';
            }
        });

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            select.addEventListener('change', function () { mounted.grid.ajax.reload(); });
        });
    }

    /* ---------- Who holds what ---------- */

    function handovers() {
        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[2, 'desc']],
            params: filterParams,
            searchPlaceholder: 'Search asset…',
            emptyText: 'No hand-overs.',
            exportName: 'asset-handovers',
            columns: [
                { data: 'asset_label', orderable: false },
                { data: 'holder_name', orderable: false, render: display(function (value, row) { return titleCell(value, label(row.assignee_type)); }) },
                { data: 'assigned_on', render: display(function (value) { return esc(day(value)); }) },
                { data: 'expected_return_on', render: display(function (value, row) { return row.is_overdue ? '<span class="dash-badge dash-badge-danger">' + esc(day(value)) + '</span>' : esc(day(value)); }) },
                { data: 'returned_on', render: display(function (value) { return value ? esc(day(value)) : '<span class="dash-badge dash-badge-brand">Still held</span>'; }) },
                { data: 'remarks', orderable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: null, orderable: false, searchable: false, exportable: false, render: display(function (value, row) {
                    return row.returned_on ? '' : '<button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-take-back="' + row.asset_id + '" data-label="' + esc(row.asset_label + ' — with ' + (row.holder_name || '')) + '">Take back</button>';
                }) }
            ]
        });
        const modal = document.getElementById('handoverReturn');
        const form = modal.querySelector('form');
        const SPEC = { returned_on: 'text', condition_in_id: 'int', location_id: 'int', remarks: 'text' };
        let assetId = null;

        root.querySelector('[data-grid]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-take-back]');

            if (!button) {
                return;
            }

            assetId = button.dataset.takeBack;
            form.reset();
            kit.clearErrors(form);
            kit.fillForm(form, null, SPEC, { returned_on: today });
            modal.querySelector('[data-action-asset]').textContent = button.dataset.label;
            modal.showModal();
        });

        closeOnButtons(modal);
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(form, 'POST', root.dataset.resourceUrl + '/' + assetId + '/return', kit.readForm(form, SPEC)).then(function () {
                modal.close();
                grid.ajax.reload(null, false);
            }).catch(function () {});
        });

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            select.addEventListener('change', function () { grid.ajax.reload(); });
        });
    }

    /* ---------- Repairs ---------- */

    function repairs() {
        const SPEC = { maintenance_type_id: 'int', vendor_id: 'int', reported_on: 'text', sent_on: 'text', description: 'text' };
        const DONE = { completed_on: 'text', cost: 'money', vendor_id: 'int', payment_method_id: 'int', condition_id: 'int', description: 'text' };
        const completeModal = document.getElementById('completeModal');
        const completeForm = completeModal.querySelector('form');
        let job = null;

        const mounted = kit.mount({
            root: root,
            modal: document.getElementById('repairModal'),
            order: [[3, 'desc']],
            titles: { create: 'Edit repair', edit: 'Edit repair' },
            emptyText: 'No repairs.',
            creatable: false,
            deletable: false,
            params: filterParams,
            nameOf: function (row) { return row.asset_label; },
            canEdit: function (row) { return row.status === 'completed' ? 'Completed jobs are closed' : true; },
            onView: function (row) {
                if (row.status === 'completed') {
                    ui.toast('This job is completed' + (row.voucher_no ? ' (' + row.voucher_no + ')' : '') + '.');

                    return;
                }

                job = row;
                completeForm.reset();
                kit.clearErrors(completeForm);
                kit.fillForm(completeForm, null, DONE, { completed_on: today, cost: row.cost ? row.cost : null, vendor_id: row.vendor_id, description: row.description });
                completeModal.querySelector('[data-action-asset]').textContent = row.asset_label;
                completeModal.showModal();
            },
            columns: [
                { data: 'asset_label', orderable: false },
                { data: 'type_name', orderable: false, render: display(function (value, row) { return titleCell(value || 'Repair', row.description); }) },
                { data: 'vendor_name', orderable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'reported_on', render: display(function (value, row) { return titleCell(day(value), row.completed_on ? 'done ' + day(row.completed_on) : null); }) },
                { data: 'cost', searchable: false, render: display(function (value, row) { return esc(money(value)) + (row.voucher_no ? '<span class="dash-muted dash-cell-sub">' + esc(row.voucher_no) + '</span>' : ''); }) },
                { data: 'status', render: display(function (value) { return badge(value); }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, {}); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });

        closeOnButtons(completeModal);
        completeForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(completeForm, 'POST', root.dataset.resourceUrl + '/' + job.id + '/complete', kit.readForm(completeForm, DONE)).then(function () {
                completeModal.close();
                mounted.refresh();
            }).catch(function () {});
        });

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            select.addEventListener('change', function () { mounted.grid.ajax.reload(); });
        });
    }

    /* ---------- Verification ---------- */

    function verification() {
        const SPEC = { audit_date: 'text', location_id: 'int', remarks: 'text' };
        const sheet = document.getElementById('auditSheet');
        const rows = sheet.querySelector('[data-sheet-rows]');
        const conditions = sheet.querySelector('[data-condition-options]').innerHTML;
        let audit = null;

        function openSheet(id) {
            ui.api('GET', root.dataset.resourceUrl + '/' + id).then(function (envelope) {
                audit = envelope.data;
                const locked = audit.status === 'completed';

                sheet.querySelector('[data-sheet-title]').textContent = 'Verification · ' + audit.location_name;
                sheet.querySelector('[data-sheet-subtitle]').textContent = day(audit.audit_date) + ' · ' + audit.items.length + ' asset(s)' + (locked ? ' · completed' : '');
                rows.innerHTML = audit.items.map(function (item) {
                    const found = item.found === true ? 'yes' : (item.found === false ? 'no' : '');

                    return '<tr data-item="' + item.id + '" data-tag="' + esc(String(item.asset_tag || '').toUpperCase()) + '"><td>' + titleCell(item.asset_tag + ' · ' + item.asset_name, item.holder) + '</td><td>' + esc(item.location || '—') + '</td>'
                        + '<td><select data-found' + (locked ? ' disabled' : '') + ' aria-label="Found"><option value="">Not checked</option><option value="yes"' + (found === 'yes' ? ' selected' : '') + '>Found</option><option value="no"' + (found === 'no' ? ' selected' : '') + '>Missing</option></select></td>'
                        + '<td><select data-condition' + (locked ? ' disabled' : '') + ' aria-label="Condition">' + conditions + '</select></td>'
                        + '<td><input type="text" data-remarks maxlength="255" value="' + esc(item.remarks || '') + '"' + (locked ? ' disabled' : '') + ' aria-label="Remarks"></td></tr>';
                }).join('') || '<tr><td colspan="5" class="dash-muted">No assets were expected here.</td></tr>';
                audit.items.forEach(function (item) {
                    const select = rows.querySelector('[data-item="' + item.id + '"] [data-condition]');

                    if (select && item.condition_id) {
                        select.value = String(item.condition_id);
                    }
                });
                sheet.querySelector('[data-sheet-actions]').hidden = locked;
                sheet.querySelector('[data-scan-bar]').hidden = locked;
                progress();

                if (!sheet.open) {
                    sheet.showModal();
                }
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The verification could not be opened.'), 'error'); });
        }

        function saveSheet(complete) {
            const items = Array.prototype.map.call(rows.querySelectorAll('[data-item]'), function (row) {
                const found = row.querySelector('[data-found]').value;

                return { id: Number(row.dataset.item), found: found === '' ? null : found === 'yes', condition_id: row.querySelector('[data-condition]').value ? Number(row.querySelector('[data-condition]').value) : null, remarks: row.querySelector('[data-remarks]').value.trim() || null };
            });

            ui.api('PUT', root.dataset.resourceUrl + '/' + audit.id, { items: items, complete: complete, mark_missing_lost: sheet.querySelector('[data-mark-lost]').checked }).then(function (envelope) {
                ui.toast(envelope.message);
                mounted.refresh();

                if (complete) {
                    sheet.close();
                }
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not save.'), 'error'); });
        }

        const mounted = kit.mount({
            root: root,
            modal: document.getElementById('auditModal'),
            order: [[0, 'desc']],
            titles: { create: 'Start verification', edit: 'Start verification' },
            emptyText: 'No verifications yet.',
            editable: false,
            deletable: false,
            nameOf: function (row) { return row.location_name; },
            onView: function (row) { openSheet(row.id); },
            afterSave: function (record) { openSheet(record.id); },
            columns: [
                { data: 'audit_date', render: display(function (value) { return esc(day(value)); }) },
                { data: 'location_name', orderable: false },
                { data: 'conductor_name', orderable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'items_count', searchable: false, render: display(function (value, row) { return esc(row.found_count + ' found · ' + row.missing_count + ' missing · ' + value + ' total'); }) },
                { data: 'status', render: display(function (value) { return badge(value); }) }
            ],
            fill: function (form) { kit.fillForm(form, null, SPEC, { audit_date: today }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });

        closeOnButtons(sheet);

        /* Walk the room: scan (or type) each tag; it is marked found and the counter moves on. */
        function progress() {
            const all = rows.querySelectorAll('[data-item]');
            const checked = Array.prototype.filter.call(all, function (row) { return row.querySelector('[data-found]').value !== ''; }).length;
            const missing = Array.prototype.filter.call(all, function (row) { return row.querySelector('[data-found]').value === 'no'; }).length;

            sheet.querySelector('[data-progress]').textContent = checked + ' of ' + all.length + ' checked' + (missing ? ' · ' + missing + ' missing' : '');
        }

        rows.addEventListener('change', progress);

        sheet.querySelector('[data-scan]').addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();

            const tag = event.target.value.trim().toUpperCase();
            const row = tag ? rows.querySelector('[data-tag="' + tag.replace(/"/g, '') + '"]') : null;

            event.target.value = '';

            if (!row) {
                ui.toast((tag || 'That tag') + ' is not expected here. Check the register for where it belongs.', 'error');

                return;
            }

            row.querySelector('[data-found]').value = 'yes';
            row.classList.remove('is-scanned');
            void row.offsetWidth;
            row.classList.add('is-scanned');
            row.scrollIntoView({ block: 'center', behavior: 'smooth' });
            progress();
        });

        sheet.querySelector('[data-mark-all]').addEventListener('click', function () {
            rows.querySelectorAll('[data-found]').forEach(function (select) {
                if (select.value === '') {
                    select.value = 'yes';
                }
            });
            progress();
        });
        sheet.querySelector('[data-sheet-save]').addEventListener('click', function () { saveSheet(false); });
        sheet.querySelector('[data-sheet-complete]').addEventListener('click', function () { saveSheet(true); });
    }

    /* ---------- Depreciation ---------- */

    function depreciation() {
        const panel = document.querySelector('[data-depreciation]');
        const month = panel.querySelector('[data-dep-month]');
        const result = panel.querySelector('[data-dep-result]');
        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[0, 'desc']],
            emptyText: 'No month posted yet.',
            exportName: 'depreciation',
            columns: [
                { data: 'period_month', render: display(function (value, row) { return esc(row.month_label); }) },
                { data: 'assets', searchable: false },
                { data: 'total', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'voucher_no', orderable: false, searchable: false, defaultContent: '—' }
            ]
        });

        function preview() {
            ui.api('GET', root.dataset.resourceUrl + '/preview?month=' + month.value).then(function (envelope) {
                const data = envelope.data;

                result.innerHTML = (data.already_posted ? '<p class="dash-badge dash-badge-warn">Some assets were already posted for ' + esc(data.month) + '.</p>' : '')
                    + (data.rows.length ? '<table class="dash-table"><thead><tr><th>Asset</th><th>Category</th><th>Book value</th><th>Charge</th><th>After</th></tr></thead><tbody>'
                        + data.rows.map(function (row) { return '<tr><td>' + esc(row.asset_tag + ' · ' + row.name) + '</td><td>' + esc(row.category) + '</td><td>' + esc(money(row.book_value)) + '</td><td>' + esc(money(row.amount)) + '</td><td>' + esc(money(row.book_value_after)) + '</td></tr>'; }).join('')
                        + '</tbody><tfoot><tr><th colspan="3">Total for ' + esc(data.month) + '</th><th>' + esc(money(data.total)) + '</th><th></th></tr></tfoot></table>'
                        : '<p class="dash-muted">Nothing to depreciate for ' + esc(data.month) + '.</p>');
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not preview.'), 'error'); });
        }

        panel.querySelector('[data-dep-preview]').addEventListener('click', preview);
        panel.querySelector('[data-dep-run]').addEventListener('click', function () {
            ui.api('POST', root.dataset.resourceUrl + '/run', { month: month.value }).then(function (envelope) {
                ui.toast(envelope.message);
                grid.ajax.reload();
                preview();
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not post.'), 'error'); });
        });
        panel.querySelector('[data-dep-reverse]').addEventListener('click', function () {
            const reason = window.prompt('Why reverse the depreciation of ' + month.value + '? (at least 10 characters, goes on the reversal voucher)');

            if (!reason) {
                return;
            }

            ui.api('POST', root.dataset.resourceUrl + '/reverse', { month: month.value, reason: reason }).then(function (envelope) {
                ui.toast(envelope.message);
                grid.ajax.reload();
                preview();
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not reverse.'), 'error'); });
        });
    }

    /* ---------- Disposals ---------- */

    function disposals() {
        window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[1, 'desc']],
            searchPlaceholder: 'Search asset…',
            emptyText: 'Nothing disposed.',
            exportName: 'asset-disposals',
            columns: [
                { data: 'asset_label', orderable: false },
                { data: 'disposed_on', render: display(function (value) { return esc(day(value)); }) },
                { data: 'method', render: display(function (value) { return esc(label(value)); }) },
                { data: 'book_value_at_disposal', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'sale_amount', searchable: false, render: display(function (value) { return esc(money(value)); }) },
                { data: 'gain_loss', orderable: false, searchable: false, render: display(function (value) { return '<span class="dash-badge ' + (value >= 0 ? 'dash-badge-ok' : 'dash-badge-danger') + '">' + esc((value >= 0 ? 'Gain ' : 'Loss ') + money(Math.abs(value))) + '</span>'; }) },
                { data: 'reason', className: 'dash-hide-md' },
                { data: 'voucher_no', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' }
            ]
        });
    }

    /* ---------- Categories and locations ---------- */

    function categories() {
        const SPEC = { name: 'text', code: 'text', parent_id: 'int', depreciation_method: 'text', useful_life_months: 'int', salvage_percent: 'number', asset_account_id: 'int', depreciation_account_id: 'int', accumulated_depreciation_account_id: 'int', is_active: 'bool' };

        kit.mount({
            root: root,
            modal: document.getElementById('categoryModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add category', edit: 'Edit category' },
            emptyText: 'No categories yet.',
            nameOf: function (row) { return row.name; },
            canDelete: function (row) { return row.assets_count > 0 ? 'Has assets' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, [row.code, row.parent_name].filter(Boolean).join(' · ')); }) },
                { data: 'asset_account_name', orderable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'depreciation_method', render: display(function (value, row) { return esc(value === 'none' ? 'None' : label(value) + ' · ' + row.useful_life_months + ' months · salvage ' + row.salvage_percent + '%'); }) },
                { data: 'assets_count', searchable: false },
                { data: 'is_active', render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { depreciation_method: 'straight_line', useful_life_months: 60, salvage_percent: 10, is_active: true }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });
    }

    function locations() {
        const SPEC = { name: 'text', code: 'text', building: 'text', floor: 'text', parent_id: 'int', in_charge_employee_id: 'int', is_store: 'bool', is_active: 'bool' };

        kit.mount({
            root: root,
            modal: document.getElementById('locationModal'),
            order: [[0, 'asc']],
            titles: { create: 'Add location', edit: 'Edit location' },
            emptyText: 'No locations yet.',
            nameOf: function (row) { return row.name; },
            canDelete: function (row) { return row.assets_count > 0 ? 'Assets are here' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, [row.code, row.parent_name].filter(Boolean).join(' · ')); }) },
                { data: 'building', className: 'dash-hide-md', render: display(function (value, row) { return esc([value, row.floor].filter(Boolean).join(' · ') || '—'); }) },
                { data: 'in_charge_name', orderable: false, defaultContent: '—' },
                { data: 'assets_count', searchable: false },
                { data: 'is_store', render: display(function (value) { return value ? '<span class="dash-badge dash-badge-brand">Store</span>' : 'Room'; }) },
                { data: 'is_active', render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (form, row) { kit.fillForm(form, row, SPEC, { is_active: true }); },
            payload: function (form) { return kit.readForm(form, SPEC); }
        });
    }

    ({ register: register, handovers: handovers, repairs: repairs, verification: verification, depreciation: depreciation, disposals: disposals, categories: categories, locations: locations }[screen] || function () {})();
})();

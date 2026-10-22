/**
 * ID cards: issued-card grid (select + print PDF, issue, lost / reissue) and the card template editor.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const TONES = { active: 'dash-badge-ok', lost: 'dash-badge-danger', revoked: 'dash-badge-danger', replaced: 'dash-badge-warn', expired: 'dash-badge-system' };

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function titleCell(title, subtitle) {
        return '<span class="dash-cell-title">' + esc(title) + '</span>' + (subtitle ? '<span class="dash-muted">' + esc(subtitle) + '</span>' : '');
    }

    /* ---------- Issued cards ---------- */

    const cardsRoot = document.querySelector('[data-cards]');

    if (cardsRoot) {
        const selected = new Set();
        const printButton = document.querySelector('[data-print-selected]');
        const filters = cardsRoot.querySelectorAll('[data-filter]');
        const issueDialog = document.getElementById('issueModal');
        const issueForm = issueDialog.querySelector('form');
        const statusDialog = document.getElementById('cardStatusModal');
        const statusForm = statusDialog.querySelector('form');
        let statusCard = null;

        const syncPrintButton = function () {
            printButton.disabled = selected.size === 0;
            printButton.lastChild.textContent = selected.size ? ' Print ' + selected.size + ' Selected' : ' Print Selected';
        };

        const grid = window.PinnacleGrid.create(cardsRoot.querySelector('[data-grid]'), {
            url: cardsRoot.dataset.datatableUrl,
            order: [[1, 'desc']],
            searchPlaceholder: 'Search card no, name or code…',
            emptyText: 'No cards issued yet.',
            params: function () {
                const values = {};

                filters.forEach(function (field) {
                    values[field.dataset.filter] = field.value;
                });

                return values;
            },
            columns: [
                { data: null, orderable: false, searchable: false, className: 'dash-col-check', render: display(function (value, row) {
                    return row.status === 'active' ? '<input type="checkbox" data-select="' + row.id + '"' + (selected.has(row.id) ? ' checked' : '') + ' aria-label="Select card ' + esc(row.card_no) + '">' : '';
                }) },
                { data: 'card_no', name: 'card_no', render: display(function (value, row) { return titleCell(row.holder_name || '—', value + (row.holder_code ? ' · ' + row.holder_code : '')); }) },
                { data: 'template_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—', render: display(function (value, row) { return titleCell(value || '—', row.print_count ? 'printed ' + row.print_count + '×' : 'not printed'); }) },
                { data: 'valid_until', searchable: false, className: 'dash-hide-md', render: display(function (value) { return value ? esc(dateFormat.format(new Date(value))) : '—'; }) },
                { data: 'status', searchable: false, render: display(function (value) { return '<span class="dash-badge ' + (TONES[value] || '') + '">' + esc(value.charAt(0).toUpperCase() + value.slice(1)) + '</span>'; }) },
                { data: null, orderable: false, searchable: false, className: 'dash-col-actions', render: display(function (value, row) {
                    return row.status === 'active'
                        ? '<div class="dash-actions"><button type="button" class="dash-action dash-action-edit" data-card-status data-tooltip="Lost / revoke" aria-label="Change status of ' + esc(row.card_no) + '"><svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></button></div>'
                        : '';
                }) }
            ]
        });

        filters.forEach(function (field) {
            field.addEventListener('change', function () {
                selected.clear();
                syncPrintButton();
                grid.ajax.reload();
            });
        });

        cardsRoot.querySelector('[data-grid]').addEventListener('change', function (event) {
            const box = event.target.closest('[data-select]');
            const all = event.target.closest('[data-check-all]');

            if (box) {
                box.checked ? selected.add(Number(box.dataset.select)) : selected.delete(Number(box.dataset.select));
            }

            if (all) {
                cardsRoot.querySelectorAll('[data-select]').forEach(function (other) {
                    other.checked = all.checked;
                    all.checked ? selected.add(Number(other.dataset.select)) : selected.delete(Number(other.dataset.select));
                });
            }

            syncPrintButton();
        });

        printButton.addEventListener('click', function () {
            const query = Array.from(selected).map(function (id) { return 'ids[]=' + id; }).join('&');

            ui.openFile(cardsRoot.dataset.printUrl + '?' + query, 'Generating ' + selected.size + ' ID card' + (selected.size === 1 ? '' : 's') + '…')
                .then(function () { grid.ajax.reload(null, false); })
                .catch(function () {});
        });

        /* Issue */
        kit.wireDialog(issueDialog);

        const syncIssueForm = function () {
            const type = issueForm.elements.holder_type.value;

            issueForm.querySelector('[data-student-only]').hidden = type !== 'student';
            Array.prototype.forEach.call(issueForm.elements.card_template_id.options, function (option) {
                option.hidden = option.value !== '' && option.dataset.cardType !== type;
            });
            issueForm.elements.card_template_id.value = '';
        };

        document.querySelector('[data-open-issue]').addEventListener('click', function () {
            issueForm.reset();
            kit.clearErrors(issueForm);
            syncIssueForm();
            issueDialog.showModal();
        });

        issueForm.elements.holder_type.addEventListener('change', syncIssueForm);

        issueForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = kit.readForm(issueForm, { holder_type: 'text', section_id: 'int', card_template_id: 'int', valid_until: 'text' });

            if (body.holder_type !== 'student') {
                delete body.section_id;
            }

            const hide = ui.busy('Issuing ID cards…');

            kit.submitForm(issueForm, 'POST', cardsRoot.dataset.resourceUrl + '/issue', body)
                .then(function () {
                    issueDialog.close();
                    grid.ajax.reload();
                })
                .catch(function () {})
                .finally(hide);
        });

        /* Lost / revoke / reissue */
        kit.wireDialog(statusDialog);

        cardsRoot.querySelector('[data-grid]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-card-status]');

            if (!button) {
                return;
            }

            statusCard = grid.row(button.closest('tr')).data();
            statusForm.reset();
            kit.clearErrors(statusForm);
            statusDialog.querySelector('[data-status-title]').textContent = statusCard.card_no + ' — ' + (statusCard.holder_name || '');
            statusDialog.showModal();
        });

        statusForm.elements.status.addEventListener('change', function () {
            statusForm.elements.reissue.closest('label').hidden = statusForm.elements.status.value !== 'lost';
        });

        statusForm.addEventListener('submit', function (event) {
            event.preventDefault();

            kit.submitForm(statusForm, 'POST', cardsRoot.dataset.resourceUrl + '/' + statusCard.id + '/status', kit.readForm(statusForm, { status: 'text', reason: 'text', reissue: 'bool' }))
                .then(function () {
                    statusDialog.close();
                    grid.ajax.reload(null, false);
                })
                .catch(function () {});
        });
    }

    /* ---------- Templates ---------- */

    const templatesRoot = document.querySelector('[data-card-templates]');

    if (templatesRoot) {
        const modal = document.getElementById('templateModal');
        const form = modal.querySelector('form');
        const SPEC = { name: 'text', card_type: 'text', size_key: 'text', primary_color: 'text', text_color: 'text', back_text: 'text', show_qr: 'bool', is_default: 'bool', is_active: 'bool' };

        // Only the sizes allowed for the chosen card type can be picked.
        const syncSizes = function () {
            const type = form.elements.card_type.value;
            let firstAllowed = null;

            Array.prototype.forEach.call(form.elements.size_key.options, function (option) {
                const allowed = option.dataset.types.split(' ').indexOf(type) !== -1;

                option.hidden = !allowed;
                option.disabled = !allowed;
                firstAllowed = firstAllowed || (allowed ? option.value : null);
            });

            if (form.elements.size_key.selectedOptions[0].disabled) {
                form.elements.size_key.value = firstAllowed;
            }
        };

        kit.mount({
            root: templatesRoot,
            modal: modal,
            order: [[0, 'asc']],
            titles: { create: 'New template', edit: 'Template settings' },
            emptyText: 'No card templates yet.',
            nameOf: function (row) { return row.name; },
            viewUrl: function (row) { return templatesRoot.dataset.designUrl + '/' + row.id + '/design'; },
            canDelete: function (row) { return row.is_system ? 'System template' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.card_type.replace('_', ' ') + (row.is_system ? ' · system' : '') + (row.is_default ? ' · default' : '')); }) },
                { data: 'width_mm', searchable: false, className: 'dash-hide-md', render: display(function (value, row) { return esc(value + ' × ' + row.height_mm + ' mm'); }) },
                { data: 'fields_count', searchable: false, className: 'dash-col-num' },
                { data: 'is_active', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>'; }) }
            ],
            fill: function (templateForm, row) {
                kit.fillForm(templateForm, row, SPEC, { card_type: 'invitation', size_key: 'a6_landscape', primary_color: '#1d2b45', text_color: '#1f2937', show_qr: false, is_active: true });
                templateForm.elements.card_type.disabled = Boolean(row && row.is_system);
                syncSizes();

                if (row) {
                    templateForm.elements.size_key.value = row.size_key;
                }
            },
            payload: function (templateForm) {
                const body = kit.readForm(templateForm, SPEC);

                body.card_type = templateForm.elements.card_type.value;

                return body;
            },
            afterSave: function (template) {
                if (template && !template.fields.length) {
                    window.location.assign(templatesRoot.dataset.designUrl + '/' + template.id + '/design');
                }
            }
        });

        form.elements.card_type.addEventListener('change', syncSizes);
    }
})();

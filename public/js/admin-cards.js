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
                { data: 'issued_on', searchable: false, className: 'dash-hide-md', render: display(function (value, row) {
                    return titleCell(dateFormat.format(new Date(value)) + (row.issued_by_name ? ' · ' + row.issued_by_name : ''), (row.template_name || '') + ' · ' + (row.print_count ? 'printed ' + row.print_count + '×' : 'not printed yet'));
                }) },
                { data: 'valid_until', searchable: false, className: 'dash-hide-md', render: display(function (value) { return value ? esc(dateFormat.format(new Date(value))) : '—'; }) },
                { data: 'status', searchable: false, render: display(function (value) { return '<span class="dash-badge ' + (TONES[value] || '') + '">' + esc(value.charAt(0).toUpperCase() + value.slice(1)) + '</span>'; }) },
                { data: null, orderable: false, searchable: false, className: 'dash-col-actions', render: display(function (value, row) {
                    return row.status === 'active'
                        ? '<div class="dash-actions"><button type="button" class="dash-action dash-action-view" data-print-one data-tooltip="Print / reprint" aria-label="Print ' + esc(row.card_no) + '"><svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></button><button type="button" class="dash-action dash-action-view" data-card-status data-renew data-tooltip="Renew card" aria-label="Renew ' + esc(row.card_no) + '"><svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg></button><button type="button" class="dash-action dash-action-edit" data-card-status data-tooltip="Lost / revoke" aria-label="Change status of ' + esc(row.card_no) + '"><svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></button></div>'
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

        const personSearch = issueForm.querySelector('[data-person-search]');
        const personResults = issueForm.querySelector('[data-person-results]');
        const personChips = issueForm.querySelector('[data-person-chips]');
        let chosenPeople = {};
        let searchTimer = null;

        const renderChips = function () {
            personChips.innerHTML = Object.keys(chosenPeople).map(function (id) {
                return '<span class="dash-chip">' + esc(chosenPeople[id]) + ' <button type="button" data-remove-person="' + id + '" aria-label="Remove">×</button></span>';
            }).join('');
        };

        // Whole school / staff, one section / department, or particular people picked by name.
        const syncScope = function () {
            const type = issueForm.elements.holder_type.value;
            const scope = issueForm.elements.scope.value;

            Array.prototype.forEach.call(issueForm.elements.scope.options, function (option) {
                option.textContent = option.dataset[type];
            });
            issueForm.querySelector('[data-scope-section]').hidden = type !== 'student';
            issueForm.querySelector('[data-scope-department]').hidden = type !== 'employee';
            issueForm.querySelector('[data-scope-people]').hidden = scope !== 'people';
        };

        const syncIssueForm = function () {
            const type = issueForm.elements.holder_type.value;

            chosenPeople = {};
            renderChips();
            personSearch.value = '';
            personResults.hidden = true;
            syncScope();
            Array.prototype.forEach.call(issueForm.elements.card_template_id.options, function (option) {
                option.hidden = option.value !== '' && option.dataset.cardType !== type;
            });
            issueForm.elements.card_template_id.value = '';
        };

        const issueResult = issueForm.querySelector('[data-issue-result]');
        const issueSubmit = issueForm.querySelector('[data-issue-submit]');
        const issuePrint = issueForm.querySelector('[data-issue-print]');
        let newCardIds = [];

        document.querySelector('[data-open-issue]').addEventListener('click', function () {
            issueForm.reset();
            kit.clearErrors(issueForm);
            syncIssueForm();
            issueResult.hidden = true;
            issueSubmit.hidden = false;
            issuePrint.hidden = true;
            issueDialog.showModal();
        });

        // After issuing, the popup lists who got which card and who already had one, so nothing is "lost".
        const showIssueResult = function (envelope) {
            const list = function (items, empty) {
                return items.length ? items.map(function (item) { return '<li><span>' + esc(item.name || '—') + '</span><strong>' + esc(item.card_no) + '</strong></li>'; }).join('') : '<li><span class="dash-muted">' + empty + '</span><strong></strong></li>';
            };

            newCardIds = envelope.data.issued_cards.map(function (card) { return card.id; });
            issueForm.querySelector('[data-issue-summary]').textContent = envelope.message;
            issueForm.querySelector('[data-issued-list]').innerHTML = list(envelope.data.issued_cards, 'None');
            issueForm.querySelector('[data-skipped-list]').innerHTML = list(envelope.data.skipped_holders, 'None');
            issueResult.hidden = false;
            issueSubmit.hidden = true;
            issuePrint.hidden = newCardIds.length === 0;
        };

        issuePrint.addEventListener('click', function () {
            ui.openFile(cardsRoot.dataset.printUrl + '?' + newCardIds.map(function (id) { return 'ids[]=' + id; }).join('&'), 'Generating ' + newCardIds.length + ' ID card' + (newCardIds.length === 1 ? '' : 's') + '…')
                .then(function () {
                    issueDialog.close();
                    grid.ajax.reload(null, false);
                })
                .catch(function () {});
        });

        issueForm.elements.holder_type.addEventListener('change', syncIssueForm);
        issueForm.elements.scope.addEventListener('change', syncScope);

        personSearch.addEventListener('input', function () {
            clearTimeout(searchTimer);

            if (personSearch.value.trim().length < 2) {
                personResults.hidden = true;

                return;
            }

            searchTimer = setTimeout(function () {
                const type = issueForm.elements.holder_type.value;
                const group = type === 'student' ? issueForm.elements.section_id.value : issueForm.elements.department_id.value;

                ui.api('GET', cardsRoot.dataset.resourceUrl + '/holders?holder_type=' + type + '&q=' + encodeURIComponent(personSearch.value.trim()) + (group ? '&' + (type === 'student' ? 'section_id' : 'department_id') + '=' + group : ''))
                    .then(function (envelope) {
                        personResults.innerHTML = envelope.data.length
                            ? envelope.data.map(function (person) { return '<li><button type="button" data-pick-person="' + person.id + '" data-name="' + esc(person.name) + '">' + esc(person.name) + ' <span class="dash-muted">' + esc(person.code || '') + '</span></button></li>'; }).join('')
                            : '<li class="dash-muted">No match</li>';
                        personResults.hidden = false;
                    })
                    .catch(function () {});
            }, 250);
        });

        personResults.addEventListener('click', function (event) {
            const pick = event.target.closest('[data-pick-person]');

            if (pick) {
                chosenPeople[pick.dataset.pickPerson] = pick.dataset.name;
                renderChips();
                personSearch.value = '';
                personResults.hidden = true;
                personSearch.focus();
            }
        });

        personChips.addEventListener('click', function (event) {
            const remove = event.target.closest('[data-remove-person]');

            if (remove) {
                delete chosenPeople[remove.dataset.removePerson];
                renderChips();
            }
        });

        issueForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = kit.readForm(issueForm, { holder_type: 'text', section_id: 'int', department_id: 'int', card_template_id: 'int', valid_until: 'text' });
            const scope = issueForm.elements.scope.value;

            // Particular people: only the picked ids are sent; the section / department only narrowed the search.
            if (body.holder_type !== 'student' || scope === 'people') {
                delete body.section_id;
            }

            if (body.holder_type !== 'employee' || scope === 'people') {
                delete body.department_id;
            }

            if (scope === 'people') {
                body.holder_ids = Object.keys(chosenPeople).map(Number);

                if (!body.holder_ids.length) {
                    kit.clearErrors(issueForm);
                    kit.showErrors(issueForm, { errors: { fields: { holder_ids: ['Pick at least one person.'] } } });

                    return;
                }
            }

            const hide = ui.busy('Issuing ID cards…');

            kit.submitForm(issueForm, 'POST', cardsRoot.dataset.resourceUrl + '/issue', body)
                .then(function (envelope) {
                    showIssueResult(envelope);
                    grid.ajax.reload();
                })
                .catch(function () {})
                .finally(hide);
        });

        /* Lost / revoke / reissue */
        kit.wireDialog(statusDialog);

        cardsRoot.querySelector('[data-grid]').addEventListener('click', function (event) {
            const printOne = event.target.closest('[data-print-one]');
            const button = event.target.closest('[data-card-status]');

            if (printOne) {
                const row = grid.row(printOne.closest('tr')).data();

                ui.openFile(cardsRoot.dataset.printUrl + '?ids[]=' + row.id, 'Generating card ' + row.card_no + '…')
                    .then(function () { grid.ajax.reload(null, false); })
                    .catch(function () {});

                return;
            }

            if (!button) {
                return;
            }

            statusCard = grid.row(button.closest('tr')).data();
            statusForm.reset();
            kit.clearErrors(statusForm);
            statusDialog.querySelector('[data-status-title]').textContent = (button.hasAttribute('data-renew') ? 'Renew ' : '') + statusCard.card_no + ' — ' + (statusCard.holder_name || '');

            if (button.hasAttribute('data-renew')) {
                statusForm.elements.status.value = 'expired';
                statusForm.elements.reissue.checked = true;
            }

            statusForm.elements.reissue.closest('label').hidden = ['lost', 'expired'].indexOf(statusForm.elements.status.value) === -1;
            statusDialog.showModal();
        });

        statusForm.elements.status.addEventListener('change', function () {
            statusForm.elements.reissue.closest('label').hidden = ['lost', 'expired'].indexOf(statusForm.elements.status.value) === -1;
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
        const SPEC = { name: 'text', card_type: 'text', size_key: 'text', theme: 'text', primary_color: 'text', text_color: 'text', back_text: 'text', show_qr: 'bool', is_default: 'bool', is_active: 'bool' };

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
                kit.fillForm(templateForm, row, SPEC, { card_type: 'invitation', size_key: 'a6_landscape', theme: 'classic', primary_color: '#1d2b45', text_color: '#1f2937', show_qr: false, is_active: true });
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

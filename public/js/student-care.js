/**
 * Student profile → Health, discipline & achievements: tabbed lists with add / edit / delete in one popup,
 * and bonafide / character certificates (issue, then print).
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-care]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const dialog = document.getElementById('careModal');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const LABELS = { medical: 'health record', discipline: 'discipline note', achievements: 'achievement', certificates: 'certificate' };
    const FIELDS = {
        medical: { condition_id: 'int', checked_on: 'text', height_cm: 'text', weight_kg: 'text', vision: 'text', doctor_name: 'text', doctor_phone: 'text', allergies: 'text', medications: 'text', notes: 'text' },
        discipline: { incident_date: 'text', category_id: 'int', description: 'text', action_taken: 'text', notify_parents: 'bool' },
        achievements: { title: 'text', category_id: 'int', level: 'text', achieved_on: 'text', description: 'text' },
        certificates: { certificate_type: 'text', issued_on: 'text', conduct_id: 'int', purpose: 'text', remarks: 'text' }
    };
    let tab = 'medical';
    let records = null;
    let editing = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function date(value) {
        return value ? dateFormat.format(new Date(String(value).slice(0, 10) + 'T00:00:00')) : '';
    }

    function today() {
        return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    }

    function describe(type, row) {
        if (type === 'medical') {
            return [row.condition ? row.condition.name : 'Health check', [row.height_cm ? row.height_cm + ' cm' : null, row.weight_kg ? row.weight_kg + ' kg' : null, row.vision ? 'vision ' + row.vision : null, row.allergies ? 'allergies: ' + row.allergies : null, row.medications ? 'medicines: ' + row.medications : null].filter(Boolean).join(' · '), date(row.checked_on)];
        }

        if (type === 'discipline') {
            return [(row.category ? row.category.name : 'Incident') + ' — ' + row.description, [row.action_taken ? 'Action: ' + row.action_taken : null, row.parent_informed ? 'Parents told ' + date(row.parent_informed_on) : 'Parents not told'].filter(Boolean).join(' · '), date(row.incident_date)];
        }

        if (type === 'achievements') {
            return [row.title, [row.category ? row.category.name : null, row.level ? row.level.charAt(0).toUpperCase() + row.level.slice(1) + ' level' : null, row.description].filter(Boolean).join(' · '), date(row.achieved_on)];
        }

        return [row.certificate_no + ' — ' + (row.certificate_type === 'bonafide' ? 'Bonafide' : 'Character'), [row.purpose ? 'For ' + row.purpose : null, row.issuer ? 'by ' + row.issuer.name : null].filter(Boolean).join(' · '), date(row.issued_on)];
    }

    function render() {
        Object.keys(LABELS).forEach(function (key) {
            root.querySelector('[data-care-count="' + key + '"]').textContent = records[key].length ? '(' + records[key].length + ')' : '';
        });

        root.querySelector('[data-care-add-label]').textContent = tab === 'certificates' ? 'Issue certificate' : 'Add ' + LABELS[tab];
        root.querySelector('[data-care-list]').innerHTML = records[tab].length ? records[tab].map(function (row) {
            const parts = describe(tab, row);
            const actions = tab === 'certificates'
                ? '<button type="button" class="dash-link-btn" data-print="' + row.id + '">Print</button>'
                : '<button type="button" class="dash-link-btn" data-edit="' + row.id + '">Edit</button> <button type="button" class="dash-link-btn" data-delete="' + row.id + '">Delete</button>';

            return '<li><div><strong>' + esc(parts[0]) + '</strong>' + (parts[1] ? '<span class="dash-muted">' + esc(parts[1]) + '</span>' : '') + '</div><div class="dash-care-row-end"><span class="dash-muted">' + esc(parts[2]) + '</span>' + actions + '</div></li>';
        }).join('') : '<li><span class="dash-muted">Nothing recorded yet.</span></li>';
    }

    function load() {
        return ui.api('GET', root.dataset.url).then(function (envelope) {
            records = envelope.data;
            render();
        }).catch(function () {
            root.querySelector('[data-care-list]').innerHTML = '<li><span class="dash-muted">Could not load the records.</span></li>';
        });
    }

    function open(row) {
        editing = row;
        dialog.querySelectorAll('[data-care-form]').forEach(function (form) {
            form.hidden = form.dataset.careForm !== tab;
        });

        const form = dialog.querySelector('[data-care-form="' + tab + '"]');
        form.reset();
        kit.clearErrors(form);
        kit.fillForm(form, row || {}, FIELDS[tab], {});

        ['checked_on', 'incident_date', 'achieved_on', 'issued_on'].forEach(function (name) {
            if (form.elements[name]) {
                form.elements[name].value = row && row[name] ? String(row[name]).slice(0, 10) : (name === 'checked_on' || name === 'achieved_on' ? '' : today());
            }
        });

        if (form.elements.notify_parents) {
            form.elements.notify_parents.checked = !row;
            form.elements.notify_parents.closest('label').hidden = Boolean(row);
        }

        dialog.querySelector('[data-care-title]').textContent = tab === 'certificates' ? 'Issue a certificate' : (row ? 'Edit ' : 'Add ') + LABELS[tab];
        dialog.showModal();
    }

    root.querySelectorAll('[data-care-tab]').forEach(function (button) {
        button.addEventListener('click', function () {
            tab = button.dataset.careTab;
            root.querySelectorAll('[data-care-tab]').forEach(function (other) {
                other.classList.toggle('is-active', other === button);
                other.setAttribute('aria-selected', other === button ? 'true' : 'false');
            });
            render();
        });
    });

    root.querySelector('[data-care-add]').addEventListener('click', function () { open(null); });

    root.querySelector('[data-care-list]').addEventListener('click', function (event) {
        const edit = event.target.closest('[data-edit]');
        const remove = event.target.closest('[data-delete]');
        const print = event.target.closest('[data-print]');

        if (edit) {
            open(records[tab].find(function (row) { return String(row.id) === edit.dataset.edit; }));
        }

        if (remove) {
            ui.confirm(LABELS[tab]).then(function (yes) {
                if (!yes) {
                    return;
                }

                ui.api('DELETE', root.dataset.recordUrl + '/' + tab + '/' + remove.dataset.delete).then(function (envelope) {
                    ui.toast(envelope.message);
                    load();
                }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not delete.', 'error'); });
            });
        }

        if (print) {
            ui.openFile(root.dataset.certificatePdfUrl + '/' + print.dataset.print + '/pdf', 'Preparing the certificate…').catch(function () {});
        }
    });

    kit.wireDialog(dialog);

    dialog.querySelectorAll('[data-care-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const type = form.dataset.careForm;
            const body = kit.readForm(form, FIELDS[type]);
            const request = type === 'certificates'
                ? kit.submitForm(form, 'POST', root.dataset.certificateUrl, body)
                : (editing ? kit.submitForm(form, 'PUT', root.dataset.recordUrl + '/' + type + '/' + editing.id, body) : kit.submitForm(form, 'POST', root.dataset.storeUrl + '/' + type, body));

            request.then(function (envelope) {
                dialog.close();
                load();

                if (type === 'certificates' && envelope.data.pdf_url) {
                    ui.openFile(envelope.data.pdf_url, 'Preparing the certificate…').catch(function () {});
                }
            }).catch(function () {});
        });
    });

    load();
})();

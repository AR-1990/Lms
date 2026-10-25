/**
 * Data Import: one card per import type (template download + file pick + check), then the row-by-row
 * result; "Import all rows" sends the same file again with save=1 once the check is clean.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-imports]');

    if (!root || !window.PinnacleDashboard) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const result = document.querySelector('[data-import-result]');
    const saveButton = result.querySelector('[data-import-save]');
    let pending = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function send(type, file, save) {
        const body = new window.FormData();
        body.append('file', file);
        body.append('save', save ? '1' : '0');

        return ui.api('POST', root.dataset.storeUrl + '/' + type, body);
    }

    function show(title, text, rows) {
        result.hidden = false;
        result.querySelector('[data-result-title]').textContent = title;
        result.querySelector('[data-result-text]').textContent = text;
        result.querySelector('[data-result-table]').hidden = !rows.length;
        result.querySelector('[data-result-rows]').innerHTML = rows.map(function (row) {
            return '<tr><td><strong>' + esc(row.row) + '</strong></td><td>' + row.messages.map(esc).join('<br>') + '</td></tr>';
        }).join('');
        result.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }

    function check(type, label, file) {
        const hide = ui.busy('Checking ' + file.name + '…');
        saveButton.hidden = true;
        pending = null;

        send(type, file, false).then(function (envelope) {
            pending = { type: type, file: file, label: label, rows: envelope.data.rows };
            show(label + ': ready', envelope.message + ' Press "Import all rows" to save them.', []);
            saveButton.hidden = false;
            saveButton.lastChild.textContent = ' Import ' + envelope.data.rows + ' row(s)';
        }).catch(function (envelope) {
            const rows = envelope && envelope.data && envelope.data.errors ? envelope.data.errors : [];
            show(label + ': not ready', (envelope && envelope.message) || 'The file could not be read.', rows);
        }).finally(hide);
    }

    saveButton.addEventListener('click', function () {
        if (!pending) {
            return;
        }

        const hide = ui.busy('Importing ' + pending.rows + ' row(s)…');
        saveButton.disabled = true;

        send(pending.type, pending.file, true).then(function (envelope) {
            ui.toast(envelope.message);
            show(pending.label + ': done', envelope.message, []);
            saveButton.hidden = true;
            pending = null;
        }).catch(function (envelope) {
            const rows = envelope && envelope.data && envelope.data.errors ? envelope.data.errors : [];
            show(pending.label + ': not imported', (envelope && envelope.message) || 'Nothing was imported.', rows);
        }).finally(function () {
            saveButton.disabled = false;
            hide();
        });
    });

    ui.api('GET', root.dataset.url).then(function (envelope) {
        root.innerHTML = envelope.data.types.map(function (type) {
            const required = type.columns.filter(function (column) { return column.slice(-1) === '*'; }).map(function (column) { return column.slice(0, -1); });

            return '<section class="dash-panel dash-import-card" data-type="' + esc(type.type) + '" data-label="' + esc(type.label) + '">'
                + '<div class="dash-panel-head"><div><h2>' + esc(type.label) + '</h2><p>' + type.columns.length + ' columns · required: ' + esc(required.join(', ')) + '</p></div></div>'
                + '<div class="dash-import-actions">'
                + '<a class="dash-btn dash-btn-ghost" href="' + esc(type.template_url) + '" download>Download template</a>'
                + '<label class="dash-btn dash-btn-primary dash-import-pick"><input type="file" accept=".csv,text/csv" data-file hidden>Choose CSV &amp; check</label>'
                + '</div></section>';
        }).join('') || '<p class="dash-muted">You do not have permission to import anything.</p>';
    }).catch(function () {
        root.innerHTML = '<p class="dash-muted">Could not load the import types.</p>';
    });


    root.addEventListener('change', function (event) {
        if (!event.target.matches('[data-file]') || !event.target.files.length) {
            return;
        }

        const card = event.target.closest('[data-type]');
        check(card.dataset.type, card.dataset.label, event.target.files[0]);
        event.target.value = '';
    });
})();

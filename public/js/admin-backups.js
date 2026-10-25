/**
 * Settings → Backups: the kept backups (newest first) with downloads, and "Back up now".
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-backups]');

    if (!root || !window.PinnacleDashboard) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const list = root.querySelector('[data-backup-list]');
    const button = root.querySelector('[data-backup-now]');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    function size(bytes) {
        return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    function load() {
        ui.api('GET', root.dataset.url).then(function (envelope) {
            list.innerHTML = envelope.data.backups.length ? envelope.data.backups.map(function (backup) {
                const url = root.dataset.downloadUrl + '/' + encodeURIComponent(backup.file);
                const when = dateFormat.format(new Date(backup.created_at));

                return '<li><div><strong>' + when + '</strong><span class="dash-muted">' + size(backup.size) + '</span></div><a class="dash-link-btn" href="' + url + '" download>Download</a></li>';
            }).join('') : '<li><span class="dash-muted">No backup yet. The first one runs tonight, or press "Back up now".</span></li>';
        }).catch(function () {
            list.innerHTML = '<li><span class="dash-muted">Could not load the backups.</span></li>';
        });
    }

    button.addEventListener('click', function () {
        const hide = ui.busy('Backing up the database…');
        button.disabled = true;

        ui.api('POST', root.dataset.url).then(function (envelope) {
            ui.toast(envelope.message);
            load();
        }).catch(function (envelope) {
            ui.toast((envelope && envelope.message) || 'The backup failed.', 'error');
        }).finally(function () {
            button.disabled = false;
            hide();
        });
    });

    load();
})();

/**
 * Academics → Substitutions: for a date, each away teacher's periods with the free teachers to cover them.
 * Runs on /api/erp/substitutions (the same endpoints serve the mobile app).
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-substitutions]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui) {
        return;
    }

    const dateInput = document.querySelector('[data-subs-date]');
    const list = root.querySelector('[data-subs-list]');
    const empty = root.querySelector('[data-subs-empty]');

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function render(data) {
        empty.hidden = data.teachers.length > 0;
        list.innerHTML = data.teachers.map(function (teacher) {
            return '<section class="dash-panel"><div class="dash-panel-head"><div><h2>' + esc(teacher.name) + '</h2><p>' + esc(teacher.reason) + ' · ' + esc(data.day) + '</p></div></div>'
                + '<div class="dash-table-wrap"><table class="dash-table"><thead><tr><th>Period</th><th>Class</th><th>Cover</th></tr></thead><tbody>'
                + teacher.periods.map(function (period) {
                    const cover = period.cover
                        ? '<span class="dash-badge dash-badge-ok">' + esc(period.cover.name) + '</span> <button type="button" class="dash-link-btn" data-remove="' + period.cover.id + '">Remove</button>'
                        : (period.free_teachers.length
                            ? '<div class="dash-subs-pick"><select data-teacher aria-label="Cover teacher">' + period.free_teachers.map(function (free) { return '<option value="' + free.id + '">' + esc(free.name) + ' (' + free.periods_today + ' periods today)</option>'; }).join('') + '</select><button type="button" class="dash-btn dash-btn-primary" data-assign="' + period.timetable_slot_id + '">Assign</button></div>'
                            : '<span class="dash-badge dash-badge-danger">No teacher is free</span>');

                    return '<tr><td><span class="dash-cell-title">' + esc(period.period) + '</span><span class="dash-cell-sub">' + esc(period.starts_at || '') + '</span></td><td>' + esc(period.class) + '<span class="dash-cell-sub">' + esc(period.subject || '') + '</span></td><td>' + cover + '</td></tr>';
                }).join('') + '</tbody></table></div></section>';
        }).join('');
    }

    function load() {
        ui.api('GET', root.dataset.url + '?date=' + dateInput.value).then(function (envelope) { render(envelope.data); })
            .catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not load the day.', 'error'); });
    }

    list.addEventListener('click', function (event) {
        const assign = event.target.closest('[data-assign]');
        const remove = event.target.closest('[data-remove]');

        if (assign) {
            assign.disabled = true;
            ui.api('POST', root.dataset.saveUrl, { date: dateInput.value, timetable_slot_id: Number(assign.dataset.assign), substitute_teacher_id: Number(assign.parentNode.querySelector('[data-teacher]').value) })
                .then(function (envelope) { ui.toast(envelope.message); load(); })
                .catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not assign.', 'error'); assign.disabled = false; });
        }

        if (remove) {
            ui.api('DELETE', root.dataset.deleteUrl + '/' + remove.dataset.remove)
                .then(function (envelope) { ui.toast(envelope.message); load(); })
                .catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not remove.', 'error'); });
        }
    });

    dateInput.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    dateInput.addEventListener('change', load);
    load();
})();

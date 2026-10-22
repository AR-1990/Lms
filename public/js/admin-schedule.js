/**
 * Timetable grid (click a cell to set subject / teacher / room) and the school calendar.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!ui || !kit) {
        return;
    }

    const escape = window.PinnacleGrid ? window.PinnacleGrid.escape : function (value) { return String(value); };

    /* ---------- Timetable ---------- */

    const timetable = document.querySelector('[data-timetable]');

    if (timetable) {
        const sectionSelect = timetable.querySelector('[data-section]');
        const dialog = document.getElementById('slotModal');
        const form = dialog.querySelector('form');
        const DAYS = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        let slots = [];
        let cell = null;

        const key = function (day, period) { return day + ':' + period; };

        const render = function () {
            const byCell = {};

            slots.forEach(function (slot) { byCell[key(slot.day_of_week, slot.period_id)] = slot; });

            timetable.querySelectorAll('[data-slot]').forEach(function (button) {
                const slot = byCell[key(button.dataset.day, button.dataset.period)];

                button.classList.toggle('is-filled', Boolean(slot && slot.subject_id));
                button.innerHTML = slot && slot.subject_id
                    ? '<strong>' + escape(slot.subject) + '</strong>' + escape(slot.teacher || 'No teacher') + (slot.room ? ' · ' + escape(slot.room) : '')
                    : '—';
                button.dataset.slotId = slot ? slot.id : '';
            });
        };

        const load = function () {
            if (!sectionSelect.value) {
                return;
            }

            ui.api('GET', timetable.dataset.sectionUrl + '/' + sectionSelect.value).then(function (envelope) {
                slots = envelope.data;
                render();
            });
        };

        kit.wireDialog(dialog);

        timetable.addEventListener('click', function (event) {
            const button = event.target.closest('[data-slot]');

            if (!button) {
                return;
            }

            cell = button;

            const slot = slots.find(function (item) { return String(item.id) === button.dataset.slotId; });
            const classId = sectionSelect.selectedOptions[0].dataset.classId;
            const periodName = button.closest('tr').querySelector('.dash-cell-title').textContent;

            form.reset();
            kit.clearErrors(form);
            Array.prototype.forEach.call(form.elements.subject_id.options, function (option) {
                option.hidden = option.value !== '' && option.dataset.classId !== classId;
            });
            form.elements.subject_id.value = slot && slot.subject_id ? slot.subject_id : '';
            form.elements.teacher_id.value = slot && slot.teacher_id ? slot.teacher_id : '';
            form.elements.room.value = slot && slot.room ? slot.room : '';
            dialog.querySelector('[data-slot-title]').textContent = DAYS[button.dataset.day] + ' · ' + periodName;
            dialog.querySelector('[data-clear-slot]').hidden = !slot;
            dialog.showModal();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            kit.submitForm(form, 'POST', timetable.dataset.slotsUrl, Object.assign(kit.readForm(form, { subject_id: 'int', teacher_id: 'int', room: 'text' }), {
                section_id: Number(sectionSelect.value),
                day_of_week: Number(cell.dataset.day),
                period_id: Number(cell.dataset.period)
            })).then(function () {
                dialog.close();
                load();
            }).catch(function () {});
        });

        dialog.querySelector('[data-clear-slot]').addEventListener('click', function () {
            dialog.close();
            ui.eraseRecord(dialog.querySelector('[data-slot-title]').textContent, cell, timetable.dataset.slotsUrl + '/' + cell.dataset.slotId).then(function (result) {
                if (result === 'deleted') {
                    cell.classList.remove('is-erasing');
                    load();
                }
            });
        });

        sectionSelect.addEventListener('change', load);
        load();
    }

    /* ---------- Calendar ---------- */

    const calendar = document.querySelector('[data-calendar]');

    if (calendar && window.PinnacleGrid) {
        const SPEC = { title: 'text', event_type_id: 'int', venue: 'text', starts_on: 'text', ends_on: 'text', start_time: 'text', end_time: 'text', description: 'text', is_holiday: 'bool', is_public: 'bool' };
        const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        const filters = calendar.querySelectorAll('[data-filter]');

        const mounted = kit.mount({
            root: calendar,
            modal: document.getElementById('calendarModal'),
            order: [[1, 'asc']],
            titles: { create: 'Add event', edit: 'Edit event' },
            searchPlaceholder: 'Search events…',
            emptyText: 'Nothing on the calendar.',
            params: function () {
                const values = {};

                filters.forEach(function (field) { values[field.dataset.filter] = field.value; });

                return values;
            },
            nameOf: function (row) { return row.title; },
            columns: [
                { data: 'title', render: function (value, type, row) { return type === 'display' ? '<span class="dash-cell-title">' + escape(value) + '</span><span class="dash-muted">' + escape([row.event_type_name, row.venue].filter(Boolean).join(' · ')) + '</span>' : value; } },
                { data: 'starts_on', searchable: false, render: function (value, type, row) { return type === 'display' ? escape(dateFormat.format(new Date(value)) + (row.ends_on !== value ? ' – ' + dateFormat.format(new Date(row.ends_on)) : '')) : value; } },
                { data: 'is_holiday', searchable: false, className: 'dash-hide-md', render: function (value, type, row) {
                    return type === 'display' ? (value ? '<span class="dash-badge dash-badge-warn">Holiday</span> ' : '') + (row.is_public ? '<span class="dash-badge dash-badge-ok">Website</span>' : '') : value;
                } }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row ? Object.assign({}, row, { start_time: row.start_time ? row.start_time.slice(0, 5) : '', end_time: row.end_time ? row.end_time.slice(0, 5) : '' }) : null, SPEC);
            },
            payload: function (form) {
                return kit.readForm(form, SPEC);
            }
        });

        filters.forEach(function (field) {
            field.addEventListener('change', function () { mounted.grid.ajax.reload(); });
        });
    }
})();

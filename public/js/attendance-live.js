/**
 * Attendance → Live Today: polls /api/erp/attendance/live every 15 seconds (paused while the tab is hidden)
 * and shows today's student and staff counts, each class, and the latest arrivals. Manual marking stays on
 * the register pages, linked from each class.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-live-board]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui) {
        return;
    }

    const SOURCES = { kiosk: 'Gate kiosk', qr: 'QR scan', biometric: 'Biometric', manual: 'Register', app: 'App' };
    const TONES = { present: 'dash-badge-ok', late: 'dash-badge-warn', half_day: 'dash-badge-warn', absent: 'dash-badge-danger', leave: 'dash-badge-brand' };
    const INTERVAL = 15000;
    let timer = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function setCounts(selector, values) {
        root.querySelectorAll('[' + selector + ']').forEach(function (element) {
            element.textContent = values[element.getAttribute(selector)] || 0;
        });
    }

    function render(data) {
        const holiday = root.querySelector('[data-live-holiday]');
        holiday.hidden = !data.holiday;
        holiday.textContent = data.holiday ? 'Today is a holiday: ' + data.holiday : '';

        if (data.students) {
            root.querySelector('[data-live-students]').hidden = false;
            root.querySelector('[data-live-sections-panel]').hidden = false;
            setCounts('data-count', data.students.totals);
            root.querySelector('[data-count-of="total"]').textContent = 'of ' + data.students.totals.total;
            root.querySelector('[data-live-sections]').innerHTML = data.students.sections.map(function (section) {
                const done = section.unmarked === 0;

                return '<tr><td><span class="dash-cell-title">' + esc(section.name) + '</span><span class="dash-live-bar" aria-hidden="true"><span style="transform:scaleX(' + (section.total ? section.present / section.total : 0) + ')"></span></span></td>'
                    + '<td class="dash-col-num">' + section.present + ' / ' + section.total + (section.late ? ' <span class="dash-muted">(' + section.late + ' late)</span>' : '') + '</td>'
                    + '<td class="dash-col-num">' + section.absent + '</td>'
                    + '<td class="dash-col-num">' + (done ? '<span class="dash-badge dash-badge-ok">Done</span>' : section.unmarked) + '</td>'
                    + '<td class="dash-col-actions"><a class="dash-link-btn" href="' + root.dataset.registerUrl + '?section=' + section.section_id + '">' + (done ? 'Open' : 'Mark') + '</a></td></tr>';
            }).join('') || '<tr><td colspan="5" class="dash-muted">No classes with students.</td></tr>';
        }

        if (data.staff) {
            root.querySelector('[data-live-staff]').hidden = false;
            setCounts('data-staff', data.staff);
            root.querySelector('[data-staff-of="total"]').textContent = 'of ' + data.staff.total;
            const link = root.querySelector('[data-staff-link]');
            link.hidden = false;
            link.href = root.dataset.staffUrl;
        }

        root.querySelector('[data-live-feed]').innerHTML = data.recent.length ? data.recent.map(function (event) {
            const action = event.action === 'checked_out' ? 'out' : 'in';

            return '<li><div><strong>' + esc(event.name) + '</strong><span class="dash-muted">' + esc([event.type === 'staff' ? 'Staff' : null, event.detail, SOURCES[event.source] || event.source].filter(Boolean).join(' · ')) + '</span></div>'
                + '<span><span class="dash-badge ' + (TONES[event.status] || 'dash-badge-system') + '">' + esc(action === 'out' ? 'Out' : (event.status === 'late' ? 'Late' : 'In')) + '</span> <span class="dash-muted">' + esc(event.time) + '</span></span></li>';
        }).join('') : '<li><span class="dash-muted">Nobody has checked in yet today.</span></li>';

        document.querySelector('[data-live-clock]').textContent = 'Live · ' + String(data.time).slice(0, 5);
    }

    function refresh() {
        ui.api('GET', root.dataset.url).then(function (envelope) { render(envelope.data); }).catch(function () {
            const clock = document.querySelector('[data-live-clock]');
            clock.textContent = 'Reconnecting…';
        });
    }

    function start() {
        window.clearInterval(timer);
        refresh();
        timer = window.setInterval(refresh, INTERVAL);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            window.clearInterval(timer);
        } else {
            start();
        }
    });

    start();
})();

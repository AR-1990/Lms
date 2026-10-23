/**
 * Counselling screen: sessions grid, the session form (student search, counsellor, concern, follow-up) and a
 * session popup with the student's earlier sessions. Opening /performance/counselling?student=&alert= starts
 * a session for a performance alert.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-counselling]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const SPEC = { student_id: 'int', performance_alert_id: 'int', session_date: 'text', counsellor_id: 'int', concern_id: 'int', follow_up_on: 'text', notes: 'text', action_plan: 'text', parents_met: 'bool', is_confidential: 'bool' };
    const modal = document.getElementById('counsellingModal');
    const form = modal.querySelector('form');
    const search = form.querySelector('[data-student-search]');
    const results = form.querySelector('[data-student-results]');
    const alertNote = modal.querySelector('[data-alert-note]');
    const view = document.getElementById('counsellingView');
    let prefill = root.dataset.prefillStudent ? { id: root.dataset.prefillStudent, name: root.dataset.prefillName, alert: root.dataset.prefillAlert } : null;
    let timer = null;
    let mounted = null;

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function day(value) {
        return value ? dateFormat.format(new Date(value)) : '—';
    }

    function titleCell(title, sub) {
        return '<strong>' + esc(title || '—') + '</strong>' + (sub ? '<span class="dash-muted dash-cell-sub">' + esc(sub) + '</span>' : '');
    }

    function followUp(row) {
        if (!row.follow_up_on) {
            return '<span class="dash-muted">None</span>';
        }

        if (row.follow_up_done_at) {
            return '<span class="dash-badge dash-badge-ok">Done</span>';
        }

        return '<span class="dash-badge ' + (row.follow_up_on <= today ? 'dash-badge-danger' : 'dash-badge-warn') + '">' + esc(day(row.follow_up_on)) + '</span>';
    }

    function filterParams() {
        const params = {};

        root.querySelectorAll('[data-filter]').forEach(function (select) {
            params[select.dataset.filter] = select.value;
        });

        return params;
    }

    /* ---------- Student search in the form ---------- */

    search.addEventListener('input', function () {
        form.elements.student_id.value = '';
        form.elements.performance_alert_id.value = '';
        clearTimeout(timer);
        timer = setTimeout(function () {
            ui.api('GET', root.dataset.studentsUrl + '?term=' + encodeURIComponent(search.value.trim())).then(function (envelope) {
                results.innerHTML = envelope.data.map(function (student) {
                    return '<li><button type="button" data-student="' + student.id + '" data-name="' + esc(student.name + ' · ' + (student.detail || '')) + '">' + esc(student.name) + ' <span class="dash-muted">' + esc(student.detail || '') + '</span></button></li>';
                }).join('') || '<li class="dash-muted">No student found.</li>';
            }).catch(function () {});
        }, 300);
    });

    results.addEventListener('click', function (event) {
        const pick = event.target.closest('[data-student]');

        if (pick) {
            form.elements.student_id.value = pick.dataset.student;
            search.value = pick.dataset.name;
            results.innerHTML = '';
        }
    });

    /* ---------- Session popup ---------- */

    function show(note) {
        const facts = [
            ['Class', esc(note.class_label || '—')],
            ['Date', esc(day(note.session_date))],
            ['Concern', esc(note.concern_name || '—')],
            ['Counsellor', esc(note.counsellor_name || '—')],
            ['Parents met', note.parents_met ? 'Yes' : 'No'],
            ['Visibility', note.is_confidential ? '<span class="dash-badge dash-badge-system">Confidential</span>' : '<span class="dash-badge dash-badge-ok">Shared with family</span>'],
            ['Follow-up', followUp(note)]
        ];

        view.querySelector('[data-view-title]').textContent = note.student_name;
        view.querySelector('[data-view-sub]').textContent = [note.admission_no, note.alert ? 'From alert: ' + note.alert : null].filter(Boolean).join(' · ');
        view.querySelector('[data-view-body]').innerHTML = '<dl class="dash-asset-facts">' + facts.map(function (fact) { return '<div><dt>' + fact[0] + '</dt><dd>' + fact[1] + '</dd></div>'; }).join('') + '</dl>'
            + '<h4>What was discussed</h4><p class="dash-counselling-text">' + esc(note.notes) + '</p>'
            + (note.action_plan ? '<h4>Action plan</h4><p class="dash-counselling-text">' + esc(note.action_plan) + '</p>' : '')
            + '<h4>Earlier sessions</h4>' + (note.history.length ? '<ul class="dash-counselling-history">' + note.history.map(function (row) {
                return '<li><strong>' + esc(day(row.session_date)) + (row.concern ? ' · ' + esc(row.concern) : '') + '</strong> <span class="dash-muted">' + esc(row.counsellor || '') + (row.is_confidential ? ' · confidential' : '') + '</span>'
                    + '<p>' + esc(row.notes) + '</p>' + (row.action_plan ? '<p class="dash-muted">Plan: ' + esc(row.action_plan) + '</p>' : '') + '</li>';
            }).join('') + '</ul>' : '<p class="dash-muted">This is the first session.</p>');
        view.querySelector('[data-view-actions]').innerHTML = '<button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>'
            + '<button type="button" class="dash-btn dash-btn-ghost" data-view-next>New session for this student</button>'
            + (note.follow_up_on && !note.follow_up_done_at ? '<button type="button" class="dash-btn dash-btn-primary" data-view-done>Follow-up done</button>' : '');

        view.querySelectorAll('[data-modal-close]').forEach(function (button) { button.onclick = function () { view.close(); }; });

        const next = view.querySelector('[data-view-next]');
        next.onclick = function () {
            view.close();
            prefill = { id: note.student_id, name: note.student_name + ' · ' + (note.admission_no || ''), alert: '' };
            mounted.open(null);
        };

        const done = view.querySelector('[data-view-done]');

        if (done) {
            done.onclick = function () {
                done.disabled = true;
                ui.api('POST', root.dataset.resourceUrl + '/' + note.id + '/follow-up-done').then(function (envelope) {
                    ui.toast(envelope.message);
                    mounted.refresh();
                    show(envelope.data);
                }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'That did not work.'), 'error'); done.disabled = false; });
            };
        }

        if (!view.open) {
            view.showModal();
        }
    }

    mounted = kit.mount({
        root: root,
        modal: modal,
        order: [[2, 'desc']],
        titles: { create: 'New session', edit: 'Edit session' },
        emptyText: 'No sessions yet.',
        searchPlaceholder: 'Search student…',
        params: filterParams,
        nameOf: function (row) { return row.student_name + ' · ' + day(row.session_date); },
        loadRow: function (row) { return ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; }); },
        onView: function (row) {
            ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) { show(envelope.data); })
                .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The session could not be opened.'), 'error'); });
        },
        columns: [
            { data: 'student_name', orderable: false, render: display(function (value, row) { return titleCell(value, row.admission_no); }) },
            { data: 'class_label', orderable: false, searchable: false, defaultContent: '—' },
            { data: 'session_date', render: display(function (value, row) { return titleCell(day(value), row.parents_met ? 'parents met' : null); }) },
            { data: 'concern_name', orderable: false, defaultContent: '—' },
            { data: 'notes', orderable: false, className: 'dash-hide-md', render: display(function (value, row) { return (row.is_confidential ? '<span class="dash-badge dash-badge-system">Confidential</span> ' : '') + '<span class="dash-muted">' + esc(value) + '</span>'; }) },
            { data: 'counsellor_name', orderable: false, defaultContent: '—' },
            { data: 'follow_up_on', render: display(function (value, row) { return followUp(row); }) }
        ],
        fill: function (formElement, row) {
            const firstCounsellor = formElement.elements.counsellor_id.options[0];
            kit.fillForm(formElement, row, SPEC, { session_date: today, is_confidential: true, counsellor_id: firstCounsellor ? firstCounsellor.value : '' });
            search.value = row ? row.student_name + ' · ' + (row.admission_no || '') : '';
            results.innerHTML = '';
            alertNote.textContent = 'Notes are confidential unless you switch it off.';

            if (!row && prefill) {
                formElement.elements.student_id.value = prefill.id;
                formElement.elements.performance_alert_id.value = prefill.alert || '';
                search.value = prefill.name;
                alertNote.textContent = prefill.alert ? 'Saving marks the performance alert as acknowledged.' : alertNote.textContent;
                prefill = null;
            }
        },
        afterSave: function (note) { show(note); },
        payload: function (formElement) { return kit.readForm(formElement, SPEC); }
    });

    root.querySelectorAll('[data-filter]').forEach(function (select) {
        select.addEventListener('change', function () { mounted.grid.ajax.reload(); });
    });

    if (prefill) {
        mounted.open(null);
    }
})();

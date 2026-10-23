/**
 * Performance tracker screens: section overview with a student profile (trend, subjects, skills,
 * goals, alerts), the skill rating grid, and the at-risk alert list.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const root = document.querySelector('[data-performance]');

    if (!ui || !kit || !root) {
        return;
    }

    const api = root.dataset.apiUrl;
    const screen = root.dataset.performance;
    const escape = function (value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    };
    const RISK = { on_track: ['dash-badge-ok', 'On track'], watch: ['dash-badge-warn', 'Watch'], at_risk: ['dash-badge-danger', 'At risk'] };
    const percent = function (value) { return value === null || value === undefined ? '—' : Number(value).toFixed(1) + '%'; };
    const risk = function (level) { return '<span class="dash-badge ' + RISK[level][0] + '">' + RISK[level][1] + '</span>'; };
    const sectionSelect = root.querySelector('[data-section]');
    const termSelect = root.querySelector('[data-term]');
    const query = function () { return 'section_id=' + sectionSelect.value + '&exam_term_id=' + termSelect.value; };
    const fail = function (envelope, fallback) {
        const fields = envelope && envelope.errors && envelope.errors.fields;
        ui.toast(fields ? fields[Object.keys(fields)[0]][0] : kit.messageOf(envelope, fallback), 'error');
    };

    /**
     * A small line chart of term averages against the class average.
     */
    function trendChart(terms) {
        const points = terms.filter(function (term) { return term.average_percent !== null; });

        if (points.length < 2) {
            return '';
        }

        const width = 320;
        const height = 120;
        const x = function (index) { return 24 + index * (width - 40) / (points.length - 1); };
        const y = function (value) { return height - 18 - value * (height - 30) / 100; };
        const line = function (key) { return points.map(function (term, index) { return (index ? 'L' : 'M') + x(index) + ' ' + y(term[key] || 0); }).join(' '); };

        return '<svg class="dash-trend" viewBox="0 0 ' + width + ' ' + height + '" role="img" aria-label="Average by term">'
            + '<path d="' + line('class_average_percent') + '" class="dash-trend-class"/>'
            + '<path d="' + line('average_percent') + '" class="dash-trend-student"/>'
            + points.map(function (term, index) { return '<circle cx="' + x(index) + '" cy="' + y(term.average_percent) + '" r="3.5" class="dash-trend-dot"/><text x="' + x(index) + '" y="' + (height - 4) + '" text-anchor="middle">' + escape(term.term) + '</text>'; }).join('')
            + '</svg><p class="dash-muted dash-trend-key"><span class="dash-trend-swatch"></span> student · <span class="dash-trend-swatch is-class"></span> class average</p>';
    }

    /* ---------- Overview ---------- */

    if (screen === 'overview') {
        const rows = root.querySelector('[data-rows]');
        const dialog = document.getElementById('studentPerformance');
        const goalForm = dialog.querySelector('[data-goal-form]');
        let studentId = null;

        const load = function () {
            if (!sectionSelect.value || !termSelect.value) {
                rows.innerHTML = '<tr><td colspan="6" class="dash-muted">Choose a section and term.</td></tr>';

                return;
            }

            ui.api('GET', api + '/overview?' + query()).then(function (envelope) {
                rows.innerHTML = envelope.data.length ? envelope.data.map(function (row) {
                    return '<tr class="dash-row-link" data-student="' + row.student_id + '" tabindex="0"><td><span class="dash-cell-title">' + escape(row.name) + '</span><span class="dash-muted">' + escape(row.admission_no) + (row.open_alerts ? ' · ' + row.open_alerts + ' alert(s)' : '') + '</span></td>'
                        + '<td class="dash-col-num">' + percent(row.average_percent) + '</td><td class="dash-col-num">' + percent(row.attendance_percent) + '</td>'
                        + '<td class="dash-col-num dash-hide-md">' + percent(row.homework_completion_percent) + '</td><td class="dash-col-num dash-hide-md">' + (row.rank_in_section || '—') + '</td><td>' + risk(row.risk_level) + '</td></tr>';
                }).join('') : '<tr><td colspan="6" class="dash-muted">No figures yet — publish results or press Recalculate.</td></tr>';
            }).catch(function (envelope) { fail(envelope, 'Could not load the tracker.'); });
        };

        const openStudent = function (id) {
            studentId = id;

            ui.api('GET', api + '/students/' + id).then(function (envelope) {
                const data = envelope.data;
                const latest = data.terms[data.terms.length - 1];

                dialog.querySelector('[data-detail-title]').textContent = data.student.name;
                dialog.querySelector('[data-detail-subtitle]').innerHTML = escape(data.student.admission_no) + (latest ? ' · ' + risk(latest.risk_level) : '');
                dialog.querySelector('[data-detail-body]').innerHTML = trendChart(data.terms)
                    + (latest ? '<ul class="dash-kv"><li><span>Average</span><strong>' + percent(latest.average_percent) + ' <span class="dash-muted">class ' + percent(latest.class_average_percent) + '</span></strong></li>'
                        + '<li><span>Attendance</span><strong>' + percent(latest.attendance_percent) + ' · late ' + latest.late_count + '</strong></li>'
                        + '<li><span>Homework done</span><strong>' + percent(latest.homework_completion_percent) + ' · late ' + latest.late_submissions + '</strong></li>'
                        + '<li><span>Remarks</span><strong>' + latest.appreciation_count + ' appreciation · ' + latest.discipline_count + ' discipline</strong></li></ul>' : '<p class="dash-muted">No figures yet.</p>')
                    + (latest && latest.subjects.length ? '<h4 class="dash-detail-heading">Subjects</h4><table class="dash-table dash-mini-table"><thead><tr><th>Subject</th><th class="dash-col-num">Score</th><th class="dash-col-num">Class avg</th><th class="dash-col-num">Top</th><th>Trend</th></tr></thead><tbody>'
                        + latest.subjects.map(function (subject) {
                            const change = subject.change_from_previous;
                            const arrow = change === null ? '—' : (change > 0 ? '<span class="dash-up">▲ ' + change + '</span>' : (change < 0 ? '<span class="dash-down">▼ ' + Math.abs(change) + '</span>' : '＝'));

                            return '<tr><td>' + escape(subject.subject) + '</td><td class="dash-col-num">' + percent(subject.average_percent) + '</td><td class="dash-col-num">' + percent(subject.class_average_percent) + '</td><td class="dash-col-num">' + percent(subject.highest_percent) + '</td><td>' + arrow + '</td></tr>';
                        }).join('') + '</tbody></table>' : '')
                    + (data.skills.length ? '<h4 class="dash-detail-heading">Skills</h4><ul class="dash-kv">' + data.skills.map(function (skill) { return '<li><span>' + escape(skill.skill) + '</span><strong>' + escape(skill.rating || '—') + '</strong></li>'; }).join('') + '</ul>' : '')
                    + '<h4 class="dash-detail-heading">Goals</h4><ul class="dash-kv" data-goals>' + (data.goals.length ? data.goals.map(function (goal) {
                        return '<li data-goal="' + goal.id + '"><span>' + escape(goal.title) + (goal.subject ? ' · ' + escape(goal.subject) : '') + (goal.target_date ? ' · by ' + escape(goal.target_date) : '') + '</span><strong>'
                            + (goal.status === 'active' ? '<button type="button" class="dash-link-btn" data-goal-status="achieved">Achieved</button> · <button type="button" class="dash-link-btn" data-goal-status="missed">Missed</button>' : escape(goal.status)) + '</strong></li>';
                    }).join('') : '<li><span>No goals yet.</span><strong></strong></li>') + '</ul>'
                    + (data.alerts.length ? '<h4 class="dash-detail-heading">Open alerts</h4><ul class="dash-kv">' + data.alerts.map(function (alert) { return '<li><span>' + escape(alert.rule) + '</span><strong>' + escape(alert.metric_value) + ' · ' + escape(alert.status) + '</strong></li>'; }).join('') + '</ul>' : '');

                goalForm.reset();
                kit.clearErrors(goalForm);

                if (!dialog.open) {
                    dialog.showModal();
                }
            });
        };

        kit.wireDialog(dialog);

        rows.addEventListener('click', function (event) {
            const row = event.target.closest('[data-student]');

            if (row) {
                openStudent(row.dataset.student);
            }
        });

        rows.addEventListener('keydown', function (event) {
            const row = event.target.closest('[data-student]');

            if (row && event.key === 'Enter') {
                openStudent(row.dataset.student);
            }
        });

        goalForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(goalForm, 'POST', api + '/goals', Object.assign(kit.readForm(goalForm, { title: 'text', subject_id: 'int', target_date: 'text' }), { student_id: Number(studentId) }))
                .then(function () { openStudent(studentId); })
                .catch(function () {});
        });

        dialog.querySelector('[data-detail-body]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-goal-status]');

            if (!button) {
                return;
            }

            ui.api('PUT', api + '/goals/' + button.closest('[data-goal]').dataset.goal, { status: button.dataset.goalStatus })
                .then(function (envelope) { ui.toast(envelope.message); openStudent(studentId); })
                .catch(function (envelope) { fail(envelope, 'Could not update the goal.'); });
        });

        document.querySelector('[data-refresh]').addEventListener('click', function () {
            const hide = ui.busy('Recalculating performance…');

            ui.api('POST', api + '/refresh', { section_id: Number(sectionSelect.value), exam_term_id: Number(termSelect.value) })
                .then(function (envelope) { ui.toast(envelope.message); load(); })
                .catch(function (envelope) { fail(envelope, 'Could not recalculate.'); })
                .finally(hide);
        });

        sectionSelect.addEventListener('change', load);
        termSelect.addEventListener('change', load);
        load();
    }

    /* ---------- Skill ratings ---------- */

    if (screen === 'skills') {
        const table = root.querySelector('[data-skill-table]');
        const ratingOptions = root.querySelector('[data-rating-template]').innerHTML;
        let original = {};

        const load = function () {
            if (!sectionSelect.value || !termSelect.value) {
                return;
            }

            ui.api('GET', api + '/skills?' + query()).then(function (envelope) {
                const sheet = envelope.data;

                original = {};
                table.innerHTML = '<thead><tr><th>Student</th>' + sheet.skills.map(function (skill) { return '<th>' + escape(skill.name) + '</th>'; }).join('') + '</tr></thead><tbody>'
                    + sheet.rows.map(function (row) {
                        return '<tr data-enrollment="' + row.enrollment_id + '"><td>' + escape(row.name) + '</td>' + sheet.skills.map(function (skill) {
                            const value = row.ratings[skill.id] || '';

                            original[row.enrollment_id + ':' + skill.id] = String(value);

                            return '<td><select class="dash-inline-score" data-skill="' + skill.id + '" data-value="' + value + '" aria-label="' + escape(skill.name) + ' for ' + escape(row.name) + '">' + ratingOptions + '</select></td>';
                        }).join('') + '</tr>';
                    }).join('') + '</tbody>';

                table.querySelectorAll('[data-skill]').forEach(function (select) { select.value = select.dataset.value; });
            }).catch(function (envelope) { fail(envelope, 'Could not load the ratings.'); });
        };

        root.querySelector('[data-save-skills]').addEventListener('click', function () {
            const rows = [];

            table.querySelectorAll('[data-skill]').forEach(function (select) {
                const enrollment = select.closest('[data-enrollment]').dataset.enrollment;

                if (original[enrollment + ':' + select.dataset.skill] !== select.value) {
                    rows.push({ enrollment_id: Number(enrollment), assessment_skill_id: Number(select.dataset.skill), rating_id: select.value ? Number(select.value) : null });
                }
            });

            if (!rows.length) {
                ui.toast('Nothing changed.');

                return;
            }

            ui.api('POST', api + '/skills?' + query(), { rows: rows })
                .then(function (envelope) { ui.toast(envelope.message); load(); })
                .catch(function (envelope) { fail(envelope, 'Could not save ratings.'); });
        });

        sectionSelect.addEventListener('change', load);
        termSelect.addEventListener('change', load);
        load();
    }

    /* ---------- Alerts ---------- */

    if (screen === 'alerts') {
        const list = root.querySelector('[data-alerts]');
        const statusSelect = root.querySelector('[data-status]');

        const load = function () {
            ui.api('GET', api + '/alerts?per_page=100' + (statusSelect.value ? '&status=' + statusSelect.value : '')).then(function (envelope) {
                list.innerHTML = envelope.data.items.length ? envelope.data.items.map(function (alert) {
                    return '<li class="dash-alert-item is-' + alert.status + '" data-alert="' + alert.id + '"><div><strong>' + escape(alert.student) + '</strong> <span class="dash-muted">' + escape(alert.admission_no) + '</span>'
                        + '<p>' + escape(alert.rule) + ' — value ' + escape(alert.metric_value) + ' (' + escape(alert.condition) + ')</p>'
                        + (alert.action_taken ? '<p class="dash-muted">Action: ' + escape(alert.action_taken) + '</p>' : '') + '</div>'
                        + (alert.status === 'resolved' ? '<span class="dash-badge dash-badge-ok">Resolved</span>'
                            : '<div class="dash-alert-actions"><input class="dash-inline-score dash-inline-feedback" placeholder="What was done?" data-action-taken aria-label="Action taken">'
                            + (root.dataset.counsellingUrl ? '<a class="dash-btn dash-btn-ghost dash-btn-sm" href="' + escape(root.dataset.counsellingUrl + '?student=' + alert.student_id + '&alert=' + alert.id) + '">Counselling</a>' : '')
                            + (alert.status === 'open' ? '<button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-alert-status="acknowledged">Acknowledge</button>' : '')
                            + '<button type="button" class="dash-btn dash-btn-primary dash-btn-sm" data-alert-status="resolved">Resolve</button></div>')
                        + '</li>';
                }).join('') : '<li class="dash-muted">No alerts.</li>';
            });
        };

        list.addEventListener('click', function (event) {
            const button = event.target.closest('[data-alert-status]');

            if (!button) {
                return;
            }

            const item = button.closest('[data-alert]');

            ui.api('PUT', api + '/alerts/' + item.dataset.alert, { status: button.dataset.alertStatus, action_taken: item.querySelector('[data-action-taken]').value.trim() || null })
                .then(function (envelope) { ui.toast(envelope.message); load(); })
                .catch(function (envelope) { fail(envelope, 'Could not update the alert.'); });
        });

        statusSelect.addEventListener('change', load);
        load();
    }
})();

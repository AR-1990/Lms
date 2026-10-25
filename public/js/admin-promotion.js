/**
 * Year-end promotion wizard: pick the two sessions, copy sections into the new one,
 * then open each class, adjust the suggested moves and save; finally make the new session current.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-promotion]');

    if (!root || !window.PinnacleDashboard) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const esc = function (value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    };
    const from = root.querySelector('[data-from]');
    const to = root.querySelector('[data-to]');
    const classesPanel = root.querySelector('[data-classes-panel]');
    const studentsPanel = root.querySelector('[data-students-panel]');
    const finishPanel = root.querySelector('[data-finish-panel]');
    const prepareBox = root.querySelector('[data-prepare-box]');
    const prepareButton = root.querySelector('[data-prepare]');
    const rows = root.querySelector('[data-student-rows]');
    const bulkSection = root.querySelector('[data-bulk-section]');
    const selectAll = root.querySelector('[data-select-all]');
    const moveError = root.querySelector('[data-move-error]');
    const confirmDialog = document.getElementById('promotionConfirm');
    let summary = null;
    let opened = null;

    /* ---------- Helpers ---------- */

    function query(extra) {
        const params = new URLSearchParams(Object.assign({ from_session_id: from.value, to_session_id: to.value }, extra || {}));

        return '?' + params.toString();
    }

    function fail(envelope) {
        ui.toast(envelope && envelope.message ? envelope.message : 'Something went wrong.', 'error');
    }

    function errorText(envelope) {
        return (envelope && envelope.errors && envelope.errors.summary) || (envelope && envelope.message) || 'Something went wrong.';
    }

    function ask(title, text) {
        confirmDialog.querySelector('[data-confirm-title]').textContent = title;
        confirmDialog.querySelector('[data-confirm-text]').textContent = text;
        confirmDialog.showModal();

        return new Promise(function (resolve) {
            const yes = confirmDialog.querySelector('[data-confirm-yes]');
            const done = function (answer) {
                yes.removeEventListener('click', onYes);
                confirmDialog.removeEventListener('close', onClose);
                confirmDialog.close();
                resolve(answer);
            };
            const onYes = function () { done(true); };
            const onClose = function () { done(false); };

            yes.addEventListener('click', onYes);
            confirmDialog.addEventListener('close', onClose);
        });
    }

    confirmDialog.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () { confirmDialog.close(); });
    });

    /* ---------- Step 1: sessions ---------- */

    // Default "into" to the session right after "from", by start date (read from the summary once loaded).
    function pickNextSession() {
        const options = Array.prototype.slice.call(to.options);
        const fromIndex = Array.prototype.indexOf.call(from.options, from.selectedOptions[0]);
        // Sessions are listed newest first, so the next year sits just above "from".
        const next = fromIndex > 0 ? options[fromIndex - 1] : null;

        root.querySelector('[data-no-next]').hidden = Boolean(next);

        if (next) {
            to.value = next.value;
        }

        return Boolean(next);
    }

    function loadSummary() {
        studentsPanel.hidden = true;
        opened = null;

        if (from.value === to.value) {
            classesPanel.hidden = true;
            prepareBox.hidden = true;

            if (finishPanel) {
                finishPanel.hidden = true;
            }

            root.querySelector('[data-no-next]').hidden = false;

            return;
        }

        root.querySelector('[data-no-next]').hidden = true;

        ui.api('GET', root.dataset.summaryUrl + query()).then(function (envelope) {
            summary = envelope.data;
            renderPrepare();
            renderClasses();
        }).catch(function (envelope) {
            classesPanel.hidden = true;
            prepareBox.hidden = true;
            fail(envelope);
        });
    }

    function renderPrepare() {
        prepareBox.hidden = false;
        const ready = summary.to_sections > 0;

        root.querySelector('[data-prepare-title]').textContent = ready
            ? summary.to_session.name + ' has ' + summary.to_sections + ' section(s)'
            : 'Get ' + summary.to_session.name + ' ready';
        root.querySelector('[data-prepare-text]').textContent = ready
            ? 'Copy again to add any sections, subjects or fee structures added to ' + summary.from_session.name + ' since. Nothing already there is changed.'
            : 'Copy ' + summary.from_session.name + '\'s sections, subjects and fee structures into the new session first, so promoted students can be placed in sections.';
        prepareBox.classList.toggle('is-ready', ready);
    }

    if (prepareButton) {
        prepareButton.addEventListener('click', function () {
            const hide = ui.busy('Copying into ' + summary.to_session.name + '…');

            ui.api('POST', root.dataset.prepareUrl, { from_session_id: Number(from.value), to_session_id: Number(to.value) }).then(function (envelope) {
                const copied = envelope.data;
                ui.toast(copied.sections + ' section(s), ' + copied.curriculum + ' subject(s)' + (copied.fee_structures === null ? '' : ' and ' + copied.fee_structures + ' fee structure(s)') + ' copied.');
                loadSummary();
            }).catch(function (envelope) {
                ui.toast(errorText(envelope), 'error');
            }).finally(hide);
        });
    }

    /* ---------- Step 2: classes ---------- */

    function renderClasses() {
        const list = root.querySelector('[data-class-list]');
        const waiting = summary.classes.reduce(function (sum, item) { return sum + item.waiting; }, 0);
        const moved = summary.classes.reduce(function (sum, item) { return sum + item.moved; }, 0);

        classesPanel.hidden = false;
        root.querySelector('[data-overall]').textContent = waiting ? waiting + ' student(s) waiting' : (moved ? 'All classes done' : 'No students');
        root.querySelector('[data-overall]').className = 'dash-badge ' + (waiting ? 'dash-badge-warn' : 'dash-badge-ok');

        list.innerHTML = summary.classes.length ? summary.classes.slice().reverse().map(function (item) {
            const total = item.waiting + item.moved;
            const share = total ? item.moved / total : 0;

            return '<li class="dash-promo-class' + (item.waiting ? '' : ' is-done') + '">'
                + '<div class="dash-promo-class-name"><strong>' + esc(item.name) + '</strong>'
                + '<span class="dash-muted">' + (item.next_class ? 'Goes up to ' + esc(item.next_class.name) : 'Last class — graduates') + '</span></div>'
                + '<div class="dash-promo-meter" aria-hidden="true"><span style="transform: scaleX(' + share.toFixed(3) + ')"></span></div>'
                + '<span class="dash-promo-count">' + item.moved + ' / ' + total + ' moved</span>'
                + '<div class="dash-promo-class-actions">'
                + (item.moved ? '<button type="button" class="dash-link-btn" data-roll-class="' + item.id + '" data-name="' + esc(item.name) + '">Roll numbers</button>'
                    + '<button type="button" class="dash-link-btn" data-undo-class="' + item.id + '" data-name="' + esc(item.name) + '">Undo</button>' : '')
                + (item.waiting
                    ? '<button type="button" class="dash-btn dash-btn-primary dash-btn-sm" data-open-class="' + item.id + '">Open</button>'
                    : '<span class="dash-badge dash-badge-ok">Done</span>')
                + '</div></li>';
        }).join('') : '<li><span class="dash-muted">No students are enrolled in ' + esc(summary.from_session.name) + '.</span></li>';

        if (finishPanel) {
            finishPanel.hidden = summary.to_session.is_current;
            finishPanel.querySelector('[data-finish-text]').textContent = waiting
                ? waiting + ' student(s) are still waiting. You can make ' + summary.to_session.name + ' current now, but do it once every class is done.'
                : 'Every class is done. Make ' + summary.to_session.name + ' current so attendance, timetables, exams and fee challans use it.';
            finishPanel.querySelector('[data-make-current]').lastChild.textContent = ' Make ' + summary.to_session.name + ' current';
        }
    }

    const rollDialog = document.getElementById('rollNumbersModal');
    const rollForm = rollDialog.querySelector('form');

    rollDialog.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () { rollDialog.close(); });
    });

    rollForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const error = rollForm.querySelector('[data-form-error]');
        error.hidden = true;

        ui.api('POST', root.dataset.rollUrl, { to_session_id: Number(to.value), class_id: Number(rollDialog.dataset.classId), order: rollForm.elements.order.value }).then(function (envelope) {
            rollDialog.close();
            ui.toast(envelope.message);
        }).catch(function (envelope) {
            error.textContent = errorText(envelope);
            error.hidden = false;
        });
    });

    root.querySelector('[data-class-list]').addEventListener('click', function (event) {
        const open = event.target.closest('[data-open-class]');
        const roll = event.target.closest('[data-roll-class]');
        const undo = event.target.closest('[data-undo-class]');

        if (open) {
            openClass(Number(open.dataset.openClass));
        }

        if (roll) {
            rollDialog.dataset.classId = roll.dataset.rollClass;
            rollDialog.querySelector('[data-roll-title]').textContent = 'Roll numbers — ' + roll.dataset.name + ', ' + summary.to_session.name;
            rollForm.querySelector('[data-form-error]').hidden = true;
            rollDialog.showModal();
        }

        if (undo) {
            ask('Undo the move of ' + undo.dataset.name + '?', 'Everyone moved from ' + undo.dataset.name + ' goes back to ' + summary.from_session.name + ' as before: their new places, carried concessions and transport are removed, and graduates become active again. This only works while nothing (attendance, challans, results) is recorded for them in ' + summary.to_session.name + '.').then(function (yes) {
                if (!yes) {
                    return;
                }

                ui.api('POST', root.dataset.undoUrl, { from_session_id: Number(from.value), to_session_id: Number(to.value), class_id: Number(undo.dataset.undoClass) }).then(function (envelope) {
                    ui.toast(envelope.message);
                    loadSummary();
                }).catch(function (envelope) {
                    ui.toast(errorText(envelope), 'error');
                });
            });
        }
    });

    /* ---------- Class students ---------- */

    const MOVE_LABELS = { promote: 'Promote', repeat: 'Keep in class', demote: 'Move down', graduate: 'Graduate' };

    function targetClassId(action) {
        if (action === 'promote') {
            return opened.next_class ? opened.next_class.id : null;
        }

        if (action === 'demote') {
            return opened.previous_class ? opened.previous_class.id : null;
        }

        return action === 'repeat' ? opened.class.id : null;
    }

    function sectionOptions(action, selected) {
        const classId = targetClassId(action);

        if (classId === null) {
            return '<option value="">—</option>';
        }

        return '<option value="">No section yet</option>' + opened.sections.filter(function (section) {
            return section.class_id === classId;
        }).map(function (section) {
            return '<option value="' + section.id + '"' + (String(section.id) === String(selected) ? ' selected' : '') + '>' + esc(section.name) + '</option>';
        }).join('');
    }

    function moveOptions(selected) {
        return ['promote', 'repeat', 'demote', 'graduate'].filter(function (action) {
            return targetClassId(action) !== null || action === 'graduate';
        }).map(function (action) {
            const target = action === 'promote' ? ' to ' + opened.next_class.name : (action === 'demote' ? ' to ' + opened.previous_class.name : '');

            return '<option value="' + action + '"' + (action === selected ? ' selected' : '') + '>' + esc(MOVE_LABELS[action] + target) + '</option>';
        }).join('');
    }

    function resultCell(student) {
        if (!student.result) {
            return '<span class="dash-muted">No final result</span>';
        }

        const tone = student.result === 'fail' ? 'dash-badge-danger' : 'dash-badge-ok';

        return '<span class="dash-badge ' + tone + '">' + esc(student.result === 'fail' ? 'Fail' : 'Pass') + '</span>'
            + (student.percentage !== null ? ' <span class="dash-promo-pct">' + esc(Number(student.percentage)) + '%' + (student.grade ? ' · ' + esc(student.grade) : '') + '</span>' : '');
    }

    function renderStudents() {
        rows.innerHTML = opened.students.map(function (student) {
            return '<tr data-enrollment="' + student.enrollment_id + '" data-suggested="' + student.suggested + '">'
                + '<td class="dash-col-check"><input type="checkbox" data-pick checked aria-label="Move ' + esc(student.name) + '"></td>'
                + '<td><a class="dash-cell-title" href="' + esc(root.dataset.profileUrl + '/' + student.student_id) + '" target="_blank" rel="noopener">' + esc(student.name) + '</a>'
                + '<span class="dash-muted">' + esc([student.admission_no, student.section ? 'Section ' + student.section : null, student.roll_no ? 'Roll ' + student.roll_no : null].filter(Boolean).join(' · ')) + '</span></td>'
                + '<td>' + resultCell(student) + '</td>'
                + '<td><label class="dash-select"><span class="dash-visually-hidden">Move</span><select data-action>' + moveOptions(student.suggested) + '</select></label></td>'
                + '<td><label class="dash-select"><span class="dash-visually-hidden">New section</span><select data-section>' + sectionOptions(student.suggested, null) + '</select></label></td>'
                + '</tr>';
        }).join('') || '<tr><td colspan="5" class="dash-muted">Everyone in this class has been moved.</td></tr>';

        const promotedSections = opened.next_class ? opened.sections.filter(function (section) { return section.class_id === opened.next_class.id; }) : [];
        bulkSection.closest('label').hidden = promotedSections.length === 0;
        bulkSection.innerHTML = '<option value="">Section for everyone promoted…</option>' + promotedSections.map(function (section) {
            return '<option value="' + section.id + '">' + esc(opened.next_class.name + ' ' + section.name) + '</option>';
        }).join('');

        selectAll.checked = true;
        moveError.hidden = true;
        updateCount();
    }

    function picked() {
        return Array.prototype.slice.call(rows.querySelectorAll('tr[data-enrollment]')).filter(function (row) {
            return row.querySelector('[data-pick]').checked;
        });
    }

    function updateCount() {
        const chosen = picked();
        const tally = {};

        chosen.forEach(function (row) {
            const action = row.querySelector('[data-action]').value;
            tally[action] = (tally[action] || 0) + 1;
        });

        root.querySelector('[data-move-count]').textContent = chosen.length
            ? chosen.length + ' selected — ' + Object.keys(tally).map(function (action) { return tally[action] + ' ' + MOVE_LABELS[action].toLowerCase(); }).join(', ')
            : 'Nobody selected';
        root.querySelector('[data-move]').disabled = chosen.length === 0;
    }

    function openClass(classId) {
        const hide = ui.busy('Loading students…');

        ui.api('GET', root.dataset.studentsUrl + query({ class_id: classId })).then(function (envelope) {
            opened = envelope.data;
            root.querySelector('[data-class-title]').textContent = opened.class.name + ' → ' + summary.to_session.name;
            root.querySelector('[data-class-hint]').textContent = opened.next_class
                ? 'Suggested from the final exam: pass goes up to ' + opened.next_class.name + ', fail stays in ' + opened.class.name + '. Change any row before saving.'
                : opened.class.name + ' is the last class, so students graduate. Keep a student back by choosing "Keep in class".';
            renderStudents();
            studentsPanel.hidden = false;
            studentsPanel.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        }).catch(fail).finally(hide);
    }

    rows.addEventListener('change', function (event) {
        const row = event.target.closest('tr[data-enrollment]');

        if (event.target.matches('[data-action]')) {
            row.querySelector('[data-section]').innerHTML = sectionOptions(event.target.value, bulkSection.value && event.target.value === 'promote' ? bulkSection.value : null);
        }

        if (event.target.matches('[data-pick]')) {
            selectAll.checked = picked().length === rows.querySelectorAll('tr[data-enrollment]').length;
        }

        updateCount();
    });

    selectAll.addEventListener('change', function () {
        rows.querySelectorAll('[data-pick]').forEach(function (box) { box.checked = selectAll.checked; });
        updateCount();
    });

    bulkSection.addEventListener('change', function () {
        rows.querySelectorAll('tr[data-enrollment]').forEach(function (row) {
            if (row.querySelector('[data-action]').value === 'promote') {
                row.querySelector('[data-section]').value = bulkSection.value;
            }
        });
    });

    root.querySelector('[data-reset-suggested]').addEventListener('click', function () {
        rows.querySelectorAll('tr[data-enrollment]').forEach(function (row) {
            row.querySelector('[data-action]').value = row.dataset.suggested;
            row.querySelector('[data-section]').innerHTML = sectionOptions(row.dataset.suggested, row.dataset.suggested === 'promote' ? bulkSection.value : null);
        });
        updateCount();
    });

    root.querySelector('[data-close-class]').addEventListener('click', function () {
        studentsPanel.hidden = true;
        opened = null;
    });

    root.querySelector('[data-move]').addEventListener('click', function () {
        const chosen = picked();
        const graduating = chosen.filter(function (row) { return row.querySelector('[data-action]').value === 'graduate'; }).length;
        const text = chosen.length + ' student(s) of ' + opened.class.name + ' will be placed in ' + summary.to_session.name + '.'
            + (graduating ? ' ' + graduating + ' will graduate: their login is closed and they leave the student list.' : '')
            + ' This cannot be undone from here.';

        ask('Save ' + chosen.length + ' move(s)?', text).then(function (yes) {
            if (!yes) {
                return;
            }

            const hide = ui.busy('Moving students…');
            moveError.hidden = true;

            ui.api('POST', root.dataset.promoteUrl, {
                from_session_id: Number(from.value),
                to_session_id: Number(to.value),
                class_id: opened.class.id,
                carry_concessions: root.querySelector('[data-carry-concessions]').checked,
                carry_transport: root.querySelector('[data-carry-transport]').checked,
                students: chosen.map(function (row) {
                    const section = row.querySelector('[data-section]').value;

                    return { enrollment_id: Number(row.dataset.enrollment), action: row.querySelector('[data-action]').value, section_id: section ? Number(section) : null };
                })
            }).then(function (envelope) {
                const done = envelope.data;
                ui.toast([done.promoted + ' promoted', done.repeated + ' kept', done.demoted + ' moved down', done.graduated + ' graduated', done.concessions + ' concession(s) carried', done.transport + ' transport ride(s) carried'].filter(function (part) { return part.charAt(0) !== '0'; }).join(', ') + '.');
                loadSummary();
            }).catch(function (envelope) {
                moveError.textContent = errorText(envelope);
                moveError.hidden = false;
            }).finally(hide);
        });
    });

    /* ---------- Step 3: make the new session current ---------- */

    if (finishPanel) {
        finishPanel.querySelector('[data-make-current]').addEventListener('click', function () {
            const target = summary.to_session;

            ask('Make ' + target.name + ' current?', 'From now on attendance, timetables, exams and new fee challans use ' + target.name + '. ' + summary.from_session.name + ' stays available in reports and each student\'s history.').then(function (yes) {
                if (!yes) {
                    return;
                }

                ui.api('PUT', root.dataset.sessionsUrl + '/' + target.id, {
                    name: target.name,
                    starts_on: target.starts_on,
                    ends_on: target.ends_on,
                    is_current: true,
                    admissions_open: Boolean(target.admissions_open)
                }).then(function () {
                    ui.toast(target.name + ' is now the current session.');
                    window.location.reload();
                }).catch(function (envelope) {
                    ui.toast(errorText(envelope), 'error');
                });
            });
        });
    }

    from.addEventListener('change', function () {
        pickNextSession();
        loadSummary();
    });
    to.addEventListener('change', loadSummary);

    if (pickNextSession()) {
        loadSummary();
    }
})();

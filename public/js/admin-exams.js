/**
 * Exam screens: exams (date sheet, fees, admit cards, seating, results), marks entry, setup lists.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!ui || !kit) {
        return;
    }

    const escape = function (value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    };
    const headline = function (value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
    };
    const TONES = { draft: 'dash-badge-system', scheduled: 'dash-badge-brand', admit_cards_issued: 'dash-badge-brand', marks_entry: 'dash-badge-warn', review: 'dash-badge-warn', published: 'dash-badge-ok' };
    const badge = function (status) {
        return '<span class="dash-badge ' + (TONES[status] || 'dash-badge-system') + '">' + escape(headline(status)) + '</span>';
    };
    const fieldError = function (envelope, fallback) {
        const fields = envelope && envelope.errors && envelope.errors.fields;

        return fields ? fields[Object.keys(fields)[0]][0] : kit.messageOf(envelope, fallback);
    };

    /* ---------- Exams ---------- */

    const examsRoot = document.querySelector('[data-exams]');

    if (examsRoot && window.PinnacleGrid) {
        const SPEC = { exam_term_id: 'int', class_id: 'int', name: 'text', exam_type_id: 'int', grading_scale_id: 'int', marks_entry_deadline: 'text', fee_head_id: 'int', exam_fee_amount: 'money', fee_due_date: 'text', min_attendance_percent: 'number', require_fee_clearance: 'bool', instructions: 'text' };
        const api = examsRoot.dataset.apiUrl;
        const detail = document.getElementById('examDetail');
        const papersBox = detail.querySelector('[data-papers]');
        const paperTemplate = detail.querySelector('[data-paper-template]');
        let exam = null;

        const mounted = kit.mount({
            root: examsRoot,
            modal: document.getElementById('examModal'),
            order: [[0, 'desc']],
            titles: { create: 'New exam', edit: 'Edit exam' },
            searchPlaceholder: 'Search exam…',
            emptyText: 'No exams yet.',
            params: function () {
                const values = {};

                examsRoot.querySelectorAll('[data-filter]').forEach(function (field) { values[field.dataset.filter] = field.value; });

                return values;
            },
            nameOf: function (row) { return row.name; },
            onView: function (row) { open(row.id); },
            canDelete: function (row) { return row.status === 'draft' ? true : 'Only a draft exam can be deleted'; },
            columns: [
                { data: 'name', render: function (value, type, row) { return type === 'display' ? '<span class="dash-cell-title">' + escape(value) + '</span><span class="dash-muted">' + escape((row.term_name || '') + ' · ' + (row.class_name || '')) + '</span>' : value; } },
                { data: 'subjects_count', searchable: false, className: 'dash-hide-md dash-col-num' },
                { data: 'status', searchable: false, render: function (value, type) { return type === 'display' ? badge(value) : value; } }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row, SPEC, { require_fee_clearance: true });
            },
            payload: function (form) {
                return kit.readForm(form, SPEC);
            }
        });

        examsRoot.querySelectorAll('[data-filter]').forEach(function (field) {
            field.addEventListener('change', function () { mounted.grid.ajax.reload(); });
        });
        kit.wireDialog(detail);

        function addPaper(paper) {
            const line = paperTemplate.content.firstElementChild.cloneNode(true);

            Array.prototype.forEach.call(line.querySelector('[data-paper-field="subject_id"]').options, function (option) {
                option.hidden = option.dataset.classId !== String(exam.class_id);
            });

            ['subject_id', 'exam_date', 'starts_at', 'theory_max_marks', 'practical_max_marks', 'pass_marks'].forEach(function (name) {
                const input = line.querySelector('[data-paper-field="' + name + '"]');

                if (paper && paper[name] !== null && paper[name] !== undefined) {
                    input.value = paper[name];
                } else if (name === 'subject_id') {
                    const first = input.querySelector('option:not([hidden])');
                    input.value = first ? first.value : '';
                } else if (name === 'theory_max_marks') {
                    input.value = 100;
                }
            });

            papersBox.appendChild(line);
        }

        function render() {
            detail.querySelector('[data-detail-title]').textContent = exam.name;
            detail.querySelector('[data-detail-subtitle]').innerHTML = escape((exam.term_name || '') + ' · ' + (exam.class_name || '') + ' · ') + badge(exam.status)
                + (exam.admit_cards_count ? ' · ' + exam.eligible_count + '/' + exam.admit_cards_count + ' admit cards eligible' : '');
            detail.querySelector('[data-step-buttons]').innerHTML = exam.next_statuses.map(function (status) {
                return '<button type="button" class="dash-btn dash-btn-sm ' + (status === 'draft' ? 'dash-btn-ghost' : 'dash-btn-primary') + '" data-step="' + status + '">Move to ' + escape(headline(status)) + '</button>';
            }).join('');

            papersBox.innerHTML = '';
            (exam.papers.length ? exam.papers : [null]).forEach(addPaper);

            detail.querySelector('[data-seat-paper]').innerHTML = exam.papers.map(function (paper) {
                return '<option value="' + paper.id + '">' + escape(paper.subject + (paper.exam_date ? ' · ' + paper.exam_date : '')) + '</option>';
            }).join('');

            Array.prototype.forEach.call(detail.querySelector('[data-result-section]').options, function (option) {
                option.hidden = option.value !== '' && option.dataset.classId !== String(exam.class_id);
            });
        }

        function loadAdmitCards() {
            ui.api('GET', api + '/exams/' + exam.id + '/admit-cards').then(function (envelope) {
                detail.querySelector('[data-admit-rows]').innerHTML = envelope.data.length ? envelope.data.map(function (card) {
                    return '<tr><td><span class="dash-cell-title">' + escape(card.student) + '</span><span class="dash-muted">' + escape(card.exam_roll_no + ' · ' + (card.section || '')) + '</span></td>'
                        + '<td>' + (card.is_eligible ? '<span class="dash-badge dash-badge-ok">Eligible</span>' + (card.override_reason ? '<span class="dash-muted">override: ' + escape(card.override_reason) + '</span>' : '') : '<span class="dash-badge dash-badge-danger">' + escape(headline(card.ineligible_reason)) + '</span>') + '</td>'
                        + '<td>' + (card.is_eligible ? '' : '<input class="dash-inline-score dash-inline-feedback" placeholder="Reason to allow" data-override-reason="' + card.id + '"> <button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-override="' + card.id + '">Allow</button>') + '</td></tr>';
                }).join('') : '<tr><td class="dash-muted">No admit cards yet.</td></tr>';
            });
        }

        function open(id) {
            ui.api('GET', api + '/exams/' + id).then(function (envelope) {
                exam = envelope.data;
                render();
                loadAdmitCards();
                detail.querySelector('[data-seat-rows]').innerHTML = '';
                detail.querySelector('[data-tabulation]').innerHTML = '';

                if (!detail.open) {
                    detail.showModal();
                }
            });
        }

        const act = function (method, path, body, busyText) {
            const hide = busyText ? ui.busy(busyText) : function () {};

            return ui.api(method, api + path, body)
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    mounted.refresh();

                    return envelope;
                })
                .catch(function (envelope) {
                    ui.toast(fieldError(envelope, 'Could not complete that.'), 'error');

                    return Promise.reject(envelope);
                })
                .finally(hide);
        };

        detail.addEventListener('click', function (event) {
            const step = event.target.closest('[data-step]');
            const action = event.target.closest('[data-action]');
            const override = event.target.closest('[data-override]');

            if (step) {
                act('POST', '/exams/' + exam.id + '/status', { status: step.dataset.step }).then(function () { open(exam.id); }).catch(function () {});
            }

            if (action) {
                act('POST', '/exams/' + exam.id + '/' + action.dataset.action, undefined, action.dataset.action === 'fee-challans' ? 'Generating exam fee challans…' : 'Checking fees and preparing admit cards…')
                    .then(function () { open(exam.id); }).catch(function () {});
            }

            if (override) {
                act('POST', '/admit-cards/' + override.dataset.override + '/override', { reason: detail.querySelector('[data-override-reason="' + override.dataset.override + '"]').value.trim() })
                    .then(loadAdmitCards).catch(function () {});
            }

            if (event.target.closest('[data-remove-paper]')) {
                event.target.closest('[data-paper]').remove();
            }
        });

        detail.querySelector('[data-add-paper]').addEventListener('click', function () { addPaper(null); });

        detail.querySelector('[data-save-papers]').addEventListener('click', function () {
            const papers = Array.prototype.map.call(papersBox.querySelectorAll('[data-paper]'), function (line) {
                const value = function (name) { return line.querySelector('[data-paper-field="' + name + '"]').value; };

                return {
                    subject_id: Number(value('subject_id')),
                    exam_date: value('exam_date') || null,
                    starts_at: value('starts_at') || null,
                    theory_max_marks: Number(value('theory_max_marks') || 0),
                    practical_max_marks: Number(value('practical_max_marks') || 0),
                    pass_marks: value('pass_marks') === '' ? null : Number(value('pass_marks'))
                };
            });

            act('PUT', '/exams/' + exam.id + '/date-sheet', { papers: papers }).then(function (envelope) {
                exam = envelope.data;
                render();
            }).catch(function () {});
        });

        detail.querySelector('[data-print-admit]').addEventListener('click', function () {
            ui.openFile(api + '/exams/' + exam.id + '/admit-cards/pdf', 'Generating admit cards…').then(loadAdmitCards).catch(function () {});
        });

        detail.querySelector('[data-make-seating]').addEventListener('click', function () {
            const paperId = detail.querySelector('[data-seat-paper]').value;
            const halls = Array.prototype.filter.call(detail.querySelectorAll('[data-hall]'), function (box) { return box.checked; }).map(function (box) { return Number(box.value); });

            act('POST', '/exam-papers/' + paperId + '/seating', { hall_ids: halls }, 'Making the seating plan…').then(function () {
                return ui.api('GET', api + '/exam-papers/' + paperId + '/seating');
            }).then(function (envelope) {
                detail.querySelector('[data-seat-rows]').innerHTML = envelope.data.map(function (seat) {
                    return '<tr><td>' + escape(seat.hall) + '</td><td>Seat ' + escape(seat.seat_no) + ' (row ' + seat.row_no + ')</td><td>' + escape(seat.student) + '</td><td class="dash-muted">' + escape(seat.section || '') + '</td></tr>';
                }).join('');
            }).catch(function () {});
        });

        const sectionQuery = function () {
            const section = detail.querySelector('[data-result-section]').value;

            return section ? '?section_id=' + section : '';
        };

        detail.querySelector('[data-tabulate]').addEventListener('click', function () {
            ui.api('GET', api + '/exams/' + exam.id + '/tabulation' + sectionQuery()).then(function (envelope) {
                const data = envelope.data;

                detail.querySelector('[data-tabulation]').innerHTML = '<thead><tr><th>#</th><th>Student</th>' + data.papers.map(function (paper) { return '<th class="dash-col-num">' + escape(paper.subject) + '<span class="dash-muted">/' + paper.max + '</span></th>'; }).join('')
                    + '<th class="dash-col-num">Total</th><th class="dash-col-num">%</th><th>Grade</th><th>Result</th></tr></thead><tbody>'
                    + data.rows.map(function (row) {
                        return '<tr><td>' + row.position + '</td><td><span class="dash-cell-title">' + escape(row.name) + '</span><span class="dash-muted">' + escape(row.section || '') + '</span></td>'
                            + data.papers.map(function (paper) { const mark = row.marks[paper.id]; return '<td class="dash-col-num">' + (mark === null || mark === undefined ? '—' : escape(mark)) + '</td>'; }).join('')
                            + '<td class="dash-col-num">' + row.total_obtained + '/' + row.total_max + '</td><td class="dash-col-num">' + row.percentage + '</td><td>' + escape(row.grade || '—') + '</td>'
                            + '<td><span class="dash-badge ' + (row.result === 'pass' ? 'dash-badge-ok' : 'dash-badge-danger') + '">' + escape(row.result) + '</span></td></tr>';
                    }).join('') + '</tbody>';
            }).catch(function (envelope) { ui.toast(fieldError(envelope, 'Could not build the tabulation.'), 'error'); });
        });

        detail.querySelector('[data-publish]').addEventListener('click', function () {
            act('POST', '/exams/' + exam.id + '/publish', undefined, 'Calculating results and positions…').then(function () { open(exam.id); }).catch(function () {});
        });

        detail.querySelector('[data-report-cards]').addEventListener('click', function () {
            ui.openFile(api + '/exams/' + exam.id + '/report-cards' + sectionQuery(), 'Generating report cards…').catch(function () {});
        });
    }

    /* ---------- Marks entry ---------- */

    const marksRoot = document.querySelector('[data-marks]');

    if (marksRoot) {
        const paperSelect = marksRoot.querySelector('[data-paper]');
        const sectionSelect = marksRoot.querySelector('[data-section]');
        const body = marksRoot.querySelector('[data-marks-rows]');
        const note = marksRoot.querySelector('[data-marks-note]');
        const manager = marksRoot.dataset.manager === '1';
        let paper = null;

        const syncSections = function () {
            const option = paperSelect.selectedOptions[0];

            Array.prototype.forEach.call(sectionSelect.options, function (section) {
                const sameClass = option && section.dataset.classId === option.dataset.classId;
                const teaches = manager || section.dataset.subjects.split(' ').indexOf(option ? option.dataset.subjectId : '') !== -1;

                section.hidden = !(sameClass && teaches);
            });

            if (!sectionSelect.selectedOptions[0] || sectionSelect.selectedOptions[0].hidden) {
                const first = sectionSelect.querySelector('option:not([hidden])');
                sectionSelect.value = first ? first.value : '';
            }
        };

        const render = function (sheet) {
            paper = sheet.paper;
            const practical = paper.practical_max_marks > 0;

            marksRoot.querySelector('[data-practical-head]').hidden = !practical;
            note.hidden = false;
            note.textContent = paper.subject + ' — out of ' + paper.theory_max_marks + (practical ? ' + ' + paper.practical_max_marks + ' practical' : '') + (paper.pass_marks ? ' · pass ' + paper.pass_marks : '') + ' · exam ' + headline(paper.exam_status);

            body.innerHTML = sheet.rows.map(function (row) {
                return '<tr data-enrollment="' + row.enrollment_id + '"><td><span class="dash-cell-title">' + escape((row.roll_no ? row.roll_no + '. ' : '') + row.name) + '</span><span class="dash-muted">' + escape(row.admission_no) + (row.status ? ' · ' + escape(row.status) : '') + '</span></td>'
                    + '<td class="dash-col-num"><input type="number" min="0" max="' + paper.theory_max_marks + '" step="0.5" class="dash-inline-score" data-theory value="' + (row.theory_marks === null ? '' : row.theory_marks) + '" aria-label="Theory marks"></td>'
                    + '<td class="dash-col-num"' + (practical ? '' : ' hidden') + '><input type="number" min="0" max="' + paper.practical_max_marks + '" step="0.5" class="dash-inline-score" data-practical value="' + (row.practical_marks === null ? '' : row.practical_marks) + '" aria-label="Practical marks"></td>'
                    + '<td><input type="checkbox" data-absent' + (row.is_absent ? ' checked' : '') + ' aria-label="Absent"></td>'
                    + '<td>' + escape(row.grade || '—') + '</td>'
                    + '<td class="dash-hide-md"><input type="text" class="dash-inline-score dash-inline-feedback" data-remarks maxlength="255" value="' + escape(row.teacher_remarks || '') + '" aria-label="Remarks"></td></tr>';
            }).join('') || '<tr><td colspan="6" class="dash-muted">No students.</td></tr>';
        };

        const load = function () {
            if (!paperSelect.value || !sectionSelect.value) {
                body.innerHTML = '<tr><td colspan="6" class="dash-muted">No paper or section to show.</td></tr>';

                return;
            }

            ui.api('GET', marksRoot.dataset.sheetUrl + '?exam_subject_id=' + paperSelect.value + '&section_id=' + sectionSelect.value)
                .then(function (envelope) { render(envelope.data); })
                .catch(function (envelope) { body.innerHTML = '<tr><td colspan="6" class="dash-muted">' + escape(fieldError(envelope, 'Could not open the marks sheet.')) + '</td></tr>'; });
        };

        marksRoot.querySelectorAll('[data-save-marks]').forEach(function (button) {
            button.addEventListener('click', function () {
                const rows = Array.prototype.map.call(body.querySelectorAll('tr[data-enrollment]'), function (tr) {
                    const number = function (selector) { const value = tr.querySelector(selector).value; return value === '' ? null : Number(value); };

                    return { enrollment_id: Number(tr.dataset.enrollment), theory_marks: number('[data-theory]'), practical_marks: number('[data-practical]'), is_absent: tr.querySelector('[data-absent]').checked, teacher_remarks: tr.querySelector('[data-remarks]').value.trim() || null };
                });

                ui.api('POST', marksRoot.dataset.saveUrl, {
                    exam_subject_id: Number(paperSelect.value),
                    section_id: Number(sectionSelect.value),
                    submit: button.dataset.saveMarks === '1',
                    reason: marksRoot.querySelector('[data-reason]').value.trim() || null,
                    rows: rows
                }).then(function (envelope) {
                    ui.toast(envelope.message);
                    render(envelope.data);
                }).catch(function (envelope) { ui.toast(fieldError(envelope, 'Could not save marks.'), 'error'); });
            });
        });

        const approve = marksRoot.querySelector('[data-approve-marks]');

        if (approve) {
            approve.addEventListener('click', function () {
                ui.api('POST', marksRoot.dataset.approveUrl, { exam_subject_id: Number(paperSelect.value), section_id: Number(sectionSelect.value) })
                    .then(function (envelope) { ui.toast(envelope.message); load(); })
                    .catch(function (envelope) { ui.toast(fieldError(envelope, 'Could not approve.'), 'error'); });
            });
        }

        paperSelect.addEventListener('change', function () { syncSections(); load(); });
        sectionSelect.addEventListener('change', load);
        syncSections();
        load();
    }

    /* ---------- Setup lists ---------- */

    const setupRoot = document.querySelector('[data-exam-setup]');

    if (setupRoot && window.PinnacleGrid) {
        const type = setupRoot.dataset.examSetup;
        const SPECS = {
            terms: { name: 'text', academic_session_id: 'int', weightage_percent: 'number', starts_on: 'text', ends_on: 'text', sort_order: 'int', is_final: 'bool' },
            scales: { name: 'text', is_default: 'bool' },
            halls: { name: 'text', capacity: 'int', rows: 'int', columns: 'int', is_active: 'bool' },
            skills: { name: 'text', skill_group_id: 'int', sort_order: 'int', is_active: 'bool' },
            rules: { name: 'text', metric: 'text', operator: 'text', threshold: 'number', notify_class_teacher: 'bool', notify_parents: 'bool', is_active: 'bool' }
        };
        const active = function (value, t) { return t === 'display' ? (value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge dash-badge-system">Inactive</span>') : value; };
        const COLUMNS = {
            terms: [
                { data: 'name', render: function (value, t, row) { return t === 'display' ? '<span class="dash-cell-title">' + escape(value) + '</span>' + (row.is_final ? '<span class="dash-muted">final term</span>' : '') : value; } },
                { data: 'starts_on', searchable: false, className: 'dash-hide-md', render: function (value, t, row) { return t === 'display' ? escape((value || '—') + ' → ' + (row.ends_on || '—')) : value; } },
                { data: 'weightage_percent', searchable: false, render: function (value, t) { return t === 'display' ? (value === null ? '—' : escape(value + '%')) : value; } }
            ],
            scales: [
                { data: 'name' },
                { data: 'bands', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '' },
                { data: 'is_default', searchable: false, render: function (value, t) { return t === 'display' ? (value ? '<span class="dash-badge dash-badge-ok">Default</span>' : '') : value; } }
            ],
            halls: [
                { data: 'name' },
                { data: 'rows', searchable: false, className: 'dash-hide-md', render: function (value, t, row) { return t === 'display' ? (value ? escape(value + ' rows × ' + (row.columns || '?')) : '—') : value; } },
                { data: 'capacity', searchable: false, className: 'dash-col-num' }
            ],
            skills: [
                { data: 'name' },
                { data: 'sort_order', searchable: false, className: 'dash-hide-md' },
                { data: 'is_active', searchable: false, render: active }
            ],
            rules: [
                { data: 'name' },
                { data: 'metric', searchable: false, className: 'dash-hide-md', render: function (value, t, row) { return t === 'display' ? escape(headline(value) + ' ' + row.operator + ' ' + Number(row.threshold)) : value; } },
                { data: 'is_active', searchable: false, render: active }
            ]
        };
        const modal = document.getElementById('setupModal');
        const bands = modal.querySelector('[data-bands]');
        const bandTemplate = modal.querySelector('[data-band-template]');

        const addBand = function (item) {
            const line = bandTemplate.content.firstElementChild.cloneNode(true);

            ['grade', 'min_percent', 'max_percent', 'grade_point', 'remark'].forEach(function (name) {
                if (item && item[name] !== null && item[name] !== undefined) {
                    line.querySelector('[data-band-field="' + name + '"]').value = item[name];
                }
            });
            bands.appendChild(line);
        };

        kit.mount({
            root: setupRoot,
            modal: modal,
            order: [[0, 'asc']],
            titles: { create: 'Add', edit: 'Edit' },
            emptyText: 'Nothing here yet.',
            nameOf: function (row) { return row.name; },
            columns: COLUMNS[type],
            fill: function (form, row) {
                kit.fillForm(form, row, SPECS[type], { academic_session_id: setupRoot.dataset.sessionId, is_active: true, notify_class_teacher: true, metric: 'attendance_percent', operator: '<' });

                if (bands) {
                    bands.innerHTML = '';
                    (row && row.items ? row.items : [null]).forEach(addBand);
                }
            },
            payload: function (form) {
                const body = kit.readForm(form, SPECS[type]);

                if (bands) {
                    body.items = Array.prototype.map.call(bands.querySelectorAll('[data-band]'), function (line) {
                        const value = function (name) { return line.querySelector('[data-band-field="' + name + '"]').value.trim(); };

                        return { grade: value('grade'), min_percent: value('min_percent') === '' ? null : Number(value('min_percent')), max_percent: value('max_percent') === '' ? null : Number(value('max_percent')), grade_point: value('grade_point') === '' ? null : Number(value('grade_point')), remark: value('remark') || null };
                    });
                }

                return body;
            }
        });

        if (bands) {
            modal.querySelector('[data-add-band]').addEventListener('click', function () { addBand(null); });
            bands.addEventListener('click', function (event) {
                if (event.target.closest('[data-remove-band]')) {
                    event.target.closest('[data-band]').remove();
                }
            });
        }
    }
})();

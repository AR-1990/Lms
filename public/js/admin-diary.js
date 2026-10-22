/**
 * Daily diary page (entries, note, student remarks, publish, who has not signed) and homework
 * (set, see submissions, grade).
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
    const dateFormat = new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
    const shortDate = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short' });

    function filterSubjects(select, classId) {
        Array.prototype.forEach.call(select.options, function (option) {
            option.hidden = option.value !== '' && option.dataset.classId !== String(classId);
        });

        if (select.selectedOptions[0] && select.selectedOptions[0].hidden) {
            select.value = select.querySelector('option:not([hidden])') ? select.querySelector('option:not([hidden])').value : '';
        }
    }

    /* ---------- Daily diary ---------- */

    const diary = document.querySelector('[data-diary]');

    if (diary) {
        const sectionSelect = diary.querySelector('[data-section]');
        const dateInput = diary.querySelector('[data-date]');
        const entriesList = diary.querySelector('[data-entries]');
        const note = diary.querySelector('[data-note]');
        const publishButton = diary.querySelector('[data-publish]');
        const entryDialog = document.getElementById('entryModal');
        const entryForm = entryDialog.querySelector('form');
        const remarkDialog = document.getElementById('remarkModal');
        const remarkForm = remarkDialog.querySelector('form');
        let page = null;

        const render = function () {
            diary.querySelector('[data-diary-date]').textContent = dateFormat.format(new Date(dateInput.value));
            diary.querySelector('[data-diary-status]').innerHTML = page.status === 'published'
                ? '<span class="dash-badge dash-badge-ok">Published</span>'
                : (page.status === 'draft' ? '<span class="dash-badge dash-badge-warn">Draft</span>' : '<span class="dash-badge dash-badge-system">Empty</span>');
            publishButton.hidden = page.status === 'published';
            note.value = page.note || '';

            entriesList.innerHTML = page.entries.length ? page.entries.map(function (entry) {
                return '<li class="dash-diary-entry" data-entry="' + entry.id + '">'
                    + '<div class="dash-diary-entry-head"><span class="dash-badge dash-badge-brand">' + escape(entry.entry_type) + '</span> <strong>' + escape(entry.subject || 'General') + '</strong>'
                    + (entry.teacher ? ' <span class="dash-muted">· ' + escape(entry.teacher) + '</span>' : '')
                    + '<button type="button" class="dash-action dash-action-delete" data-delete-entry data-tooltip="Delete" aria-label="Delete entry"><svg class="dash-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 21-4.3-4.3a2.4 2.4 0 0 1 0-3.4l9.6-9.6a2.4 2.4 0 0 1 3.4 0l5.6 5.6a2.4 2.4 0 0 1 0 3.4L13 21"/><path d="M22 21H7"/><path d="m5 11 9 9"/></svg></button></div>'
                    + (entry.title ? '<p class="dash-diary-entry-title">' + escape(entry.title) + '</p>' : '')
                    + '<p>' + escape(entry.details) + '</p>'
                    + (entry.due_on ? '<p class="dash-muted">Due ' + escape(shortDate.format(new Date(entry.due_on))) + (entry.homework_id ? ' · gradable homework' : '') + '</p>' : '')
                    + '</li>';
            }).join('') : '<li class="dash-muted dash-diary-empty">Nothing written for this day yet.</li>';

            diary.querySelector('[data-remarks-wrap]').hidden = page.remarks.length === 0;
            diary.querySelector('[data-remarks]').innerHTML = page.remarks.map(function (remark) {
                return '<li><span>' + escape(remark.student) + (remark.type ? ' · ' + escape(remark.type) : '') + '</span><strong>' + escape(remark.remark) + '</strong></li>';
            }).join('');

            diary.querySelector('[data-unsigned-wrap]').hidden = page.status !== 'published';
            diary.querySelector('[data-unsigned]').textContent = page.unsigned.length ? page.unsigned.map(function (student) { return student.name; }).join(', ') : 'Every parent has signed.';

            remarkForm.querySelector('[data-remark-students]').innerHTML = page.students.map(function (student) {
                return '<option value="' + student.student_id + '">' + escape(student.name) + '</option>';
            }).join('');
        };

        const load = function () {
            if (!sectionSelect.value) {
                entriesList.innerHTML = '<li class="dash-muted">You are not assigned to any section.</li>';

                return;
            }

            ui.api('GET', diary.dataset.pageUrl + '?section_id=' + sectionSelect.value + '&date=' + dateInput.value).then(function (envelope) {
                page = envelope.data;
                render();
            }).catch(function (envelope) {
                ui.toast(kit.messageOf(envelope, 'Could not open the diary.'), 'error');
            });
        };

        kit.wireDialog(entryDialog);
        kit.wireDialog(remarkDialog);

        document.querySelector('[data-open-entry]').addEventListener('click', function () {
            entryForm.reset();
            kit.clearErrors(entryForm);
            filterSubjects(entryForm.elements.subject_id, sectionSelect.selectedOptions[0].dataset.classId);
            entryForm.elements.subject_id.value = '';
            entryDialog.showModal();
        });

        entryForm.addEventListener('submit', function (event) {
            event.preventDefault();

            kit.submitForm(entryForm, 'POST', diary.dataset.entriesUrl, Object.assign(kit.readForm(entryForm, {
                entry_type_id: 'int', subject_id: 'int', title: 'text', details: 'text', due_on: 'text', max_marks: 'int', create_homework: 'bool', allow_submission: 'bool'
            }), { section_id: Number(sectionSelect.value), diary_date: dateInput.value })).then(function () {
                entryDialog.close();
                load();
            }).catch(function () {});
        });

        document.querySelector('[data-open-remark]').addEventListener('click', function () {
            if (!page || !page.id) {
                ui.toast('Add an entry or a note first, then remarks.', 'error');

                return;
            }

            remarkForm.reset();
            kit.clearErrors(remarkForm);
            remarkDialog.showModal();
        });

        remarkForm.addEventListener('submit', function (event) {
            event.preventDefault();

            kit.submitForm(remarkForm, 'POST', diary.dataset.diariesUrl + '/' + page.id + '/remarks', kit.readForm(remarkForm, { student_id: 'int', remark_type_id: 'int', remark: 'text' }))
                .then(function () {
                    remarkDialog.close();
                    load();
                })
                .catch(function () {});
        });

        diary.querySelector('[data-save-note]').addEventListener('click', function () {
            ui.api('PUT', diary.dataset.noteUrl, { section_id: Number(sectionSelect.value), date: dateInput.value, note: note.value.trim() || null })
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    load();
                })
                .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not save the note.'), 'error'); });
        });

        publishButton.addEventListener('click', function () {
            if (!page || !page.id) {
                ui.toast('Add at least one entry or a note before publishing.', 'error');

                return;
            }

            ui.api('POST', diary.dataset.diariesUrl + '/' + page.id + '/publish')
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    load();
                })
                .catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not publish.'), 'error'); });
        });

        entriesList.addEventListener('click', function (event) {
            const button = event.target.closest('[data-delete-entry]');

            if (!button) {
                return;
            }

            const item = button.closest('[data-entry]');

            ui.eraseRecord('this diary entry', item, diary.dataset.entriesUrl + '/' + item.dataset.entry).then(function (result) {
                if (result === 'deleted') {
                    load();
                }
            });
        });

        sectionSelect.addEventListener('change', load);
        dateInput.addEventListener('change', load);
        load();
    }

    /* ---------- Homework ---------- */

    const homework = document.querySelector('[data-homework]');

    if (homework && window.PinnacleGrid) {
        const SPEC = { section_id: 'int', subject_id: 'int', title: 'text', description: 'text', assigned_on: 'text', due_on: 'text', max_marks: 'int', allow_submission: 'bool' };
        const modal = document.getElementById('homeworkModal');
        const form = modal.querySelector('form');
        const submissions = document.getElementById('submissionsModal');
        const rowsBody = submissions.querySelector('[data-submission-rows]');
        const filter = homework.querySelector('[data-filter="section_id"]');
        const TONES = { pending: 'dash-badge-system', submitted: 'dash-badge-brand', late: 'dash-badge-warn', graded: 'dash-badge-ok' };
        let current = null;

        const syncSubjects = function () {
            filterSubjects(form.elements.subject_id, form.elements.section_id.selectedOptions[0] ? form.elements.section_id.selectedOptions[0].dataset.classId : '');
        };

        const openSubmissions = function (row) {
            current = row;
            submissions.querySelector('[data-submissions-title]').textContent = row.title;
            submissions.querySelector('[data-submissions-subtitle]').textContent = row.section_name + ' · ' + row.subject_name + (row.max_marks ? ' · out of ' + row.max_marks : '');

            ui.api('GET', homework.dataset.resourceUrl + '/' + row.id + '/submissions').then(function (envelope) {
                rowsBody.innerHTML = envelope.data.map(function (item) {
                    return '<tr data-student="' + item.student_id + '"><td><span class="dash-cell-title">' + escape(item.name) + '</span>' + (item.content ? '<span class="dash-muted">' + escape(item.content.slice(0, 80)) + '</span>' : '') + '</td>'
                        + '<td><span class="dash-badge ' + TONES[item.status] + '">' + escape(item.status) + '</span></td>'
                        + '<td><input type="number" min="0" step="0.5" class="dash-inline-score" data-marks value="' + (item.marks === null ? '' : item.marks) + '" aria-label="Marks"></td>'
                        + '<td class="dash-hide-md"><input type="text" class="dash-inline-score dash-inline-feedback" data-feedback maxlength="2000" value="' + escape(item.feedback || '') + '" aria-label="Feedback"></td>'
                        + '<td><button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-grade>Save</button></td></tr>';
                }).join('');
                submissions.showModal();
            });
        };

        const mounted = kit.mount({
            root: homework,
            modal: modal,
            order: [[2, 'desc']],
            titles: { create: 'Set homework', edit: 'Edit homework' },
            searchPlaceholder: 'Search title…',
            emptyText: 'No homework set yet.',
            params: function () { return { section_id: filter.value }; },
            nameOf: function (row) { return row.title; },
            onView: openSubmissions,
            columns: [
                { data: 'title', render: function (value, type, row) { return type === 'display' ? '<span class="dash-cell-title">' + escape(value) + '</span><span class="dash-muted">' + escape(row.subject_name) + '</span>' : value; } },
                { data: 'section_name', orderable: false, searchable: false, className: 'dash-hide-md' },
                { data: 'due_on', searchable: false, render: function (value, type) { return type === 'display' ? (value ? escape(shortDate.format(new Date(value))) : '—') : value; } },
                { data: 'submitted_count', searchable: false, className: 'dash-col-num' }
            ],
            fill: function (homeworkForm, row) {
                kit.fillForm(homeworkForm, row, SPEC, { assigned_on: new Date().toISOString().slice(0, 10) });
                syncSubjects();

                if (row) {
                    homeworkForm.elements.subject_id.value = row.subject_id;
                }
            },
            payload: function (homeworkForm) {
                return kit.readForm(homeworkForm, SPEC);
            }
        });

        form.elements.section_id.addEventListener('change', syncSubjects);
        filter.addEventListener('change', function () { mounted.grid.ajax.reload(); });
        kit.wireDialog(submissions);

        rowsBody.addEventListener('click', function (event) {
            const button = event.target.closest('[data-grade]');

            if (!button) {
                return;
            }

            const tr = button.closest('tr');
            const marks = tr.querySelector('[data-marks]').value;

            ui.api('POST', homework.dataset.resourceUrl + '/' + current.id + '/grade', {
                student_id: Number(tr.dataset.student),
                marks: marks === '' ? null : Number(marks),
                feedback: tr.querySelector('[data-feedback]').value.trim() || null
            }).then(function (envelope) {
                ui.toast(envelope.message);
                tr.querySelector('.dash-badge').className = 'dash-badge dash-badge-ok';
                tr.querySelector('.dash-badge').textContent = 'graded';
                mounted.refresh();
            }).catch(function (envelope) {
                ui.toast(kit.messageOf(envelope, 'Could not grade.'), 'error');
            });
        });
    }
})();

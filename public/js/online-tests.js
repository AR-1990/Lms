/**
 * Exams → Online Tests (teachers and exam cell): grid, the test builder (questions with options and one
 * right answer), publish / close and the results popup. Runs on /api/erp/online-tests.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-online-tests]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;
    const esc = window.PinnacleGrid.escape;
    const modal = document.getElementById('onlineTestModal');
    const form = modal.querySelector('form');
    const list = form.querySelector('[data-ot-list]');
    const sectionSelect = form.querySelector('[data-ot-section]');
    const subjectSelect = form.querySelector('[data-ot-subject]');
    const paperSelect = form.querySelector('[data-ot-paper]');
    const questionTemplate = document.getElementById('otQuestionTemplate');
    const optionTemplate = document.getElementById('otOptionTemplate');
    const resultsDialog = document.getElementById('onlineTestResults');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    const statusBadge = { draft: '<span class="dash-badge">Draft</span>', published: '<span class="dash-badge dash-badge-ok">Published</span>', closed: '<span class="dash-badge dash-badge-warn">Closed</span>' };
    const spec = { title: 'text', instructions: 'text', section_id: 'int', subject_id: 'int', exam_subject_id: 'int', opens_at: 'text', closes_at: 'text', duration_minutes: 'int', shuffle_questions: 'bool', show_answers: 'bool' };
    let locked = false;

    function display(render) {
        return function (value, type, row) { return type === 'display' ? render(value, row) : value; };
    }

    /** An ISO time as the value of a datetime-local input, in the browser's own time. */
    function toLocalInput(iso) {
        if (!iso) {
            return '';
        }

        const date = new Date(iso);

        return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    }

    /** Show only the subjects of the chosen class, and the papers of that class and subject. */
    function filterChoices() {
        const sectionId = sectionSelect.value;
        const classId = sectionSelect.selectedOptions[0] ? sectionSelect.selectedOptions[0].dataset.classId : '';

        Array.prototype.forEach.call(subjectSelect.options, function (option) {
            if (option.value) {
                option.hidden = option.disabled = option.dataset.sectionId !== sectionId;
            }
        });

        if (subjectSelect.selectedOptions[0] && subjectSelect.selectedOptions[0].disabled) {
            subjectSelect.value = '';
        }

        subjectSelect.options[0].textContent = sectionId ? 'Choose…' : 'Choose a class first';

        Array.prototype.forEach.call(paperSelect.options, function (option) {
            if (option.value) {
                option.hidden = option.disabled = option.dataset.classId !== classId || option.dataset.subjectId !== subjectSelect.value;
            }
        });

        if (paperSelect.selectedOptions[0] && paperSelect.selectedOptions[0].disabled) {
            paperSelect.value = '';
        }
    }

    function renumber() {
        let total = 0;

        list.querySelectorAll('.dash-ot-question').forEach(function (item, index) {
            item.querySelector('[data-ot-number]').textContent = index + 1;
            item.querySelectorAll('[data-ot-correct]').forEach(function (radio) { radio.name = 'correct_' + index; });
            total += parseInt(item.querySelector('[data-ot-marks]').value, 10) || 0;
        });

        form.querySelector('[data-ot-total]').textContent = list.children.length ? '· ' + list.children.length + ' question(s), ' + total + ' marks' : '';
    }

    function addOption(item, option) {
        const row = optionTemplate.content.firstElementChild.cloneNode(true);
        row.querySelector('[data-ot-option]').value = option ? option.option_text : '';
        row.querySelector('[data-ot-correct]').checked = Boolean(option && option.is_correct);
        item.querySelector('[data-ot-options]').appendChild(row);
    }

    function addQuestion(question) {
        const item = questionTemplate.content.firstElementChild.cloneNode(true);
        item.querySelector('[data-ot-question]').value = question ? question.question : '';
        item.querySelector('[data-ot-marks]').value = question ? question.marks : 1;
        list.appendChild(item);
        (question ? question.options : [null, null, null, null]).forEach(function (option) { addOption(item, option); });
        renumber();

        return item;
    }

    function setLocked(isLocked) {
        locked = isLocked;
        form.querySelector('[data-ot-locked]').hidden = !isLocked;
        form.querySelector('[data-ot-tools]').hidden = isLocked;
        list.querySelectorAll('input, textarea, button').forEach(function (control) { control.disabled = isLocked; });
        sectionSelect.disabled = subjectSelect.disabled = isLocked;
    }

    function readQuestions() {
        return Array.prototype.map.call(list.querySelectorAll('.dash-ot-question'), function (item) {
            return {
                question: item.querySelector('[data-ot-question]').value.trim(),
                marks: parseInt(item.querySelector('[data-ot-marks]').value, 10) || 1,
                options: Array.prototype.map.call(item.querySelectorAll('.dash-ot-option'), function (row) {
                    return { option_text: row.querySelector('[data-ot-option]').value.trim(), is_correct: row.querySelector('[data-ot-correct]').checked };
                })
            };
        });
    }

    /* ---------- Question bank: copy from an earlier test, or a CSV of questions ---------- */

    const copySelect = form.querySelector('[data-ot-copy]');
    const csvInput = form.querySelector('[data-ot-csv]');
    form.querySelector('[data-ot-template]').href = root.dataset.templateUrl;

    function appendQuestions(questions) {
        // A builder that only has the empty starter question takes the new ones in its place.
        const items = list.querySelectorAll('.dash-ot-question');

        if (items.length === 1 && !items[0].querySelector('[data-ot-question]').value.trim()) {
            items[0].remove();
        }

        questions.forEach(addQuestion);
        renumber();
    }

    function loadCopyList() {
        ui.api('GET', root.dataset.datatableUrl + '?per_page=100').then(function (envelope) {
            copySelect.innerHTML = '<option value="">Copy from a test…</option>' + envelope.data.items.filter(function (test) { return test.questions_count > 0; }).map(function (test) {
                return '<option value="' + test.id + '">' + esc(test.title) + ' — ' + esc((test.class_name || '') + ' ' + (test.section_name || '')) + ' · ' + esc(test.subject_name || '') + ' (' + test.questions_count + ')</option>';
            }).join('');
        }).catch(function () {});
    }

    copySelect.addEventListener('change', function () {
        if (!copySelect.value) {
            return;
        }

        ui.api('GET', root.dataset.resourceUrl + '/' + copySelect.value).then(function (envelope) {
            appendQuestions(envelope.data.questions.map(function (question) {
                return { question: question.question, marks: question.marks, options: question.options.map(function (option) { return { option_text: option.option_text, is_correct: option.is_correct }; }) };
            }));
            ui.toast(envelope.data.questions.length + ' question(s) copied. Edit them if needed, then save.');
        }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not copy the questions.', 'error'); })
            .finally(function () { copySelect.value = ''; });
    });

    csvInput.addEventListener('change', function () {
        if (!csvInput.files[0]) {
            return;
        }

        const body = new window.FormData();
        body.append('file', csvInput.files[0]);
        body.append('save', '0');
        ui.api('POST', root.dataset.importUrl, body).then(function (envelope) {
            appendQuestions(envelope.data.questions);
            ui.toast(envelope.message);
        }).catch(function (envelope) {
            const rows = envelope && envelope.data && envelope.data.errors ? envelope.data.errors.slice(0, 5).map(function (error) { return 'Row ' + error.row + ': ' + error.messages.join(' '); }).join(' · ') : '';
            ui.toast(rows || (envelope && envelope.message) || 'Could not read the file.', 'error');
        }).finally(function () { csvInput.value = ''; });
    });

    sectionSelect.addEventListener('change', filterChoices);
    subjectSelect.addEventListener('change', filterChoices);
    form.querySelector('[data-ot-add-question]').addEventListener('click', function () {
        addQuestion(null).querySelector('[data-ot-question]').focus();
    });

    list.addEventListener('click', function (event) {
        const item = event.target.closest('.dash-ot-question');

        if (event.target.closest('[data-ot-remove-question]')) {
            item.remove();
            renumber();
        } else if (event.target.closest('[data-ot-add-option]') && item.querySelectorAll('.dash-ot-option').length < 6) {
            addOption(item, null);
            renumber();
        } else if (event.target.closest('[data-ot-remove-option]') && item.querySelectorAll('.dash-ot-option').length > 2) {
            event.target.closest('.dash-ot-option').remove();
        }
    });
    list.addEventListener('input', function (event) {
        if (event.target.matches('[data-ot-marks]')) {
            renumber();
        }
    });

    const mounted = kit.mount({
        root: root,
        modal: modal,
        order: [],
        titles: { create: 'New online test', edit: 'Edit online test' },
        searchPlaceholder: 'Search tests…',
        emptyText: 'No online tests yet.',
        nameOf: function (row) { return row.title; },
        canDelete: function (row) { return row.submitted_count === 0; },
        columns: [
            { data: 'title', render: display(function (value, row) {
                const actions = (row.status !== 'published' ? '<button type="button" class="dash-link-btn" data-status="published" data-id="' + row.id + '">Publish</button>' : '<button type="button" class="dash-link-btn" data-status="closed" data-id="' + row.id + '">Close</button>')
                    + '<button type="button" class="dash-link-btn" data-results="' + row.id + '">Results</button>';

                return '<span class="dash-cell-title">' + esc(value) + '</span><span class="dash-cell-sub">' + (row.total_marks || 0) + ' marks · ' + row.duration_minutes + ' min</span>' + actions;
            }) },
            { data: 'class_name', name: 'class_name', render: display(function (value, row) { return esc((value || '') + ' ' + (row.section_name || '')); }) },
            { data: 'subject_name', name: 'subject_name', className: 'dash-hide-md' },
            { data: 'questions_count', searchable: false, orderable: false, className: 'dash-col-num' },
            { data: 'submitted_count', searchable: false, orderable: false, className: 'dash-col-num' },
            { data: 'closes_at', searchable: false, className: 'dash-hide-md', render: display(function (value) { return value ? esc(dateFormat.format(new Date(value))) : '<span class="dash-muted">No end</span>'; }) },
            { data: 'status', searchable: false, render: display(function (value) { return statusBadge[value] || esc(value); }) }
        ],
        loadRow: function (row) {
            return ui.api('GET', root.dataset.resourceUrl + '/' + row.id).then(function (envelope) {
                return Object.assign({}, envelope.data, { submitted_count: row.submitted_count });
            });
        },
        fill: function (target, row) {
            kit.fillForm(target, row ? Object.assign({}, row, { opens_at: toLocalInput(row.opens_at), closes_at: toLocalInput(row.closes_at) }) : null, spec, { duration_minutes: 30, shuffle_questions: true });
            filterChoices();

            if (row) {
                subjectSelect.value = row.subject_id;
                filterChoices();
                paperSelect.value = row.exam_subject_id || '';
            }

            list.innerHTML = '';
            (row ? row.questions : [null]).forEach(addQuestion);
            setLocked(Boolean(row && row.submitted_count > 0));
            loadCopyList();
        },
        payload: function (target) {
            const body = kit.readForm(target, spec);

            if (!locked) {
                body.questions = readQuestions();
            } else {
                body.section_id = parseInt(sectionSelect.value, 10);
                body.subject_id = parseInt(subjectSelect.value, 10);
            }

            return body;
        }
    });

    kit.wireDialog(resultsDialog);

    root.addEventListener('click', function (event) {
        const statusButton = event.target.closest('[data-status]');
        const resultsButton = event.target.closest('[data-results]');

        if (statusButton) {
            statusButton.disabled = true;
            ui.api('POST', root.dataset.resourceUrl + '/' + statusButton.dataset.id + '/status', { status: statusButton.dataset.status })
                .then(function (envelope) { ui.toast(envelope.message); mounted.refresh(); })
                .catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not change the test.', 'error'); statusButton.disabled = false; });
        }

        if (resultsButton) {
            ui.api('GET', root.dataset.resourceUrl + '/' + resultsButton.dataset.results + '/results').then(function (envelope) {
                const data = envelope.data;
                const done = data.rows.filter(function (row) { return row.status === 'submitted'; });
                const average = done.length ? done.reduce(function (sum, row) { return sum + row.score; }, 0) / done.length : null;

                resultsDialog.querySelector('[data-results-title]').textContent = data.test.title;
                resultsDialog.querySelector('[data-results-summary]').textContent = done.length + ' of ' + data.rows.length + ' submitted' + (average === null ? '' : ' · average ' + average.toFixed(1) + ' / ' + data.test.total_marks);
                resultsDialog.querySelector('[data-results-rows]').innerHTML = data.rows.map(function (row) {
                    const status = row.status === 'submitted' ? '<span class="dash-badge dash-badge-ok">Submitted</span>' : (row.status === 'in_progress' ? '<span class="dash-badge dash-badge-warn">In progress</span>' : '<span class="dash-badge">Not attempted</span>');

                    return '<tr><td>' + esc(row.roll_no || '') + '</td><td>' + esc(row.name) + '<span class="dash-cell-sub">' + esc(row.admission_no || '') + '</span></td><td class="dash-col-num">' + (row.score === null ? '—' : row.score + ' / ' + data.test.total_marks) + '</td><td>' + status + '</td></tr>';
                }).join('') || '<tr><td colspan="4" class="dash-muted">No students in this class.</td></tr>';
                resultsDialog.showModal();
            }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not load the results.', 'error'); });
        }
    });
})();

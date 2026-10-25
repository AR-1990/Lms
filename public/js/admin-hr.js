/**
 * HR screens: employees, teacher assignments, candidates, job openings, departments, designations.
 * Grids and popups use PinnacleResource over the /api/erp HR endpoints. Salaries are paisa in the API.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-hr]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const dateTimeFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    const modal = document.getElementById('hrModal');
    const detail = document.getElementById('detailModal');
    const filters = Array.prototype.slice.call(root.querySelectorAll('[data-filter]'));
    const resourceUrl = root.dataset.resourceUrl;
    const STATUS_TONES = { active: 'dash-badge-ok', hired: 'dash-badge-ok', open: 'dash-badge-ok', offered: 'dash-badge-brand', interview: 'dash-badge-brand', demo: 'dash-badge-brand', shortlisted: 'dash-badge-brand', on_leave: 'dash-badge-warn', on_hold: 'dash-badge-warn', suspended: 'dash-badge-warn', draft: 'dash-badge-warn' };

    function headline(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
    }

    function badge(status) {
        return '<span class="dash-badge ' + (STATUS_TONES[status] || 'dash-badge-system') + '">' + esc(headline(status)) + '</span>';
    }

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function titleCell(title, subtitle) {
        return '<span class="dash-cell-title">' + esc(title) + '</span>' + (subtitle ? '<span class="dash-muted">' + esc(subtitle) + '</span>' : '');
    }

    function shortDate(value) {
        return value ? esc(dateFormat.format(new Date(value))) : '—';
    }

    function today() {
        const now = new Date();

        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    }

    function kv(items) {
        return '<ul class="dash-kv">' + items.filter(function (item) {
            return item[1] !== null && item[1] !== undefined && item[1] !== '';
        }).map(function (item) {
            return '<li><span>' + esc(item[0]) + '</span><strong>' + (item[2] ? item[1] : esc(item[1])) + '</strong></li>';
        }).join('') + '</ul>';
    }

    function filterValues() {
        const values = {};

        filters.forEach(function (field) {
            values[field.dataset.filter] = field.value;
        });

        return values;
    }

    function toggleCreateOnly(form, creating) {
        form.querySelectorAll('[data-create-only]').forEach(function (element) {
            element.hidden = !creating;
            element.querySelectorAll('input, select').forEach(function (input) {
                input.disabled = !creating;
            });
        });
    }

    /* ---------- Screen descriptions ---------- */

    const EMPLOYEE_FIELDS = {
        first_name: 'text', middle_name: 'text', last_name: 'text', father_or_spouse_name: 'text', gender_id: 'int', date_of_birth: 'text',
        cnic: 'text', phone: 'text', email: 'text', department_id: 'int', designation_id: 'int', work_shift_id: 'int', employment_type_id: 'int', joining_date: 'text',
        probation_end_date: 'text', city: 'text', bank_id: 'int', bank_account_no: 'text', ntn: 'text', current_address: 'text', is_teaching_staff: 'bool'
    };

    const SCREENS = {
        employees: {
            titles: { create: 'Add employee', edit: 'Edit employee' },
            order: [[0, 'asc']],
            nameOf: function (row) { return row.full_name; },
            loadRow: function (row) {
                return ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; });
            },
            onView: openEmployee,
            columns: [
                { data: 'full_name', name: 'full_name', render: display(function (value, row) { return titleCell(value, row.employee_code + ' · ' + (row.designation_name || '')); }) },
                { data: 'department_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'phone', defaultContent: '—' },
                { data: 'joining_date', searchable: false, className: 'dash-hide-md', render: display(shortDate) },
                { data: 'status', searchable: false, render: display(badge) }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row, EMPLOYEE_FIELDS, { joining_date: today() });
                toggleCreateOnly(form, !row);
                form.elements.portal_role.value = '';
                form.elements.portal_password.value = '';
            },
            payload: function (form, row) {
                const body = kit.readForm(form, EMPLOYEE_FIELDS);

                // New staff: unless ticked, the designation decides whether they teach.
                if (!row && !body.is_teaching_staff) {
                    delete body.is_teaching_staff;
                }

                if (!row && form.elements.portal_role.value) {
                    body.portal_role = form.elements.portal_role.value;
                    body.portal_password = form.elements.portal_password.value;
                }

                return body;
            }
        },

        departments: {
            titles: { create: 'Add department', edit: 'Edit department' },
            order: [[0, 'asc']],
            canDelete: function (row) { return row.employees_count > 0 || row.designations_count > 0 ? 'Has staff or designations' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.code); }) },
                { data: 'head_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—', render: display(function (value) { return esc(value || '—'); }) },
                { data: 'employees_count', searchable: false, className: 'dash-col-num' },
                { data: 'is_active', searchable: false, render: display(function (value) { return badge(value ? 'active' : 'inactive'); }) }
            ],
            fields: { name: 'text', code: 'text', head_employee_id: 'int', is_active: 'bool' }
        },

        designations: {
            titles: { create: 'Add designation', edit: 'Edit designation' },
            order: [[0, 'asc']],
            canDelete: function (row) { return row.employees_count > 0 ? 'Has staff' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.grade_name); }) },
                { data: 'department_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'employees_count', searchable: false, className: 'dash-col-num' },
                { data: 'is_teaching', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge dash-badge-brand">Teaching</span>' : '<span class="dash-badge">Non-teaching</span>'; }) }
            ],
            fields: { name: 'text', department_id: 'int', grade_id: 'int', is_teaching: 'bool', is_active: 'bool' }
        },

        shifts: {
            titles: { create: 'Add shift', edit: 'Edit shift' },
            order: [[0, 'asc']],
            canDelete: function (row) { return row.employees_count > 0 ? 'Has staff' : true; },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(value, row.is_default ? 'Default — staff without a shift follow it' : ''); }) },
                { data: 'starts_at', searchable: false, render: display(function (value, row) { return esc(String(value).slice(0, 5) + ' – ' + String(row.ends_at).slice(0, 5)); }) },
                { data: 'grace_minutes', searchable: false, className: 'dash-col-num', render: display(function (value) { return esc(value + ' min'); }) },
                { data: 'employees_count', searchable: false, className: 'dash-col-num dash-hide-md' },
                { data: 'is_active', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge dash-badge-ok">Active</span>' : '<span class="dash-badge">Off</span>'; }) }
            ],
            fields: { name: 'text', starts_at: 'text', ends_at: 'text', grace_minutes: 'int', is_default: 'bool', is_active: 'bool' },
            defaults: { starts_at: '08:00', ends_at: '14:30', grace_minutes: 10, is_active: true },
            payload: function (form) {
                const body = kit.readForm(form, SCREENS.shifts.fields);
                body.starts_at = String(body.starts_at || '').slice(0, 5);
                body.ends_at = String(body.ends_at || '').slice(0, 5);

                return body;
            }
        },

        assignments: {
            titles: { create: 'Assign teacher', edit: 'Edit assignment' },
            order: [[0, 'asc']],
            nameOf: function (row) { return row.subject_name + ' — ' + row.class_name + ' ' + row.section_name; },
            columns: [
                { data: 'class_name', name: 'class_name', render: display(function (value, row) { return titleCell(value + ' ' + row.section_name, row.is_class_teacher ? 'Class teacher' : ''); }) },
                { data: 'subject_name', name: 'subject_name' },
                { data: 'teacher_name', name: 'teacher_name', render: display(function (value, row) { return titleCell(value, row.employee_code); }) },
                { data: 'periods_per_week', name: 'teacher_assignments.periods_per_week', searchable: false, className: 'dash-col-num dash-hide-md', defaultContent: '—' }
            ],
            fields: { employee_id: 'int', section_id: 'int', subject_id: 'int', periods_per_week: 'int', is_class_teacher: 'bool' },
            extra: function () { return { academic_session_id: Number(root.dataset.sessionId) }; }
        },

        openings: {
            titles: { create: 'Add opening', edit: 'Edit opening' },
            order: [[0, 'asc']],
            nameOf: function (row) { return row.title; },
            canDelete: function (row) { return row.applications_count > 0 ? 'Has candidates' : true; },
            columns: [
                { data: 'title', render: display(function (value, row) { return titleCell(value, [row.designation_name, row.department_name].filter(Boolean).join(' · ')); }) },
                { data: 'closes_on', searchable: false, className: 'dash-hide-md', render: display(shortDate) },
                { data: 'positions', searchable: false, className: 'dash-col-num', render: display(function (value, row) { return esc(row.hired_count + ' / ' + value); }) },
                { data: 'applications_count', searchable: false, className: 'dash-col-num' },
                { data: 'status', searchable: false, render: display(badge) }
            ],
            fields: {
                title: 'text', designation_id: 'int', department_id: 'int', employment_type_id: 'int', positions: 'int', salary_min: 'money', salary_max: 'money',
                opens_on: 'text', closes_on: 'text', status: 'text', subjects_note: 'text', requirements: 'text', is_published: 'bool'
            },
            defaults: { positions: 1, status: 'open' }
        },

        candidates: {
            titles: { create: 'Add candidate', edit: 'Edit candidate' },
            order: [[0, 'asc']],
            nameOf: function (row) { return row.full_name; },
            canDelete: function (row) { return row.status === 'hired' ? 'Hired' : true; },
            loadRow: function (row) {
                return ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; });
            },
            onView: openCandidate,
            columns: [
                { data: 'full_name', name: 'full_name', render: display(function (value, row) { return titleCell(value, row.application_no); }) },
                { data: 'opening_title', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—' },
                { data: 'phone' },
                { data: 'status', searchable: false, render: display(badge) }
            ],
            fields: {
                first_name: 'text', last_name: 'text', gender_id: 'int', phone: 'text', email: 'text', cnic: 'text', highest_qualification_id: 'int',
                experience_years: 'number', source_id: 'int', current_employer: 'text', expected_salary: 'money', rating: 'int', remarks: 'text'
            },
            createOnly: { job_opening_id: 'int' }
        }
    };

    const screen = SCREENS[root.dataset.hr];

    if (!screen) {
        return;
    }

    const mounted = kit.mount({
        root: root,
        modal: modal,
        order: screen.order,
        columns: screen.columns,
        titles: screen.titles,
        canDelete: screen.canDelete,
        loadRow: screen.loadRow,
        onView: screen.onView,
        searchPlaceholder: 'Search…',
        emptyText: 'Nothing here yet.',
        nameOf: screen.nameOf || function (row) { return row.name; },
        params: filters.length ? filterValues : null,
        fill: screen.fill || function (form, row) {
            kit.fillForm(form, row, Object.assign({}, screen.fields, screen.createOnly || {}), screen.defaults);
            toggleCreateOnly(form, !row);
        },
        payload: screen.payload || function (form, row) {
            return Object.assign(kit.readForm(form, screen.fields), row ? {} : kit.readForm(form, screen.createOnly || {}), screen.extra ? screen.extra() : {});
        }
    });

    filters.forEach(function (field) {
        field.addEventListener('change', function () {
            mounted.grid.ajax.reload();
        });
    });

    /* Department head picker: current employees, loaded once. */
    const employeeSelect = document.querySelector('[data-employee-select]');

    if (employeeSelect) {
        ui.api('GET', '/api/erp/employees?per_page=100&status=active').then(function (envelope) {
            envelope.data.items.forEach(function (employee) {
                employeeSelect.insertAdjacentHTML('beforeend', '<option value="' + employee.id + '">' + esc(employee.full_name + ' (' + employee.employee_code + ')') + '</option>');
            });
        });
    }

    /* ---------- Employee detail: service record, status, portal login ---------- */

    function openEmployee(row) {
        ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) {
            renderEmployee(envelope.data);

            if (!detail.open) {
                detail.showModal();
            }
        });
    }

    function renderEmployee(employee) {
        detail.dataset.id = employee.id;
        detail.querySelector('[data-detail-title]').textContent = employee.full_name;
        detail.querySelector('[data-detail-subtitle]').textContent = employee.employee_code + ' · ' + (employee.designation_name || '') + (employee.department_name ? ' · ' + employee.department_name : '');

        const record = (employee.service_record || []).map(function (item) {
            return '<li><span>' + shortDate(item.effective_on) + '</span><strong>' + esc(headline(item.field)) + ': ' + esc(item.old_value ? headline(item.old_value) + ' → ' : '') + esc(headline(item.new_value)) + (item.remarks ? ' <span class="dash-muted">' + esc(item.remarks) + '</span>' : '') + '</strong></li>';
        }).join('');

        detail.querySelector('[data-detail-body]').innerHTML = kv([
            ['Status', badge(employee.status), true],
            ['Phone', employee.phone],
            ['Email', employee.email],
            ['CNIC', employee.cnic],
            ['Joined', shortDate(employee.joining_date), true],
            ['Employment', employee.employment_type_name],
            ['Portal login', employee.portal_login ? esc(employee.portal_login.username) + (employee.portal_login.is_active ? '' : ' (disabled)') : 'None', true]
        ]) + '<h4 class="dash-detail-heading">Service record</h4><ul class="dash-kv">' + (record || '<li><span>—</span><strong>No entries</strong></li>') + '</ul>';

        const statusForm = detail.querySelector('[data-status-form]');
        statusForm.elements.status.value = employee.status;
        statusForm.elements.effective_on.value = today();
        statusForm.elements.remarks.value = '';
        detail.querySelector('[data-portal-form]').hidden = Boolean(employee.portal_login);
    }

    if (detail && root.dataset.hr === 'employees') {
        kit.wireDialog(detail);

        const statusForm = detail.querySelector('[data-status-form]');
        statusForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(statusForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/status', kit.readForm(statusForm, { status: 'text', effective_on: 'text', remarks: 'text' }))
                .then(function (envelope) {
                    renderEmployee(envelope.data);
                    mounted.refresh();
                })
                .catch(function () {});
        });

        const portalForm = detail.querySelector('[data-portal-form]');
        portalForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(portalForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/portal-account', kit.readForm(portalForm, { portal_role: 'text', portal_password: 'text' }))
                .then(function (envelope) {
                    portalForm.reset();
                    renderEmployee(envelope.data);
                })
                .catch(function () {});
        });
    }

    /* ---------- Candidate detail: next step, interviews, hire ---------- */

    function openCandidate(row) {
        ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) {
            renderCandidate(envelope.data);

            if (!detail.open) {
                detail.showModal();
            }
        });
    }

    function renderCandidate(candidate) {
        detail.dataset.id = candidate.id;
        detail.querySelector('[data-detail-title]').textContent = candidate.full_name;
        detail.querySelector('[data-detail-subtitle]').textContent = candidate.application_no + ' · ' + (candidate.opening_title || '');

        const interviews = (candidate.interviews || []).map(function (interview) {
            return '<li><span>Round ' + interview.round_no + (interview.round_type ? ' · ' + esc(interview.round_type) : '') + '</span><strong>'
                + esc(dateTimeFormat.format(new Date(interview.scheduled_at))) + ' — ' + badge(interview.status)
                + (interview.status === 'scheduled'
                    ? ' <input type="number" min="0" max="10" step="0.5" class="dash-inline-score" data-score-for="' + interview.id + '" placeholder="Score /10" aria-label="Score out of 10">'
                        + ' <button type="button" class="dash-link-btn" data-interview-done="' + interview.id + '" data-scheduled-at="' + esc(interview.scheduled_at) + '">Mark completed</button>'
                    : '')
                + (interview.overall_score ? ' <span class="dash-muted">Score ' + esc(interview.overall_score) + '</span>' : '') + '</strong></li>';
        }).join('');

        const history = (candidate.history || []).map(function (item) {
            return '<li><span>' + esc(item.at ? dateFormat.format(new Date(item.at)) : '') + '</span><strong>' + esc(headline(item.to_status)) + (item.remarks ? ' <span class="dash-muted">' + esc(item.remarks) + '</span>' : '') + '</strong></li>';
        }).join('');

        detail.querySelector('[data-detail-body]').innerHTML = kv([
            ['Status', badge(candidate.status), true],
            ['Phone', candidate.phone],
            ['Email', candidate.email],
            ['Experience', candidate.experience_years ? candidate.experience_years + ' years' : null],
            ['Expected salary', candidate.expected_salary ? money(candidate.expected_salary) : null],
            ['Employee', candidate.employee_code]
        ]) + '<h4 class="dash-detail-heading">Interviews</h4><ul class="dash-kv">' + (interviews || '<li><span>—</span><strong>None yet</strong></li>') + '</ul>'
            + '<h4 class="dash-detail-heading">History</h4><ul class="dash-kv">' + history + '</ul>';

        const closed = candidate.next_statuses.length === 0;
        detail.querySelector('[data-step-buttons]').innerHTML = candidate.next_statuses.map(function (status) {
            return '<button type="button" class="dash-btn ' + (status === 'rejected' || status === 'withdrawn' ? 'dash-btn-ghost' : 'dash-btn-primary') + ' dash-btn-sm" data-step="' + status + '">' + esc(headline(status)) + '</button>';
        }).join('');
        detail.querySelector('[data-step-form]').hidden = closed;
        detail.querySelector('[data-interview-form]').hidden = closed;

        const hireForm = detail.querySelector('[data-hire-form]');
        hireForm.hidden = candidate.status !== 'offered';
        hireForm.elements.joining_date.value = today();
    }

    if (detail && root.dataset.hr === 'candidates') {
        kit.wireDialog(detail);

        const stepForm = detail.querySelector('[data-step-form]');
        stepForm.addEventListener('click', function (event) {
            const button = event.target.closest('[data-step]');

            if (!button) {
                return;
            }

            kit.submitForm(stepForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/status', { status: button.dataset.step, remarks: stepForm.elements.remarks.value.trim() || null })
                .then(function (envelope) {
                    stepForm.elements.remarks.value = '';
                    renderCandidate(envelope.data);
                    mounted.refresh();
                })
                .catch(function () {});
        });

        const interviewForm = detail.querySelector('[data-interview-form]');
        interviewForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(interviewForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/interviews', kit.readForm(interviewForm, { round_type_id: 'int', scheduled_at: 'text', venue_or_link: 'text' }))
                .then(function () {
                    interviewForm.reset();
                    openCandidate({ id: detail.dataset.id });
                    mounted.refresh();
                })
                .catch(function () {});
        });

        detail.querySelector('[data-detail-body]').addEventListener('click', function (event) {
            const done = event.target.closest('[data-interview-done]');

            if (!done) {
                return;
            }

            const scoreInput = detail.querySelector('[data-score-for="' + done.dataset.interviewDone + '"]');
            const score = scoreInput ? scoreInput.value.trim() : '';

            ui.api('PUT', root.dataset.interviewsUrl + '/' + done.dataset.interviewDone, {
                status: 'completed',
                scheduled_at: done.dataset.scheduledAt,
                overall_score: score === '' ? null : Number(score)
            }).then(function (envelope) {
                ui.toast(envelope.message);
                openCandidate({ id: detail.dataset.id });
            }).catch(function (envelope) {
                ui.toast(kit.messageOf(envelope, 'Could not update the interview.'), 'error');
            });
        });

        const hireForm = detail.querySelector('[data-hire-form]');
        hireForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = kit.readForm(hireForm, { joining_date: 'text', designation_id: 'int', employment_type_id: 'int', gender_id: 'int', portal_role: 'text', portal_password: 'text' });

            Object.keys(body).forEach(function (key) {
                if (body[key] === null) {
                    delete body[key];
                }
            });

            kit.submitForm(hireForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/hire', body)
                .then(function () {
                    detail.close();
                    mounted.refresh();
                })
                .catch(function () {});
        });
    }
})();

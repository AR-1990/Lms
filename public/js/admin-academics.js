/**
 * Academic setup screens (sessions, classes, sections, subjects, curriculum, bell schedule).
 * Each screen is a PinnacleResource grid + paper popup over its /api/erp endpoint;
 * the panel's data-academics attribute picks the screen description below.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-academics]');

    if (!root || !window.PinnacleResource) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const esc = window.PinnacleGrid.escape;
    const modal = document.getElementById('academicsModal');
    const filters = Array.prototype.slice.call(root.querySelectorAll('[data-filter]'));
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

    /* ---------- Small form / cell helpers ---------- */

    function text(form, name) {
        return form.elements[name].value.trim() || null;
    }

    function number(form, name) {
        const value = form.elements[name].value.trim();

        return value === '' ? null : Number(value);
    }

    function set(form, name, value) {
        form.elements[name].value = value === null || value === undefined ? '' : value;
    }

    function filterValue(name) {
        const field = root.querySelector('[data-filter="' + name + '"]');

        return field ? field.value : '';
    }

    function badge(on, yes, no) {
        return '<span class="dash-badge ' + (on ? 'dash-badge-ok' : 'dash-badge-system') + '">' + (on ? yes : no) + '</span>';
    }

    function display(render) {
        return function (value, type, row) {
            return type === 'display' ? render(value, row) : value;
        };
    }

    function titleCell(title, subtitle) {
        return '<span class="dash-cell-title">' + esc(title) + '</span>' + (subtitle ? '<span class="dash-muted">' + esc(subtitle) + '</span>' : '');
    }

    function marks(row) {
        const total = (row.theory_max_marks || 0) + (row.practical_max_marks || 0);

        if (!total) {
            return '—';
        }

        return row.practical_max_marks ? esc(row.theory_max_marks || 0) + ' + ' + esc(row.practical_max_marks) + ' = ' + total : String(total);
    }

    const statusFilter = { is_active: function () { return filterValue('is_active'); } };
    const sessionClassFilter = {
        academic_session_id: function () { return filterValue('academic_session_id'); },
        class_id: function () { return filterValue('class_id'); }
    };

    /* ---------- Screen descriptions ---------- */

    const SCREENS = {
        sessions: {
            titles: { create: 'Add session', edit: 'Edit session' },
            order: [[1, 'desc']],
            canDelete: function (row) {
                if (row.is_current) {
                    return 'Current session';
                }

                return row.sections_count > 0 || row.curriculum_count > 0 ? 'Has sections or curriculum' : true;
            },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(row.name, row.admissions_open ? 'Admissions open' : ''); }) },
                { data: 'starts_on', render: display(function (value) { return esc(dateFormat.format(new Date(value))); }) },
                { data: 'ends_on', render: display(function (value) { return esc(dateFormat.format(new Date(value))); }) },
                { data: 'sections_count', searchable: false, className: 'dash-col-num dash-hide-md' },
                { data: 'is_current', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge dash-badge-brand">Current</span>' : '<span class="dash-badge">Past / upcoming</span>'; }) }
            ],
            fill: function (form, row) {
                set(form, 'name', row && row.name);
                set(form, 'starts_on', row && row.starts_on);
                set(form, 'ends_on', row && row.ends_on);
                form.elements.is_current.checked = Boolean(row && row.is_current);
                form.elements.admissions_open.checked = Boolean(row && row.admissions_open);
            },
            payload: function (form) {
                return {
                    name: text(form, 'name'),
                    starts_on: text(form, 'starts_on'),
                    ends_on: text(form, 'ends_on'),
                    is_current: form.elements.is_current.checked,
                    admissions_open: form.elements.admissions_open.checked
                };
            }
        },

        classes: {
            titles: { create: 'Add class', edit: 'Edit class' },
            order: [[0, 'asc']],
            params: statusFilter,
            canDelete: function (row) {
                return row.sections_count > 0 ? 'Has sections' : true;
            },
            columns: [
                { data: 'level_order', searchable: false, className: 'dash-col-num' },
                { data: 'name', render: display(function (value, row) { return titleCell(row.name, row.code); }) },
                { data: 'wing_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—', render: display(function (value) { return esc(value || '—'); }) },
                { data: 'next_class_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—', render: display(function (value) { return esc(value || 'Final class'); }) },
                { data: 'sections_count', searchable: false, className: 'dash-col-num' },
                { data: 'is_active', searchable: false, render: display(function (value) { return badge(value, 'Active', 'Inactive'); }) }
            ],
            fill: function (form, row) {
                set(form, 'name', row && row.name);
                set(form, 'code', row && row.code);
                set(form, 'level_order', row && row.level_order);
                set(form, 'wing_id', row && row.wing_id);
                set(form, 'next_class_id', row && row.next_class_id);
                form.elements.is_active.checked = row ? row.is_active : true;

                Array.prototype.forEach.call(form.elements.next_class_id.options, function (option) {
                    option.disabled = Boolean(row) && Number(option.value) === row.id;
                });
            },
            payload: function (form) {
                return {
                    name: text(form, 'name'),
                    code: text(form, 'code'),
                    level_order: number(form, 'level_order'),
                    wing_id: number(form, 'wing_id'),
                    next_class_id: number(form, 'next_class_id'),
                    is_active: form.elements.is_active.checked
                };
            }
        },

        sections: {
            titles: { create: 'Add section', edit: 'Edit section' },
            order: [[0, 'asc']],
            params: sessionClassFilter,
            columns: [
                { data: 'class_name', name: 'class_name', render: display(function (value) { return '<span class="dash-cell-title">' + esc(value) + '</span>'; }) },
                { data: 'name', name: 'sections.name' },
                { data: 'room', name: 'sections.room', defaultContent: '—', render: display(function (value) { return esc(value || '—'); }) },
                { data: 'capacity', name: 'sections.capacity', searchable: false, className: 'dash-col-num', defaultContent: '—' },
                { data: 'session_name', orderable: false, searchable: false, className: 'dash-hide-md' }
            ],
            nameOf: function (row) {
                return row.class_name + ' ' + row.name;
            },
            fill: function (form, row) {
                set(form, 'academic_session_id', row ? row.academic_session_id : filterValue('academic_session_id'));
                set(form, 'class_id', row ? row.class_id : (filterValue('class_id') || form.elements.class_id.options[0].value));
                set(form, 'name', row && row.name);
                set(form, 'room', row && row.room);
                set(form, 'capacity', row && row.capacity);
            },
            payload: function (form) {
                return {
                    academic_session_id: number(form, 'academic_session_id'),
                    class_id: number(form, 'class_id'),
                    name: text(form, 'name'),
                    room: text(form, 'room'),
                    capacity: number(form, 'capacity')
                };
            }
        },

        subjects: {
            titles: { create: 'Add subject', edit: 'Edit subject' },
            order: [[0, 'asc']],
            params: statusFilter,
            canDelete: function (row) {
                return row.curriculum_count > 0 ? 'Used in curriculum' : true;
            },
            columns: [
                { data: 'name', render: display(function (value, row) { return titleCell(row.name, row.code); }) },
                { data: 'subject_type_name', orderable: false, searchable: false, className: 'dash-hide-md', defaultContent: '—', render: display(function (value) { return esc(value || '—'); }) },
                { data: 'has_practical', searchable: false, render: display(function (value) { return value ? 'Yes' : 'No'; }) },
                { data: 'curriculum_count', searchable: false, className: 'dash-col-num' },
                { data: 'is_active', searchable: false, render: display(function (value) { return badge(value, 'Active', 'Inactive'); }) }
            ],
            fill: function (form, row) {
                set(form, 'name', row && row.name);
                set(form, 'code', row && row.code);
                set(form, 'subject_type_id', row && row.subject_type_id);
                form.elements.has_practical.checked = Boolean(row && row.has_practical);
                form.elements.is_active.checked = row ? row.is_active : true;
            },
            payload: function (form) {
                return {
                    name: text(form, 'name'),
                    code: text(form, 'code'),
                    subject_type_id: number(form, 'subject_type_id'),
                    has_practical: form.elements.has_practical.checked,
                    is_active: form.elements.is_active.checked
                };
            }
        },

        curriculum: {
            titles: { create: 'Add subject to class', edit: 'Edit class subject' },
            order: [[0, 'asc']],
            params: sessionClassFilter,
            columns: [
                { data: 'class_name', name: 'class_name', render: display(function (value) { return '<span class="dash-cell-title">' + esc(value) + '</span>'; }) },
                { data: 'subject_name', name: 'subject_name', render: display(function (value, row) { return titleCell(value, row.is_optional ? 'Optional' : row.subject_code); }) },
                { data: 'periods_per_week', name: 'class_subject.periods_per_week', searchable: false, className: 'dash-col-num dash-hide-md', defaultContent: '—' },
                { data: 'theory_max_marks', name: 'class_subject.theory_max_marks', searchable: false, render: display(function (value, row) { return marks(row); }) },
                { data: 'pass_marks', name: 'class_subject.pass_marks', searchable: false, className: 'dash-col-num dash-hide-md', defaultContent: '—' }
            ],
            nameOf: function (row) {
                return row.subject_name + ' — ' + row.class_name;
            },
            fill: function (form, row) {
                set(form, 'academic_session_id', row ? row.academic_session_id : filterValue('academic_session_id'));
                set(form, 'class_id', row ? row.class_id : (filterValue('class_id') || form.elements.class_id.options[0].value));
                set(form, 'subject_id', row ? row.subject_id : form.elements.subject_id.options[0].value);
                set(form, 'periods_per_week', row && row.periods_per_week);
                set(form, 'theory_max_marks', row ? row.theory_max_marks : 100);
                set(form, 'practical_max_marks', row && row.practical_max_marks);
                set(form, 'pass_marks', row ? row.pass_marks : 33);
                set(form, 'sort_order', row && row.sort_order);
                form.elements.is_optional.checked = Boolean(row && row.is_optional);
            },
            payload: function (form) {
                return {
                    academic_session_id: number(form, 'academic_session_id'),
                    class_id: number(form, 'class_id'),
                    subject_id: number(form, 'subject_id'),
                    periods_per_week: number(form, 'periods_per_week'),
                    theory_max_marks: number(form, 'theory_max_marks'),
                    practical_max_marks: number(form, 'practical_max_marks'),
                    pass_marks: number(form, 'pass_marks'),
                    sort_order: number(form, 'sort_order'),
                    is_optional: form.elements.is_optional.checked
                };
            }
        },

        periods: {
            titles: { create: 'Add period', edit: 'Edit period' },
            order: [[0, 'asc']],
            columns: [
                { data: 'sort_order', searchable: false, className: 'dash-col-num' },
                { data: 'name', render: display(function (value) { return '<span class="dash-cell-title">' + esc(value) + '</span>'; }) },
                { data: 'starts_at', searchable: false },
                { data: 'ends_at', searchable: false },
                { data: 'is_break', searchable: false, render: display(function (value) { return value ? '<span class="dash-badge">Break</span>' : '<span class="dash-badge dash-badge-brand">Teaching</span>'; }) }
            ],
            fill: function (form, row) {
                set(form, 'name', row && row.name);
                set(form, 'sort_order', row && row.sort_order);
                set(form, 'starts_at', row && row.starts_at);
                set(form, 'ends_at', row && row.ends_at);
                form.elements.is_break.checked = Boolean(row && row.is_break);
            },
            payload: function (form) {
                return {
                    name: text(form, 'name'),
                    sort_order: number(form, 'sort_order'),
                    starts_at: text(form, 'starts_at'),
                    ends_at: text(form, 'ends_at'),
                    is_break: form.elements.is_break.checked
                };
            }
        }
    };

    const screen = SCREENS[root.dataset.academics];

    if (!screen) {
        return;
    }

    const mounted = window.PinnacleResource.mount({
        root: root,
        modal: modal,
        order: screen.order,
        columns: screen.columns,
        titles: screen.titles,
        canDelete: screen.canDelete,
        fill: screen.fill,
        payload: screen.payload,
        searchPlaceholder: 'Search…',
        emptyText: 'Nothing here yet. Add the first one.',
        nameOf: screen.nameOf || function (row) {
            return row.name;
        },
        params: screen.params ? function () {
            const values = {};

            Object.keys(screen.params).forEach(function (key) {
                values[key] = screen.params[key]();
            });

            return values;
        } : null
    });

    filters.forEach(function (field) {
        field.addEventListener('change', function () {
            mounted.grid.ajax.reload();
        });
    });

    /* ---------- Curriculum: copy a whole session ---------- */

    const copyButton = document.querySelector('[data-copy-open]');
    const copyModal = document.getElementById('copyModal');

    if (copyButton && copyModal) {
        const copyForm = copyModal.querySelector('[data-copy-form]');

        copyButton.addEventListener('click', function () {
            copyForm.querySelectorAll('[data-error-for], [data-form-error]').forEach(function (element) {
                element.hidden = true;
            });
            copyForm.elements.to_session_id.value = filterValue('academic_session_id');
            copyModal.showModal();
        });

        copyModal.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                copyModal.close();
            });
        });

        copyForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const submit = copyForm.querySelector('[data-submit]');
            submit.disabled = true;

            ui.api('POST', root.dataset.copyUrl, {
                from_session_id: number(copyForm, 'from_session_id'),
                to_session_id: number(copyForm, 'to_session_id')
            }).then(function (envelope) {
                copyModal.close();
                ui.toast(envelope.message);
                mounted.refresh();
            }).catch(function (envelope) {
                const general = copyForm.querySelector('[data-form-error]');
                general.textContent = window.PinnacleResource.messageOf(envelope, 'Could not copy.');
                general.hidden = false;
            }).finally(function () {
                submit.disabled = false;
            });
        });
    }
})();

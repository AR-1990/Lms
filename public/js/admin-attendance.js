/**
 * Attendance screens: student daily register, staff register, monthly summary and leave requests.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const root = document.querySelector('[data-attendance]');

    if (!ui || !root) {
        return;
    }

    const screen = root.dataset.attendance;
    const escape = function (value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    };
    const LABELS = { present: 'P', absent: 'A', late: 'L', leave: 'Lv', half_day: 'H' };
    const TITLES = { present: 'Present', absent: 'Absent', late: 'Late', leave: 'Leave', half_day: 'Half day' };

    function marks(name, current) {
        return '<div class="dash-marks" role="radiogroup" aria-label="Attendance">' + Object.keys(LABELS).map(function (status) {
            return '<label class="dash-mark dash-mark-' + status + '" title="' + TITLES[status] + '"><input type="radio" name="' + name + '" value="' + status + '"' + (current === status ? ' checked' : '') + '><span>' + LABELS[status] + '</span></label>';
        }).join('') + '</div>';
    }

    /* ---------- Daily registers (students / staff) ---------- */

    if (screen === 'register' || screen === 'staff') {
        const isStaff = screen === 'staff';
        const rowsBody = root.querySelector('[data-register-rows]');
        const note = root.querySelector('[data-register-note]');
        const counts = root.querySelector('[data-register-counts]');
        const saveButton = root.querySelector('[data-save-register]');
        const sectionSelect = root.querySelector('[data-section]');
        const dateInput = root.querySelector('[data-date]');
        let rows = [];

        const updateCounts = function () {
            const tally = {};
            let unmarked = 0;

            rowsBody.querySelectorAll('tr[data-key]').forEach(function (tr) {
                const checked = tr.querySelector('input[type="radio"]:checked');

                if (checked) {
                    tally[checked.value] = (tally[checked.value] || 0) + 1;
                } else {
                    unmarked += 1;
                }
            });

            counts.textContent = Object.keys(LABELS).filter(function (status) { return tally[status]; }).map(function (status) {
                return TITLES[status] + ' ' + tally[status];
            }).concat(unmarked ? ['Not marked ' + unmarked] : []).join(' · ');
        };

        const load = function () {
            if (!isStaff && !sectionSelect.value) {
                rowsBody.innerHTML = '<tr><td colspan="3" class="dash-muted">You are not assigned to any section.</td></tr>';
                saveButton.disabled = true;

                return;
            }

            const query = (isStaff ? '' : 'section_id=' + sectionSelect.value + '&') + 'date=' + dateInput.value;

            rowsBody.innerHTML = '<tr><td colspan="3" class="dash-muted">Loading…</td></tr>';
            ui.api('GET', root.dataset.sheetUrl + '?' + query).then(function (envelope) {
                const sheet = envelope.data;
                const closed = Boolean(sheet.holiday);

                rows = sheet.rows;
                note.hidden = !(sheet.holiday || sheet.is_sunday || sheet.marked);
                note.textContent = sheet.holiday ? 'Holiday: ' + sheet.holiday + ' — no attendance needed.' : (sheet.is_sunday ? 'This date is a Sunday.' : 'Already marked — saving updates the register.');
                note.classList.toggle('is-warning', Boolean(sheet.holiday || sheet.is_sunday));
                saveButton.disabled = closed;

                rowsBody.innerHTML = rows.length ? rows.map(function (row) {
                    const key = isStaff ? row.employee_id : row.enrollment_id;
                    const who = isStaff
                        ? '<span class="dash-cell-title">' + escape(row.name) + '</span><span class="dash-muted">' + escape([row.employee_code, row.designation].filter(Boolean).join(' · ')) + '</span>'
                        : '<span class="dash-cell-title">' + escape((row.roll_no ? row.roll_no + '. ' : '') + row.name) + '</span><span class="dash-muted">' + escape(row.admission_no) + (row.on_leave ? ' · approved leave' : '') + '</span>';
                    const extra = isStaff
                        ? '<input type="time" data-in value="' + escape(row.check_in_at || '') + '" aria-label="Check in"> <input type="time" data-out value="' + escape(row.check_out_at || '') + '" aria-label="Check out">'
                        : '<input type="text" data-remarks maxlength="255" value="' + escape(row.remarks || '') + '" placeholder="Remarks" aria-label="Remarks">';

                    return '<tr data-key="' + key + '"><td>' + who + '</td><td>' + marks('m' + key, row.status) + '</td><td class="dash-hide-md dash-register-extra">' + extra + '</td></tr>';
                }).join('') : '<tr><td colspan="3" class="dash-muted">Nobody to mark.</td></tr>';

                updateCounts();
            }).catch(function (envelope) {
                rowsBody.innerHTML = '<tr><td colspan="3" class="dash-muted">' + escape(window.PinnacleResource ? window.PinnacleResource.messageOf(envelope, 'Could not load the register.') : 'Could not load the register.') + '</td></tr>';
            });
        };

        rowsBody.addEventListener('change', updateCounts);

        root.querySelector('[data-all-present]').addEventListener('click', function () {
            rowsBody.querySelectorAll('tr[data-key]').forEach(function (tr) {
                const present = tr.querySelector('input[value="present"]');

                if (present && !tr.querySelector('input[type="radio"]:checked')) {
                    present.checked = true;
                }
            });
            updateCounts();
        });

        saveButton.addEventListener('click', function () {
            const payload = [];
            let missing = 0;

            rowsBody.querySelectorAll('tr[data-key]').forEach(function (tr) {
                const checked = tr.querySelector('input[type="radio"]:checked');

                if (!checked) {
                    missing += 1;
                    tr.classList.add('is-missing');

                    return;
                }

                tr.classList.remove('is-missing');
                payload.push(isStaff
                    ? { employee_id: Number(tr.dataset.key), status: checked.value, check_in_at: tr.querySelector('[data-in]').value || null, check_out_at: tr.querySelector('[data-out]').value || null }
                    : { enrollment_id: Number(tr.dataset.key), status: checked.value, remarks: tr.querySelector('[data-remarks]').value.trim() || null });
            });

            if (missing) {
                ui.toast(missing + ' not marked yet.', 'error');

                return;
            }

            saveButton.disabled = true;
            ui.api('POST', root.dataset.saveUrl, Object.assign({ date: dateInput.value, rows: payload }, isStaff ? {} : { section_id: Number(sectionSelect.value) }))
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    load();
                })
                .catch(function (envelope) {
                    const fields = envelope && envelope.errors && envelope.errors.fields;
                    ui.toast(fields ? fields[Object.keys(fields)[0]][0] : (envelope.message || 'Could not save.'), 'error');
                })
                .finally(function () {
                    saveButton.disabled = false;
                });
        });

        [sectionSelect, dateInput].forEach(function (control) {
            if (control) {
                control.addEventListener('change', load);
            }
        });

        load();
    }

    /* ---------- Monthly summary ---------- */

    if (screen === 'monthly') {
        const body = root.querySelector('[data-monthly-rows]');
        const sectionSelect = root.querySelector('[data-section]');
        const monthInput = root.querySelector('[data-month]');

        const load = function () {
            if (!sectionSelect.value) {
                body.innerHTML = '<tr><td colspan="7" class="dash-muted">You are not assigned to any section.</td></tr>';

                return;
            }

            ui.api('GET', root.dataset.monthlyUrl + '?section_id=' + sectionSelect.value + '&month=' + monthInput.value).then(function (envelope) {
                body.innerHTML = envelope.data.length ? envelope.data.map(function (row) {
                    const low = row.percentage !== null && row.percentage < 75;

                    return '<tr><td><span class="dash-cell-title">' + escape((row.roll_no ? row.roll_no + '. ' : '') + row.name) + '</span><span class="dash-muted">' + escape(row.admission_no) + '</span></td>'
                        + '<td class="dash-col-num">' + row.present + '</td><td class="dash-col-num">' + row.absent + '</td><td class="dash-col-num dash-hide-md">' + row.late + '</td>'
                        + '<td class="dash-col-num dash-hide-md">' + row.leave + '</td><td class="dash-col-num dash-hide-md">' + row.half_day + '</td>'
                        + '<td class="dash-col-num"><span class="dash-badge ' + (row.percentage === null ? 'dash-badge-system' : (low ? 'dash-badge-danger' : 'dash-badge-ok')) + '">' + (row.percentage === null ? '—' : row.percentage + '%') + '</span></td></tr>';
                }).join('') : '<tr><td colspan="7" class="dash-muted">No students.</td></tr>';
            });
        };

        sectionSelect.addEventListener('change', load);
        monthInput.addEventListener('change', load);
        load();
    }

    /* ---------- Leave requests ---------- */

    if (screen === 'leaves' && window.PinnacleGrid && window.PinnacleResource) {
        const kit = window.PinnacleResource;
        const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short' });
        const TONES = { pending: 'dash-badge-warn', approved: 'dash-badge-ok', rejected: 'dash-badge-danger' };
        const dialog = document.getElementById('leaveModal');
        const form = dialog.querySelector('form');
        const filter = root.querySelector('[data-filter="status"]');
        let current = null;

        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[1, 'asc']],
            emptyText: 'No leave requests.',
            params: function () { return { status: filter.value }; },
            columns: [
                { data: 'student_name', orderable: false, searchable: false, render: function (value, type, row) { return type === 'display' ? '<span class="dash-cell-title">' + escape(value) + '</span><span class="dash-muted">' + escape(row.admission_no) + '</span>' : value; } },
                { data: 'from_date', searchable: false, render: function (value, type, row) { return type === 'display' ? escape(dateFormat.format(new Date(value)) + (row.to_date !== value ? ' – ' + dateFormat.format(new Date(row.to_date)) : '')) : value; } },
                { data: 'reason', orderable: false, className: 'dash-hide-md', render: function (value, type) { return type === 'display' ? escape(value) : value; } },
                { data: 'status', searchable: false, render: function (value, type, row) { return type === 'display' ? '<span class="dash-badge ' + TONES[value] + '">' + escape(value.charAt(0).toUpperCase() + value.slice(1)) + '</span>' + (row.reviewed_by_name ? '<span class="dash-muted">by ' + escape(row.reviewed_by_name) + '</span>' : '') : value; } },
                { data: null, orderable: false, searchable: false, className: 'dash-col-actions', render: function (value, type, row) { return type === 'display' ? '<div class="dash-actions"><button type="button" class="dash-action dash-action-view" data-open data-tooltip="Open" aria-label="Open request of ' + escape(row.student_name) + '"><svg class="dash-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h6a4 4 0 0 1 4 4v13a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v13a3 3 0 0 1 3-3h7z"/></svg></button></div>' : ''; } }
            ]
        });

        filter.addEventListener('change', function () { grid.ajax.reload(); });
        kit.wireDialog(dialog);

        root.querySelector('[data-grid]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-open]');

            if (!button) {
                return;
            }

            current = grid.row(button.closest('tr')).data();
            form.reset();
            kit.clearErrors(form);
            dialog.querySelector('[data-detail-title]').textContent = current.student_name;
            dialog.querySelector('[data-detail-subtitle]').textContent = 'Requested by ' + (current.requested_by_name || '—');
            dialog.querySelector('[data-detail-body]').innerHTML = '<ul class="dash-kv"><li><span>Dates</span><strong>' + escape(current.from_date + ' to ' + current.to_date) + '</strong></li><li><span>Reason</span><strong>' + escape(current.reason) + '</strong></li>'
                + (current.review_remarks ? '<li><span>Remarks</span><strong>' + escape(current.review_remarks) + '</strong></li>' : '') + '</ul>';
            dialog.querySelector('[data-decision-fields]').hidden = current.status !== 'pending';
            dialog.querySelector('[data-decision-actions]').hidden = current.status !== 'pending';
            dialog.showModal();
        });

        dialog.querySelector('[data-decision-actions]').addEventListener('click', function (event) {
            const button = event.target.closest('[data-decision]');

            if (!button || !current) {
                return;
            }

            kit.submitForm(form, 'POST', root.dataset.resourceUrl + '/' + current.id + '/decision', { decision: button.dataset.decision, remarks: form.elements.remarks.value.trim() || null })
                .then(function () {
                    dialog.close();
                    grid.ajax.reload(null, false);
                })
                .catch(function () {});
        });
    }

    /* ---------- QR scan at the gate ---------- */

    if (screen === 'scan') {
        const form = root.querySelector('[data-scan-form]');
        const input = form.elements.code;
        const log = root.querySelector('[data-scan-log]');
        const video = root.querySelector('[data-video]');
        const startButton = root.querySelector('[data-start-camera]');
        let lastCode = '';
        let lastAt = 0;
        let busy = false;

        const mark = function (code) {
            const now = Date.now();

            // A camera sees the same QR many times a second; ignore repeats for 4 seconds.
            if (!code || busy || (code === lastCode && now - lastAt < 4000)) {
                return;
            }

            lastCode = code;
            lastAt = now;
            busy = true;

            ui.api('POST', root.dataset.scanUrl, { code: code })
                .then(function (envelope) {
                    log.insertAdjacentHTML('afterbegin', '<li class="is-ok"><strong>' + escape(envelope.data.name) + '</strong><span>' + escape(envelope.message) + '</span></li>');
                })
                .catch(function (envelope) {
                    const fields = envelope && envelope.errors && envelope.errors.fields;

                    log.insertAdjacentHTML('afterbegin', '<li class="is-error"><strong>Not marked</strong><span>' + escape(fields ? fields[Object.keys(fields)[0]][0] : (envelope && envelope.message) || 'Could not read the card.') + '</span></li>');
                })
                .finally(function () {
                    busy = false;
                    input.value = '';
                    input.focus();
                });
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            mark(input.value.trim());
        });

        if (!('BarcodeDetector' in window)) {
            root.querySelector('[data-camera-note]').textContent = 'This browser cannot read QR codes from the camera; use a handheld scanner or the box below.';
            startButton.hidden = true;
        }

        startButton.addEventListener('click', function () {
            const detector = new window.BarcodeDetector({ formats: ['qr_code'] });

            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
                video.srcObject = stream;
                video.hidden = false;
                root.querySelector('[data-camera-placeholder]').hidden = true;

                return video.play();
            }).then(function () {
                const tick = function () {
                    detector.detect(video).then(function (codes) {
                        if (codes.length) {
                            mark(codes[0].rawValue);
                        }
                    }).catch(function () {}).finally(function () {
                        window.setTimeout(tick, 350);
                    });
                };

                tick();
            }).catch(function () {
                ui.toast('The camera could not be opened. Allow camera access or use a handheld scanner.', 'error');
            });
        });
    }
})();

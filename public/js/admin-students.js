/**
 * Students & admissions screens: student list + profile actions, applications workflow,
 * inquiries (+ convert to application). Everything goes through the /api/erp endpoints.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!ui || !kit || !window.PinnacleGrid) {
        return;
    }

    const esc = window.PinnacleGrid.escape;
    const money = window.PinnacleGrid.money;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const dateTimeFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    const TONES = { active: 'dash-badge-ok', enrolled: 'dash-badge-ok', paid: 'dash-badge-ok', accepted: 'dash-badge-brand', offered: 'dash-badge-brand', converted: 'dash-badge-ok', new: 'dash-badge-brand', posted: 'dash-badge-warn', partially_paid: 'dash-badge-warn', waitlisted: 'dash-badge-warn', suspended: 'dash-badge-warn' };

    function headline(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
            return letter.toUpperCase();
        });
    }

    function badge(status) {
        return '<span class="dash-badge ' + (TONES[status] || 'dash-badge-system') + '">' + esc(headline(status)) + '</span>';
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

    function toggleCreateOnly(form, creating) {
        form.querySelectorAll('[data-create-only]').forEach(function (element) {
            element.hidden = !creating;
            element.querySelectorAll('input, select').forEach(function (input) {
                input.disabled = !creating;
            });
        });
    }

    function mountFilters(root, grid) {
        root.querySelectorAll('[data-filter]').forEach(function (field) {
            field.addEventListener('change', function () {
                grid.ajax.reload();
            });
        });
    }

    function filterReader(root) {
        return function () {
            const values = {};

            root.querySelectorAll('[data-filter]').forEach(function (field) {
                values[field.dataset.filter] = field.value;
            });

            return values;
        };
    }

    /* ---------- Application form (shared by applications and inquiry conversion) ---------- */

    const APPLICATION_FIELDS = { first_name: 'text', last_name: 'text', gender_id: 'int', date_of_birth: 'text', b_form_no: 'text', previous_school: 'text', previous_class: 'text', city: 'text', address_line: 'text' };

    function applicationPayload(form, creating) {
        const body = kit.readForm(form, APPLICATION_FIELDS);
        const guardians = [0, 1].map(function (index) {
            const prefix = 'g' + index + '_';
            const guardian = kit.readForm(form, Object.fromEntries([['relation_id', 'int'], ['name', 'text'], ['phone', 'text'], ['cnic', 'text']].map(function (pair) {
                return [prefix + pair[0], pair[1]];
            })));

            return { relation_id: guardian[prefix + 'relation_id'], name: guardian[prefix + 'name'], phone: guardian[prefix + 'phone'], cnic: guardian[prefix + 'cnic'], is_primary_contact: index === 0 };
        }).filter(function (guardian, index) {
            return index === 0 || guardian.name;
        });

        body.guardians = guardians;

        if (creating) {
            Object.assign(body, kit.readForm(form, { academic_session_id: 'int', class_applied_id: 'int' }));
        }

        return body;
    }

    function fillApplication(form, row) {
        kit.fillForm(form, row, APPLICATION_FIELDS);
        toggleCreateOnly(form, !row);

        [0, 1].forEach(function (index) {
            const guardian = row && row.guardians ? row.guardians[index] : null;
            const prefix = 'g' + index + '_';

            form.elements[prefix + 'relation_id'].value = guardian ? guardian.relation_id : (index === 0 ? form.elements[prefix + 'relation_id'].options[0].value : '');
            form.elements[prefix + 'name'].value = guardian ? guardian.name || '' : '';
            form.elements[prefix + 'phone'].value = guardian ? guardian.phone || '' : '';
            form.elements[prefix + 'cnic'].value = guardian ? guardian.cnic || '' : '';
        });
    }

    /* ---------- Students list ---------- */

    const studentsRoot = document.querySelector('[data-students="students"]');

    if (studentsRoot) {
        const STUDENT_FIELDS = { first_name: 'text', middle_name: 'text', last_name: 'text', gender_id: 'int', date_of_birth: 'text', b_form_no: 'text', religion_id: 'int', blood_group_id: 'int', city: 'text', address_line: 'text' };
        const resourceUrl = studentsRoot.dataset.resourceUrl;

        const mounted = kit.mount({
            root: studentsRoot,
            modal: document.getElementById('studentsModal'),
            order: [[1, 'asc']],
            titles: { create: 'Add student', edit: 'Edit student' },
            searchPlaceholder: 'Search name or class…',
            emptyText: 'No students yet.',
            params: filterReader(studentsRoot),
            nameOf: function (row) { return row.full_name; },
            viewUrl: function (row) { return studentsRoot.dataset.profileUrl + '/' + row.id; },
            loadRow: function (row) {
                return ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; });
            },
            columns: [
                { data: 'full_name', name: 'full_name', render: display(function (value, row) { return titleCell(value, row.admission_no); }) },
                { data: 'class_name', name: 'class_name', defaultContent: '—', render: display(function (value, row) { return value ? titleCell(value + ' ' + (row.section_name || ''), row.roll_no ? 'Roll ' + row.roll_no : '') : '<span class="dash-muted">Not enrolled</span>'; }) },
                { data: 'date_of_birth', name: 'students.date_of_birth', searchable: false, className: 'dash-hide-md', render: display(shortDate) },
                { data: 'current_status', name: 'students.current_status', searchable: false, render: display(badge) }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row, STUDENT_FIELDS);
                toggleCreateOnly(form, !row);

                if (!row) {
                    form.elements.admission_date.value = today();
                }
            },
            payload: function (form, row) {
                const body = kit.readForm(form, STUDENT_FIELDS);

                if (row) {
                    return body;
                }

                const section = form.elements.section_id.selectedOptions[0];

                return Object.assign(body, {
                    admission_no: form.elements.admission_no.value.trim() || null,
                    admission_date: form.elements.admission_date.value,
                    enrollment: {
                        academic_session_id: Number(studentsRoot.dataset.sessionId),
                        class_id: section ? Number(section.dataset.classId) : null,
                        section_id: section ? Number(section.value) : null,
                        roll_no: form.elements.roll_no.value.trim() || null
                    },
                    guardians: [{
                        relation_id: Number(form.elements.guardian_relation_id.value),
                        name: form.elements.guardian_name.value.trim() || null,
                        phone: form.elements.guardian_phone.value.trim() || null,
                        cnic: form.elements.guardian_cnic.value.trim() || null,
                        is_primary_contact: true,
                        is_fee_payer: true
                    }]
                });
            }
        });

        mountFilters(studentsRoot, mounted.grid);
    }

    /* ---------- Student profile ---------- */

    const profile = document.querySelector('[data-student-profile]');

    if (profile) {
        const studentUrl = profile.dataset.resourceUrl + '/' + profile.dataset.studentId;
        const reload = function () {
            window.location.reload();
        };

        const guardianForm = document.querySelector('[data-guardian-form]');
        guardianForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(guardianForm, 'POST', studentUrl + '/guardians', kit.readForm(guardianForm, { relation_id: 'int', name: 'text', phone: 'text', cnic: 'text', is_primary_contact: 'bool' }))
                .then(reload)
                .catch(function () {});
        });

        document.querySelector('[data-guardian-list]').addEventListener('click', function (event) {
            const unlink = event.target.closest('[data-unlink]');
            const login = event.target.closest('[data-parent-login]');

            if (unlink) {
                const item = unlink.closest('li');

                ui.eraseRecord(unlink.dataset.name, item, studentUrl + '/guardians/' + unlink.dataset.unlink).then(function (result) {
                    if (result === 'deleted') {
                        item.remove();
                    }
                });
            }

            if (login) {
                const dialog = document.getElementById('parentLoginModal');
                const form = dialog.querySelector('form');
                form.reset();
                kit.clearErrors(form);
                dialog.dataset.guardianId = login.dataset.parentLogin;
                dialog.dataset.mode = 'parent';
                dialog.querySelector('#parentLoginTitle').textContent = 'Parent login';
                dialog.querySelector('[data-parent-login-name]').textContent = login.dataset.name + ' will sign in with their phone number.';
                dialog.showModal();
            }
        });

        const loginDialog = document.getElementById('parentLoginModal');
        kit.wireDialog(loginDialog);
        loginDialog.querySelector('form').addEventListener('submit', function (event) {
            event.preventDefault();
            const form = event.target;

            const url = loginDialog.dataset.mode === 'student'
                ? studentUrl + '/portal-account'
                : profile.dataset.guardiansUrl + '/' + loginDialog.dataset.guardianId + '/portal-account';

            kit.submitForm(form, 'POST', url, { password: form.elements.password.value })
                .then(reload)
                .catch(function () {});
        });

        const studentLogin = document.querySelector('[data-student-login]');

        if (studentLogin) {
            studentLogin.addEventListener('click', function () {
                const form = loginDialog.querySelector('form');
                form.reset();
                kit.clearErrors(form);
                loginDialog.dataset.mode = 'student';
                loginDialog.querySelector('#parentLoginTitle').textContent = 'Student login';
                loginDialog.querySelector('[data-parent-login-name]').textContent = studentLogin.dataset.name + ' will sign in with their admission number.';
                loginDialog.showModal();
            });
        }

        const enrollmentForm = document.querySelector('[data-enrollment-form]');

        if (enrollmentForm) {
            enrollmentForm.elements.roll_no.value = enrollmentForm.dataset.rollNo || '';
            enrollmentForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const section = enrollmentForm.elements.section_id.selectedOptions[0];

                kit.submitForm(enrollmentForm, 'PUT', '/api/erp/enrollments/' + enrollmentForm.dataset.enrollmentId, {
                    class_id: Number(section.dataset.classId),
                    section_id: Number(section.value),
                    roll_no: enrollmentForm.elements.roll_no.value.trim() || null
                }).then(reload).catch(function () {});
            });
        }

        const statusForm = document.querySelector('[data-status-form]');
        statusForm.elements.effective_on.value = today();
        statusForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(statusForm, 'POST', studentUrl + '/status', kit.readForm(statusForm, { status: 'text', effective_on: 'text', reason_id: 'int', remarks: 'text' }))
                .then(reload)
                .catch(function () {});
        });

        ui.api('GET', profile.dataset.invoicesUrl + '?per_page=50&student_id=' + profile.dataset.studentId).then(function (envelope) {
            const items = envelope.data.items;
            const due = items.reduce(function (sum, invoice) {
                return sum + (invoice.status === 'cancelled' ? 0 : invoice.balance);
            }, 0);

            document.querySelector('[data-fee-due]').textContent = due > 0 ? money(due) + ' due' : 'Nothing due';
            document.querySelector('[data-invoice-rows]').innerHTML = items.length ? items.map(function (invoice) {
                return '<tr><td>' + titleCell(invoice.invoice_no, headline(invoice.invoice_type)) + '</td><td>' + esc(invoice.billing_month || '—') + '</td>'
                    + '<td class="dash-col-num">' + money(invoice.total) + '</td><td class="dash-col-num">' + money(invoice.balance) + '</td><td>' + badge(invoice.status) + '</td></tr>';
            }).join('') : '<tr><td colspan="5" class="dash-muted">No challans yet.</td></tr>';
        });

        const years = document.querySelector('[data-student-years]');
        const YEAR_END = { active: ['Studying', 'dash-badge-ok'], promoted: ['Promoted', 'dash-badge-ok'], repeated: ['Kept in class', 'dash-badge-warn'], demoted: ['Moved down', 'dash-badge-warn'], graduated: ['Graduated', 'dash-badge-brand'], withdrawn: ['Left', 'dash-badge-system'], transferred: ['Transferred', 'dash-badge-system'] };

        ui.api('GET', years.dataset.url).then(function (envelope) {
            years.querySelector('[data-year-rows]').innerHTML = envelope.data.length ? envelope.data.map(function (year) {
                const end = YEAR_END[year.status] || [headline(year.status), 'dash-badge-system'];
                const results = year.results.map(function (card) {
                    const tone = card.result === 'fail' ? 'dash-badge-danger' : 'dash-badge-ok';

                    return '<span class="dash-muted">' + esc(card.exam) + '</span> <span class="dash-badge ' + tone + '">' + esc(card.percentage !== null ? Number(card.percentage) + '%' : headline(card.result)) + (card.grade ? ' · ' + esc(card.grade) : '') + '</span>';
                }).join('<br>');

                return '<tr><td>' + titleCell(year.session ? year.session.name : '—', year.session && year.session.is_current ? 'Current' : null) + '</td>'
                    + '<td>' + titleCell(year.class || '—', [year.section, year.roll_no ? 'Roll ' + year.roll_no : null].filter(Boolean).join(' · ')) + '</td>'
                    + '<td><span class="dash-badge ' + end[1] + '">' + esc(end[0]) + '</span></td>'
                    + '<td>' + (results || '<span class="dash-muted">No published results</span>') + '</td>'
                    + '<td class="dash-col-num">' + (year.attendance.percentage !== null ? esc(year.attendance.percentage) + '%<span class="dash-muted"> of ' + year.attendance.days + (year.attendance.days === 1 ? ' day' : ' days') + '</span>' : '—') + '</td>'
                    + '<td class="dash-col-num">' + money(year.fees.billed) + '</td>'
                    + '<td class="dash-col-num">' + money(year.fees.paid) + '</td>'
                    + '<td class="dash-col-num">' + (year.fees.balance > 0 ? '<strong>' + money(year.fees.balance) + '</strong>' : money(0)) + '</td></tr>';
            }).join('') : '<tr><td colspan="8" class="dash-muted">No sessions yet.</td></tr>';
        }).catch(function () {
            years.querySelector('[data-year-rows]').innerHTML = '<tr><td colspan="8" class="dash-muted">Could not load the history.</td></tr>';
        });

        /* ---------- Leaving & clearance ---------- */

        const leaving = document.querySelector('[data-student-leaving]');
        const refundDialog = document.getElementById('refundModal');
        const reverseDialog = document.getElementById('refundReverseModal');
        const certificateDialog = document.getElementById('certificateModal');
        let clearance = null;

        function check(done, title, detail) {
            return '<li class="dash-leave-check' + (done ? ' is-done' : '') + '"><span class="dash-leave-mark" aria-hidden="true">' + (done ? '✓' : '!') + '</span>'
                + '<div><strong>' + esc(title) + '</strong>' + (detail ? '<span class="dash-muted">' + detail + '</span>' : '') + '</div></li>';
        }

        function renderClearance(data) {
            clearance = data;
            const badge = leaving.querySelector('[data-clear-badge]');
            const dues = data.dues.map(function (invoice) { return esc(invoice.invoice_no) + ' — ' + money(invoice.balance); }).join(' · ');
            const items = data.items_held.map(function (item) { return esc(item.name) + (item.asset_tag ? ' (' + esc(item.asset_tag) + ')' : ''); }).join(' · ');
            const held = data.deposit_available + data.advance_available;

            badge.textContent = data.slc.no ? 'Certificate ' + data.slc.no : (!data.is_leaving ? 'Still studying' : (data.is_clear ? 'Cleared' : 'Not cleared'));
            badge.className = 'dash-badge ' + (!data.is_leaving && !data.slc.no ? 'dash-badge-system' : (data.is_clear ? 'dash-badge-ok' : 'dash-badge-warn'));

            leaving.querySelector('[data-clear-list]').innerHTML = [
                check(data.fee_due === 0, data.fee_due === 0 ? 'No fees due' : money(data.fee_due) + ' of fees due', dues || null),
                check(data.items_held.length === 0, data.items_held.length ? data.items_held.length + ' school item(s) not returned' : 'No school items held', items || 'Books, devices or kits handed out from Assets are taken back there.'),
                check(held === 0, held ? money(held) + ' to pay back' : 'Nothing to pay back', [data.deposit_available ? 'Security deposit ' + money(data.deposit_available) : null, data.advance_available ? 'Unused advance ' + money(data.advance_available) : null].filter(Boolean).join(' · ') || null),
                check(Boolean(data.slc.no), data.slc.no ? 'Leaving certificate ' + esc(data.slc.no) : 'Leaving certificate not issued', data.slc.no ? 'Issued ' + shortDate(data.slc.issued_on) : (data.is_leaving ? 'Issue it once the checks above are clear.' : 'First change the status to withdrawn, left or graduated (Details above).'))
            ].join('');

            const refundButton = leaving.querySelector('[data-open-refund]');

            if (refundButton) {
                refundButton.hidden = held === 0;
                leaving.querySelector('[data-adjust-deposit]').hidden = !(data.deposit_available > 0 && data.fee_due > 0);
            }

            leaving.querySelector('[data-open-certificate]').hidden = !data.is_leaving;
            leaving.querySelector('[data-open-certificate]').lastChild.textContent = data.slc.no ? ' Edit certificate' : ' Issue leaving certificate';
            leaving.querySelector('[data-print-certificate]').hidden = !data.slc.no;

            leaving.querySelector('[data-refunds-wrap]').hidden = data.refunds.length === 0;
            leaving.querySelector('[data-refund-rows]').innerHTML = data.refunds.map(function (refund) {
                const reversed = refund.status === 'reversed';

                return '<tr><td>' + titleCell(refund.refund_no, dateFormat.format(new Date(refund.refunded_on)) + (refund.payment_method ? ' · ' + refund.payment_method : '')) + '</td>'
                    + '<td>' + esc(refund.source === 'deposit' ? 'Security deposit' : 'Advance fee') + '<span class="dash-muted">' + esc(reversed ? 'Reversed: ' + refund.reversal_reason : refund.reason) + '</span></td>'
                    + '<td class="dash-col-num">' + money(refund.amount) + '</td>'
                    + '<td><span class="dash-badge ' + (reversed ? 'dash-badge-system' : 'dash-badge-ok') + '">' + (reversed ? 'Reversed' : 'Paid') + '</span></td>'
                    + '<td class="dash-col-actions">' + (!reversed && reverseDialog ? '<button type="button" class="dash-link-btn" data-reverse-refund="' + refund.id + '" data-refund-no="' + esc(refund.refund_no) + '">Reverse</button>' : '') + '</td></tr>';
            }).join('');
        }

        function loadClearance() {
            return ui.api('GET', leaving.dataset.url).then(function (envelope) { renderClearance(envelope.data); }).catch(function () {
                leaving.querySelector('[data-clear-list]').innerHTML = '<li><span class="dash-muted">Could not load the clearance.</span></li>';
            });
        }

        loadClearance();
        kit.wireDialog(certificateDialog);

        leaving.querySelector('[data-open-certificate]').addEventListener('click', function () {
            const form = certificateDialog.querySelector('form');
            kit.clearErrors(form);
            form.elements.issued_on.value = clearance.slc.issued_on || today();
            form.elements.conduct_id.value = clearance.slc.conduct_id || '';
            form.elements.remarks.value = clearance.slc.remarks || '';
            certificateDialog.showModal();
        });

        certificateDialog.querySelector('form').addEventListener('submit', function (event) {
            event.preventDefault();
            const form = event.target;

            kit.submitForm(form, 'POST', leaving.dataset.certificateUrl, kit.readForm(form, { issued_on: 'text', conduct_id: 'int', remarks: 'text' })).then(function (envelope) {
                certificateDialog.close();
                renderClearance(envelope.data);
            }).catch(function () {});
        });

        leaving.querySelector('[data-print-certificate]').addEventListener('click', function () {
            ui.openFile(leaving.dataset.certificatePdfUrl, 'Preparing the certificate…');
        });

        if (refundDialog) {
            const adjustDialog = document.getElementById('depositAdjustModal');
            kit.wireDialog(adjustDialog);

            leaving.querySelector('[data-adjust-deposit]').addEventListener('click', function () {
                const amount = Math.min(clearance.deposit_available, clearance.fee_due);
                adjustDialog.querySelector('[data-adjust-text]').textContent = money(amount) + ' of the ' + money(clearance.deposit_available) + ' security deposit pays the oldest unpaid challans. A receipt is made and posted to the ledger; reversing that receipt gives the deposit back.';
                adjustDialog.querySelector('[data-form-error]').hidden = true;
                adjustDialog.showModal();
            });

            adjustDialog.querySelector('[data-adjust-yes]').addEventListener('click', function () {
                const error = adjustDialog.querySelector('[data-form-error]');

                ui.api('POST', leaving.dataset.adjustUrl).then(function (envelope) {
                    adjustDialog.close();
                    ui.toast(envelope.message);
                    renderClearance(envelope.data);
                }).catch(function (envelope) {
                    error.textContent = kit.messageOf(envelope, 'Could not adjust the deposit.');
                    error.hidden = false;
                });
            });

            kit.wireDialog(refundDialog);
            kit.wireDialog(reverseDialog);
            const refundForm = refundDialog.querySelector('form');
            const sourceLabels = { deposit: 'Security deposit', advance: 'Unused advance fee' };

            leaving.querySelector('[data-open-refund]').addEventListener('click', function () {
                refundForm.reset();
                kit.clearErrors(refundForm);
                Array.prototype.forEach.call(refundForm.elements.source.options, function (option) {
                    const available = option.value === 'deposit' ? clearance.deposit_available : clearance.advance_available;
                    option.textContent = sourceLabels[option.value] + ' — ' + money(available) + ' held';
                    option.disabled = available === 0;
                });
                refundForm.elements.source.value = clearance.deposit_available ? 'deposit' : 'advance';
                refundForm.elements.amount.value = (refundForm.elements.source.value === 'deposit' ? clearance.deposit_available : clearance.advance_available) / 100;
                refundForm.elements.refunded_on.value = today();
                refundDialog.showModal();
            });

            refundForm.elements.source.addEventListener('change', function () {
                refundForm.elements.amount.value = (refundForm.elements.source.value === 'deposit' ? clearance.deposit_available : clearance.advance_available) / 100;
            });

            refundForm.addEventListener('submit', function (event) {
                event.preventDefault();
                const body = kit.readForm(refundForm, { source: 'text', amount: 'money', payment_method_id: 'int', refunded_on: 'text', reference_no: 'text', reason: 'text' });
                body.student_id = Number(profile.dataset.studentId);

                kit.submitForm(refundForm, 'POST', leaving.dataset.refundsUrl, body).then(function () {
                    refundDialog.close();
                    loadClearance();
                }).catch(function () {});
            });

            leaving.querySelector('[data-refund-rows]').addEventListener('click', function (event) {
                const button = event.target.closest('[data-reverse-refund]');

                if (!button) {
                    return;
                }

                const form = reverseDialog.querySelector('form');
                form.reset();
                kit.clearErrors(form);
                reverseDialog.dataset.refundId = button.dataset.reverseRefund;
                reverseDialog.querySelector('[data-reverse-text]').textContent = 'Refund ' + button.dataset.refundNo + ': its voucher is reversed and the money counts as held again.';
                reverseDialog.showModal();
            });

            reverseDialog.querySelector('form').addEventListener('submit', function (event) {
                event.preventDefault();

                kit.submitForm(event.target, 'POST', leaving.dataset.refundsUrl + '/' + reverseDialog.dataset.refundId + '/reverse', { reason: event.target.elements.reason.value.trim() }).then(function () {
                    reverseDialog.close();
                    loadClearance();
                }).catch(function () {});
            });
        }
    }

    /* ---------- Applications ---------- */

    const applicationsRoot = document.querySelector('[data-students="applications"]');

    if (applicationsRoot) {
        const resourceUrl = applicationsRoot.dataset.resourceUrl;
        const detail = document.getElementById('detailModal');

        const mounted = kit.mount({
            root: applicationsRoot,
            modal: document.getElementById('studentsModal'),
            order: [[2, 'desc']],
            titles: { create: 'New application', edit: 'Edit application' },
            searchPlaceholder: 'Search child name…',
            emptyText: 'No applications yet.',
            params: filterReader(applicationsRoot),
            nameOf: function (row) { return row.full_name; },
            canEdit: function (row) { return ['enrolled', 'rejected', 'withdrawn'].indexOf(row.status) === -1 ? true : 'Closed'; },
            canDelete: function (row) { return row.status === 'enrolled' ? 'Enrolled' : true; },
            loadRow: function (row) {
                return ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) { return envelope.data; });
            },
            onView: openApplication,
            columns: [
                { data: 'full_name', name: 'full_name', render: display(function (value, row) { return titleCell(value, row.application_no); }) },
                { data: 'class_name', orderable: false, searchable: false },
                { data: 'applied_on', searchable: false, className: 'dash-hide-md', render: display(shortDate) },
                { data: 'fees_due', searchable: false, orderable: false, className: 'dash-col-num', render: display(function (value) { return Number(value) > 0 ? money(value) : '<span class="dash-muted">—</span>'; }) },
                { data: 'status', searchable: false, render: display(badge) }
            ],
            fill: fillApplication,
            payload: function (form, row) {
                return applicationPayload(form, !row);
            }
        });

        mountFilters(applicationsRoot, mounted.grid);
        kit.wireDialog(detail);

        function openApplication(row) {
            ui.api('GET', resourceUrl + '/' + row.id).then(function (envelope) {
                renderApplication(envelope.data);

                if (!detail.open) {
                    detail.showModal();
                }
            });
        }

        function renderApplication(app) {
            detail.dataset.id = app.id;
            detail.querySelector('[data-detail-title]').textContent = app.full_name;
            detail.querySelector('[data-detail-subtitle]').textContent = app.application_no + ' · ' + (app.class_name || '') + ' · ' + (app.session_name || '');

            const guardians = app.guardians.map(function (guardian) {
                return '<li><span>' + esc(guardian.relation || 'Guardian') + '</span><strong>' + esc(guardian.name) + (guardian.phone ? ' · ' + esc(guardian.phone) : '') + '</strong></li>';
            }).join('');

            const invoices = app.invoices.map(function (invoice) {
                return '<li><span>' + esc(headline(invoice.invoice_type)) + ' · ' + esc(invoice.invoice_no) + '</span><strong>' + money(invoice.total) + ' — ' + badge(invoice.status)
                    + (invoice.balance > 0 ? ' <a class="dash-link-btn" href="' + esc(applicationsRoot.dataset.receiptsUrl + '?application=' + app.id) + '">Receive ' + money(invoice.balance) + '</a>' : '') + '</strong></li>';
            }).join('');

            const tests = app.tests.map(function (test) {
                return '<li><span>' + esc(test.subject || 'Entry test') + ' · ' + esc(dateTimeFormat.format(new Date(test.scheduled_at))) + '</span><strong>'
                    + (test.result ? esc(test.obtained_marks + (test.max_marks ? ' / ' + test.max_marks : '')) + ' — ' + badge(test.result)
                        : '<input type="number" min="0" step="0.5" class="dash-inline-score" data-marks-for="' + test.id + '" placeholder="Marks" aria-label="Marks">'
                        + ' <select class="dash-inline-score" data-result-for="' + test.id + '" aria-label="Result"><option value="pass">Pass</option><option value="fail">Fail</option><option value="absent">Absent</option></select>'
                        + ' <button type="button" class="dash-link-btn" data-test-save="' + test.id + '" data-scheduled-at="' + esc(test.scheduled_at) + '">Save</button>')
                    + '</strong></li>';
            }).join('');

            const interviews = app.interviews.map(function (interview) {
                return '<li><span>Interview · ' + esc(dateTimeFormat.format(new Date(interview.scheduled_at))) + '</span><strong>'
                    + (interview.attended ? 'Held' + (interview.overall_rating ? ' · rating ' + esc(interview.overall_rating) + '/5' : '') + (interview.recommendation ? ' · ' + esc(headline(interview.recommendation)) : '')
                        : '<button type="button" class="dash-link-btn" data-interview-held="' + interview.id + '" data-scheduled-at="' + esc(interview.scheduled_at) + '">Mark as held</button>')
                    + '</strong></li>';
            }).join('');

            const history = app.history.map(function (item) {
                return '<li><span>' + esc(item.at ? dateFormat.format(new Date(item.at)) : '') + '</span><strong>' + esc(headline(item.to_status)) + (item.remarks ? ' <span class="dash-muted">' + esc(item.remarks) + '</span>' : '') + '</strong></li>';
            }).join('');

            detail.querySelector('[data-detail-body]').innerHTML = kv([
                ['Status', badge(app.status), true],
                ['Date of birth', shortDate(app.date_of_birth), true],
                ['B-Form', app.b_form_no],
                ['Previous school', app.previous_school],
                ['Admission no', app.admission_no ? '<a href="' + esc(applicationsRoot.dataset.profileUrl + '/' + app.student_id) + '">' + esc(app.admission_no) + '</a>' : null, true]
            ]) + '<h4 class="dash-detail-heading">Guardians</h4><ul class="dash-kv">' + guardians + '</ul>'
                + '<h4 class="dash-detail-heading">Challans</h4><ul class="dash-kv">' + (invoices || '<li><span>—</span><strong>No challans (no fee structure charges)</strong></li>') + '</ul>'
                + (tests ? '<h4 class="dash-detail-heading">Entry tests</h4><ul class="dash-kv">' + tests + '</ul>' : '')
                + (interviews ? '<h4 class="dash-detail-heading">Interviews</h4><ul class="dash-kv">' + interviews + '</ul>' : '')
                + '<h4 class="dash-detail-heading">History</h4><ul class="dash-kv">' + history + '</ul>';

            const open = app.next_statuses.length > 0;
            detail.querySelector('[data-step-buttons]').innerHTML = app.next_statuses.map(function (status) {
                return '<button type="button" class="dash-btn ' + (['rejected', 'withdrawn'].indexOf(status) !== -1 ? 'dash-btn-ghost' : 'dash-btn-primary') + ' dash-btn-sm" data-step="' + status + '">' + esc(headline(status)) + '</button>';
            }).join('');
            detail.querySelector('[data-step-form]').hidden = !open;
            detail.querySelector('[data-test-form]').hidden = !open || app.status === 'accepted';
            detail.querySelector('[data-interview-form]').hidden = !open || app.status === 'accepted';

            const enrolForm = detail.querySelector('[data-enrol-form]');
            enrolForm.hidden = app.status !== 'accepted';
            enrolForm.elements.admission_date.value = today();
            Array.prototype.forEach.call(enrolForm.elements.section_id.options, function (option) {
                option.hidden = option.value !== '' && Number(option.dataset.classId) !== app.class_applied_id;
            });
            enrolForm.elements.section_id.value = '';
        }

        const reopen = function () {
            openApplication({ id: detail.dataset.id });
            mounted.refresh();
        };

        const stepForm = detail.querySelector('[data-step-form]');
        stepForm.addEventListener('click', function (event) {
            const button = event.target.closest('[data-step]');

            if (!button) {
                return;
            }

            const body = { status: button.dataset.step, remarks: stepForm.elements.remarks.value.trim() || null };

            if (button.dataset.step === 'rejected' && stepForm.elements.rejection_reason_id.value) {
                body.rejection_reason_id = Number(stepForm.elements.rejection_reason_id.value);
            }

            kit.submitForm(stepForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/status', body)
                .then(function () {
                    stepForm.reset();
                    reopen();
                })
                .catch(function () {});
        });

        const testForm = detail.querySelector('[data-test-form]');
        testForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(testForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/tests', kit.readForm(testForm, { scheduled_at: 'text', subject_id: 'int', max_marks: 'int' }))
                .then(function () {
                    testForm.reset();
                    reopen();
                })
                .catch(function () {});
        });

        const interviewForm = detail.querySelector('[data-interview-form]');
        interviewForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(interviewForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/interviews', kit.readForm(interviewForm, { scheduled_at: 'text', venue_or_link: 'text' }))
                .then(function () {
                    interviewForm.reset();
                    reopen();
                })
                .catch(function () {});
        });

        detail.querySelector('[data-detail-body]').addEventListener('click', function (event) {
            const testSave = event.target.closest('[data-test-save]');
            const held = event.target.closest('[data-interview-held]');
            const request = testSave
                ? ui.api('PUT', applicationsRoot.dataset.testsUrl + '/' + testSave.dataset.testSave, {
                    scheduled_at: testSave.dataset.scheduledAt,
                    obtained_marks: detail.querySelector('[data-marks-for="' + testSave.dataset.testSave + '"]').value || null,
                    result: detail.querySelector('[data-result-for="' + testSave.dataset.testSave + '"]').value
                })
                : (held ? ui.api('PUT', applicationsRoot.dataset.interviewsUrl + '/' + held.dataset.interviewHeld, { scheduled_at: held.dataset.scheduledAt, attended: true }) : null);

            if (!request) {
                return;
            }

            request.then(function (envelope) {
                ui.toast(envelope.message);
                renderApplication(envelope.data);
                mounted.refresh();
            }).catch(function (envelope) {
                ui.toast(kit.messageOf(envelope, 'Could not save.'), 'error');
            });
        });

        const enrolForm = detail.querySelector('[data-enrol-form]');
        enrolForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = kit.readForm(enrolForm, { admission_date: 'text', section_id: 'int', roll_no: 'text', parent_password: 'text', student_password: 'text', issue_first_challan: 'bool', issue_id_card: 'bool' });

            Object.keys(body).forEach(function (key) {
                if (body[key] === null) {
                    delete body[key];
                }
            });

            kit.submitForm(enrolForm, 'POST', resourceUrl + '/' + detail.dataset.id + '/enrol', body)
                .then(function (envelope) {
                    detail.close();
                    mounted.refresh();
                    window.location.assign(applicationsRoot.dataset.profileUrl + '/' + envelope.data.id);
                })
                .catch(function () {});
        });
    }

    /* ---------- Inquiries ---------- */

    const inquiriesRoot = document.querySelector('[data-students="inquiries"]');

    if (inquiriesRoot) {
        const INQUIRY_FIELDS = { parent_name: 'text', phone: 'text', email: 'text', child_name: 'text', child_dob: 'text', class_sought_id: 'int', source_id: 'int', follow_up_on: 'text', status: 'text', previous_school: 'text', message: 'text' };
        const convertDialog = document.getElementById('studentsModal');
        const convertForm = convertDialog.querySelector('form');

        const mounted = kit.mount({
            root: inquiriesRoot,
            modal: document.getElementById('inquiryModal'),
            order: [[2, 'asc']],
            titles: { create: 'New inquiry', edit: 'Edit inquiry' },
            searchPlaceholder: 'Search parent or child…',
            emptyText: 'No inquiries yet.',
            params: filterReader(inquiriesRoot),
            nameOf: function (row) { return row.parent_name; },
            canEdit: function (row) { return row.status === 'converted' ? 'Converted' : true; },
            canDelete: function (row) { return row.status === 'converted' ? 'Converted' : true; },
            onView: function (row) {
                if (['converted', 'closed'].indexOf(row.status) !== -1) {
                    ui.toast('This inquiry is ' + row.status + '.', 'error');

                    return;
                }

                const [firstName, ...rest] = String(row.child_name || '').split(' ');

                kit.clearErrors(convertForm);
                fillApplication(convertForm, null);
                convertForm.elements.first_name.value = firstName || '';
                convertForm.elements.last_name.value = rest.join(' ');
                convertForm.elements.date_of_birth.value = row.child_dob || '';
                convertForm.elements.previous_school.value = row.previous_school || '';
                convertForm.elements.g0_name.value = row.parent_name;
                convertForm.elements.g0_phone.value = row.phone;

                if (row.class_sought_id) {
                    convertForm.elements.class_applied_id.value = row.class_sought_id;
                }

                convertDialog.querySelector('[data-modal-title]').textContent = 'Convert inquiry ' + row.inquiry_no;
                convertDialog.dataset.inquiryId = row.id;
                convertDialog.showModal();
            },
            columns: [
                { data: 'parent_name', render: display(function (value, row) { return titleCell(value, row.phone + ' · ' + row.inquiry_no) + '<button type="button" class="dash-link-btn" data-follow="' + row.id + '">Follow-ups (' + (row.follow_ups_count || 0) + ')</button>'; }) },
                { data: 'child_name', defaultContent: '', render: display(function (value, row) { return titleCell(value || '—', row.class_name); }) },
                { data: 'follow_up_on', searchable: false, className: 'dash-hide-md', render: display(shortDate) },
                { data: 'status', searchable: false, render: display(badge) }
            ],
            fill: function (form, row) {
                kit.fillForm(form, row, INQUIRY_FIELDS, { status: 'new' });
            },
            payload: function (form) {
                return kit.readForm(form, INQUIRY_FIELDS);
            }
        });

        mountFilters(inquiriesRoot, mounted.grid);
        kit.wireDialog(convertDialog);

        // Follow-up log: every call / visit with its outcome, and the next follow-up date.
        const followDialog = document.getElementById('followUpModal');
        const followForm = followDialog.querySelector('[data-follow-form]');
        const followList = followDialog.querySelector('[data-follow-list]');
        let followRow = null;

        const loadFollowUps = function () {
            ui.api('GET', inquiriesRoot.dataset.resourceUrl + '/' + followRow.id + '/follow-ups').then(function (envelope) {
                followList.innerHTML = envelope.data.length ? envelope.data.map(function (item) {
                    return '<li><div><strong>' + esc(item.outcome) + '</strong><span class="dash-muted">' + esc([shortDate(item.followed_up_on), item.channel ? item.channel.name : null, item.creator ? item.creator.name : null].filter(Boolean).join(' · ')) + '</span></div>'
                        + (item.next_follow_up_on ? '<span class="dash-badge dash-badge-brand">Next ' + esc(shortDate(item.next_follow_up_on)) + '</span>' : '') + '</li>';
                }).join('') : '<li><span class="dash-muted">No follow-ups yet.</span></li>';
            }).catch(function () {});
        };

        kit.wireDialog(followDialog);

        inquiriesRoot.addEventListener('click', function (event) {
            const button = event.target.closest('[data-follow]');

            if (!button) {
                return;
            }

            followRow = mounted.grid.row(button.closest('tr')).data();
            followDialog.querySelector('[data-follow-title]').textContent = 'Follow-ups: ' + followRow.parent_name;
            followDialog.querySelector('[data-follow-sub]').textContent = [followRow.child_name, followRow.class_name, followRow.phone].filter(Boolean).join(' · ');
            followForm.reset();
            kit.clearErrors(followForm);
            followForm.hidden = ['converted', 'closed'].indexOf(followRow.status) !== -1;
            followForm.elements.followed_up_on.value = today();
            followList.innerHTML = '<li class="dash-muted">Loading…</li>';
            followDialog.showModal();
            loadFollowUps();
        });

        followForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(followForm, 'POST', inquiriesRoot.dataset.resourceUrl + '/' + followRow.id + '/follow-ups', kit.readForm(followForm, { followed_up_on: 'text', channel_id: 'int', outcome: 'text', next_follow_up_on: 'text' }))
                .then(function () {
                    followForm.elements.outcome.value = '';
                    followForm.elements.next_follow_up_on.value = '';
                    loadFollowUps();
                    mounted.refresh();
                })
                .catch(function () {});
        });

        convertForm.addEventListener('submit', function (event) {
            event.preventDefault();
            kit.submitForm(convertForm, 'POST', inquiriesRoot.dataset.resourceUrl + '/' + convertDialog.dataset.inquiryId + '/convert', applicationPayload(convertForm, true))
                .then(function () {
                    convertDialog.close();
                    mounted.refresh();
                })
                .catch(function () {});
        });
    }
})();

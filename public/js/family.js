/**
 * Child page for parents and students: one overview call (class, timetable, exams, fees, leave, transport)
 * plus the attendance, diary, results and year-by-year endpoints, each shown on its own tab.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-family]');

    if (!root || !window.PinnacleDashboard) {
        return;
    }

    const ui = window.PinnacleDashboard;
    const DAYS = ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const STATUS_TONE = { present: 'dash-badge-ok', late: 'dash-badge-warn', half_day: 'dash-badge-warn', leave: 'dash-badge-brand', absent: 'dash-badge-danger' };
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const dayFormat = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: '2-digit', month: 'short' });
    const loaded = {};
    let overview = null;

    /* ---------- Helpers ---------- */

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function money(paisa) {
        return 'Rs ' + Math.round((Number(paisa) || 0) / 100).toLocaleString('en-PK');
    }

    function date(value, format) {
        return value ? esc((format || dateFormat).format(new Date(value + 'T00:00:00'))) : '—';
    }

    function headline(value) {
        return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
    }

    function today() {
        return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    }

    function badge(text, tone) {
        return '<span class="dash-badge ' + (tone || 'dash-badge-system') + '">' + esc(text) + '</span>';
    }

    function download(url, label) {
        return '<button type="button" class="dash-link-btn" data-download="' + esc(url) + '">' + esc(label || 'Download') + '</button>';
    }

    function empty(text) {
        return '<p class="dash-muted dash-family-empty">' + esc(text) + '</p>';
    }

    function failed(element) {
        element.innerHTML = empty('Could not load this. Please try again.');
    }

    root.addEventListener('click', function (event) {
        const button = event.target.closest('[data-download]');

        if (button) {
            ui.openFile(button.dataset.download, 'Preparing the file…').catch(function () {});
        }
    });

    const switcher = root.querySelector('[data-child-switch]');

    if (switcher) {
        switcher.addEventListener('change', function () { window.location.assign(switcher.value); });
    }

    /* ---------- Tabs ---------- */

    const tabs = Array.prototype.slice.call(root.querySelectorAll('[data-family-tab]'));
    const LOADERS = { attendance: loadAttendance, exams: loadResults, years: loadYears };

    function showTab(key) {
        tabs.forEach(function (tab) {
            const active = tab.dataset.familyTab === key;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        root.querySelectorAll('[data-family-panel]').forEach(function (panel) {
            panel.hidden = panel.dataset.familyPanel !== key;
        });

        if (LOADERS[key] && !loaded[key]) {
            loaded[key] = true;
            LOADERS[key]();
        }

        try { window.history.replaceState(null, '', '#' + key); } catch (error) { /* ignore */ }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { showTab(tab.dataset.familyTab); });
    });

    /* ---------- Overview (header, today, exams, fees, leave) ---------- */

    function renderHead(data) {
        const student = data.student;
        root.querySelector('[data-family-place]').textContent = [student.admission_no, student.class ? student.class + (student.section ? ' ' + student.section : '') : null, student.roll_no ? 'Roll ' + student.roll_no : null, student.session].filter(Boolean).join(' · ');
        root.querySelector('[data-family-badges]').innerHTML = badge(headline(student.status), student.status === 'active' ? 'dash-badge-ok' : 'dash-badge-system')
            + data.transport.map(function (ride) { return ' ' + badge('Bus: ' + ride.route + (ride.stop ? ' — ' + ride.stop : ''), 'dash-badge-brand'); }).join('');
        root.querySelector('[data-teacher-line]').textContent = student.class_teacher ? 'Class teacher: ' + student.class_teacher : '';

        if (student.leaving_certificate_url) {
            root.querySelector('[data-certificate]').innerHTML = download(student.leaving_certificate_url, 'School Leaving Certificate');
        }
    }

    function renderToday(slots) {
        const day = new Date().getDay() || 7;
        const list = root.querySelector('[data-today-periods]');
        const todays = slots.filter(function (slot) { return slot.day_of_week === day; }).sort(byPeriod);
        root.querySelector('[data-today-title]').textContent = DAYS[day] + "'s timetable";

        list.innerHTML = todays.length ? todays.map(function (slot) {
            return '<li><div><strong>' + esc(slot.subject || 'Free period') + '</strong><span class="dash-muted">' + esc([slot.teacher, slot.room || null].filter(Boolean).join(' · ')) + '</span></div>'
                + '<span class="dash-family-time">' + esc(slot.period ? (slot.period.starts_at || '').slice(0, 5) + '–' + (slot.period.ends_at || '').slice(0, 5) : '') + '</span></li>';
        }).join('') : '<li><span class="dash-muted">No classes on the timetable today.</span></li>';

        const days = [1, 2, 3, 4, 5, 6, 7].filter(function (number) { return slots.some(function (slot) { return slot.day_of_week === number; }); });
        const periods = [];
        slots.slice().sort(byPeriod).forEach(function (slot) {
            if (slot.period && !periods.some(function (period) { return period.id === slot.period_id; })) {
                periods.push({ id: slot.period_id, name: slot.period.name, starts: (slot.period.starts_at || '').slice(0, 5) });
            }
        });

        root.querySelector('[data-week]').innerHTML = periods.length ? '<thead><tr><th>Period</th>' + days.map(function (number) { return '<th>' + DAYS[number].slice(0, 3) + '</th>'; }).join('') + '</tr></thead><tbody>'
            + periods.map(function (period) {
                return '<tr><td><span class="dash-cell-title">' + esc(period.name) + '</span><span class="dash-muted">' + esc(period.starts) + '</span></td>' + days.map(function (number) {
                    const slot = slots.find(function (item) { return item.period_id === period.id && item.day_of_week === number; });

                    return '<td' + (number === day ? ' class="is-today"' : '') + '>' + (slot ? esc(slot.subject || '') : '<span class="dash-muted">—</span>') + '</td>';
                }).join('') + '</tr>';
            }).join('') + '</tbody>' : '<tbody><tr><td class="dash-muted">The timetable has not been set yet.</td></tr></tbody>';
    }

    function byPeriod(a, b) {
        return String(a.period ? a.period.starts_at : '').localeCompare(String(b.period ? b.period.starts_at : ''));
    }

    function renderExams(exams) {
        const box = root.querySelector('[data-exams]');
        const now = today();
        const upcoming = [];

        exams.forEach(function (exam) {
            exam.date_sheet.forEach(function (paper) {
                if (paper.date && paper.date >= now) {
                    upcoming.push({ date: paper.date, text: paper.subject + ' — ' + exam.name });
                }
            });
        });
        upcoming.sort(function (a, b) { return a.date.localeCompare(b.date); });
        root.querySelector('[data-stat="exam"]').innerHTML = upcoming.length ? esc(upcoming[0].text.split(' — ')[0]) + '<span class="dash-muted">' + date(upcoming[0].date, dayFormat) + '</span>' : 'None';

        box.innerHTML = exams.length ? exams.map(function (exam) {
            const card = exam.admit_card;
            const admit = card
                ? (card.is_eligible ? badge('Roll no ' + card.exam_roll_no, 'dash-badge-ok') + ' ' + download(card.pdf_url, 'Admit card') : badge(card.reason === 'fee_due' ? 'Admit card held: fees due' : 'Admit card held: ' + headline(card.reason), 'dash-badge-warn'))
                : '';

            return '<section class="dash-panel"><div class="dash-panel-head"><div><h2>' + esc(exam.name) + '</h2><p>' + esc([exam.term, headline(exam.status)].filter(Boolean).join(' · ')) + '</p></div>'
                + '<div class="dash-family-actions">' + admit + (exam.report_card_url ? ' ' + download(exam.report_card_url, 'Report card') : '') + '</div></div>'
                + (exam.date_sheet.length ? '<div class="dash-table-wrap"><table class="dash-table"><thead><tr><th>Subject</th><th>Date</th><th>Time</th></tr></thead><tbody>'
                    + exam.date_sheet.map(function (paper) {
                        return '<tr' + (paper.date === now ? ' class="is-today"' : '') + '><td>' + esc(paper.subject) + '</td><td>' + date(paper.date, dayFormat) + '</td><td>' + esc(paper.starts_at || '—') + (paper.duration_minutes ? ' <span class="dash-muted">' + paper.duration_minutes + ' min</span>' : '') + '</td></tr>';
                    }).join('') + '</tbody></table></div>' : empty('The date sheet is not out yet.'))
                + '</section>';
        }).join('') : '<section class="dash-panel">' + empty('No exams this session yet.') + '</section>';
    }

    function renderFees(fees) {
        root.querySelector('[data-stat="fees"]').innerHTML = fees.due > 0 ? esc(money(fees.due)) : 'Nothing due';
        const feeBadge = root.querySelector('[data-fee-badge]');
        feeBadge.textContent = fees.due > 0 ? money(fees.due) + ' due' : (fees.advance > 0 ? money(fees.advance) + ' paid in advance' : 'All paid');
        feeBadge.className = 'dash-badge ' + (fees.due > 0 ? 'dash-badge-warn' : 'dash-badge-ok');

        root.querySelector('[data-open-challans]').innerHTML = fees.open_challans.length ? fees.open_challans.map(function (challan) {
            return '<tr><td><span class="dash-cell-title">' + esc(challan.invoice_no) + '</span><span class="dash-muted">' + esc(challan.billing_month || headline(challan.invoice_type)) + '</span></td>'
                + '<td>' + date(challan.due_date) + (challan.is_overdue ? ' ' + badge('Overdue', 'dash-badge-danger') : '') + '</td>'
                + '<td class="dash-col-num">' + esc(money(challan.balance)) + '</td><td class="dash-col-actions">' + download(challan.pdf_url, 'Challan') + '</td></tr>';
        }).join('') : '<tr><td colspan="4" class="dash-muted">Nothing is due. Thank you!</td></tr>';

        root.querySelector('[data-receipts]').innerHTML = fees.receipts.length ? fees.receipts.map(function (receipt) {
            const reversed = receipt.status === 'reversed';

            return '<tr><td><span class="dash-cell-title">' + esc(receipt.receipt_no) + '</span><span class="dash-muted">' + esc(receipt.method || '') + '</span></td><td>' + date(receipt.received_on) + '</td>'
                + '<td class="dash-col-num">' + esc(money(receipt.amount)) + '</td><td>' + badge(reversed ? 'Reversed' : 'Paid', reversed ? 'dash-badge-system' : 'dash-badge-ok') + '</td>'
                + '<td class="dash-col-actions">' + download(receipt.pdf_url, 'Receipt') + '</td></tr>';
        }).join('') : '<tr><td colspan="5" class="dash-muted">No payments yet.</td></tr>';
    }

    /* ---------- Online payment by screenshot ---------- */

    const payBox = root.querySelector('[data-pay-online]');
    const proofForm = payBox.querySelector('[data-proof-form]');

    function renderProofs(data) {
        const PROOF_TONES = { approved: 'dash-badge-ok', pending: 'dash-badge-warn', rejected: 'dash-badge-danger' };
        const LABELS = { approved: 'Receipt made', pending: 'School is checking', rejected: 'Not accepted' };
        const methods = data.pay_to.methods;

        // Only offered once the school has written how to pay (Accounts → Payment Methods).
        payBox.hidden = methods.length === 0 && data.proofs.length === 0;
        proofForm.hidden = methods.length === 0;
        payBox.querySelector('[data-pay-to]').innerHTML = methods.map(function (method) {
            return '<div><strong>' + esc(method.name) + '</strong><span>' + esc(method.payment_instructions) + '</span></div>';
        }).join('') + (data.pay_to.bank ? '<div><strong>Bank transfer</strong><span>' + esc([data.pay_to.bank.bank, data.pay_to.bank.account_title, data.pay_to.bank.account_number, data.pay_to.bank.iban ? 'IBAN ' + data.pay_to.bank.iban : null].filter(Boolean).join(' · ')) + '</span></div>' : '');
        payBox.querySelector('[data-proof-method]').innerHTML = methods.map(function (method) { return '<option value="' + method.id + '">' + esc(method.name) + '</option>'; }).join('');
        payBox.querySelector('[data-proofs]').innerHTML = data.proofs.length ? data.proofs.map(function (proof) {
            return '<tr><td><span class="dash-cell-title">' + esc(proof.reference_no) + '</span><span class="dash-muted">' + esc(proof.method || '') + (proof.invoice_no ? ' · ' + esc(proof.invoice_no) : '') + '</span></td><td>' + date(proof.paid_on) + '</td>'
                + '<td class="dash-col-num">' + esc(money(proof.amount)) + '</td><td>' + badge(LABELS[proof.status], PROOF_TONES[proof.status]) + (proof.reject_reason ? '<span class="dash-muted">' + esc(proof.reject_reason) + '</span>' : '') + (proof.receipt_no ? '<span class="dash-muted">' + esc(proof.receipt_no) + '</span>' : '') + '</td></tr>';
        }).join('') : '<tr><td colspan="4" class="dash-muted">Nothing sent yet.</td></tr>';
    }

    function loadProofs() {
        return ui.api('GET', root.dataset.proofsUrl).then(function (envelope) { renderProofs(envelope.data); }).catch(function () {});
    }

    function fillChallans(openChallans) {
        payBox.querySelector('[data-proof-challan]').innerHTML = openChallans.map(function (challan) {
            return '<option value="' + challan.id + '" data-balance="' + challan.balance + '">' + esc(challan.invoice_no) + ' — ' + esc(money(challan.balance)) + '</option>';
        }).join('') + '<option value="">Oldest unpaid challans</option>';
        proofForm.elements.amount.value = openChallans.length ? Math.round(openChallans[0].balance / 100) : '';
        proofForm.elements.paid_on.value = today();
    }

    payBox.querySelector('[data-proof-challan]').addEventListener('change', function (event) {
        const option = event.target.selectedOptions[0];

        if (option && option.dataset.balance) {
            proofForm.elements.amount.value = Math.round(Number(option.dataset.balance) / 100);
        }
    });

    proofForm.addEventListener('submit', function (event) {
        event.preventDefault();
        window.PinnacleResource.clearErrors(proofForm);

        const body = new window.FormData();
        body.append('payment_method_id', proofForm.elements.payment_method_id.value);
        body.append('amount', String(Math.round(Number(proofForm.elements.amount.value) * 100)));
        body.append('paid_on', proofForm.elements.paid_on.value);
        body.append('reference_no', proofForm.elements.reference_no.value.trim());
        body.append('payer_name', proofForm.elements.payer_name.value.trim());

        if (proofForm.elements.fee_invoice_id.value) {
            body.append('fee_invoice_id', proofForm.elements.fee_invoice_id.value);
        }

        if (proofForm.elements.screenshot.files[0]) {
            body.append('screenshot', proofForm.elements.screenshot.files[0]);
        }

        const submit = proofForm.querySelector('[data-submit]');
        submit.disabled = true;
        ui.api('POST', root.dataset.proofsUrl, body).then(function (envelope) {
            ui.toast(envelope.message);
            proofForm.elements.reference_no.value = '';
            proofForm.elements.payer_name.value = '';
            proofForm.elements.screenshot.value = '';
            loadProofs();
        }).catch(function (envelope) {
            window.PinnacleResource.showErrors(proofForm, envelope);
        }).finally(function () { submit.disabled = false; });
    });

    function renderLeave(requests) {
        const TONES = { approved: 'dash-badge-ok', pending: 'dash-badge-warn', rejected: 'dash-badge-danger' };

        root.querySelector('[data-leave-list]').innerHTML = requests.length ? requests.map(function (leave) {
            return '<li><div><strong>' + date(leave.from_date, dayFormat) + (leave.to_date !== leave.from_date ? ' – ' + date(leave.to_date, dayFormat) : '') + '</strong>'
                + '<span class="dash-muted">' + esc(leave.reason) + (leave.review_remarks ? ' · Teacher: ' + esc(leave.review_remarks) : '') + '</span></div>' + badge(headline(leave.status), TONES[leave.status]) + '</li>';
        }).join('') : '<li><span class="dash-muted">No leave requests yet.</span></li>';
    }

    function renderRecords(records) {
        const rows = records.achievements.map(function (item) {
            return '<li><div><strong>🏆 ' + esc(item.title) + '</strong><span class="dash-muted">' + esc([item.category ? item.category.name : null, item.level ? headline(item.level) + ' level' : null, item.description].filter(Boolean).join(' · ')) + '</span></div><span class="dash-muted">' + (item.achieved_on ? date(String(item.achieved_on).slice(0, 10)) : '') + '</span></li>';
        }).concat(records.discipline.map(function (item) {
            return '<li><div><strong>' + esc((item.category ? item.category.name : 'Note') + ' — ' + item.description) + '</strong>' + (item.action_taken ? '<span class="dash-muted">Action: ' + esc(item.action_taken) + '</span>' : '') + '</div><span class="dash-muted">' + date(String(item.incident_date).slice(0, 10)) + '</span></li>';
        }));

        root.querySelector('[data-family-records]').innerHTML = rows.join('') || '<li><span class="dash-muted">Nothing recorded yet.</span></li>';
    }

    function renderLibrary(books) {
        root.querySelector('[data-family-library]').innerHTML = books.length ? books.map(function (book) {
            const state = book.status === 'issued'
                ? badge(book.is_overdue ? 'Overdue — due ' + date(book.due_on) : 'Due ' + date(book.due_on), book.is_overdue ? 'dash-badge-danger' : 'dash-badge-warn')
                : badge(book.status === 'lost' ? 'Lost' : 'Returned ' + date(book.returned_on), book.status === 'lost' ? 'dash-badge-danger' : 'dash-badge-ok');

            return '<li><div><strong>' + esc(book.title) + '</strong><span class="dash-muted">' + esc([book.author, book.accession_no, book.fine_amount > 0 ? 'Fine ' + money(book.fine_amount) : null].filter(Boolean).join(' · ')) + '</span></div>' + state + '</li>';
        }).join('') : '<li><span class="dash-muted">No library books yet.</span></li>';
    }

    function loadOverview() {
        return ui.api('GET', root.dataset.overviewUrl).then(function (envelope) {
            overview = envelope.data;
            renderHead(overview);
            renderToday(overview.timetable);
            renderExams(overview.exams);
            renderFees(overview.fees);
            fillChallans(overview.fees.open_challans);
            loadProofs();
            renderLeave(overview.leave_requests);
            renderRecords(overview.records);
            renderLibrary(overview.library);
        }).catch(function () {
            failed(root.querySelector('[data-exams]'));
            root.querySelector('[data-today-periods]').innerHTML = '<li><span class="dash-muted">Could not load the timetable.</span></li>';
        });
    }

    /* ---------- Diary ---------- */

    function loadDiary() {
        const box = root.querySelector('[data-diary]');
        const canSign = root.dataset.canSign === '1';

        ui.api('GET', root.dataset.diaryUrl).then(function (envelope) {
            box.innerHTML = envelope.data.length ? envelope.data.map(function (day) {
                return '<article class="dash-family-diary-day">'
                    + '<header><strong>' + date(day.date, dayFormat) + '</strong>'
                    + (day.signed ? badge('Signed', 'dash-badge-ok') : (canSign ? '<button type="button" class="dash-btn dash-btn-ghost dash-btn-sm" data-sign-diary="' + day.id + '">Sign</button>' : ''))
                    + '</header>'
                    + (day.note ? '<p class="dash-family-note">' + esc(day.note) + '</p>' : '')
                    + '<ul>' + day.entries.map(function (entry) {
                        return '<li><span class="dash-badge dash-badge-brand">' + esc(entry.type || 'Note') + '</span> <strong>' + esc(entry.subject || '') + '</strong> ' + esc(entry.title || '')
                            + (entry.details ? '<span class="dash-muted">' + esc(entry.details) + '</span>' : '')
                            + (entry.due_on ? '<span class="dash-family-due">Due ' + date(entry.due_on, dayFormat) + '</span>' : '') + '</li>';
                    }).join('') + '</ul>'
                    + day.remarks.map(function (remark) { return '<p class="dash-family-remark">' + esc(remark.type ? remark.type + ': ' : '') + esc(remark.remark) + '</p>'; }).join('')
                    + '</article>';
            }).join('') : empty('No diary in the last two weeks.');
        }).catch(function () { failed(box); });
    }

    root.querySelector('[data-diary]').addEventListener('click', function (event) {
        const button = event.target.closest('[data-sign-diary]');

        if (!button) {
            return;
        }

        button.disabled = true;
        ui.api('POST', root.dataset.signUrl + '/' + button.dataset.signDiary + '/acknowledge', { student_id: Number(root.dataset.studentId) }).then(function (envelope) {
            ui.toast(envelope.message);
            button.outerHTML = badge('Signed', 'dash-badge-ok');
        }).catch(function (envelope) {
            button.disabled = false;
            ui.toast((envelope && envelope.message) || 'Could not sign the diary.', 'error');
        });
    });

    /* ---------- Attendance ---------- */

    const monthField = root.querySelector('[data-attendance-month]');
    monthField.value = today().slice(0, 7);
    monthField.max = today().slice(0, 7);

    function monthRange(month) {
        const [year, number] = month.split('-').map(Number);
        const last = new Date(year, number, 0).getDate();
        const to = month + '-' + String(last).padStart(2, '0');

        return { from: month + '-01', to: to > today() ? today() : to };
    }

    function loadAttendance(statOnly) {
        const range = monthRange(monthField.value || today().slice(0, 7));

        return ui.api('GET', root.dataset.attendanceUrl + '?from=' + range.from + '&to=' + range.to).then(function (envelope) {
            const data = envelope.data;

            if (statOnly === true) {
                root.querySelector('[data-stat="attendance"]').textContent = data.percentage === null ? '—' : data.percentage + '%';

                return;
            }

            root.querySelector('[data-attendance-range]').textContent = date(range.from) + ' to ' + date(range.to) + (data.percentage === null ? '' : ' · ' + data.percentage + '% attended');
            root.querySelector('[data-attendance-counts]').innerHTML = Object.keys(data.counts).map(function (status) {
                return '<li><span class="dash-badge ' + (STATUS_TONE[status] || '') + '">' + esc(headline(status)) + '</span><strong>' + data.counts[status] + '</strong></li>';
            }).join('');
            root.querySelector('[data-attendance-days]').innerHTML = data.days.length ? data.days.map(function (day) {
                return '<span class="dash-family-day ' + (STATUS_TONE[day.status] || '') + '" title="' + esc(headline(day.status)) + '"><b>' + date(day.date, dayFormat) + '</b>' + esc(headline(day.status)) + '</span>';
            }).join('') : empty('No attendance marked in this month.');
        }).catch(function () { failed(root.querySelector('[data-attendance-days]')); });
    }

    monthField.addEventListener('change', function () { loadAttendance(); });

    /* ---------- Results ---------- */

    function loadResults() {
        const box = root.querySelector('[data-results]');

        ui.api('GET', root.dataset.onlineTestsUrl).then(function (envelope) {
            root.querySelector('[data-online-tests]').innerHTML = envelope.data.length ? envelope.data.map(function (test) {
                const score = test.attempt_status === 'submitted' ? badge(test.score + ' / ' + test.total_marks, 'dash-badge-ok') : (test.is_open ? badge(test.attempt_status === 'in_progress' ? 'Started' : 'Open now', 'dash-badge-warn') : badge('Not taken', 'dash-badge-system'));

                return '<tr><td><span class="dash-cell-title">' + esc(test.title) + '</span>' + (test.closes_at ? '<span class="dash-muted">Until ' + esc(dayFormat.format(new Date(test.closes_at))) + '</span>' : '') + '</td><td>' + esc(test.subject || '') + '</td><td class="dash-col-num">' + score + '</td></tr>';
            }).join('') : '<tr><td colspan="3" class="dash-muted">No online tests yet.</td></tr>';
        }).catch(function () {
            root.querySelector('[data-online-tests]').innerHTML = '<tr><td colspan="3" class="dash-muted">Could not load the online tests.</td></tr>';
        });

        ui.api('GET', root.dataset.resultsUrl).then(function (envelope) {
            box.innerHTML = envelope.data.length ? envelope.data.map(function (result) {
                return '<div class="dash-family-result"><header><strong>' + esc(result.exam) + '</strong>'
                    + badge((result.percentage !== null ? Number(result.percentage) + '%' : '') + (result.grade ? ' · ' + result.grade : ''), result.result === 'fail' ? 'dash-badge-danger' : 'dash-badge-ok')
                    + (result.position_in_section ? ' <span class="dash-muted">Position ' + esc(result.position_in_section) + ' in section</span>' : '') + '</header>'
                    + '<div class="dash-table-wrap"><table class="dash-table"><thead><tr><th>Subject</th><th class="dash-col-num">Marks</th><th>Grade</th></tr></thead><tbody>'
                    + result.subjects.map(function (subject) {
                        return '<tr><td>' + esc(subject.subject) + '</td><td class="dash-col-num">' + (subject.absent ? 'Absent' : esc(subject.obtained === null ? '—' : subject.obtained) + ' / ' + esc(subject.max)) + '</td><td>' + esc(subject.grade || '—') + '</td></tr>';
                    }).join('') + '</tbody></table></div></div>';
            }).join('') : empty('No published results yet.');
        }).catch(function () { failed(box); });
    }

    function loadLatestResult() {
        ui.api('GET', root.dataset.resultsUrl).then(function (envelope) {
            const latest = envelope.data[0];
            root.querySelector('[data-stat="result"]').innerHTML = latest ? esc((latest.percentage !== null ? Number(latest.percentage) + '%' : headline(latest.result)) + (latest.grade ? ' · ' + latest.grade : '')) + '<span class="dash-muted">' + esc(latest.exam) + '</span>' : 'None yet';
        }).catch(function () { root.querySelector('[data-stat="result"]').textContent = '—'; });
    }

    /* ---------- Year by year ---------- */

    function loadYears() {
        const rows = root.querySelector('[data-years]');
        const ENDED = { active: 'Studying', promoted: 'Promoted', repeated: 'Kept in class', demoted: 'Moved down', graduated: 'Graduated', withdrawn: 'Left' };

        ui.api('GET', root.dataset.historyUrl).then(function (envelope) {
            rows.innerHTML = envelope.data.length ? envelope.data.map(function (year) {
                return '<tr><td>' + esc(year.session ? year.session.name : '—') + '</td><td>' + esc([year.class, year.section].filter(Boolean).join(' ')) + '</td><td>' + esc(ENDED[year.status] || headline(year.status)) + '</td>'
                    + '<td>' + (year.results.map(function (card) { return esc(card.exam) + ': ' + esc(card.percentage !== null ? Number(card.percentage) + '%' : headline(card.result)); }).join('<br>') || '<span class="dash-muted">—</span>') + '</td>'
                    + '<td class="dash-col-num">' + (year.attendance.percentage !== null ? esc(year.attendance.percentage) + '%' : '—') + '</td>'
                    + '<td class="dash-col-num">' + esc(money(year.fees.billed)) + '</td><td class="dash-col-num">' + esc(money(year.fees.balance)) + '</td></tr>';
            }).join('') : '<tr><td colspan="7" class="dash-muted">No sessions yet.</td></tr>';
        }).catch(function () { rows.innerHTML = '<tr><td colspan="7" class="dash-muted">Could not load the history.</td></tr>'; });
    }

    /* ---------- Leave request ---------- */

    const leaveForm = root.querySelector('[data-leave-form]');

    if (leaveForm && window.PinnacleResource) {
        const kit = window.PinnacleResource;
        leaveForm.elements.from_date.min = today();
        leaveForm.elements.to_date.min = today();

        leaveForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const body = kit.readForm(leaveForm, { from_date: 'text', to_date: 'text', reason: 'text' });
            body.student_id = Number(root.dataset.studentId);

            kit.submitForm(leaveForm, 'POST', root.dataset.leaveUrl, body).then(function () {
                leaveForm.reset();
                loadOverview();
            }).catch(function () {});
        });
    }

    /* ---------- Start ---------- */

    loadOverview();
    loadDiary();
    loadAttendance(true);
    loadLatestResult();

    const start = window.location.hash.replace('#', '');
    showTab(tabs.some(function (tab) { return tab.dataset.familyTab === start; }) ? start : 'today');
})();

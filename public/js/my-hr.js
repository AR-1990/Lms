/**
 * My HR (employee self-service): leave balances and requests, payslips, loans, appraisals (own and to review)
 * and a manager's team leave.
 * Same API as the mobile app: /api/erp/my-hr/*.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-my-hr]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit) {
        return;
    }

    const api = root.dataset.api;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const monthFormat = new Intl.DateTimeFormat('en-GB', { month: 'long', year: 'numeric' });
    const rupees = function (paisa) { return 'Rs ' + Math.round((Number(paisa) || 0) / 100).toLocaleString('en-PK'); };
    const BADGES = { pending: 'dash-badge-warn', approved: 'dash-badge-ok', rejected: 'dash-badge-danger', cancelled: 'dash-badge-system', paid: 'dash-badge-ok', final: 'dash-badge-ok', active: 'dash-badge-warn', closed: 'dash-badge-system' };

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function badge(status) {
        return '<span class="dash-badge ' + (BADGES[status] || '') + '">' + esc(status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';
    }

    function range(from, to) {
        return dateFormat.format(new Date(from)) + (to !== from ? ' – ' + dateFormat.format(new Date(to)) : '');
    }

    function loadLeave() {
        return ui.api('GET', api + '/leave').then(function (envelope) {
            root.querySelector('[data-balances]').innerHTML = envelope.data.balances.map(function (balance) {
                const total = (balance.allocated || 0) + (balance.carried_forward || 0);
                const percent = total ? Math.min(100, Math.round(balance.used * 100 / total)) : 0;

                return '<article class="dash-leave-card">'
                    + '<span class="dash-stat-label">' + esc(balance.name) + '</span>'
                    + (balance.is_paid
                        ? '<strong class="dash-stat-value">' + esc(balance.remaining) + '<small> / ' + esc(total) + ' days left</small></strong><div class="dash-board-progress" style="--board:#7c3aed"><span style="width:' + percent + '%"></span></div>'
                        : '<strong class="dash-stat-value">' + esc(balance.used) + '<small> days taken (unpaid)</small></strong>')
                    + '</article>';
            }).join('');

            root.querySelector('[data-leave-list]').innerHTML = envelope.data.requests.length ? envelope.data.requests.map(function (leave) {
                const cancellable = leave.status === 'pending' || (leave.status === 'approved' && new Date(leave.from_date) >= new Date(new Date().toDateString()));

                return '<li><div><strong>' + esc(leave.leave_type) + ' · ' + esc(leave.days) + ' day' + (leave.days === 1 ? '' : 's') + '</strong>'
                    + '<span class="dash-muted">' + esc(range(leave.from_date, leave.to_date)) + (leave.review_remarks ? ' · ' + esc(leave.review_remarks) : '') + '</span></div>'
                    + '<span>' + badge(leave.status) + (cancellable ? ' <button type="button" class="dash-link-btn" data-cancel-leave="' + leave.id + '">Cancel</button>' : '') + '</span></li>';
            }).join('') : '<li class="dash-muted">No leave yet.</li>';
        });
    }

    function loadPayslips() {
        return ui.api('GET', api + '/payslips').then(function (envelope) {
            root.querySelector('[data-payslip-list]').innerHTML = envelope.data.length ? envelope.data.map(function (payslip) {
                return '<li><div><strong>' + esc(monthFormat.format(new Date(payslip.month))) + '</strong><span class="dash-muted">Gross ' + rupees(payslip.gross) + ' · deductions ' + rupees(payslip.deductions) + '</span>'
                    + '<span class="dash-muted">' + esc([payslip.present_days + ' of ' + payslip.working_days + ' days present', payslip.late_count ? payslip.late_count + ' late' + (Number(payslip.late_deduction_days) ? ' (' + Number(payslip.late_deduction_days) + ' day cut)' : '') : null, Number(payslip.absent_days) ? payslip.absent_days + ' absent' : null].concat((payslip.leave_taken || []).map(function (leave) { return leave.days + ' ' + leave.leave_type; })).filter(Boolean).join(' · ')) + '</span></div>'
                    + '<span><strong>' + rupees(payslip.net) + '</strong> ' + badge(payslip.status) + ' <button type="button" class="dash-link-btn" data-payslip="' + payslip.id + '">PDF</button></span></li>';
            }).join('') : '<li class="dash-muted">No payslips yet.</li>';
        });
    }

    function loadLoans() {
        return ui.api('GET', api + '/loans').then(function (envelope) {
            root.querySelector('[data-loan-list]').innerHTML = envelope.data.length ? envelope.data.map(function (loan) {
                const paid = loan.instalment_rows.filter(function (row) { return row.status === 'deducted'; }).length;

                return '<li><div><strong>' + rupees(loan.amount) + (loan.loan_type ? ' · ' + esc(loan.loan_type.name) : '') + '</strong>'
                    + '<span class="dash-muted">' + esc(loan.instalments) + ' × ' + rupees(loan.instalment_amount) + ' from ' + esc(monthFormat.format(new Date(loan.start_month)))
                    + (loan.status === 'active' ? ' · ' + paid + ' paid, ' + rupees(loan.outstanding) + ' left' : '') + (loan.review_remarks ? ' · ' + esc(loan.review_remarks) : '') + '</span></div>'
                    + badge(loan.status) + '</li>';
            }).join('') : '<li class="dash-muted">No loans.</li>';
        });
    }

    function loadTeam() {
        const list = root.querySelector('[data-team-list]');

        if (!list) {
            return Promise.resolve();
        }

        return ui.api('GET', root.dataset.teamUrl + '/datatable?draw=1&length=50&status=pending').then(function (envelope) {
            const rows = envelope.data.data;

            list.innerHTML = rows.length ? rows.map(function (leave) {
                return '<li><div><strong>' + esc(leave.employee_name) + ' · ' + esc(leave.leave_type_name) + ' · ' + esc(leave.days) + ' day(s)</strong>'
                    + '<span class="dash-muted">' + esc(range(leave.from_date, leave.to_date)) + ' · ' + esc(leave.reason) + '</span></div>'
                    + '<span><button type="button" class="dash-btn dash-btn-primary" data-team-decide="' + leave.id + '" data-approve="1">Approve</button> '
                    + '<button type="button" class="dash-btn dash-btn-ghost" data-team-decide="' + leave.id + '" data-approve="0">Reject</button></span></li>';
            }).join('') : '<li class="dash-muted">Nothing waiting for you.</li>';
        });
    }

    const APPRAISAL = { self_review: ['Rate yourself', 'dash-badge-warn'], draft: ['With reviewer', 'dash-badge-warn'], submitted: ['Ready to read', 'dash-badge-ok'], acknowledged: ['Acknowledged', 'dash-badge-system'] };

    function appraisalLine(appraisal, title, sub) {
        const meta = APPRAISAL[appraisal.status] || [appraisal.status, ''];

        return '<li><div><strong>' + esc(title) + '</strong><span class="dash-muted">' + esc(sub) + '</span></div>'
            + '<span><span class="dash-badge ' + meta[1] + '">' + esc(meta[0]) + '</span> <button type="button" class="dash-link-btn" data-appraisal="' + appraisal.id + '">Open</button></span></li>';
    }

    function loadAppraisals() {
        return ui.api('GET', root.dataset.appraisalsUrl).then(function (envelope) {
            const panel = root.querySelector('[data-appraisal-review-panel]');

            root.querySelector('[data-appraisal-mine]').innerHTML = envelope.data.mine.length ? envelope.data.mine.map(function (appraisal) {
                return appraisalLine(appraisal, appraisal.cycle, [appraisal.reviewer ? 'Reviewer ' + appraisal.reviewer : 'Reviewer not set yet', appraisal.overall_score !== null ? 'score ' + Number(appraisal.overall_score).toFixed(2) + ' / 5' : null, appraisal.rating].filter(Boolean).join(' · '));
            }).join('') : '<li class="dash-muted">No appraisals yet.</li>';
            root.querySelector('[data-appraisal-review]').innerHTML = envelope.data.to_review.map(function (appraisal) {
                return appraisalLine(appraisal, appraisal.employee, appraisal.cycle + (appraisal.status === 'self_review' ? ' · still rating themselves' : ''));
            }).join('');
            panel.hidden = envelope.data.to_review.length === 0;
        });
    }

    root.addEventListener('click', function (event) {
        const appraisal = event.target.closest('[data-appraisal]');

        if (appraisal && window.PinnacleAppraisal) {
            window.PinnacleAppraisal.open(appraisal.dataset.appraisal, loadAppraisals);
        }

        const cancel = event.target.closest('[data-cancel-leave]');
        const payslip = event.target.closest('[data-payslip]');
        const decide = event.target.closest('[data-team-decide]');

        if (cancel) {
            ui.api('POST', api + '/leave/' + cancel.dataset.cancelLeave + '/cancel').then(function (envelope) {
                ui.toast(envelope.message);
                loadLeave();
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'Could not cancel.'), 'error'); });
        }

        if (payslip) {
            ui.openFile(api + '/payslips/' + payslip.dataset.payslip + '/pdf', 'Preparing your payslip…').catch(function () {});
        }

        if (decide && decide.dataset.approve === '1') {
            ui.api('POST', root.dataset.teamUrl + '/' + decide.dataset.teamDecide + '/decide', { approve: true }).then(function (envelope) {
                ui.toast(envelope.message);
                loadTeam();
            }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'That did not work.'), 'error'); });
        } else if (decide) {
            rejecting = decide.dataset.teamDecide;
            rejectForm.reset();
            kit.clearErrors(rejectForm);
            rejectForm.closest('dialog').showModal();
        }
    });

    const rejectForm = document.querySelector('[data-team-reject]');
    let rejecting = null;

    rejectForm.addEventListener('submit', function (event) {
        event.preventDefault();
        kit.submitForm(rejectForm, 'POST', root.dataset.teamUrl + '/' + rejecting + '/decide', { approve: false, remarks: rejectForm.elements.remarks.value.trim() }).then(function () {
            rejectForm.closest('dialog').close();
            loadTeam();
        }).catch(function () {});
    });

    document.querySelectorAll('[data-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            const dialog = document.getElementById(button.dataset.open);

            dialog.querySelector('form').reset();
            kit.clearErrors(dialog.querySelector('form'));
            dialog.showModal();
        });
    });

    document.querySelectorAll('dialog [data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () { button.closest('dialog').close(); });
    });

    const leaveForm = document.querySelector('[data-apply-leave]');

    leaveForm.addEventListener('submit', function (event) {
        event.preventDefault();
        kit.submitForm(leaveForm, 'POST', api + '/leave', kit.readForm(leaveForm, { leave_type_id: 'int', from_date: 'text', to_date: 'text', is_half_day: 'bool', reason: 'text' })).then(function () {
            leaveForm.closest('dialog').close();
            loadLeave();
        }).catch(function () {});
    });

    const loanForm = document.querySelector('[data-request-loan]');

    loanForm.addEventListener('submit', function (event) {
        event.preventDefault();
        kit.submitForm(loanForm, 'POST', api + '/loans', kit.readForm(loanForm, { loan_type_id: 'int', amount: 'money', instalments: 'int', start_month: 'text', reason: 'text' })).then(function () {
            loanForm.closest('dialog').close();
            loadLoans();
        }).catch(function () {});
    });

    Promise.all([loadLeave(), loadPayslips(), loadLoans(), loadTeam(), loadAppraisals()]).catch(function () {
        ui.toast('Some of your HR details could not be loaded.', 'error');
    });
})();

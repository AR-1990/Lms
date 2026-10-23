/**
 * The appraisal popup shared by HR → Appraisals and My HR. What it shows depends on the API's `can` flags:
 * the employee rates themselves or acknowledges, the reviewer (or HR) scores 1–5 per criterion, HR can
 * change the reviewer or reopen. window.PinnacleAppraisal.open(id, onChange).
 */
// Runs after every deferred script, so it works wherever the popup partial sits on the page.
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const modal = document.getElementById('appraisalModal');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!modal || !ui || !kit) {
        return;
    }

    const url = modal.dataset.appraisalUrl;
    const form = modal.querySelector('[data-appraisal-form]');
    const body = modal.querySelector('[data-appraisal-body]');
    const actions = modal.querySelector('[data-appraisal-actions]');
    const LABELS = { 1: 'Poor', 2: 'Fair', 3: 'Good', 4: 'Very good', 5: 'Outstanding' };
    const STATUS = { self_review: ['Self-review', 'dash-badge-warn'], draft: ['With reviewer', 'dash-badge-warn'], submitted: ['Awaiting acknowledgement', 'dash-badge-system'], acknowledged: ['Acknowledged', 'dash-badge-ok'] };
    let current = null;
    let onChange = null;
    let timer = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function score(value) {
        return value === null || value === undefined ? '—' : Number(value).toFixed(2).replace(/\.?0+$/, '');
    }

    function statusBadge(status) {
        const meta = STATUS[status] || [status, ''];

        return '<span class="dash-badge ' + meta[1] + '">' + esc(meta[0]) + '</span>';
    }

    /* One row of 1–5 pills for a criterion. */
    function picker(name, value) {
        let html = '<div class="dash-score-pick" role="radiogroup">';

        for (let point = 1; point <= 5; point += 1) {
            html += '<label data-tooltip="' + LABELS[point] + '"><input type="radio" name="' + name + '" value="' + point + '"' + (Number(value) === point ? ' checked' : '') + '><span>' + point + '</span></label>';
        }

        return html + '</div>';
    }

    function textarea(name, label, value, rows) {
        return '<div class="dash-field dash-field-wide"><label for="appraisal_' + name + '">' + esc(label) + '</label>'
            + '<textarea id="appraisal_' + name + '" name="' + name + '" rows="' + (rows || 3) + '">' + esc(value || '') + '</textarea>'
            + '<p class="dash-field-error" data-error-for="' + name + '" hidden></p></div>';
    }

    function render() {
        const a = current;
        const can = a.can;
        const mode = can.self_review ? 'self' : (can.review ? 'review' : 'read');
        const facts = [
            ['Status', statusBadge(a.status)],
            ['Department', esc(a.employee.department || '—')],
            ['Reviewer', esc(a.reviewer || 'Not set')],
            ['Self score', score(a.self_score) + (a.self_score !== null ? ' / 5' : '')],
            ['Overall score', score(a.overall_score) + (a.overall_score !== null ? ' / 5' : '')],
            ['Rating', esc(a.rating || '—')]
        ];

        modal.querySelector('[data-appraisal-title]').textContent = a.employee.name + ' · ' + a.cycle;
        modal.querySelector('[data-appraisal-sub]').textContent = [a.employee.code, a.employee.designation, a.cycle_open ? null : 'cycle closed'].filter(Boolean).join(' · ');

        let html = '<dl class="dash-asset-facts">' + facts.map(function (fact) { return '<div><dt>' + fact[0] + '</dt><dd>' + fact[1] + '</dd></div>'; }).join('') + '</dl>';

        if (mode === 'self') {
            html += '<p class="dash-muted dash-appraisal-hint">Rate yourself honestly from 1 (poor) to 5 (outstanding). Your reviewer sees this before scoring you.</p>';
        } else if (mode === 'review') {
            html += '<p class="dash-muted dash-appraisal-hint">Score each criterion from 1 (poor) to 5 (outstanding). The overall score is weighted; the employee sees everything once you submit.</p>';
        }

        html += '<h4>Scores</h4><div class="dash-appraisal-scores">';
        a.scores.forEach(function (row, index) {
            html += '<article class="dash-appraisal-row" data-score-row="' + row.id + '">'
                + '<header><strong>' + esc(row.criterion) + '</strong><span class="dash-badge dash-badge-system">' + score(row.weight) + '%</span></header>'
                + (row.description ? '<p class="dash-muted">' + esc(row.description) + '</p>' : '');

            if (mode === 'self') {
                html += picker('self_' + index, row.self_score)
                    + '<input type="text" class="dash-appraisal-comment" data-comment maxlength="500" placeholder="Example or note (optional)" value="' + esc(row.self_comment || '') + '">';
            } else {
                html += '<div class="dash-appraisal-line"><span>Self</span><strong>' + score(row.self_score) + '</strong>' + (row.self_comment ? '<em>' + esc(row.self_comment) + '</em>' : '') + '</div>';

                if (mode === 'review') {
                    html += picker('score_' + index, row.score)
                        + '<input type="text" class="dash-appraisal-comment" data-comment maxlength="500" placeholder="Reviewer comment (optional)" value="' + esc(row.comment || '') + '">';
                } else if (row.score !== null) {
                    html += '<div class="dash-appraisal-line"><span>Reviewer</span><strong>' + score(row.score) + '</strong>' + (row.comment ? '<em>' + esc(row.comment) + '</em>' : '') + '</div>';
                }
            }

            html += '</article>';
        });
        html += '</div><p class="dash-field-error" data-error-for="scores" hidden></p>';

        if (mode === 'review') {
            html += '<p class="dash-appraisal-live">Weighted score so far: <strong data-live-score>' + score(a.overall_score) + '</strong> / 5</p>'
                + '<div class="dash-form-grid">'
                + '<div class="dash-field"><label for="appraisal_rating">Rating</label><select id="appraisal_rating" name="rating_id"><option value="">Suggest from the score</option>'
                + a.ratings.map(function (rating) { return '<option value="' + rating.id + '"' + (rating.id === a.rating_id ? ' selected' : '') + '>' + esc(rating.name) + '</option>'; }).join('')
                + '</select><p class="dash-field-error" data-error-for="rating_id" hidden></p></div>'
                + textarea('strengths', 'Strengths', a.strengths)
                + textarea('improvements', 'Areas to improve', a.improvements)
                + textarea('goals', 'Goals for the next period', a.goals)
                + '</div>';
        } else {
            [['strengths', 'Strengths'], ['improvements', 'Areas to improve'], ['goals', 'Goals for the next period']].forEach(function (pair) {
                if (a[pair[0]]) {
                    html += '<h4>' + pair[1] + '</h4><p class="dash-appraisal-text">' + esc(a[pair[0]]) + '</p>';
                }
            });
        }

        if (can.acknowledge) {
            html += '<div class="dash-form-grid">' + textarea('employee_comments', 'Your comments (optional)', a.employee_comments) + '</div>';
        } else if (a.employee_comments) {
            html += '<h4>Employee comments</h4><p class="dash-appraisal-text">' + esc(a.employee_comments) + '</p>';
        }

        if (can.reassign) {
            html += '<h4>Reviewer</h4><div class="dash-field dash-field-wide dash-appraisal-reviewer"><input type="search" data-reviewer-search placeholder="Search staff to make reviewer…" aria-label="New reviewer" autocomplete="off"><ul class="dash-holder-results" data-reviewer-results></ul></div>';
        }

        body.innerHTML = html;

        let buttons = '<button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>'
            + '<button type="button" class="dash-btn dash-btn-ghost" data-appraisal-print>Print</button>';

        if (mode === 'self' || mode === 'review') {
            buttons += '<button type="button" class="dash-btn dash-btn-ghost" data-appraisal-save="0">Save draft</button>'
                + '<button type="button" class="dash-btn dash-btn-primary" data-appraisal-save="1">' + (mode === 'self' ? 'Send to reviewer' : 'Submit to employee') + '</button>';
        }

        if (can.reopen) {
            buttons += '<button type="button" class="dash-btn dash-btn-ghost" data-appraisal-reopen>Reopen for changes</button>';
        }

        if (can.acknowledge) {
            buttons += '<button type="button" class="dash-btn dash-btn-primary" data-appraisal-acknowledge>Acknowledge</button>';
        }

        actions.innerHTML = buttons;
        modal.dataset.mode = mode;
    }

    function readScores() {
        const mode = modal.dataset.mode;

        return Array.prototype.map.call(body.querySelectorAll('[data-score-row]'), function (row) {
            const picked = row.querySelector('input[type="radio"]:checked');
            const comment = row.querySelector('[data-comment]');
            const line = { id: Number(row.dataset.scoreRow) };

            line[mode === 'self' ? 'self_score' : 'score'] = picked ? Number(picked.value) : null;
            line[mode === 'self' ? 'self_comment' : 'comment'] = comment && comment.value.trim() !== '' ? comment.value.trim() : null;

            return line;
        });
    }

    /* Weighted mean of the picked scores, as the server works it out. */
    function liveScore() {
        const target = body.querySelector('[data-live-score]');

        if (!target) {
            return;
        }

        let total = 0;
        let weights = 0;

        readScores().forEach(function (line, index) {
            if (line.score !== null) {
                const weight = Number(current.scores[index].weight) || 0;
                total += line.score * (weight || 1);
                weights += weight || 1;
            }
        });

        target.textContent = weights ? score(Math.round(total / weights * 100) / 100) : '—';
    }

    function done(envelope) {
        ui.toast(envelope.message);
        current = envelope.data;
        render();

        if (onChange) {
            onChange();
        }
    }

    function failed(envelope) {
        kit.showErrors(form, envelope);
    }

    function save(submit) {
        const mode = modal.dataset.mode;
        const payload = { submit: submit, scores: readScores() };

        if (mode === 'review') {
            ['strengths', 'improvements', 'goals'].forEach(function (field) { payload[field] = form.elements[field].value.trim() || null; });
            payload.rating_id = form.elements.rating_id.value ? Number(form.elements.rating_id.value) : null;
        }

        kit.clearErrors(form);

        return ui.api('PUT', url + '/' + current.id + (mode === 'self' ? '/self-review' : '/review'), payload).then(done).catch(failed);
    }

    actions.addEventListener('click', function (event) {
        const saveButton = event.target.closest('[data-appraisal-save]');

        if (event.target.closest('[data-modal-close]')) {
            modal.close();
        } else if (saveButton) {
            saveButton.disabled = true;
            save(saveButton.dataset.appraisalSave === '1').finally(function () { saveButton.disabled = false; });
        } else if (event.target.closest('[data-appraisal-print]')) {
            ui.openFile(url + '/' + current.id + '/pdf', 'Preparing the appraisal…').catch(function () {});
        } else if (event.target.closest('[data-appraisal-acknowledge]')) {
            kit.clearErrors(form);
            ui.api('POST', url + '/' + current.id + '/acknowledge', { employee_comments: form.elements.employee_comments.value.trim() || null }).then(done).catch(failed);
        } else if (event.target.closest('[data-appraisal-reopen]')) {
            ui.api('POST', url + '/' + current.id + '/reopen').then(done).catch(failed);
        }
    });

    modal.querySelector('.dash-modal-head [data-modal-close]').addEventListener('click', function () {
        if (modal.open) {
            modal.close();
        }
    });

    body.addEventListener('change', function (event) {
        if (event.target.type === 'radio') {
            liveScore();
        }
    });

    /* HR: pick a new reviewer. */
    body.addEventListener('input', function (event) {
        if (!event.target.matches('[data-reviewer-search]')) {
            return;
        }

        const results = body.querySelector('[data-reviewer-results]');
        clearTimeout(timer);
        timer = setTimeout(function () {
            ui.api('GET', modal.dataset.reviewersUrl + '?term=' + encodeURIComponent(event.target.value.trim())).then(function (envelope) {
                results.innerHTML = envelope.data.map(function (person) {
                    return '<li><button type="button" data-reviewer="' + person.id + '">' + esc(person.name) + ' <span class="dash-muted">' + esc(person.detail || '') + '</span></button></li>';
                }).join('') || '<li class="dash-muted">No one found.</li>';
            }).catch(function () {});
        }, 300);
    });

    body.addEventListener('click', function (event) {
        const pick = event.target.closest('[data-reviewer]');

        if (pick) {
            ui.api('PUT', url + '/' + current.id + '/reviewer', { reviewer_id: Number(pick.dataset.reviewer) })
                .then(function () { return ui.api('GET', url + '/' + current.id); })
                .then(function (envelope) { envelope.message = 'Reviewer changed.'; done(envelope); })
                .catch(failed);
        }
    });

    window.PinnacleAppraisal = {
        open: function (id, changed) {
            onChange = changed || null;

            return ui.api('GET', url + '/' + id).then(function (envelope) {
                current = envelope.data;
                kit.clearErrors(form);
                render();

                if (!modal.open) {
                    modal.showModal();
                }
            }).catch(function (envelope) {
                ui.toast(kit.messageOf(envelope, 'The appraisal could not be opened.'), 'error');
            });
        }
    };
});

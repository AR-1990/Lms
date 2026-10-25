/**
 * Student → Online Tests: the list, the timed attempt (answers kept in this browser until submitted, so a
 * refresh loses nothing; auto-submits when the time runs out) and the result / review.
 * Same endpoints as the mobile app: /api/erp/my-online-tests and /api/erp/online-tests/{id}/start|submit|review.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-student-tests]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui) {
        return;
    }

    const base = root.dataset.url;
    const listBox = root.querySelector('[data-test-list]');
    const take = root.querySelector('[data-test-take]');
    const result = root.querySelector('[data-test-result]');
    const confirmBox = document.getElementById('testSubmitConfirm');
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    let current = null;
    let timer = null;
    let submitting = false;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function storageKey() {
        return 'pinnacle.test.' + current.attempt_id;
    }

    function saved() {
        try {
            return JSON.parse(window.localStorage.getItem(storageKey()) || '{}');
        } catch (error) {
            return {};
        }
    }

    function remember(answers) {
        try {
            window.localStorage.setItem(storageKey(), JSON.stringify(answers));
        } catch (error) {
            // Private mode: answers stay on the page only.
        }
    }

    function show(section) {
        [listBox, take, result].forEach(function (box) { box.hidden = box !== section; });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function loadList() {
        ui.api('GET', base + '/my-online-tests').then(function (envelope) {
            listBox.innerHTML = envelope.data.length ? envelope.data.map(function (test) {
                let action = '<span class="dash-badge">Not open</span>';

                if (test.attempt_status === 'submitted') {
                    action = '<span class="dash-badge dash-badge-ok">' + test.score + ' / ' + test.total_marks + '</span><button type="button" class="dash-btn dash-btn-ghost" data-review="' + test.id + '">See answers</button>';
                } else if (test.is_open) {
                    action = '<button type="button" class="dash-btn dash-btn-primary" data-start="' + test.id + '">' + (test.attempt_status === 'in_progress' ? 'Continue' : 'Start') + '</button>';
                }

                return '<article class="dash-panel dash-ot-card">'
                    + '<div><span class="dash-ot-subject">' + esc(test.subject) + '</span><h2>' + esc(test.title) + '</h2>'
                    + '<p class="dash-muted">' + test.total_marks + ' marks · ' + test.duration_minutes + ' minutes' + (test.closes_at ? ' · until ' + esc(dateFormat.format(new Date(test.closes_at))) : '') + '</p></div>'
                    + '<div class="dash-ot-card-actions">' + action + '</div></article>';
            }).join('') : '<section class="dash-panel"><p class="dash-muted">No online tests for your class yet.</p></section>';
            show(listBox);
        }).catch(function (envelope) {
            listBox.innerHTML = '<section class="dash-panel"><p class="dash-muted">' + esc((envelope && envelope.message) || 'Could not load your tests.') + '</p></section>';
        });
    }

    function updateAnswered() {
        const answered = take.querySelectorAll('input[type="radio"]:checked').length;
        const total = current.questions.length;
        take.querySelector('[data-take-answered]').textContent = answered + ' of ' + total + ' answered';
        take.querySelector('[data-take-progress]').style.transform = 'scaleX(' + (total ? answered / total : 0) + ')';
    }

    function tick(endsAt) {
        const left = Math.max(0, Math.round((endsAt - Date.now()) / 1000));
        const clock = take.querySelector('[data-take-timer]');
        clock.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
        clock.classList.toggle('is-low', left <= 60);

        if (left === 0) {
            window.clearInterval(timer);
            submit(true);
        }
    }

    function start(testId) {
        ui.api('POST', base + '/online-tests/' + testId + '/start').then(function (envelope) {
            current = Object.assign({ test_id: testId }, envelope.data);
            const answers = saved();

            take.querySelector('[data-take-title]').textContent = current.title;
            take.querySelector('[data-take-instructions]').textContent = current.instructions || current.questions.length + ' questions · ' + current.total_marks + ' marks';
            take.querySelector('[data-take-questions]').innerHTML = current.questions.map(function (question, index) {
                return '<li class="dash-ot-paper-question"><p><span class="dash-ot-number">' + (index + 1) + '</span>' + esc(question.question) + ' <span class="dash-muted">(' + question.marks + ')</span></p>'
                    + question.options.map(function (option) {
                        return '<label class="dash-ot-choice"><input type="radio" name="q' + question.id + '" value="' + option.id + '" data-question="' + question.id + '"' + (String(answers[question.id]) === String(option.id) ? ' checked' : '') + '><span>' + esc(option.option_text) + '</span></label>';
                    }).join('') + '</li>';
            }).join('');
            updateAnswered();
            show(take);

            const endsAt = Date.now() + current.seconds_left * 1000;
            window.clearInterval(timer);
            tick(endsAt);
            timer = window.setInterval(function () { tick(endsAt); }, 1000);
        }).catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not start the test.', 'error'); loadList(); });
    }

    function renderResult(data) {
        result.querySelector('[data-result-score]').textContent = data.score + ' / ' + data.total_marks;
        result.querySelector('[data-result-text]').textContent = data.correct_count + ' correct · ' + data.title;
        result.querySelector('[data-result-review]').innerHTML = (data.questions || []).map(function (question, index) {
            return '<li class="dash-ot-paper-question ' + (question.is_correct ? 'is-right' : 'is-wrong') + '"><p><span class="dash-ot-number">' + (index + 1) + '</span>' + esc(question.question) + '</p>'
                + question.options.map(function (option) {
                    const classes = (option.is_correct ? ' is-answer' : '') + (option.id === question.chosen_option_id ? ' is-chosen' : '');

                    return '<div class="dash-ot-choice' + classes + '"><span>' + esc(option.option_text) + '</span>' + (option.id === question.chosen_option_id ? '<em>Your answer</em>' : '') + '</div>';
                }).join('') + '</li>';
        }).join('');
        show(result);
    }

    function submit(auto) {
        if (submitting || !current) {
            return;
        }

        const answers = current.questions.map(function (question) {
            const checked = take.querySelector('input[data-question="' + question.id + '"]:checked');

            return { question_id: question.id, option_id: checked ? parseInt(checked.value, 10) : null };
        });

        submitting = true;
        ui.api('POST', base + '/online-tests/' + current.test_id + '/submit', { answers: answers }).then(function (envelope) {
            window.clearInterval(timer);

            try {
                window.localStorage.removeItem(storageKey());
            } catch (error) {
                // Nothing kept.
            }

            ui.toast(auto ? 'Time is up — your test was submitted.' : envelope.message);
            renderResult(envelope.data);
        }).catch(function (envelope) {
            ui.toast((envelope && envelope.message) || 'Could not submit. Check your connection and try again.', 'error');
        }).finally(function () { submitting = false; });
    }

    take.addEventListener('change', function (event) {
        if (event.target.matches('input[type="radio"]')) {
            const answers = saved();
            answers[event.target.dataset.question] = event.target.value;
            remember(answers);
            updateAnswered();
        }
    });

    take.querySelector('[data-take-submit]').addEventListener('click', function () {
        const unanswered = current.questions.length - take.querySelectorAll('input[type="radio"]:checked').length;

        confirmBox.querySelector('[data-confirm-text]').textContent = (unanswered ? unanswered + ' question(s) are not answered. ' : '') + 'You cannot change answers after submitting.';
        confirmBox.showModal();
    });

    listBox.addEventListener('click', function (event) {
        const startButton = event.target.closest('[data-start]');
        const reviewButton = event.target.closest('[data-review]');

        if (startButton) {
            start(startButton.dataset.start);
        }

        if (reviewButton) {
            ui.api('GET', base + '/online-tests/' + reviewButton.dataset.review + '/review').then(function (envelope) { renderResult(envelope.data); })
                .catch(function (envelope) { ui.toast((envelope && envelope.message) || 'Could not load your answers.', 'error'); });
        }
    });

    confirmBox.querySelector('[data-confirm-cancel]').addEventListener('click', function () { confirmBox.close(); });
    confirmBox.querySelector('[data-confirm-ok]').addEventListener('click', function () {
        confirmBox.close();
        submit(false);
    });

    result.querySelector('[data-result-back]').addEventListener('click', loadList);
    loadList();
})();

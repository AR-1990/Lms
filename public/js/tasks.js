/**
 * Tasks: My Tasks grid, board cards, the Kanban board (drag between columns, live updates over Reverb)
 * and the shared task popup (details, checklist, comments). Data: /api/erp/tasks and /api/erp/task-boards.
 */
(function () {
    'use strict';

    const ui = window.PinnacleDashboard;
    const root = document.querySelector('[data-tasks-root]');

    if (!ui || !root) {
        return;
    }

    const api = root.dataset.api;
    const kit = window.PinnacleResource;
    const dateFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    const PRIORITY_TONE = { Urgent: 'is-urgent', High: 'is-high', Low: 'is-low' };
    let options = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function toLocalInput(iso) {
        if (!iso) {
            return '';
        }

        const date = new Date(iso);
        const pad = function (value) { return String(value).padStart(2, '0'); };

        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function messageOf(envelope, fallback) {
        return kit ? kit.messageOf(envelope, fallback) : ((envelope && envelope.message) || fallback);
    }

    function loadOptions(boardId) {
        return ui.api('GET', api + '/tasks/options' + (boardId ? '?board_id=' + boardId : '')).then(function (envelope) {
            options = envelope.data;

            return options;
        });
    }

    function fillSelect(select, items, emptyLabel, selected) {
        select.innerHTML = (emptyLabel !== null ? '<option value="">' + esc(emptyLabel) + '</option>' : '')
            + items.map(function (item) {
                const isSelected = Array.isArray(selected) ? selected.indexOf(item.id) !== -1 : item.id === selected;

                return '<option value="' + item.id + '"' + (isSelected ? ' selected' : '') + '>' + esc(item.name) + '</option>';
            }).join('');
    }

    function onTaskChanged(callback) {
        const echo = window.PinnacleRealtime && window.PinnacleRealtime.echo;

        if (echo) {
            echo.private('App.Models.User.' + root.dataset.userId).listen('.task.changed', callback);
        }
    }

    /* ---------- Task popup ---------- */

    const modal = document.getElementById('taskModal');
    let taskPopup = null;

    if (modal) {
        const form = modal.querySelector('form');
        const boardSelect = form.querySelector('[data-task-board]');
        const statusSelect = form.querySelector('[data-task-status]');
        const assignees = form.querySelector('[data-task-assignees]');
        const checklist = form.querySelector('[data-task-checklist]');
        const comments = form.querySelector('[data-task-comments]');
        const thread = form.querySelector('[data-task-thread]');
        const mentions = form.querySelector('[data-task-mentions]');
        const deleteButton = form.querySelector('[data-task-delete]');
        let current = null;
        let afterChange = function () {};

        const itemRow = function (item) {
            return '<li><input type="checkbox" data-item-done' + (item.is_done ? ' checked' : '') + ' aria-label="Done">'
                + '<input type="text" data-item-text maxlength="255" value="' + esc(item.title) + '" placeholder="Step">'
                + '<button type="button" class="dash-icon-btn" data-item-remove aria-label="Remove step">×</button></li>';
        };

        const commentRow = function (comment) {
            return '<li><strong>' + esc(comment.user_name) + '</strong> <time>' + esc(dateFormat.format(new Date(comment.created_at))) + '</time><p>' + esc(comment.body) + '</p></li>';
        };

        const loadStatuses = function (boardId, selected) {
            return ui.api('GET', api + '/tasks/options' + (boardId ? '?board_id=' + boardId : '')).then(function (envelope) {
                fillSelect(statusSelect, envelope.data.statuses, null, selected || (envelope.data.statuses[0] && envelope.data.statuses[0].id));
            });
        };

        boardSelect.addEventListener('change', function () {
            loadStatuses(Number(boardSelect.value) || null, null);
        });

        form.querySelector('[data-task-add-item]').addEventListener('click', function () {
            checklist.insertAdjacentHTML('beforeend', itemRow({ title: '', is_done: false }));
            checklist.lastElementChild.querySelector('[data-item-text]').focus();
        });

        checklist.addEventListener('click', function (event) {
            if (event.target.closest('[data-item-remove]')) {
                event.target.closest('li').remove();
            }
        });

        const open = function (taskId, presets) {
            presets = presets || {};
            current = null;
            form.reset();

            if (kit) {
                kit.clearErrors(form);
            }

            return Promise.all([
                options ? Promise.resolve(options) : loadOptions(),
                ui.api('GET', api + '/task-boards'),
                taskId ? ui.api('GET', api + '/tasks/' + taskId) : Promise.resolve(null)
            ]).then(function (results) {
                const task = results[2] ? results[2].data : null;
                const boardId = task ? task.task_board_id : (presets.board_id || null);

                current = task;
                fillSelect(boardSelect, results[1].data, 'Personal (no board)', boardId);
                fillSelect(form.querySelector('[data-task-priority]'), options.priorities, '—', task ? task.priority_id : (options.priorities[1] && options.priorities[1].id));
                fillSelect(form.querySelector('[data-task-category]'), options.categories, '—', task ? task.category_id : null);
                fillSelect(assignees, options.people, null, task ? task.assignees.map(function (person) { return person.user_id; }) : []);
                fillSelect(mentions, options.people, null, []);

                form.elements.title.value = task ? task.title : '';
                form.elements.description.value = task ? task.description || '' : '';
                form.elements.start_at.value = toLocalInput(task && task.start_at);
                form.elements.due_at.value = toLocalInput(task && task.due_at);
                form.elements.recurrence.value = task ? task.recurrence : 'none';
                form.elements.estimated_minutes.value = task && task.estimated_minutes ? task.estimated_minutes : '';
                checklist.innerHTML = (task ? task.checklist : []).map(itemRow).join('');
                thread.innerHTML = task && task.comments.length ? task.comments.map(commentRow).join('') : '<li class="dash-muted">No comments yet.</li>';
                comments.hidden = !task;
                deleteButton.hidden = !task;
                modal.querySelector('[data-task-title]').textContent = task ? task.task_no + ' · ' + task.title : 'New task';
                modal.querySelector('[data-task-meta]').textContent = task
                    ? 'Created by ' + (task.created_by_name || '—') + (task.completed_at ? ' · done ' + dateFormat.format(new Date(task.completed_at)) : '')
                    : 'Assign it, give it a due date, add a checklist.';

                return loadStatuses(boardId, task ? task.task_status_id : presets.status_id);
            }).then(function () {
                modal.showModal();
            }).catch(function (envelope) {
                ui.toast(messageOf(envelope, 'The task could not be opened.'), 'error');
            });
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const chosen = Array.prototype.map.call(assignees.selectedOptions, function (option) { return Number(option.value); });
            const body = {
                title: form.elements.title.value.trim(),
                description: form.elements.description.value.trim() || null,
                task_board_id: Number(boardSelect.value) || null,
                task_status_id: Number(statusSelect.value) || null,
                priority_id: Number(form.elements.priority_id.value) || null,
                category_id: Number(form.elements.category_id.value) || null,
                start_at: form.elements.start_at.value || null,
                due_at: form.elements.due_at.value || null,
                recurrence: form.elements.recurrence.value,
                estimated_minutes: Number(form.elements.estimated_minutes.value) || null,
                checklist: Array.prototype.map.call(checklist.querySelectorAll('li'), function (row) {
                    return { title: row.querySelector('[data-item-text]').value.trim(), is_done: row.querySelector('[data-item-done]').checked };
                }).filter(function (item) { return item.title !== ''; })
            };

            if (current || chosen.length) {
                body.assignee_ids = chosen;
            }

            const submit = form.querySelector('[data-submit]');
            submit.disabled = true;

            (current ? ui.api('PUT', api + '/tasks/' + current.id, body) : ui.api('POST', api + '/tasks', body))
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    modal.close();
                    afterChange();
                })
                .catch(function (envelope) {
                    if (kit) {
                        kit.showErrors(form, envelope);
                    }
                })
                .finally(function () {
                    submit.disabled = false;
                });
        });

        form.querySelector('[data-task-send]').addEventListener('click', function () {
            const box = form.querySelector('[data-task-comment]');
            const text = box.value.trim();

            if (!text || !current) {
                return;
            }

            ui.api('POST', api + '/tasks/' + current.id + '/comments', {
                body: text,
                mention_ids: Array.prototype.map.call(mentions.selectedOptions, function (option) { return Number(option.value); })
            }).then(function (envelope) {
                if (thread.querySelector('.dash-muted')) {
                    thread.innerHTML = '';
                }

                thread.insertAdjacentHTML('beforeend', commentRow(envelope.data));
                box.value = '';
                Array.prototype.forEach.call(mentions.options, function (option) { option.selected = false; });
                afterChange();
            }).catch(function (envelope) {
                ui.toast(messageOf(envelope, 'The comment was not sent.'), 'error');
            });
        });

        deleteButton.addEventListener('click', function () {
            if (!current) {
                return;
            }

            const task = current;

            modal.close();
            ui.eraseRecord(task.task_no + ' ' + task.title, null, api + '/tasks/' + task.id).then(function (result) {
                if (result) {
                    afterChange();
                }
            });
        });

        modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { modal.close(); });
        });

        taskPopup = {
            open: open,
            onChange: function (callback) { afterChange = callback; }
        };
    }

    /* ---------- My Tasks ---------- */

    if (root.hasAttribute('data-my-tasks') && window.PinnacleGrid) {
        const gridEsc = window.PinnacleGrid.escape;
        const display = function (render) {
            return function (value, type, row) { return type === 'display' ? render(value, row) : value; };
        };
        const grid = window.PinnacleGrid.create(root.querySelector('[data-grid]'), {
            url: root.dataset.datatableUrl,
            order: [[1, 'asc']],
            searchPlaceholder: 'Search tasks…',
            emptyText: 'Nothing here. Enjoy the calm ☕',
            params: function () {
                const values = {};

                root.querySelectorAll('[data-filter]').forEach(function (field) { values[field.dataset.filter] = field.value; });

                return values;
            },
            columns: [
                { data: 'title', render: display(function (value, row) {
                    const meta = [row.task_no, row.board_name, row.checklist_count ? row.checklist_done_count + '/' + row.checklist_count + ' steps' : null, row.comments_count ? row.comments_count + ' 💬' : null].filter(Boolean).join(' · ');

                    return '<button type="button" class="dash-task-link" data-open-task="' + row.id + '"><span class="dash-cell-title">' + gridEsc(value) + '</span></button><span class="dash-muted">' + gridEsc(meta) + '</span>';
                }) },
                { data: 'due_at', searchable: false, className: 'dash-hide-md', render: display(function (value, row) {
                    return value ? '<span class="' + (row.is_overdue ? 'dash-text-danger' : '') + '">' + gridEsc(dateFormat.format(new Date(value))) + '</span>' : '—';
                }) },
                { data: 'task_status_id', searchable: false, render: display(function (value, row) {
                    return '<span class="dash-notice-badge" style="--badge:' + gridEsc(row.status_color || '#64748b') + '">' + gridEsc(row.status_name) + '</span>'
                        + (row.priority_name ? ' <span class="dash-task-priority ' + (PRIORITY_TONE[row.priority_name] || '') + '">' + gridEsc(row.priority_name) + '</span>' : '');
                }) },
                { data: 'assignee_names', orderable: false, searchable: false, className: 'dash-hide-md' }
            ]
        });

        root.querySelectorAll('[data-filter]').forEach(function (field) {
            field.addEventListener('change', function () { grid.ajax.reload(); });
        });

        root.addEventListener('click', function (event) {
            const link = event.target.closest('[data-open-task]');

            if (link && taskPopup) {
                taskPopup.open(Number(link.dataset.openTask));
            }
        });

        document.querySelector('[data-task-new]').addEventListener('click', function () {
            taskPopup.open(null);
        });

        taskPopup.onChange(function () { grid.ajax.reload(null, false); });
        onTaskChanged(function () { grid.ajax.reload(null, false); });

        if (root.dataset.openId) {
            taskPopup.open(Number(root.dataset.openId));
        }
    }

    /* ---------- Boards ---------- */

    if (root.hasAttribute('data-boards')) {
        const boardModal = document.getElementById('boardModal');
        const boardForm = boardModal.querySelector('form');
        const members = boardForm.querySelector('[data-board-members]');
        let editingBoard = null;

        const renderBoards = function () {
            return ui.api('GET', api + '/task-boards').then(function (envelope) {
                root.innerHTML = envelope.data.length ? envelope.data.map(function (board) {
                    const done = board.tasks_count - board.open_tasks_count;
                    const percent = board.tasks_count ? Math.round(done * 100 / board.tasks_count) : 0;

                    return '<article class="dash-board-card" style="--board:' + esc(board.color || '#1d4ed8') + '">'
                        + '<a href="' + esc(root.dataset.boardUrl + '/' + board.id) + '" class="dash-board-link"><h2>' + esc(board.name) + '</h2>'
                        + '<p>' + esc(board.description || '') + '</p></a>'
                        + '<div class="dash-board-progress" role="progressbar" aria-valuenow="' + percent + '" aria-valuemin="0" aria-valuemax="100"><span style="width:' + percent + '%"></span></div>'
                        + '<footer><span>' + board.open_tasks_count + ' open · ' + done + ' done</span><span>' + esc(board.owner ? board.owner.name : '') + '</span>'
                        + '<button type="button" class="dash-icon-btn" data-edit-board="' + board.id + '" aria-label="Edit board">✎</button></footer></article>';
                }).join('') : '<p class="dash-muted">No boards yet. Create one for your next project.</p>';
            });
        };

        const openBoard = function (board) {
            editingBoard = board;
            boardForm.reset();

            if (kit) {
                kit.clearErrors(boardForm);
            }

            (options ? Promise.resolve(options) : loadOptions()).then(function () {
                fillSelect(members, options.people.filter(function (person) { return person.id !== Number(root.dataset.userId); }), null, board ? board.members.map(function (member) { return member.user_id; }) : []);
                boardForm.elements.name.value = board ? board.name : '';
                boardForm.elements.description.value = board ? board.description || '' : '';
                boardForm.elements.visibility.value = board ? board.visibility : 'private';
                boardForm.elements.department_id.value = board && board.department_id ? board.department_id : '';
                boardForm.elements.color.value = board && board.color ? board.color : '#1d4ed8';
                boardModal.querySelector('[data-board-title]').textContent = board ? 'Edit board' : 'New board';
                boardModal.showModal();
            });
        };

        document.querySelector('[data-board-new]').addEventListener('click', function () { openBoard(null); });

        root.addEventListener('click', function (event) {
            const edit = event.target.closest('[data-edit-board]');

            if (edit) {
                ui.api('GET', api + '/task-boards/' + edit.dataset.editBoard).then(function (envelope) { openBoard(envelope.data); });
            }
        });

        boardModal.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { boardModal.close(); });
        });

        boardForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const body = {
                name: boardForm.elements.name.value.trim(),
                description: boardForm.elements.description.value.trim() || null,
                visibility: boardForm.elements.visibility.value,
                department_id: Number(boardForm.elements.department_id.value) || null,
                color: boardForm.elements.color.value,
                members: Array.prototype.map.call(members.selectedOptions, function (option) { return { user_id: Number(option.value), role: 'editor' }; })
            };

            (editingBoard ? ui.api('PUT', api + '/task-boards/' + editingBoard.id, body) : ui.api('POST', api + '/task-boards', body))
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    boardModal.close();
                    renderBoards();
                })
                .catch(function (envelope) {
                    if (kit) {
                        kit.showErrors(boardForm, envelope);
                    }
                });
        });

        renderBoards().catch(function () { root.innerHTML = '<p class="dash-muted">Boards could not be loaded.</p>'; });
    }

    /* ---------- Kanban ---------- */

    if (root.hasAttribute('data-kanban')) {
        const boardId = Number(root.dataset.boardId);
        let canEdit = false;
        let dragged = null;

        const card = function (task) {
            const assigneeInitials = (task.assignees || []).map(function (person) {
                return '<span class="dash-avatar-chip" data-tooltip="' + esc(person.name) + '">' + esc(String(person.name || '?').split(' ').map(function (part) { return part.charAt(0); }).join('').slice(0, 2)) + '</span>';
            }).join('');

            return '<article class="dash-kanban-card' + (task.is_overdue ? ' is-overdue' : '') + (task.completed_at ? ' is-done' : '') + '" draggable="' + canEdit + '" data-task="' + task.id + '">'
                + '<button type="button" class="dash-task-link" data-open-task="' + task.id + '"><span class="dash-muted">' + esc(task.task_no) + '</span><strong>' + esc(task.title) + '</strong></button>'
                + '<div class="dash-kanban-meta">'
                + (task.priority ? '<span class="dash-task-priority ' + (PRIORITY_TONE[task.priority] || '') + '">' + esc(task.priority) + '</span>' : '')
                + (task.due_at ? '<span class="' + (task.is_overdue ? 'dash-text-danger' : '') + '">⏰ ' + esc(dateFormat.format(new Date(task.due_at))) + '</span>' : '')
                + (task.checklist_count ? '<span>☑ ' + task.checklist_done_count + '/' + task.checklist_count + '</span>' : '')
                + (task.comments_count ? '<span>💬 ' + task.comments_count + '</span>' : '')
                + '</div><div class="dash-kanban-people">' + assigneeInitials + '</div></article>';
        };

        const render = function () {
            return ui.api('GET', api + '/task-boards/' + boardId).then(function (envelope) {
                canEdit = envelope.data.can_edit;
                root.innerHTML = envelope.data.columns.map(function (column) {
                    return '<section class="dash-kanban-column" style="--column:' + esc(column.color || '#64748b') + '" data-column="' + column.id + '">'
                        + '<header><h2>' + esc(column.name) + '</h2><span class="dash-kanban-count">' + column.tasks.length + '</span></header>'
                        + '<div class="dash-kanban-cards" data-drop="' + column.id + '">' + column.tasks.map(card).join('') + '</div>'
                        + (canEdit ? '<button type="button" class="dash-kanban-add" data-add-task="' + column.id + '">+ Add task</button>' : '')
                        + '</section>';
                }).join('');
            });
        };

        root.addEventListener('click', function (event) {
            const link = event.target.closest('[data-open-task]');
            const add = event.target.closest('[data-add-task]');

            if (link) {
                taskPopup.open(Number(link.dataset.openTask));
            }

            if (add) {
                taskPopup.open(null, { board_id: boardId, status_id: Number(add.dataset.addTask) });
            }
        });

        root.addEventListener('dragstart', function (event) {
            dragged = event.target.closest('[data-task]');

            if (dragged) {
                dragged.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
            }
        });

        root.addEventListener('dragover', function (event) {
            const zone = event.target.closest('[data-drop]');

            if (!dragged || !zone) {
                return;
            }

            event.preventDefault();
            zone.closest('.dash-kanban-column').classList.add('is-over');

            const after = Array.prototype.find.call(zone.querySelectorAll('[data-task]:not(.is-dragging)'), function (other) {
                const rect = other.getBoundingClientRect();

                return event.clientY < rect.top + rect.height / 2;
            });

            zone.insertBefore(dragged, after || null);
        });

        root.addEventListener('dragleave', function (event) {
            const column = event.target.closest('.dash-kanban-column');

            if (column && !column.contains(event.relatedTarget)) {
                column.classList.remove('is-over');
            }
        });

        root.addEventListener('drop', function (event) {
            event.preventDefault();
        });

        root.addEventListener('dragend', function () {
            if (!dragged) {
                return;
            }

            const zone = dragged.closest('[data-drop]');
            const position = Array.prototype.indexOf.call(zone.querySelectorAll('[data-task]'), dragged);
            const taskId = dragged.dataset.task;

            dragged.classList.remove('is-dragging');
            root.querySelectorAll('.is-over').forEach(function (column) { column.classList.remove('is-over'); });
            dragged = null;

            ui.api('PATCH', api + '/tasks/' + taskId + '/move', { task_status_id: Number(zone.dataset.drop), sort_order: position })
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    render();
                })
                .catch(function (envelope) {
                    ui.toast(messageOf(envelope, 'The task could not be moved.'), 'error');
                    render();
                });
        });

        document.querySelector('[data-task-new]').addEventListener('click', function () {
            taskPopup.open(null, { board_id: boardId });
        });

        taskPopup.onChange(render);

        const echo = window.PinnacleRealtime && window.PinnacleRealtime.echo;

        if (echo) {
            echo.private('tasks.board.' + boardId).listen('.task.changed', render);

            const live = document.querySelector('[data-live-status]');

            echo.connector.pusher.connection.bind('state_change', function (states) {
                live.hidden = states.current !== 'connected';
            });
        }

        render().catch(function () { root.innerHTML = '<p class="dash-muted">The board could not be loaded.</p>'; });
    }
})();

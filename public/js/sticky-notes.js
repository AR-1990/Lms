/**
 * Sticky notes board (dashboard widget and "My notes" page) on /api/erp/sticky-notes.
 * Click a note to edit (auto-saves), tick done, pin, drag to reorder; overdue reminders wiggle.
 */
(function () {
    'use strict';

    const board = document.querySelector('[data-sticky-board]');
    const ui = window.PinnacleDashboard;

    if (!board || !ui) {
        return;
    }

    const url = board.dataset.url;
    const limit = Number(board.dataset.limit) || 0;
    const view = board.dataset.view;
    const grid = board.querySelector('[data-sticky-grid]');
    const editor = document.getElementById('stickyEditor');
    const form = editor.querySelector('form');
    const statusLine = editor.querySelector('[data-sticky-status]');
    const itemsList = editor.querySelector('[data-sticky-items]');
    const timeFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    let notes = [];
    let colors = [];
    let editing = null;
    let saveTimer = null;
    let dragged = null;

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

    function render() {
        const shown = limit ? notes.filter(function (note) { return note.show_on_dashboard; }).slice(0, limit) : notes;

        if (!shown.length) {
            grid.innerHTML = '<p class="dash-muted dash-sticky-empty">' + (view === 'active' ? 'No notes yet. Press "+ Note" to jot something down.' : 'Nothing here.') + '</p>';

            return;
        }

        grid.innerHTML = shown.map(function (note, index) {
            const items = (note.items || []).map(function (item) {
                return '<li><label><input type="checkbox" data-sticky-item="' + item.id + '"' + (item.is_done ? ' checked' : '') + '> <span>' + esc(item.text) + '</span></label></li>';
            }).join('');

            return '<article class="dash-sticky' + (note.is_pinned ? ' is-pinned' : '') + (note.is_overdue ? ' is-overdue' : '') + (note.is_done ? ' is-done' : '') + '"'
                + ' style="--note:' + esc(note.color || '#fff3b0') + ';--tilt:' + ((index % 3) - 1) * 0.8 + 'deg" draggable="' + (view === 'active') + '" data-sticky="' + note.id + '">'
                + (note.is_overdue ? '<span class="dash-sticky-flag">Reminder</span>' : '')
                + '<button type="button" class="dash-sticky-open" data-sticky-open aria-label="Edit note">'
                + (note.title ? '<strong>' + esc(note.title) + '</strong>' : '')
                + (note.body ? '<p>' + esc(note.body) + '</p>' : '')
                + '</button>'
                + (items ? '<ul class="dash-sticky-items">' + items + '</ul>' : '')
                + '<footer>'
                + (note.remind_at ? '<span class="dash-sticky-time">⏰ ' + esc(timeFormat.format(new Date(note.remind_at))) + '</span>' : '<span></span>')
                + '<span class="dash-sticky-actions">'
                + '<button type="button" data-sticky-pin aria-label="' + (note.is_pinned ? 'Unpin' : 'Pin') + '" data-tooltip="' + (note.is_pinned ? 'Unpin' : 'Pin') + '">📌</button>'
                + '<button type="button" data-sticky-done aria-label="' + (note.is_done ? 'Back to board' : 'Mark done') + '" data-tooltip="' + (note.is_done ? 'Back to board' : 'Done') + '">' + (note.is_done ? '↺' : '✓') + '</button>'
                + '</span></footer></article>';
        }).join('');
    }

    function load() {
        return ui.api('GET', url + '?view=' + view + '&per_page=100').then(function (envelope) {
            notes = envelope.data.items;
            render();
        });
    }

    function noteOf(element) {
        const card = element.closest('[data-sticky]');

        return card ? notes.find(function (note) { return note.id === Number(card.dataset.sticky); }) : null;
    }

    function replace(updated) {
        notes = notes.map(function (note) { return note.id === updated.id ? updated : note; });
    }

    /* ---------- Editor ---------- */

    function itemRow(item) {
        return '<li><input type="checkbox" data-item-done' + (item.is_done ? ' checked' : '') + ' aria-label="Done">'
            + '<input type="text" data-item-text maxlength="255" value="' + esc(item.text) + '" placeholder="Item">'
            + '<button type="button" class="dash-icon-btn" data-item-remove aria-label="Remove item">×</button></li>';
    }

    function payload() {
        const color = form.querySelector('input[name="color_id"]:checked');

        return {
            title: form.elements.title.value.trim() || null,
            body: form.elements.body.value.trim() || null,
            color_id: color ? Number(color.value) : null,
            remind_at: form.elements.remind_at.value || null,
            is_pinned: form.elements.is_pinned.checked,
            items: Array.prototype.map.call(itemsList.querySelectorAll('li'), function (row) {
                return { text: row.querySelector('[data-item-text]').value.trim(), is_done: row.querySelector('[data-item-done]').checked };
            }).filter(function (item) { return item.text !== ''; })
        };
    }

    function isBlank(body) {
        return !body.title && !body.body && !body.items.length;
    }

    function save() {
        clearTimeout(saveTimer);

        const body = payload();

        if (isBlank(body)) {
            return Promise.resolve();
        }

        statusLine.textContent = 'Saving…';

        const request = editing ? ui.api('PUT', url + '/' + editing.id, body) : ui.api('POST', url, body);

        return request.then(function (envelope) {
            if (editing) {
                replace(envelope.data);
            } else {
                notes.unshift(envelope.data);
            }

            editing = envelope.data;
            statusLine.textContent = 'Saved ✓';
            render();
        }).catch(function (envelope) {
            statusLine.textContent = window.PinnacleResource ? window.PinnacleResource.messageOf(envelope, 'Could not save.') : 'Could not save.';
        });
    }

    function queueSave() {
        clearTimeout(saveTimer);
        statusLine.textContent = 'Typing…';
        saveTimer = setTimeout(save, 700);
    }

    function open(note) {
        editing = note || null;
        form.reset();
        form.elements.title.value = note ? note.title || '' : '';
        form.elements.body.value = note ? note.body || '' : '';
        form.elements.remind_at.value = toLocalInput(note && note.remind_at);
        form.elements.is_pinned.checked = Boolean(note && note.is_pinned);
        itemsList.innerHTML = (note ? note.items || [] : []).map(itemRow).join('');
        editor.querySelector('[data-sticky-swatches]').innerHTML = colors.map(function (color, index) {
            const checked = note ? note.color_id === color.id : index === 0;

            return '<label class="dash-sticky-swatch" style="--note:' + esc(color.code) + '"><input type="radio" name="color_id" value="' + color.id + '"' + (checked ? ' checked' : '') + '><span>' + esc(color.name) + '</span></label>';
        }).join('');
        editor.querySelector('[data-sticky-delete]').hidden = !note;
        statusLine.textContent = 'Saved as you type.';
        editor.showModal();
        form.elements.body.focus();
    }

    function close() {
        save().finally(function () {
            editor.close();
            editing = null;
        });
    }

    form.addEventListener('input', queueSave);
    form.addEventListener('change', function (event) {
        if (event.target.type === 'radio' || event.target.type === 'checkbox' || event.target.type === 'datetime-local') {
            queueSave();
        }
    });
    form.addEventListener('submit', function (event) { event.preventDefault(); });

    editor.querySelectorAll('[data-sticky-close]').forEach(function (button) {
        button.addEventListener('click', close);
    });
    editor.addEventListener('cancel', function (event) {
        event.preventDefault();
        close();
    });

    editor.querySelector('[data-sticky-add-item]').addEventListener('click', function () {
        itemsList.insertAdjacentHTML('beforeend', itemRow({ text: '', is_done: false }));
        itemsList.lastElementChild.querySelector('[data-item-text]').focus();
    });

    itemsList.addEventListener('click', function (event) {
        if (event.target.closest('[data-item-remove]')) {
            event.target.closest('li').remove();
            queueSave();
        }
    });

    editor.querySelector('[data-sticky-delete]').addEventListener('click', function () {
        const note = editing;

        if (!note) {
            return;
        }

        clearTimeout(saveTimer);
        editor.close();
        editing = null;

        const card = grid.querySelector('[data-sticky="' + note.id + '"]');

        ui.erase(card).then(function () {
            return ui.api('DELETE', url + '/' + note.id);
        }).then(function (envelope) {
            notes = notes.filter(function (item) { return item.id !== note.id; });
            render();
            ui.toast(envelope.message);
        }).catch(function () {
            ui.toast('The note could not be removed.', 'error');
            load();
        });
    });

    /* ---------- Board actions ---------- */

    const addButton = board.querySelector('[data-sticky-add]');

    if (addButton) {
        addButton.addEventListener('click', function () {
            open(null);
        });
    }

    grid.addEventListener('click', function (event) {
        const note = noteOf(event.target);

        if (!note) {
            return;
        }

        if (event.target.closest('[data-sticky-open]')) {
            open(note);
        }

        if (event.target.closest('[data-sticky-pin]')) {
            ui.api('PATCH', url + '/' + note.id + '/pin', { is_pinned: !note.is_pinned }).then(load).catch(function () {});
        }

        if (event.target.closest('[data-sticky-done]')) {
            const card = event.target.closest('[data-sticky]');

            card.classList.add('is-leaving');
            ui.api('PATCH', url + '/' + note.id + '/done', { is_done: !note.is_done })
                .then(function (envelope) {
                    ui.toast(envelope.message);
                    setTimeout(load, 250);
                })
                .catch(function () { card.classList.remove('is-leaving'); });
        }
    });

    grid.addEventListener('change', function (event) {
        const box = event.target.closest('[data-sticky-item]');
        const note = noteOf(event.target);

        if (box && note) {
            ui.api('PATCH', url + '/' + note.id + '/items/' + box.dataset.stickyItem).then(function (envelope) {
                note.items = note.items.map(function (item) { return item.id === envelope.data.id ? envelope.data : item; });
            }).catch(function () { box.checked = !box.checked; });
        }
    });

    /* Drag to reorder (native drag and drop). */
    grid.addEventListener('dragstart', function (event) {
        dragged = event.target.closest('[data-sticky]');

        if (dragged) {
            dragged.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
        }
    });

    grid.addEventListener('dragover', function (event) {
        const over = event.target.closest('[data-sticky]');

        if (!dragged || !over || over === dragged) {
            return;
        }

        event.preventDefault();
        const rect = over.getBoundingClientRect();
        const after = event.clientX > rect.left + rect.width / 2;

        over.parentNode.insertBefore(dragged, after ? over.nextSibling : over);
    });

    grid.addEventListener('dragend', function () {
        if (!dragged) {
            return;
        }

        dragged.classList.remove('is-dragging');
        dragged = null;

        const ids = Array.prototype.map.call(grid.querySelectorAll('[data-sticky]'), function (card) { return Number(card.dataset.sticky); });

        notes.sort(function (a, b) { return ids.indexOf(a.id) - ids.indexOf(b.id); });
        ui.api('PUT', url + '/reorder', { ids: ids }).catch(function () { load(); });
    });

    Promise.all([ui.api('GET', url + '/colors'), load()]).then(function (results) {
        colors = results[0].data;
    }).catch(function () {
        grid.innerHTML = '<p class="dash-muted">Notes could not be loaded.</p>';
    });
})();

/**
 * Chat Monitor: every staff chat in a server-side grid; open one to read it silently (no ticks, no
 * presence), follow new messages live, and add or remove group members. Data: /api/erp/chat-monitor.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-monitor]');
    const ui = window.PinnacleDashboard;
    const kit = window.PinnacleResource;

    if (!root || !ui || !kit || !window.jQuery) {
        return;
    }

    const api = root.dataset.api;
    const reader = root.querySelector('[data-reader-messages]');
    const olderButton = root.querySelector('[data-reader-older]');
    const membersButton = root.querySelector('[data-reader-members]');
    const modal = document.getElementById('monitorMembersModal');
    const membersForm = modal.querySelector('form');
    const echo = window.PinnacleRealtime && window.PinnacleRealtime.echo;
    let current = null;
    let messages = [];
    let channel = null;
    let people = null;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function when(iso) {
        return iso ? new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : '—';
    }

    const typeSelect = root.querySelector('[data-monitor-type]');
    const grid = window.PinnacleGrid.create(root.querySelector('[data-monitor-grid]'), {
        url: api + '/datatable',
        order: [[4, 'desc']],
        searchPlaceholder: 'Search chat or person…',
        emptyText: 'No chats yet.',
        exportName: 'chats',
        params: function () { return { type: typeSelect.value }; },
        columns: [
            { data: 'title', render: function (value, type, row) { return type === 'display' ? '<button type="button" class="dash-link-btn" data-open="' + row.id + '">' + esc(value || 'Chat') + '</button>' : value; } },
            { data: 'type', render: function (value, type, row) { return esc(row.is_department ? 'Department' : (value === 'direct' ? 'Direct' : 'Group')); } },
            { data: 'members', orderable: false, searchable: false },
            { data: 'messages_count', searchable: false },
            { data: 'last_message_at', searchable: false, render: function (value) { return esc(when(value)); } }
        ]
    });

    typeSelect.addEventListener('change', function () { grid.ajax.reload(); });
    root.querySelector('[data-monitor-grid]').addEventListener('click', function (event) {
        const button = event.target.closest('[data-open]');

        if (button) {
            open(Number(button.dataset.open));
        }
    });

    /* ---------- Reader ---------- */

    function messageHtml(message) {
        if (message.type === 'system') {
            return '<li class="dash-monitor-system">' + esc(message.body) + '</li>';
        }

        const files = (message.attachments || []).map(function (file) {
            return '<a href="' + esc(file.url) + '" target="_blank" rel="noopener">' + esc(file.name) + '</a>';
        }).join(' ');

        return '<li class="dash-monitor-message' + (message.is_deleted ? ' is-deleted' : '') + '">'
            + '<div class="dash-monitor-meta"><strong>' + esc(message.sender_name) + '</strong><span>' + esc(when(message.created_at)) + '</span>'
            + (message.is_edited ? '<span class="dash-pill">edited</span>' : '') + (message.is_deleted ? '<span class="dash-pill dash-pill-rose">deleted by sender</span>' : '') + '</div>'
            + (message.reply_to ? '<blockquote>' + esc(message.reply_to.sender_name) + ': ' + esc(message.reply_to.body || 'deleted message') + '</blockquote>' : '')
            + '<p>' + esc(message.body) + '</p>' + (files ? '<div class="dash-monitor-files">' + files + '</div>' : '') + '</li>';
    }

    function render() {
        reader.innerHTML = messages.length ? messages.map(messageHtml).join('') : '<li class="dash-muted">No messages in this chat.</li>';
    }

    function loadMessages(beforeId) {
        return ui.api('GET', api + '/conversations/' + current.id + '/messages' + (beforeId ? '?before_id=' + beforeId : '')).then(function (envelope) {
            messages = beforeId ? envelope.data.concat(messages) : envelope.data;
            olderButton.hidden = envelope.data.length < 60;
            render();

            if (!beforeId) {
                reader.scrollTop = reader.scrollHeight;
            }
        });
    }

    function showHeader() {
        root.querySelector('[data-reader-title]').textContent = current.name || current.participants.map(function (person) { return person.name; }).join(' ↔ ');
        root.querySelector('[data-reader-meta]').textContent = (current.type === 'direct' ? 'Direct chat' : (current.is_department ? 'Department group' : 'Group'))
            + ' · ' + current.participants.length + ' member(s)' + (current.creator_name ? ' · started by ' + current.creator_name : '');
        membersButton.hidden = current.type !== 'group' || current.is_department;
        showMembers();
    }

    /* ---------- Members: who is in the chat, group admins, remove ---------- */

    function canManage() {
        return current.type === 'group' && !current.is_department;
    }

    function showMembers() {
        const admins = current.participants.filter(function (person) { return person.role === 'admin'; }).length;

        root.querySelector('[data-reader-count]').textContent = current.participants.length + ' member(s)' + (current.type === 'group' ? ' · ' + admins + ' group admin(s)' : '');
        root.querySelector('[data-reader-member-list]').innerHTML = current.participants.map(function (person) {
            const isAdmin = person.role === 'admin';
            const actions = canManage()
                ? '<span class="dash-monitor-member-actions">'
                    + '<button type="button" class="dash-link-btn" data-member-role="' + (isAdmin ? 'member' : 'admin') + '" data-member="' + person.user_id + '">' + (isAdmin ? 'Remove admin' : 'Make admin') + '</button>'
                    + '<button type="button" class="dash-link-btn dash-link-danger" data-member-remove="' + person.user_id + '">Remove</button></span>'
                : '';

            return '<li><span>' + esc(person.name) + (isAdmin ? ' <span class="dash-badge dash-badge-brand">Group admin</span>' : '') + '</span>' + actions + '</li>';
        }).join('') || '<li class="dash-muted">Nobody is in this chat.</li>';
    }

    function changeMembers(body, done) {
        return ui.api('PUT', api + '/conversations/' + current.id + '/members', body).then(function (envelope) {
            ui.toast(envelope.message);
            current = envelope.data;
            showHeader();
            loadMessages(null);
            grid.ajax.reload(null, false);

            if (done) {
                done();
            }
        }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The group could not be changed.'), 'error'); });
    }

    root.querySelector('[data-reader-member-list]').addEventListener('click', function (event) {
        const role = event.target.closest('[data-member-role]');
        const remove = event.target.closest('[data-member-remove]');

        if (role) {
            changeMembers(role.dataset.memberRole === 'admin' ? { make_admin_ids: [Number(role.dataset.member)] } : { make_member_ids: [Number(role.dataset.member)] });
        }

        if (remove) {
            const person = current.participants.find(function (entry) { return entry.user_id === Number(remove.dataset.memberRemove); });

            if (window.confirm('Remove ' + (person ? person.name : 'this person') + ' from the group?')) {
                changeMembers({ remove_ids: [Number(remove.dataset.memberRemove)] });
            }
        }
    });

    function open(id) {
        ui.api('GET', api + '/conversations/' + id).then(function (envelope) {
            current = envelope.data;
            root.querySelector('[data-monitor-reader]').hidden = false;
            showHeader();
            listen();

            return loadMessages(null);
        }).then(function () {
            root.querySelector('[data-monitor-reader]').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }).catch(function (envelope) { ui.toast(kit.messageOf(envelope, 'The chat could not be opened.'), 'error'); });
    }

    function listen() {
        if (!echo) {
            return;
        }

        if (channel) {
            echo.leave(channel.name.replace(/^private-/, ''));
        }

        // Listen only: never whisper typing or send read receipts from here.
        channel = echo.private('chat.' + current.id);
        channel.listen('.message.sent', function () { loadMessages(null); });
        channel.listen('.message.updated', function () { loadMessages(null); });
    }

    olderButton.addEventListener('click', function () {
        if (messages.length) {
            loadMessages(messages[0].id);
        }
    });

    /* ---------- Members ---------- */

    function renderPeople() {
        const term = membersForm.querySelector('[data-members-search]').value.trim().toLowerCase();
        const inGroup = current.participants.map(function (person) { return person.user_id; });

        membersForm.querySelector('[data-members-list]').innerHTML = people.filter(function (person) {
            return inGroup.indexOf(person.id) === -1 && (!term || person.name.toLowerCase().indexOf(term) !== -1);
        }).map(function (person) {
            return '<li><label class="dash-check"><input type="checkbox" value="' + person.id + '"' + (chosen[person.id] ? ' checked' : '') + '> ' + esc(person.name) + '</label></li>';
        }).join('') || '<li class="dash-muted">Everyone is already in this group.</li>';
    }

    const chosen = {};

    membersButton.addEventListener('click', function () {
        const load = people ? Promise.resolve() : ui.api('GET', root.dataset.people).then(function (envelope) { people = envelope.data; });

        load.then(function () {
            Object.keys(chosen).forEach(function (key) { delete chosen[key]; });
            kit.clearErrors(membersForm);
            renderPeople();
            modal.showModal();
        });
    });

    membersForm.querySelector('[data-members-search]').addEventListener('input', renderPeople);
    membersForm.querySelector('[data-members-list]').addEventListener('change', function (event) {
        chosen[event.target.value] = event.target.checked;
    });

    membersForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const addIds = Object.keys(chosen).filter(function (id) { return chosen[id]; }).map(Number);

        if (!addIds.length) {
            modal.close();

            return;
        }

        changeMembers({ add_ids: addIds }, function () { modal.close(); });
    });

    modal.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () { modal.close(); });
    });

    if (root.dataset.openId) {
        open(Number(root.dataset.openId));
    }
})();

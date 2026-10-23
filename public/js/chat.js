/**
 * Staff chat (WhatsApp-style) on /api/erp/chat with live updates over Reverb: chat list with unread
 * counters, threads with replies, files, reactions, @mentions, edit / delete, ✓ ✓✓ blue-✓✓ ticks,
 * typing indicator and online status.
 */
(function () {
    'use strict';

    const root = document.querySelector('[data-chat]');
    const ui = window.PinnacleDashboard;

    if (!root || !ui) {
        return;
    }

    const api = root.dataset.api;
    const me = Number(root.dataset.userId);
    const myName = root.dataset.userName;
    const EDIT_MINUTES = 15;
    const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    const timeFormat = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' });
    const dayFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short' });

    const listEl = root.querySelector('[data-chat-conversations]');
    const messagesEl = root.querySelector('[data-chat-messages]');
    const composer = root.querySelector('[data-chat-composer]');
    const input = root.querySelector('[data-chat-input]');
    const fileInput = root.querySelector('[data-chat-files]');
    const fileChips = root.querySelector('[data-chat-file-chips]');
    const bar = root.querySelector('[data-chat-bar]');
    const typingEl = root.querySelector('[data-chat-typing]');
    const mentionList = root.querySelector('[data-chat-mention-list]');
    const searchInput = root.querySelector('[data-chat-search]');

    let conversations = [];
    let current = null;
    let messages = [];
    let view = 'chats';
    let replyTo = null;
    let editing = null;
    let mentionIds = [];
    let channel = null;
    let typingTimer = null;
    let lastWhisper = 0;
    let loadingOlder = false;
    let noMoreOlder = false;
    const online = new Set();
    const echo = window.PinnacleRealtime && window.PinnacleRealtime.echo;

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function initials(name) {
        return esc(String(name || '?').split(' ').map(function (part) { return part.charAt(0); }).join('').slice(0, 2).toUpperCase());
    }

    function when(iso) {
        if (!iso) {
            return '';
        }

        const date = new Date(iso);

        return date.toDateString() === new Date().toDateString() ? timeFormat.format(date) : dayFormat.format(date);
    }

    function uuid() {
        return window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx'.replace(/x/g, function () { return (Math.random() * 16 | 0).toString(16); });
    }

    function fail(envelope, fallback) {
        ui.toast((window.PinnacleResource ? window.PinnacleResource.messageOf(envelope, fallback) : (envelope && envelope.message) || fallback), 'error');
    }

    /* ---------- Chat list ---------- */

    function loadConversations() {
        return ui.api('GET', api + '/conversations' + (view === 'archived' ? '?archived=1' : '')).then(function (envelope) {
            conversations = envelope.data;
            renderList();
        });
    }

    function renderList(searchResults) {
        if (searchResults) {
            listEl.innerHTML = searchResults.length ? searchResults.map(function (message) {
                const conversation = conversations.find(function (item) { return item.id === message.conversation_id; });

                return '<li><button type="button" class="dash-chat-item" data-open-conversation="' + message.conversation_id + '">'
                    + '<span class="dash-chat-avatar">' + initials(conversation ? conversation.name : message.sender_name) + '</span>'
                    + '<span class="dash-chat-item-text"><strong>' + esc(conversation ? conversation.name : 'Chat') + '</strong><span>' + esc(message.sender_name + ': ' + (message.body || '')) + '</span></span>'
                    + '<span class="dash-chat-item-meta"><time>' + esc(when(message.created_at)) + '</time></span></button></li>';
            }).join('') : '<li class="dash-muted dash-chat-none">No messages match.</li>';

            return;
        }

        listEl.innerHTML = conversations.length ? conversations.map(function (conversation) {
            const isOnline = conversation.other_user && online.has(conversation.other_user.id);

            return '<li><button type="button" class="dash-chat-item' + (current && current.id === conversation.id ? ' is-active' : '') + '" data-open-conversation="' + conversation.id + '">'
                + '<span class="dash-chat-avatar' + (conversation.type === 'group' ? ' is-group' : '') + (isOnline ? ' is-online' : '') + '">' + initials(conversation.name) + '</span>'
                + '<span class="dash-chat-item-text"><strong>' + esc(conversation.name) + (conversation.is_pinned ? ' <span aria-label="pinned">📌</span>' : '') + (conversation.is_muted ? ' <span aria-label="muted">🔕</span>' : '') + '</strong>'
                + '<span>' + esc(conversation.last_message ? (conversation.type === 'group' && conversation.last_message.sender_name ? conversation.last_message.sender_name.split(' ')[0] + ': ' : '') + conversation.last_message.body : (conversation.description || 'Say hello 👋')) + '</span></span>'
                + '<span class="dash-chat-item-meta"><time>' + esc(conversation.last_message ? when(conversation.last_message.created_at) : '') + '</time>'
                + (conversation.unread_count ? '<span class="dash-chat-unread">' + conversation.unread_count + '</span>' : '') + '</span></button></li>';
        }).join('') : '<li class="dash-muted dash-chat-none">' + (view === 'archived' ? 'No archived chats.' : 'No chats yet. Press + to start one.') + '</li>';
    }

    listEl.addEventListener('click', function (event) {
        const item = event.target.closest('[data-open-conversation]');

        if (item) {
            open(Number(item.dataset.openConversation));
        }
    });

    root.querySelectorAll('[data-chat-view]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            view = tab.dataset.chatView;
            root.querySelectorAll('[data-chat-view]').forEach(function (other) {
                other.classList.toggle('is-active', other === tab);
                other.setAttribute('aria-selected', String(other === tab));
            });
            loadConversations();
        });
    });

    let searchTimer = null;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);

        const term = searchInput.value.trim();

        if (term.length < 2) {
            renderList();

            return;
        }

        searchTimer = setTimeout(function () {
            const lower = term.toLowerCase();
            const named = conversations.filter(function (conversation) { return conversation.name && conversation.name.toLowerCase().indexOf(lower) !== -1; });

            ui.api('GET', api + '/search?q=' + encodeURIComponent(term)).then(function (envelope) {
                renderList(named.map(function (conversation) {
                    return { conversation_id: conversation.id, sender_name: '', body: conversation.last_message ? conversation.last_message.body : '', created_at: conversation.last_message ? conversation.last_message.created_at : null };
                }).concat(envelope.data));
            }).catch(function () {});
        }, 300);
    });

    /* ---------- Thread ---------- */

    function statusLine() {
        const status = root.querySelector('[data-chat-status]');

        if (!current) {
            return;
        }

        if (current.type === 'group') {
            status.textContent = current.members.length + ' members' + (current.is_department ? ' · department group' : '');
        } else if (current.other_user && online.has(current.other_user.id)) {
            status.textContent = 'online';
        } else if (current.other_user && current.other_user.last_seen_at) {
            status.textContent = 'last seen ' + dayFormat.format(new Date(current.other_user.last_seen_at)) + ' ' + timeFormat.format(new Date(current.other_user.last_seen_at));
        } else {
            status.textContent = '';
        }
    }

    function ticks(message) {
        if (message.sender_id !== me || message.type === 'system') {
            return '';
        }

        return '<span class="dash-chat-ticks is-' + message.status + '" aria-label="' + message.status + '">' + (message.status === 'sent' ? '✓' : '✓✓') + '</span>';
    }

    function messageHtml(message, previous) {
        const day = new Date(message.created_at).toDateString();
        const divider = !previous || new Date(previous.created_at).toDateString() !== day
            ? '<li class="dash-chat-day"><span>' + esc(day === new Date().toDateString() ? 'Today' : dayFormat.format(new Date(message.created_at))) + '</span></li>' : '';

        if (message.type === 'system') {
            return divider + '<li class="dash-chat-system" data-message="' + message.id + '">' + esc(message.body) + '</li>';
        }

        const mine = message.sender_id === me;
        const showName = current && current.type === 'group' && !mine && (!previous || previous.sender_id !== message.sender_id || previous.type === 'system');
        const canEdit = mine && !message.is_deleted && (Date.now() - new Date(message.created_at).getTime()) < EDIT_MINUTES * 60000;
        const body = message.is_deleted
            ? '<em class="dash-chat-deleted">🚫 This message was deleted</em>'
            : esc(message.body || '').replace(/@([\w.]+(?: [\w.]+)?)/g, '<mark>@$1</mark>').replace(/\n/g, '<br>');
        const files = (message.attachments || []).map(function (file) {
            return file.mime_type.indexOf('image/') === 0
                ? '<a href="' + esc(file.url) + '" target="_blank" rel="noopener" class="dash-chat-image"><img src="' + esc(file.url) + '" alt="' + esc(file.name) + '" loading="lazy"></a>'
                : '<a href="' + esc(file.url) + '" target="_blank" rel="noopener" class="dash-chat-file">📎 ' + esc(file.name) + ' <span>' + Math.ceil(file.size_bytes / 1024) + ' KB</span></a>';
        }).join('');
        const reactions = (message.reactions || []).map(function (reaction) {
            return '<button type="button" class="dash-chat-reaction' + (reaction.user_ids.indexOf(me) !== -1 ? ' is-mine' : '') + '" data-react="' + esc(reaction.emoji) + '">' + esc(reaction.emoji) + ' ' + reaction.user_ids.length + '</button>';
        }).join('');

        return divider + '<li class="dash-chat-message' + (mine ? ' is-mine' : '') + '" data-message="' + message.id + '">'
            + '<div class="dash-chat-bubble">'
            + (showName ? '<span class="dash-chat-sender">' + esc(message.sender_name) + '</span>' : '')
            + (message.is_forwarded ? '<span class="dash-chat-forwarded">↪ Forwarded</span>' : '')
            + (message.reply_to ? '<button type="button" class="dash-chat-quote" data-jump="' + message.reply_to.id + '"><strong>' + esc(message.reply_to.sender_name) + '</strong><span>' + esc(message.reply_to.body || 'Deleted message') + '</span></button>' : '')
            + files
            + (body ? '<p>' + body + '</p>' : '')
            + '<span class="dash-chat-meta">' + (message.is_edited && !message.is_deleted ? 'edited · ' : '') + esc(timeFormat.format(new Date(message.created_at))) + ' ' + ticks(message) + '</span>'
            + (reactions ? '<div class="dash-chat-reactions">' + reactions + '</div>' : '')
            + '</div>'
            + (message.is_deleted ? '' : '<div class="dash-chat-actions">'
                + '<button type="button" data-action="reply" aria-label="Reply" data-tooltip="Reply">↩</button>'
                + '<button type="button" data-action="react" aria-label="React" data-tooltip="React">☺</button>'
                + '<button type="button" data-action="forward" aria-label="Forward" data-tooltip="Forward">↪</button>'
                + (canEdit ? '<button type="button" data-action="edit" aria-label="Edit" data-tooltip="Edit">✎</button>' : '')
                + '<button type="button" data-action="delete" aria-label="Delete" data-tooltip="Delete">🗑</button>'
                + '</div>')
            + '</li>';
    }

    function renderMessages(keepScroll) {
        const atBottom = messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 80;
        const oldHeight = messagesEl.scrollHeight;

        messagesEl.innerHTML = messages.map(function (message, index) { return messageHtml(message, messages[index - 1]); }).join('')
            || '<li class="dash-chat-system">No messages yet — say hello 👋</li>';

        if (keepScroll === 'older') {
            messagesEl.scrollTop = messagesEl.scrollHeight - oldHeight;
        } else if (keepScroll !== true || atBottom) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function markRead() {
        const last = messages.filter(function (message) { return message.sender_id !== me; }).pop();
        const conversation = current;

        if (!last || !conversation || document.hidden) {
            return;
        }

        ui.api('POST', api + '/conversations/' + conversation.id + '/read', { up_to_id: last.id }).then(function () {
            const item = conversations.find(function (entry) { return entry.id === conversation.id; });

            if (item && item.unread_count) {
                item.unread_count = 0;
                renderList();
            }
        }).catch(function () {});
    }

    function subscribe(conversationId) {
        if (!echo) {
            return;
        }

        if (channel) {
            echo.leave(channel.name.replace(/^private-/, ''));
        }

        channel = echo.private('chat.' + conversationId);
        channel.listen('.message.sent', function (message) {
            if (!current || message.conversation_id !== current.id || messages.some(function (item) { return item.id === message.id; })) {
                return;
            }

            messages.push(message);
            renderMessages(true);
            typingEl.hidden = true;
            markRead();
        });
        channel.listen('.message.updated', function (message) {
            messages = messages.map(function (item) { return item.id === message.id ? message : item; });
            renderMessages(true);
        });
        channel.listen('.messages.read', function (event) {
            if (event.user_id === me) {
                return;
            }

            messages.forEach(function (message) {
                if (message.sender_id === me && message.id <= event.up_to_id && current && (current.type === 'direct')) {
                    message.status = 'read';
                }
            });
            renderMessages(true);
        });
        channel.listenForWhisper('typing', function (event) {
            if (event.id === me) {
                return;
            }

            typingEl.textContent = (current && current.type === 'group' ? event.name.split(' ')[0] + ' is' : 'typing') + (current && current.type === 'group' ? ' typing…' : '…');
            typingEl.hidden = false;
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function () { typingEl.hidden = true; }, 3000);
        });
    }

    function open(conversationId) {
        const found = conversations.find(function (item) { return item.id === conversationId; });
        const load = found ? Promise.resolve(found) : loadConversations().then(function () {
            return conversations.find(function (item) { return item.id === conversationId; });
        });

        return load.then(function (conversation) {
            if (!conversation) {
                return;
            }

            current = conversation;
            messages = [];
            noMoreOlder = false;
            cancelBar();
            root.classList.add('is-thread-open');
            root.querySelector('[data-chat-empty]').hidden = true;
            root.querySelector('[data-chat-header]').hidden = false;
            messagesEl.hidden = false;
            composer.hidden = current.only_admins_can_post && current.my_role !== 'admin';
            root.querySelector('[data-chat-name]').textContent = current.name;
            root.querySelector('[data-chat-avatar]').innerHTML = initials(current.name);
            root.querySelector('[data-chat-avatar]').classList.toggle('is-group', current.type === 'group');
            root.querySelector('[data-chat-info]').hidden = current.type !== 'group';
            root.querySelector('[data-chat-pin]').classList.toggle('is-on', current.is_pinned);
            root.querySelector('[data-chat-mute]').textContent = current.is_muted ? '🔕' : '🔔';
            root.querySelector('[data-chat-archive]').dataset.tooltip = current.is_archived ? 'Unarchive' : 'Archive';
            statusLine();
            renderList();
            messagesEl.innerHTML = '<li class="dash-chat-system">Loading…</li>';
            subscribe(current.id);

            return ui.api('GET', api + '/conversations/' + current.id + '/messages').then(function (envelope) {
                messages = envelope.data;
                noMoreOlder = messages.length < 40;
                renderMessages();
                markRead();
                input.focus();
            });
        }).catch(function (envelope) { fail(envelope, 'The chat could not be opened.'); });
    }

    messagesEl.addEventListener('scroll', function () {
        if (messagesEl.scrollTop > 40 || loadingOlder || noMoreOlder || !messages.length || !current) {
            return;
        }

        loadingOlder = true;
        ui.api('GET', api + '/conversations/' + current.id + '/messages?before=' + messages[0].id).then(function (envelope) {
            noMoreOlder = envelope.data.length < 30;
            messages = envelope.data.concat(messages);
            renderMessages('older');
        }).catch(function () {}).finally(function () { loadingOlder = false; });
    });

    /* ---------- Emoji picker (inserts at the cursor) ---------- */

    const EMOJI = {
        '😊': '😀 😃 😄 😁 😆 😅 😂 🤣 😊 😇 🙂 🙃 😉 😌 😍 🥰 😘 😗 😙 😚 😋 😛 😜 🤪 😝 🤗 🤭 🤫 🤔 🤐 🤨 😐 😑 😶 😏 😒 🙄 😬 😮‍💨 🤥 😔 😪 🤤 😴 😷 🤒 🤕 🥵 🥶 😵 🤯 🥳 😎 🤓 🧐 😕 😟 🙁 😮 😯 😲 😳 🥺 😦 😧 😨 😰 😥 😢 😭 😱 😖 😣 😞 😓 😩 😫 🥱 😤 😡 😠',
        '👍': '👍 👎 👌 ✌️ 🤞 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 🖖 👋 🤝 🙏 👏 🙌 👐 🤲 💪 ✍️ 💅 🫶 👀 🧠',
        '❤️': '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 ✨ ⭐ 🌟 💯 ✅ ❌ ⚠️ ❗ ❓ 🔥 🎉 🎊',
        '📚': '📚 📖 ✏️ 📝 📌 📎 📅 🗓️ ⏰ 🕘 🏫 🎓 🧪 🔬 💻 🖥️ 📱 ☎️ 📞 📧 💰 💵 🧾 📊 📈 🏆 🥇 ⚽ 🏏 🎨 🎵 🍽️ ☕ 🚌 🏠'
    };
    const emojiBox = root.querySelector('[data-chat-emoji]');
    const emojiPanel = emojiBox.querySelector('[data-chat-emoji-panel]');
    const emojiToggle = emojiBox.querySelector('[data-chat-emoji-toggle]');

    function showEmojiGroup(key) {
        emojiBox.querySelector('[data-chat-emoji-grid]').innerHTML = EMOJI[key].split(' ').map(function (emoji) {
            return '<button type="button" data-emoji="' + emoji + '" aria-label="' + emoji + '">' + emoji + '</button>';
        }).join('');
        emojiBox.querySelectorAll('[data-emoji-group]').forEach(function (tab) { tab.classList.toggle('is-active', tab.dataset.emojiGroup === key); });
    }

    emojiBox.querySelector('[data-chat-emoji-tabs]').innerHTML = Object.keys(EMOJI).map(function (key) {
        return '<button type="button" data-emoji-group="' + key + '" aria-label="Emoji group ' + key + '">' + key + '</button>';
    }).join('');
    showEmojiGroup('😊');

    function closeEmoji() {
        emojiPanel.hidden = true;
        emojiToggle.setAttribute('aria-expanded', 'false');
    }

    emojiToggle.addEventListener('click', function () {
        emojiPanel.hidden = !emojiPanel.hidden;
        emojiToggle.setAttribute('aria-expanded', emojiPanel.hidden ? 'false' : 'true');
    });

    emojiPanel.addEventListener('click', function (event) {
        const group = event.target.closest('[data-emoji-group]');
        const pick = event.target.closest('[data-emoji]');

        if (group) {
            showEmojiGroup(group.dataset.emojiGroup);
        }

        if (pick) {
            const start = input.selectionStart === null ? input.value.length : input.selectionStart;
            const end = input.selectionEnd === null ? start : input.selectionEnd;

            input.value = input.value.slice(0, start) + pick.dataset.emoji + input.value.slice(end);
            input.focus();
            input.selectionStart = input.selectionEnd = start + pick.dataset.emoji.length;
            autoGrow();
        }
    });

    document.addEventListener('click', function (event) {
        if (!emojiBox.contains(event.target)) {
            closeEmoji();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeEmoji();
        }
    });

    root.querySelector('[data-chat-back]').addEventListener('click', function () {
        root.classList.remove('is-thread-open');
    });

    /* ---------- Message actions ---------- */

    function messageById(id) {
        return messages.find(function (message) { return message.id === id; });
    }

    function setBar(text) {
        root.querySelector('[data-chat-bar-text]').innerHTML = text;
        bar.hidden = false;
        input.focus();
    }

    function cancelBar() {
        replyTo = null;
        editing = null;
        bar.hidden = true;
        input.value = '';
        mentionIds = [];
        fileInput.value = '';
        fileChips.innerHTML = '';
    }

    root.querySelector('[data-chat-bar-cancel]').addEventListener('click', cancelBar);

    function closePopovers() {
        root.querySelectorAll('.dash-chat-popover').forEach(function (pop) { pop.remove(); });
    }

    messagesEl.addEventListener('click', function (event) {
        const item = event.target.closest('[data-message]');
        const message = item ? messageById(Number(item.dataset.message)) : null;
        const action = event.target.closest('[data-action]');
        const reaction = event.target.closest('[data-react]');
        const jump = event.target.closest('[data-jump]');
        const choice = event.target.closest('[data-delete-for]');

        if (jump) {
            const target = messagesEl.querySelector('[data-message="' + jump.dataset.jump + '"]');

            if (target) {
                target.scrollIntoView({ block: 'center', behavior: 'smooth' });
                target.classList.add('is-flash');
                setTimeout(function () { target.classList.remove('is-flash'); }, 1200);
            }

            return;
        }

        if (!message) {
            return;
        }

        if (reaction) {
            const mineAlready = (message.reactions || []).some(function (entry) { return entry.emoji === reaction.dataset.react && entry.user_ids.indexOf(me) !== -1; });

            closePopovers();
            ui.api('POST', api + '/messages/' + message.id + '/react', { emoji: mineAlready ? null : reaction.dataset.react }).then(function (envelope) {
                messages = messages.map(function (entry) { return entry.id === envelope.data.id ? envelope.data : entry; });
                renderMessages(true);
            }).catch(function (envelope) { fail(envelope, 'Could not react.'); });

            return;
        }

        if (choice) {
            const forEveryone = choice.dataset.deleteFor === 'everyone';

            closePopovers();
            ui.api('DELETE', api + '/messages/' + message.id, { for_everyone: forEveryone }).then(function (envelope) {
                ui.toast(envelope.message);

                if (forEveryone) {
                    message.is_deleted = true;
                    message.body = null;
                    message.attachments = [];
                } else {
                    messages = messages.filter(function (entry) { return entry.id !== message.id; });
                }

                renderMessages(true);
            }).catch(function (envelope) { fail(envelope, 'Could not delete.'); });

            return;
        }

        if (!action) {
            return;
        }

        closePopovers();

        switch (action.dataset.action) {
        case 'reply':
            editing = null;
            replyTo = message;
            setBar('↩ Replying to <strong>' + esc(message.sender_id === me ? 'yourself' : message.sender_name) + '</strong>: ' + esc((message.body || 'attachment').slice(0, 80)));
            break;
        case 'edit':
            replyTo = null;
            editing = message;
            input.value = message.body || '';
            setBar('✎ Editing message');
            break;
        case 'react':
            item.insertAdjacentHTML('beforeend', '<div class="dash-chat-popover">' + REACTIONS.map(function (emoji) {
                return '<button type="button" data-react="' + emoji + '">' + emoji + '</button>';
            }).join('') + '</div>');
            break;
        case 'delete':
            item.insertAdjacentHTML('beforeend', '<div class="dash-chat-popover dash-chat-delete">'
                + '<button type="button" data-delete-for="me">Delete for me</button>'
                + (message.sender_id === me ? '<button type="button" data-delete-for="everyone">Delete for everyone</button>' : '')
                + '</div>');
            break;
        case 'forward':
            openForward(message);
            break;
        }
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.dash-chat-popover') && !event.target.closest('[data-action]')) {
            closePopovers();
        }
    });

    /* ---------- Composer ---------- */

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
        input.style.overflowY = input.scrollHeight > 160 ? 'auto' : 'hidden';
    }

    function showMentions() {
        if (!current || current.type !== 'group') {
            mentionList.hidden = true;

            return;
        }

        const before = input.value.slice(0, input.selectionStart);
        const match = before.match(/@([\w.]*)$/);

        if (!match) {
            mentionList.hidden = true;

            return;
        }

        const term = match[1].toLowerCase();
        const people = current.members.filter(function (member) { return member.id !== me && member.name.toLowerCase().indexOf(term) !== -1; }).slice(0, 6);

        mentionList.innerHTML = people.map(function (member) {
            return '<li><button type="button" data-mention="' + member.id + '" data-name="' + esc(member.name) + '">' + esc(member.name) + '</button></li>';
        }).join('');
        mentionList.hidden = people.length === 0;
    }

    mentionList.addEventListener('click', function (event) {
        const pick = event.target.closest('[data-mention]');

        if (!pick) {
            return;
        }

        const caret = input.selectionStart;
        const before = input.value.slice(0, caret).replace(/@([\w.]*)$/, '@' + pick.dataset.name + ' ');

        input.value = before + input.value.slice(caret);
        mentionIds.push(Number(pick.dataset.mention));
        mentionList.hidden = true;
        input.focus();
        input.setSelectionRange(before.length, before.length);
    });

    input.addEventListener('input', function () {
        autoGrow();
        showMentions();

        if (channel && Date.now() - lastWhisper > 2000) {
            lastWhisper = Date.now();
            channel.whisper('typing', { id: me, name: myName });
        }
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            composer.requestSubmit();
        }

        if (event.key === 'Escape') {
            cancelBar();
        }
    });

    fileInput.addEventListener('change', function () {
        fileChips.innerHTML = Array.prototype.map.call(fileInput.files, function (file) {
            return '<span class="dash-chip">📎 ' + esc(file.name) + '</span>';
        }).join('');
    });

    composer.addEventListener('submit', function (event) {
        event.preventDefault();

        const text = input.value.trim();

        if (!current || (!text && !fileInput.files.length)) {
            return;
        }

        if (editing) {
            const message = editing;

            ui.api('PUT', api + '/messages/' + message.id, { body: text }).then(function (envelope) {
                messages = messages.map(function (entry) { return entry.id === envelope.data.id ? envelope.data : entry; });
                cancelBar();
                renderMessages(true);
            }).catch(function (envelope) { fail(envelope, 'Could not edit.'); });

            return;
        }

        const body = new FormData();

        if (text) {
            body.append('body', text);
        }

        body.append('client_uuid', uuid());

        if (replyTo) {
            body.append('reply_to_message_id', replyTo.id);
        }

        mentionIds.filter(function (id, index, all) { return all.indexOf(id) === index && text.indexOf('@') !== -1; }).forEach(function (id) {
            body.append('mention_ids[]', id);
        });
        Array.prototype.forEach.call(fileInput.files, function (file) { body.append('files[]', file); });

        const conversation = current;
        const button = composer.querySelector('[type="submit"]');

        button.disabled = true;
        ui.api('POST', api + '/conversations/' + conversation.id + '/messages', body).then(function (envelope) {
            if (current && current.id === conversation.id && !messages.some(function (entry) { return entry.id === envelope.data.id; })) {
                messages.push(envelope.data);
                renderMessages();
            }

            cancelBar();
            autoGrow();
            loadConversations();
        }).catch(function (envelope) { fail(envelope, 'The message was not sent.'); }).finally(function () {
            button.disabled = false;
            input.focus();
        });
    });

    /* ---------- Header actions ---------- */

    function preference(settings, message) {
        if (!current) {
            return;
        }

        ui.api('PATCH', api + '/conversations/' + current.id + '/preferences', settings).then(function () {
            ui.toast(message);

            return loadConversations();
        }).then(function () {
            current = conversations.find(function (item) { return item.id === current.id; }) || current;
            root.querySelector('[data-chat-pin]').classList.toggle('is-on', current.is_pinned);
            root.querySelector('[data-chat-mute]').textContent = current.is_muted ? '🔕' : '🔔';
        }).catch(function () {});
    }

    root.querySelector('[data-chat-pin]').addEventListener('click', function () {
        preference({ is_pinned: !current.is_pinned }, current.is_pinned ? 'Unpinned.' : 'Pinned to the top.');
    });

    root.querySelector('[data-chat-mute]').addEventListener('click', function () {
        preference({ muted: !current.is_muted }, current.is_muted ? 'Notifications on.' : 'Muted.');
    });

    root.querySelector('[data-chat-archive]').addEventListener('click', function () {
        preference({ is_archived: !current.is_archived }, current.is_archived ? 'Back in your chats.' : 'Archived.');
    });

    /* ---------- People picker (new chat, new group, group info) ---------- */

    const peopleModal = document.getElementById('chatPeopleModal');
    const peopleList = peopleModal.querySelector('[data-people-list]');
    const peopleSearch = peopleModal.querySelector('[data-people-search]');
    const confirmButton = peopleModal.querySelector('[data-people-confirm]');
    let people = [];
    let peopleMode = 'direct';

    function renderPeople() {
        const term = peopleSearch.value.trim().toLowerCase();
        const members = peopleMode === 'info' && current ? current.members.map(function (member) { return member.id; }) : [];

        peopleList.innerHTML = people.filter(function (person) { return person.name.toLowerCase().indexOf(term) !== -1; }).map(function (person) {
            const isMember = members.indexOf(person.id) !== -1;
            const control = peopleMode === 'direct'
                ? '<button type="button" class="dash-chat-person" data-pick-person="' + person.id + '">'
                : '<label class="dash-chat-person"><input type="checkbox" value="' + person.id + '"' + (isMember ? ' checked disabled' : '') + '>';

            return '<li>' + control + '<span class="dash-chat-avatar' + (online.has(person.id) ? ' is-online' : '') + '">' + initials(person.name) + '</span><span>' + esc(person.name) + (isMember ? ' <em class="dash-muted">member</em>' : '') + '</span>' + (peopleMode === 'direct' ? '</button>' : '</label>') + '</li>';
        }).join('') || '<li class="dash-muted">Nobody found.</li>';
    }

    function openPeople(mode) {
        peopleMode = mode;
        peopleSearch.value = '';
        peopleModal.querySelector('[data-group-name]').value = mode === 'info' && current ? current.name : '';
        peopleModal.querySelectorAll('[data-group-fields]').forEach(function (field) { field.hidden = mode !== 'group'; });
        peopleModal.querySelector('[data-people-title]').textContent = { direct: 'New chat', group: 'New group', info: current ? current.name : 'Group' }[mode];
        peopleModal.querySelector('[data-people-hint]').textContent = { direct: 'Choose a colleague.', group: 'Name the group and tick its members.', info: current && current.my_role === 'admin' && !current.is_department ? 'Tick people to add them.' : 'Members of this group.' }[mode];
        confirmButton.hidden = mode === 'direct' || (mode === 'info' && (!current || current.my_role !== 'admin' || current.is_department));
        peopleModal.querySelector('[data-people-confirm-label]').textContent = mode === 'group' ? 'Create group' : 'Add to group';

        (people.length ? Promise.resolve() : ui.api('GET', api + '/people').then(function (envelope) { people = envelope.data; })).then(function () {
            renderPeople();
            peopleModal.showModal();
            peopleSearch.focus();
        }).catch(function (envelope) { fail(envelope, 'Colleagues could not be loaded.'); });
    }

    peopleSearch.addEventListener('input', renderPeople);
    root.querySelector('[data-chat-new]').addEventListener('click', function () { openPeople('direct'); });
    root.querySelector('[data-chat-new-group]').addEventListener('click', function () { openPeople('group'); });
    root.querySelector('[data-chat-info]').addEventListener('click', function () { openPeople('info'); });

    peopleList.addEventListener('click', function (event) {
        const pick = event.target.closest('[data-pick-person]');

        if (!pick) {
            return;
        }

        ui.api('POST', api + '/direct', { user_id: Number(pick.dataset.pickPerson) }).then(function (envelope) {
            peopleModal.close();

            return loadConversations().then(function () { return open(envelope.data.id); });
        }).catch(function (envelope) { fail(envelope, 'The chat could not be opened.'); });
    });

    confirmButton.addEventListener('click', function () {
        const picked = Array.prototype.map.call(peopleList.querySelectorAll('input:checked:not(:disabled)'), function (box) { return Number(box.value); });
        const request = peopleMode === 'group'
            ? ui.api('POST', api + '/groups', { name: peopleModal.querySelector('[data-group-name]').value.trim(), member_ids: picked, only_admins_can_post: peopleModal.querySelector('[data-group-admins-only]').checked })
            : ui.api('PUT', api + '/conversations/' + current.id, { add_ids: picked });

        request.then(function (envelope) {
            peopleModal.close();
            ui.toast(envelope.message);

            return loadConversations().then(function () { return open(envelope.data.id); });
        }).catch(function (envelope) {
            if (window.PinnacleResource) {
                window.PinnacleResource.showErrors(peopleModal, envelope);
            }
        });
    });

    /* ---------- Forward ---------- */

    const forwardModal = document.getElementById('chatForwardModal');
    let forwarding = null;

    function openForward(message) {
        forwarding = message;
        forwardModal.querySelector('[data-forward-list]').innerHTML = conversations.map(function (conversation) {
            return '<li><label class="dash-chat-person"><input type="checkbox" value="' + conversation.id + '"><span class="dash-chat-avatar">' + initials(conversation.name) + '</span><span>' + esc(conversation.name) + '</span></label></li>';
        }).join('');
        forwardModal.showModal();
    }

    forwardModal.querySelector('[data-forward-confirm]').addEventListener('click', function () {
        const ids = Array.prototype.map.call(forwardModal.querySelectorAll('input:checked'), function (box) { return Number(box.value); });

        if (!forwarding || !ids.length) {
            return;
        }

        ui.api('POST', api + '/messages/' + forwarding.id + '/forward', { conversation_ids: ids.slice(0, 10) }).then(function (envelope) {
            forwardModal.close();
            ui.toast(envelope.message);
            loadConversations();
        }).catch(function (envelope) { fail(envelope, 'Could not forward.'); });
    });

    [peopleModal, forwardModal].forEach(function (dialog) {
        dialog.querySelectorAll('[data-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () { dialog.close(); });
        });
    });

    /* ---------- Live: chat list, presence ---------- */

    if (echo) {
        let listTimer = null;

        echo.private('App.Models.User.' + me).listen('.conversation.updated', function (event) {
            clearTimeout(listTimer);
            listTimer = setTimeout(function () {
                loadConversations().then(function () {
                    if (current && current.id === event.conversation_id) {
                        const item = conversations.find(function (entry) { return entry.id === current.id; });

                        if (item) {
                            item.unread_count = 0;
                            renderList();
                        }
                    }
                });
            }, 250);
        });

        echo.join('chat.online')
            .here(function (users) {
                users.forEach(function (user) { online.add(user.id); });
                renderList();
                statusLine();
            })
            .joining(function (user) {
                online.add(user.id);
                renderList();
                statusLine();
            })
            .leaving(function (user) {
                online.delete(user.id);

                if (current && current.other_user && current.other_user.id === user.id) {
                    current.other_user.last_seen_at = new Date().toISOString();
                }

                renderList();
                statusLine();
            });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            markRead();
        }
    });

    loadConversations().then(function () {
        if (root.dataset.openId) {
            open(Number(root.dataset.openId));
        }
    }).catch(function () {
        listEl.innerHTML = '<li class="dash-muted">Chats could not be loaded.</li>';
    });
})();

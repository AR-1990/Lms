/**
 * Header bell on every dashboard page: unread badge, the latest notifications, mark read.
 * Same data as the mobile app: /api/erp/notifications/summary and /api/erp/notifications/{id}/read.
 */
(function () {
    'use strict';

    const bell = document.querySelector('[data-bell]');
    const ui = window.PinnacleDashboard;

    if (!bell || !ui) {
        return;
    }

    const toggle = bell.querySelector('[data-bell-toggle]');
    const panel = bell.querySelector('[data-bell-panel]');
    const list = bell.querySelector('[data-bell-list]');
    const count = bell.querySelector('[data-bell-count]');
    const timeFormat = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

    function esc(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined ? '' : String(value);

        return div.innerHTML;
    }

    function setCount(unread) {
        count.textContent = unread > 99 ? '99+' : String(unread);
        count.hidden = unread === 0;
        bell.classList.toggle('has-unread', unread > 0);
    }

    function load() {
        return ui.api('GET', bell.dataset.summaryUrl).then(function (envelope) {
            setCount(envelope.data.unread);
            list.innerHTML = envelope.data.latest.length
                ? envelope.data.latest.map(function (item) {
                    return '<li><a href="' + esc(item.url || bell.dataset.allUrl) + '" class="dash-bell-item' + (item.read_at ? '' : ' is-unread') + (item.is_urgent ? ' is-urgent' : '') + '" data-bell-item="' + item.id + '">'
                        + '<strong>' + esc(item.title) + '</strong>'
                        + (item.body ? '<span>' + esc(item.body) + '</span>' : '')
                        + '<time>' + esc(timeFormat.format(new Date(item.created_at))) + '</time></a></li>';
                }).join('')
                : '<li class="dash-muted dash-bell-empty">You are all caught up.</li>';
        });
    }

    function close() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
        if (!panel.hidden) {
            close();

            return;
        }

        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        load().catch(function () {
            list.innerHTML = '<li class="dash-muted dash-bell-empty">Could not load notifications.</li>';
        });
    });

    document.addEventListener('click', function (event) {
        if (!bell.contains(event.target)) {
            close();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            close();
            toggle.focus();
        }
    });

    list.addEventListener('click', function (event) {
        const item = event.target.closest('[data-bell-item]');

        if (!item || !item.classList.contains('is-unread')) {
            return;
        }

        // Mark read before following the link; navigation continues either way.
        event.preventDefault();
        ui.api('POST', bell.dataset.readUrl + '/' + item.dataset.bellItem + '/read')
            .catch(function () {})
            .finally(function () {
                window.location.href = item.href;
            });
    });

    bell.querySelector('[data-bell-read-all]').addEventListener('click', function () {
        ui.api('POST', bell.dataset.readUrl + '/read-all').then(function () {
            setCount(0);
            list.querySelectorAll('.is-unread').forEach(function (item) {
                item.classList.remove('is-unread');
            });
        }).catch(function () {});
    });

    /* ---------- Sound (soft two-note chime, like a chat app) ---------- */

    const soundButton = bell.querySelector('[data-bell-sound]');
    let audio = null;
    let lastUnread = null;

    function soundOn() {
        try {
            return window.localStorage.getItem('pinnacle-sound') !== 'off';
        } catch (error) {
            return true;
        }
    }

    function showSoundState() {
        soundButton.textContent = soundOn() ? '🔔 Sound on' : '🔕 Sound off';
        soundButton.setAttribute('aria-pressed', soundOn() ? 'true' : 'false');
    }

    // Browsers allow sound only after the user has clicked or typed on the page once.
    function chime(isUrgent) {
        const Context = window.AudioContext || window.webkitAudioContext;

        if (!soundOn() || !Context) {
            return;
        }

        audio = audio || new Context();

        if (audio.state === 'suspended') {
            audio.resume().catch(function () {});
        }

        (isUrgent ? [880, 660, 880] : [660, 880]).forEach(function (frequency, index) {
            const start = audio.currentTime + index * 0.12;
            const tone = audio.createOscillator();
            const volume = audio.createGain();

            tone.type = 'sine';
            tone.frequency.value = frequency;
            volume.gain.setValueAtTime(0.0001, start);
            volume.gain.exponentialRampToValueAtTime(0.12, start + 0.02);
            volume.gain.exponentialRampToValueAtTime(0.0001, start + 0.22);
            tone.connect(volume).connect(audio.destination);
            tone.start(start);
            tone.stop(start + 0.25);
        });
    }

    window.PinnacleSound = { play: chime };

    soundButton.addEventListener('click', function (event) {
        event.stopPropagation();

        try {
            window.localStorage.setItem('pinnacle-sound', soundOn() ? 'off' : 'on');
        } catch (error) {
            // Private mode: the setting lasts for this page only.
        }

        showSoundState();
        chime(false);
    });

    function refreshCount() {
        return ui.api('GET', bell.dataset.summaryUrl).then(function (envelope) {
            // Without the live server, a higher unread count since the last check still chimes.
            if (lastUnread !== null && envelope.data.unread > lastUnread && !echo) {
                chime(false);
            }

            lastUnread = envelope.data.unread;
            setCount(envelope.data.unread);
        });
    }

    const echo = window.PinnacleRealtime && window.PinnacleRealtime.echo;
    const me = document.querySelector('meta[name="user-id"]');

    if (echo && me) {
        echo.private('App.Models.User.' + me.content)
            .listen('.notification.created', function (event) {
                chime(Boolean(event.is_urgent));
                lastUnread = (lastUnread || 0) + 1;
                setCount(lastUnread);

                if (!panel.hidden) {
                    load().catch(function () {});
                }
            })
            .listen('.conversation.updated', function (event) {
                if (event.is_new && !event.is_muted && String(event.sender_id) !== me.content) {
                    chime(false);
                }
            });
    } else {
        window.setInterval(function () { refreshCount().catch(function () {}); }, 60000);
    }

    showSoundState();
    refreshCount().catch(function () {});
})();

/**
 * Dashboard shell interactions
 * Sidebar (mobile drawer + desktop collapse), account dropdown, flash banner,
 * navigation progress, click effects (shine, ripple, ink), animated counters
 * and toast notifications. No dependencies.
 */
(function () {
    'use strict';

    const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)');
    const MOBILE_QUERY = window.matchMedia('(max-width: 1023px)');
    const SIDEBAR_COOKIE = 'dash_sidebar';
    const BANNER_LIFETIME = 6000;
    const TOAST_LIFETIME = 4500;
    const FX_HOSTS = '.dash-btn, .dash-nav-link, .dash-account, .dash-dropdown-link, .dash-icon-btn, .dash-stat, .dash-kpi, .dash-quick-tile, .dash-feature, .dash-banner-close, .dash-action, .dash-page-link, .dash-role-option';
    const ERASE_DURATION = 950;
    const NO_INK_TARGETS = 'input, textarea, select, [contenteditable="true"]';

    document.addEventListener('DOMContentLoaded', function () {
        initSidebarDrawer();
        initSidebarCollapse();
        initDropdowns();
        initBanners();
        initNavigationProgress();
        initClickEffects();
        initCountUp();
        initDeleteConfirmation();
        initAutoSubmit();
        initLiveClock();
    });

    function initLiveClock() {
        const clock = document.querySelector('[data-live-clock]');

        if (!clock) {
            return;
        }

        const timeZone = clock.dataset.timezone || undefined;
        const timeFormat = new Intl.DateTimeFormat('en-US', { hour: '2-digit', minute: '2-digit', timeZone: timeZone });
        const dateFormat = new Intl.DateTimeFormat('en-GB', { weekday: 'long', day: '2-digit', month: 'long', timeZone: timeZone });
        const timeElement = clock.querySelector('[data-clock-time]');
        const dateElement = clock.querySelector('[data-clock-date]');

        const render = function () {
            const now = new Date();
            timeElement.textContent = timeFormat.format(now);
            dateElement.textContent = dateFormat.format(now).replace(',', '');
        };

        render();
        window.setInterval(render, 15000);
    }

    /**
     * Forms marked data-confirm-delete open the eraser modal first; once confirmed,
     * the row (or data-erase-target) is wiped away before the form is submitted.
     */
    /**
     * Forms marked data-confirm-delete ask first; once confirmed, the row (or data-erase-target)
     * is wiped away before the form is submitted.
     */
    function initDeleteConfirmation() {
        document.addEventListener('submit', function (event) {
            const form = event.target.closest('form[data-confirm-delete]');

            if (!form || form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            confirmErase(form.dataset.confirmName || 'This record').then(function (accepted) {
                if (!accepted) {
                    return;
                }

                form.dataset.confirmed = 'true';
                const target = form.dataset.eraseTarget ? document.querySelector(form.dataset.eraseTarget) : form.closest('tr');

                eraseElement(target).then(function () {
                    form.requestSubmit();
                });
            });
        }, true);
    }

    /**
     * Opens the eraser modal; resolves true when the user confirms.
     */
    function confirmErase(recordName) {
        const modal = document.getElementById('dashConfirmModal');

        if (!modal || typeof modal.showModal !== 'function') {
            return Promise.resolve(window.confirm('Delete "' + recordName + '"?'));
        }

        const reason = modal.querySelector('[data-confirm-reason]');

        modal.querySelector('[data-confirm-message]').textContent = '"' + recordName + '" will be erased from the list. It is kept in the deleted-records register.';
        reason.value = '';

        return new Promise(function (resolve) {
            const accept = modal.querySelector('[data-confirm-accept]');
            const cancel = modal.querySelector('[data-confirm-cancel]');

            const finish = function (result) {
                accept.removeEventListener('click', onAccept);
                cancel.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                modal.removeEventListener('cancel', onCancel);
                modal.close();
                resolve(result);
            };
            const onAccept = function () { finish({ reason: reason.value.trim() }); };
            const onCancel = function () { finish(false); };
            const onBackdrop = function (event) {
                if (event.target === modal) {
                    finish(false);
                }
            };

            accept.addEventListener('click', onAccept);
            cancel.addEventListener('click', onCancel);
            modal.addEventListener('click', onBackdrop);
            modal.addEventListener('cancel', onCancel);
            modal.showModal();
            reason.focus();
        });
    }

    /**
     * A small eraser rubs across the element from left to right; the element is wiped
     * away behind it and a few eraser crumbs fall. Resolves when the wipe is done.
     */
    function eraseElement(element) {
        if (!element || REDUCED_MOTION.matches) {
            return Promise.resolve();
        }

        const rect = element.getBoundingClientRect();
        const eraser = document.createElement('span');
        const travel = Math.max(rect.width - 18, 0);
        const strokes = 11;
        const reach = Math.max(Math.min(rect.height / 2 - 9, 14), 4);
        const frames = [];

        eraser.className = 'dash-eraser-sweep';
        eraser.setAttribute('aria-hidden', 'true');
        eraser.innerHTML = '<svg viewBox="0 0 30 18" width="30" height="18"><rect x="1" y="2" width="28" height="14" rx="3" class="dash-eraser-body"/><rect x="1" y="2" width="11" height="14" rx="3" class="dash-eraser-sleeve"/><path d="M12 2v14" class="dash-eraser-seam"/></svg>';
        eraser.style.left = rect.left + 'px';
        eraser.style.top = (rect.top + rect.height / 2 - 9) + 'px';
        document.body.appendChild(eraser);

        // Hand-like scrubbing: each stroke pushes forward, rubs up or down across the row's
        // height with a tilt, then slides back a little before the next stroke.
        frames.push({ transform: 'translate(0, 0) rotate(0deg)', offset: 0 });

        for (let stroke = 1; stroke <= strokes; stroke += 1) {
            const ahead = travel * stroke / strokes;
            const lift = stroke % 2 === 0 ? reach : -reach;
            const progress = stroke / strokes;

            frames.push({ transform: 'translate(' + ahead + 'px, ' + lift + 'px) rotate(' + (lift > 0 ? 14 : -14) + 'deg)', offset: progress - 0.5 / strokes });
            frames.push({ transform: 'translate(' + Math.max(ahead - travel * 0.035, 0) + 'px, ' + (lift * 0.35) + 'px) rotate(' + (lift > 0 ? -4 : 4) + 'deg)', offset: progress });
        }

        element.classList.add('is-erasing');

        const sweep = eraser.animate(frames, { duration: ERASE_DURATION, easing: 'ease-in-out', fill: 'forwards' });

        for (let crumb = 0; crumb < 7; crumb += 1) {
            window.setTimeout(function () {
                dropCrumb(rect.left + travel * (crumb + 0.5) / 7 + 12, rect.top + rect.height / 2 + (crumb % 2 === 0 ? reach : -reach / 2));
            }, ERASE_DURATION * (crumb + 0.5) / 7);
        }

        return sweep.finished.catch(function () {}).then(function () {
            eraser.animate([{ opacity: 1 }, { opacity: 0, transform: frames[frames.length - 1].transform + ' translateY(-6px)' }], { duration: 160, fill: 'forwards' })
                .finished.catch(function () {}).then(function () {
                    eraser.remove();
                });
        });
    }

    /**
     * Confirm (with a reason), send the DELETE, then erase the element only when the record was
     * really deleted. Non-approvers get "sent for approval" and the row stays.
     * Resolves 'deleted', 'pending' or null (cancelled / failed).
     */
    function eraseRecord(recordName, element, url) {
        return confirmErase(recordName).then(function (answer) {
            if (!answer) {
                return null;
            }

            return api('DELETE', url, { reason: answer.reason || null })
                .then(function (envelope) {
                    if (envelope.data && envelope.data.status === 'pending') {
                        showToast(envelope.message);

                        return 'pending';
                    }

                    return eraseElement(element).then(function () {
                        showToast(envelope.message);

                        return 'deleted';
                    });
                })
                .catch(function (envelope) {
                    const fields = envelope && envelope.errors && envelope.errors.fields;
                    const firstField = fields ? fields[Object.keys(fields)[0]][0] : null;

                    element.classList.remove('is-erasing');
                    showToast(firstField || (envelope && envelope.message) || 'Could not delete.', 'error');

                    return null;
                });
        });
    }

    /**
     * "Please wait" paper card with a gently swinging clock, for slow work (generating cards,
     * challans, PDFs). Returns a function that hides it. Shown only if the work takes > 250 ms.
     */
    function showBusy(message) {
        const overlay = document.createElement('div');
        let shown = false;

        overlay.className = 'dash-busy';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML = '<div class="dash-busy-card">'
            + '<svg class="dash-busy-clock" viewBox="0 0 64 64" width="64" height="64" aria-hidden="true">'
            + '<path class="dash-busy-bell" d="M14 14 8 8M50 14l6-6"/>'
            + '<circle class="dash-busy-face" cx="32" cy="34" r="22"/>'
            + '<path class="dash-busy-tick" d="M32 15v3M32 50v3M13 34h3M48 34h3"/>'
            + '<line class="dash-busy-hour" x1="32" y1="34" x2="32" y2="24"/>'
            + '<line class="dash-busy-minute" x1="32" y1="34" x2="32" y2="18"/>'
            + '<circle class="dash-busy-pin" cx="32" cy="34" r="2.2"/>'
            + '</svg>'
            + '<p class="dash-busy-text"></p><p class="dash-busy-sub">This can take a few seconds…</p></div>';
        overlay.querySelector('.dash-busy-text').textContent = message || 'Working…';

        const timer = window.setTimeout(function () {
            shown = true;
            document.body.appendChild(overlay);
        }, 250);

        return function hide() {
            window.clearTimeout(timer);

            if (shown) {
                overlay.classList.add('is-leaving');
                window.setTimeout(function () { overlay.remove(); }, 180);
            }
        };
    }

    /**
     * Fetch a file (PDF) with the busy card showing, then open it in a new tab.
     */
    function openFile(url, message) {
        const hide = showBusy(message);
        // Open the tab now, while the click still counts as a user action, so popup blockers allow it.
        const tab = window.open('', '_blank');

        if (tab) {
            tab.document.title = message || 'Preparing…';
            tab.document.body.innerHTML = '<p style="font-family: system-ui, sans-serif; padding: 2rem; color: #374151;">' + (message || 'Preparing…') + '</p>';
        }

        return window.fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/pdf, application/json', 'X-Platform': 'web' } })
            .then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () { return {}; }).then(function (body) {
                        return Promise.reject(body);
                    });
                }

                return response.blob();
            })
            .then(function (blob) {
                const link = URL.createObjectURL(blob);

                if (tab) {
                    tab.location.href = link;
                } else {
                    window.location.href = link;
                }

                window.setTimeout(function () { URL.revokeObjectURL(link); }, 60000);
            })
            .catch(function (body) {
                if (tab) {
                    tab.close();
                }

                showToast((body && body.message) || 'Could not prepare the file.', 'error');

                return Promise.reject(body);
            })
            .finally(hide);
    }

    function dropCrumb(x, y) {
        const crumb = document.createElement('span');

        crumb.className = 'dash-eraser-crumb';
        crumb.style.left = x + 'px';
        crumb.style.top = y + 'px';
        document.body.appendChild(crumb);
        crumb.animate([
            { transform: 'translate(0, 0) scale(1)', opacity: 0.9 },
            { transform: 'translate(' + (Math.random() * 10 - 5) + 'px, 14px) scale(0.6)', opacity: 0 }
        ], { duration: 420, easing: 'ease-in', fill: 'forwards' }).finished.catch(function () {}).then(function () {
            crumb.remove();
        });
    }

    /**
     * Calls the ERP API with the session cookie + CSRF token and returns the standard envelope.
     * Rejects with the envelope when success is false (validation errors are in envelope.errors.fields).
     */
    function api(method, url, body) {
        const token = document.querySelector('meta[name="csrf-token"]');
        const isUpload = body instanceof window.FormData;
        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token ? token.content : '',
            'X-Platform': 'web'
        };

        // File uploads let the browser set the multipart boundary itself.
        if (!isUpload) {
            headers['Content-Type'] = 'application/json';
        }

        return window.fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: headers,
            body: body === undefined ? undefined : (isUpload ? body : JSON.stringify(body))
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, message: 'Unexpected server response (' + response.status + ').', errors: null };
            });
        }).then(function (envelope) {
            if (!envelope.success) {
                return Promise.reject(envelope);
            }

            return envelope;
        });
    }

    function initAutoSubmit() {
        document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
            field.addEventListener('change', function () {
                field.form.requestSubmit();
            });
        });
    }

    function initSidebarDrawer() {
        const sidebar = document.getElementById('dashSidebar');
        const toggle = document.querySelector('[data-sidebar-toggle]');

        if (!sidebar || !toggle) {
            return;
        }

        const setOpen = function (isOpen) {
            sidebar.classList.toggle('is-open', isOpen);
            document.body.classList.toggle('is-scroll-locked', isOpen);
            toggle.setAttribute('aria-expanded', String(isOpen));

            if (isOpen) {
                const activeLink = sidebar.querySelector('.dash-nav-link.is-active') || sidebar.querySelector('.dash-nav-link');

                if (activeLink) {
                    activeLink.focus({ preventScroll: true });
                }
            }
        };

        toggle.addEventListener('click', function () {
            setOpen(!sidebar.classList.contains('is-open'));
        });

        document.querySelectorAll('[data-sidebar-close]').forEach(function (element) {
            element.addEventListener('click', function () {
                setOpen(false);
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
            }
        });

        MOBILE_QUERY.addEventListener('change', function (event) {
            if (!event.matches) {
                setOpen(false);
            }
        });
    }

    function initSidebarCollapse() {
        const collapseButton = document.querySelector('[data-sidebar-collapse]');

        if (!collapseButton) {
            return;
        }

        const syncLabel = function () {
            const isCollapsed = document.body.classList.contains('is-sidebar-collapsed');
            collapseButton.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            collapseButton.setAttribute('aria-expanded', String(!isCollapsed));
        };

        syncLabel();

        collapseButton.addEventListener('click', function () {
            const isCollapsed = document.body.classList.toggle('is-sidebar-collapsed');
            document.cookie = SIDEBAR_COOKIE + '=' + (isCollapsed ? 'collapsed' : 'expanded') + ';path=/;max-age=31536000;SameSite=Lax';
            syncLabel();
        });
    }

    function initDropdowns() {
        const dropdowns = document.querySelectorAll('[data-dropdown]');

        dropdowns.forEach(function (dropdown) {
            const trigger = dropdown.querySelector('[data-dropdown-trigger]');

            if (!trigger) {
                return;
            }

            trigger.addEventListener('click', function (event) {
                event.stopPropagation();
                setDropdownOpen(dropdown, !dropdown.classList.contains('is-open'));
            });
        });

        document.addEventListener('click', function (event) {
            dropdowns.forEach(function (dropdown) {
                if (!dropdown.contains(event.target)) {
                    setDropdownOpen(dropdown, false);
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            dropdowns.forEach(function (dropdown) {
                if (dropdown.classList.contains('is-open')) {
                    setDropdownOpen(dropdown, false);
                    dropdown.querySelector('[data-dropdown-trigger]').focus();
                }
            });
        });
    }

    function setDropdownOpen(dropdown, isOpen) {
        dropdown.classList.toggle('is-open', isOpen);
        dropdown.querySelector('[data-dropdown-trigger]').setAttribute('aria-expanded', String(isOpen));
    }

    function initBanners() {
        document.querySelectorAll('[data-banner]').forEach(function (banner) {
            const closeButton = banner.querySelector('[data-banner-close]');

            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    dismiss(banner);
                });
            }

            window.setTimeout(function () {
                dismiss(banner);
            }, BANNER_LIFETIME);
        });
    }

    function dismiss(element) {
        if (!element || element.classList.contains('is-leaving')) {
            return;
        }

        if (REDUCED_MOTION.matches) {
            element.remove();

            return;
        }

        element.classList.add('is-leaving');
        element.addEventListener('animationend', function () {
            element.remove();
        }, { once: true });
    }

    function initNavigationProgress() {
        document.addEventListener('click', function (event) {
            const link = event.target.closest('a[href]');

            if (link && isSamePageNavigation(link, event)) {
                document.body.classList.add('is-navigating');
            }
        });

        document.addEventListener('submit', function () {
            document.body.classList.add('is-navigating');
        });

        window.addEventListener('pageshow', function () {
            document.body.classList.remove('is-navigating');
        });
    }

    function isSamePageNavigation(link, event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return false;
        }

        if ((link.target && link.target !== '_self') || link.hasAttribute('download') || link.origin !== window.location.origin) {
            return false;
        }

        return !(link.pathname === window.location.pathname && link.hash);
    }

    function initClickEffects() {
        document.addEventListener('pointerdown', function (event) {
            if (REDUCED_MOTION.matches || event.button !== 0) {
                return;
            }

            const host = event.target.closest(FX_HOSTS);

            if (host) {
                playPressEffect(host, event);
            }

            if (!event.target.closest(NO_INK_TARGETS)) {
                dropInk(event.clientX, event.clientY);
            }
        });
    }

    function playPressEffect(host, event) {
        const layer = getEffectLayer(host);
        const bounds = host.getBoundingClientRect();
        const size = Math.max(bounds.width, bounds.height) * 2;

        const ripple = document.createElement('span');
        ripple.className = 'dash-ripple';
        ripple.style.width = size + 'px';
        ripple.style.height = size + 'px';
        ripple.style.left = (event.clientX - bounds.left - size / 2) + 'px';
        ripple.style.top = (event.clientY - bounds.top - size / 2) + 'px';

        const shine = document.createElement('span');
        shine.className = 'dash-shine';

        layer.append(ripple, shine);
        removeAfterAnimation(ripple);
        removeAfterAnimation(shine);
    }

    function getEffectLayer(host) {
        let layer = host.querySelector(':scope > .dash-fx-layer');

        if (layer) {
            return layer;
        }

        if (window.getComputedStyle(host).position === 'static') {
            host.style.position = 'relative';
        }

        layer = document.createElement('span');
        layer.className = 'dash-fx-layer';
        layer.setAttribute('aria-hidden', 'true');
        host.appendChild(layer);

        return layer;
    }

    function dropInk(x, y) {
        const ink = document.createElement('span');
        ink.className = 'dash-ink';
        ink.style.left = x + 'px';
        ink.style.top = y + 'px';
        document.body.appendChild(ink);
        removeAfterAnimation(ink);
    }

    function removeAfterAnimation(element) {
        element.addEventListener('animationend', function () {
            element.remove();
        }, { once: true });
    }

    function initCountUp() {
        const counters = document.querySelectorAll('[data-count-up]');

        if (!counters.length || REDUCED_MOTION.matches || !('IntersectionObserver' in window)) {
            return;
        }

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    animateCounter(entry.target);
                }
            });
        }, { threshold: 0.4 });

        counters.forEach(function (counter) {
            observer.observe(counter);
        });
    }

    function animateCounter(element) {
        const target = Number(element.getAttribute('data-count-up'));

        if (!Number.isFinite(target) || target <= 0) {
            return;
        }

        const duration = 900;
        const formatter = new Intl.NumberFormat();
        let startTime = null;

        const step = function (timestamp) {
            if (startTime === null) {
                startTime = timestamp;
            }

            const progress = Math.min((timestamp - startTime) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = formatter.format(Math.round(target * eased));

            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };

        element.textContent = '0';
        window.requestAnimationFrame(step);
    }

    /**
     * Show a toast from page scripts: PinnacleDashboard.toast('Saved', 'success').
     */
    function showToast(message, type) {
        const container = document.getElementById('toastContainer');

        if (!container) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'dash-toast' + (type === 'error' ? ' dash-toast-error' : '');
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        container.appendChild(toast);

        window.setTimeout(function () {
            dismiss(toast);
        }, TOAST_LIFETIME);
    }

    window.PinnacleDashboard = { toast: showToast, confirm: confirmErase, erase: eraseElement, eraseRecord: eraseRecord, busy: showBusy, openFile: openFile, api: api };
})();

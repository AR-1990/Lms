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
    const ERASE_DURATION = 520;
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
    function initDeleteConfirmation() {
        const modal = document.getElementById('dashConfirmModal');

        if (!modal || typeof modal.showModal !== 'function') {
            return;
        }

        const message = modal.querySelector('[data-confirm-message]');
        let pendingForm = null;

        document.addEventListener('submit', function (event) {
            const form = event.target.closest('form[data-confirm-delete]');

            if (!form || form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            pendingForm = form;
            message.textContent = '"' + (form.dataset.confirmName || 'This record') + '" will be permanently erased. This cannot be undone.';
            modal.showModal();
            modal.querySelector('[data-confirm-cancel]').focus();
        }, true);

        modal.querySelector('[data-confirm-cancel]').addEventListener('click', function () {
            pendingForm = null;
            modal.close();
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                pendingForm = null;
                modal.close();
            }
        });

        modal.querySelector('[data-confirm-accept]').addEventListener('click', function () {
            const form = pendingForm;

            if (!form) {
                return;
            }

            modal.close();
            form.dataset.confirmed = 'true';

            const target = form.dataset.eraseTarget ? document.querySelector(form.dataset.eraseTarget) : form.closest('tr');

            if (!target || REDUCED_MOTION.matches) {
                form.requestSubmit();

                return;
            }

            target.classList.add('is-erasing');
            window.setTimeout(function () {
                form.requestSubmit();
            }, ERASE_DURATION);
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

    window.PinnacleDashboard = { toast: showToast };
})();

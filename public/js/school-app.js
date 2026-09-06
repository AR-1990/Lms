/**
 * ==============================================================================
 * Pinnacle International Academy - Frontend Engine & Interactive Scripts
 * Sliders, Progress Timers, Scroll Reveals, Fee Calculators & Animated Login
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Interactive Hero Slider with Progress Bar
    initHeroSlider();

    // 2. Testimonials Slider Carousel
    initTestimonialsSlider();

    // 3. Scroll Reveal Animations (Intersection Observer)
    initScrollReveal();

    // 4. Mobile Drawer Navigation
    initMobileNavigation();

    // 5. Sticky Header Elevation
    initStickyHeader();

    // 6. Animated Statistics Counters
    initAnimatedCounters();

    // 7. Interactive Tabs Switcher
    initTabs();

    // 8. FAQ Accordion Items
    initAccordions();

    // 9. Interactive Dynamic Range-Slider Fee Calculator
    initFeeCalculator();

    // 10. Interactive Inquiry Form Submission
    initInquiryForms();

    // 11. Interactive Luxury Login Portal Engine
    initLoginPage();
});

/**
 * 1. Interactive Multi-Slide Hero Slider with Progress Timer
 */
function initHeroSlider() {
    const slider = document.querySelector('.hero-slider-wrapper') || document.querySelector('.hero-slider');
    if (!slider) return;

    const slides = slider.querySelectorAll('.hero-slide');
    const prevBtn = document.getElementById('heroPrevBtn');
    const nextBtn = document.getElementById('heroNextBtn');
    const dotsContainer = document.getElementById('heroSliderDots');
    const progressBar = document.getElementById('heroProgressBar');

    if (!slides.length) return;

    let currentIndex = 0;
    const slideInterval = 5500; // 5.5 seconds per slide
    let progressInterval = null;
    let progress = 0;

    // Create pagination dots
    if (dotsContainer) {
        dotsContainer.innerHTML = '';
        slides.forEach((_, idx) => {
            const dot = document.createElement('button');
            dot.className = `slider-dot ${idx === 0 ? 'active' : ''}`;
            dot.setAttribute('aria-label', `Go to slide ${idx + 1}`);
            dot.addEventListener('click', () => {
                goToSlide(idx);
                resetTimer();
            });
            dotsContainer.appendChild(dot);
        });
    }

    function updateDots() {
        if (!dotsContainer) return;
        const dots = dotsContainer.querySelectorAll('.slider-dot');
        dots.forEach((dot, idx) => {
            if (idx === currentIndex) {
                dot.classList.add('active');
            } else {
                dot.classList.remove('active');
            }
        });
    }

    function goToSlide(index) {
        slides.forEach(s => s.classList.remove('active'));
        currentIndex = (index + slides.length) % slides.length;
        slides[currentIndex].classList.add('active');
        updateDots();
        progress = 0;
        if (progressBar) progressBar.style.width = '0%';
    }

    function nextSlide() {
        goToSlide(currentIndex + 1);
    }

    function prevSlide() {
        goToSlide(currentIndex - 1);
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            nextSlide();
            resetTimer();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            prevSlide();
            resetTimer();
        });
    }

    function startTimer() {
        progress = 0;
        const tick = 50; // update every 50ms
        const step = (tick / slideInterval) * 100;

        progressInterval = setInterval(() => {
            progress += step;
            if (progressBar) {
                progressBar.style.width = `${Math.min(progress, 100)}%`;
            }
            if (progress >= 100) {
                nextSlide();
                progress = 0;
            }
        }, tick);
    }

    function resetTimer() {
        clearInterval(progressInterval);
        startTimer();
    }

    // Pause slider on mouse hover
    slider.addEventListener('mouseenter', () => {
        clearInterval(progressInterval);
    });

    slider.addEventListener('mouseleave', () => {
        startTimer();
    });

    // Touch swipe gesture support for mobile
    let touchStartX = 0;
    let touchEndX = 0;

    slider.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });

    slider.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        if (touchStartX - touchEndX > 50) {
            nextSlide();
            resetTimer();
        } else if (touchEndX - touchStartX > 50) {
            prevSlide();
            resetTimer();
        }
    }, { passive: true });

    startTimer();
}

/**
 * 2. Testimonials Carousel Slider
 */
function initTestimonialsSlider() {
    const container = document.querySelector('.testimonial-slider-container');
    if (!container) return;

    const track = container.querySelector('.testimonial-track');
    const slides = container.querySelectorAll('.testimonial-slide-item');
    const prevBtn = document.getElementById('testPrevBtn');
    const nextBtn = document.getElementById('testNextBtn');

    if (!track || !slides.length) return;

    let index = 0;

    function showSlide(i) {
        index = (i + slides.length) % slides.length;
        track.style.transform = `translateX(-${index * 100}%)`;
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => showSlide(index + 1));
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', () => showSlide(index - 1));
    }

    // Auto rotate every 6.5 seconds
    setInterval(() => {
        showSlide(index + 1);
    }, 6500);
}

/**
 * 3. Scroll-Reveal Animations (Intersection Observer)
 */
function initScrollReveal() {
    const revealElements = document.querySelectorAll('[data-reveal]');
    if (!revealElements.length) return;

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-revealed');
                obs.unobserve(entry.target);
            }
        });
    }, {
        rootMargin: '0px 0px -50px 0px',
        threshold: 0.15
    });

    revealElements.forEach(el => observer.observe(el));
}

/**
 * 4. Mobile Navigation Drawer & Overlay
 */
function initMobileNavigation() {
    const toggleBtn = document.getElementById('mobileMenuToggle') || document.getElementById('menuToggle');
    const drawer = document.getElementById('mobileDrawer');
    const overlay = document.getElementById('mobileOverlay');
    const closeBtn = document.getElementById('drawerCloseBtn') || document.getElementById('drawerClose');

    if (!toggleBtn || !drawer || !overlay) return;

    function openDrawer() {
        drawer.classList.add('is-open');
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    toggleBtn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    overlay.addEventListener('click', closeDrawer);

    const drawerLinks = drawer.querySelectorAll('a');
    drawerLinks.forEach(link => link.addEventListener('click', closeDrawer));
}

/**
 * 5. Sticky Header elevation on scroll
 */
function initStickyHeader() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 20) {
            header.classList.add('is-scrolled');
        } else {
            header.classList.remove('is-scrolled');
        }
    }, { passive: true });
}

/**
 * 6. Animated Number Counters
 */
function initAnimatedCounters() {
    const counterElements = document.querySelectorAll('[data-counter]');
    if (!counterElements.length) return;

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.getAttribute('data-target') || el.innerText, 10);
                const suffix = el.getAttribute('data-suffix') || '';
                const prefix = el.getAttribute('data-prefix') || '';
                const duration = 1800;
                const start = 0;
                const startTime = performance.now();

                function update(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                    const currentVal = Math.floor(start + (target - start) * easeProgress);

                    el.textContent = `${prefix}${currentVal.toLocaleString()}${suffix}`;

                    if (progress < 1) {
                        requestAnimationFrame(update);
                    } else {
                        el.textContent = `${prefix}${target.toLocaleString()}${suffix}`;
                    }
                }

                requestAnimationFrame(update);
                obs.unobserve(el);
            }
        });
    }, { threshold: 0.2 });

    counterElements.forEach(el => observer.observe(el));
}

/**
 * 7. Tabs Switcher Component
 */
function initTabs() {
    const tabGroups = document.querySelectorAll('[data-tabs]');

    tabGroups.forEach(group => {
        const buttons = group.querySelectorAll('.tab-btn');
        const parentContainer = group.closest('.container') || document;
        const panes = parentContainer.querySelectorAll('.tab-pane');

        buttons.forEach(btn => {
            btn.addEventListener('click', function () {
                const tabId = this.getAttribute('data-tab-target') || this.getAttribute('data-tab');

                buttons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                if (panes.length) {
                    panes.forEach(pane => {
                        if (pane.id === tabId || pane.getAttribute('data-pane') === tabId) {
                            pane.classList.add('active');
                        } else {
                            pane.classList.remove('active');
                        }
                    });
                }
            });
        });
    });
}

/**
 * 8. FAQ Accordion Items
 */
function initAccordions() {
    const accordions = document.querySelectorAll('.faq-item');

    accordions.forEach(item => {
        const trigger = item.querySelector('.faq-trigger');
        if (!trigger) return;

        trigger.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');
            const parent = item.closest('.faq-list');
            if (parent) {
                parent.querySelectorAll('.faq-item').forEach(sibling => {
                    sibling.classList.remove('is-open');
                });
            }

            if (!isOpen) {
                item.classList.add('is-open');
            }
        });
    });
}

/**
 * 9. Interactive Dynamic Range-Slider Fee Calculator
 */
function initFeeCalculator() {
    const rangeInput = document.getElementById('calcGradeRange');
    const calcGradeSelect = document.getElementById('calcGrade');
    const calcTransport = document.getElementById('calcTransport');
    const calcSibling = document.getElementById('calcSibling');

    if (!rangeInput && !calcGradeSelect) return;

    const gradesMeta = [
        { key: 'preschool', name: 'Early Years / Montessori (Ages 3-5)', tuition: 8500, reg: 15000 },
        { key: 'primary',   name: 'Primary Wing (Grades 1 to 5)',      tuition: 11000, reg: 18000 },
        { key: 'middle',    name: 'Middle Wing (Grades 6 to 8)',       tuition: 13500, reg: 20000 },
        { key: 'secondary', name: 'Matric / O-Levels (Grades 9 & 10)', tuition: 16500, reg: 22000 },
        { key: 'hssc',      name: 'College / HSSC (Grades 11 & 12)',   tuition: 19500, reg: 25000 },
    ];

    function calculate() {
        let selectedIndex = 1; // Primary default

        if (rangeInput) {
            selectedIndex = parseInt(rangeInput.value, 10);
        } else if (calcGradeSelect) {
            const val = calcGradeSelect.value;
            const found = gradesMeta.findIndex(g => g.key === val);
            if (found !== -1) selectedIndex = found;
        }

        const grade = gradesMeta[selectedIndex] || gradesMeta[1];

        // Update Grade Label
        const gradeNameEl = document.getElementById('calcSelectedGradeName');
        if (gradeNameEl) gradeNameEl.innerText = grade.name;

        let tuition = grade.tuition;
        let reg = grade.reg;
        let transport = calcTransport && calcTransport.checked ? 3500 : 0;
        let siblingRebate = 0;

        if (calcSibling && calcSibling.checked) {
            siblingRebate = Math.round(tuition * 0.15); // 15% off tuition
        }

        const netMonthly = (tuition - siblingRebate) + transport;

        // UI Outputs
        const tuitionEl = document.getElementById('calcTuitionOutput');
        const regEl = document.getElementById('calcRegOutput');
        const discountEl = document.getElementById('calcDiscountOutput');
        const transportEl = document.getElementById('calcTransportOutput');
        const totalEl = document.getElementById('calcTotalOutput');

        if (tuitionEl) tuitionEl.innerText = `Rs. ${tuition.toLocaleString()}/mo`;
        if (regEl) regEl.innerText = `Rs. ${regFeeOutput(reg)}`;
        if (discountEl) discountEl.innerText = siblingRebate > 0 ? `- Rs. ${siblingRebate.toLocaleString()}` : 'Rs. 0';
        if (transportEl) transportEl.innerText = transport > 0 ? `+ Rs. ${transport.toLocaleString()}` : 'Not included';
        if (totalEl) totalEl.innerText = `Rs. ${netMonthly.toLocaleString()}/month`;
    }

    function regFeeOutput(fee) {
        return `Rs. ${fee.toLocaleString()} (One-time)`;
    }

    if (rangeInput) rangeInput.addEventListener('input', calculate);
    if (calcGradeSelect) calcGradeSelect.addEventListener('change', calculate);
    if (calcTransport) calcTransport.addEventListener('change', calculate);
    if (calcSibling) calcSibling.addEventListener('change', calculate);

    calculate();
}

/**
 * 10. Interactive Inquiry Form Submission
 */
function initInquiryForms() {
    const forms = document.querySelectorAll('form[data-ajax-inquiry]');

    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Submit';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Sending Inquiry...';
            }

            setTimeout(() => {
                showToast('Inquiry Received! Our admissions counselor will contact you within 24 hours.', 'success');
                form.reset();

                const rangeInput = document.getElementById('calcGradeRange');
                if (rangeInput) rangeInput.dispatchEvent(new Event('input'));

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }, 700);
        });
    });
}

/**
 * 11. Interactive Luxury Login Portal Engine
 */
function initLoginPage() {
    const loginForm = document.getElementById('portalLoginForm');
    if (!loginForm) return;

    const roleChips = document.querySelectorAll('.role-chip');
    const roleInput = document.getElementById('loginRoleInput');
    const usernameLabel = document.getElementById('loginUsernameLabel');
    const usernameInput = document.getElementById('loginUsernameInput');
    const passwordInput = document.getElementById('loginPasswordInput');
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const roleDescEl = document.getElementById('loginRoleDescription');
    const demoBtns = document.querySelectorAll('.demo-chip-btn');

    const roleConfig = {
        student: {
            label: 'Student Email / Roll No',
            placeholder: 'e.g. student@lms.test',
            desc: 'Access class schedule, video lectures, homework submissions, and quiz reports.',
            demoUser: 'student@lms.test',
            demoPass: 'password'
        },
        parent: {
            label: 'Parent Email / Mobile',
            placeholder: 'e.g. parent@lms.test',
            desc: 'Real-time attendance alerts, fee challans, online payment & report cards.',
            demoUser: 'parent@lms.test',
            demoPass: 'password'
        },
        teacher: {
            label: 'Faculty Email / ID',
            placeholder: 'e.g. teacher@lms.test',
            desc: 'Digital gradebook, attendance register, lesson planning & student communications.',
            demoUser: 'teacher@lms.test',
            demoPass: 'password'
        },
        accounts: {
            label: 'Accounts Email',
            placeholder: 'e.g. accounts@lms.test',
            desc: 'Fee reconciliations, ledger journals, staff payroll & institutional audit reports.',
            demoUser: 'accounts@lms.test',
            demoPass: 'password'
        }
    };

    // Role Tab Switching
    function setRole(roleKey) {
        if (!roleConfig[roleKey]) return;

        roleChips.forEach(chip => {
            if (chip.getAttribute('data-role') === roleKey) {
                chip.classList.add('active');
            } else {
                chip.classList.remove('active');
            }
        });

        if (roleInput) roleInput.value = roleKey;

        const config = roleConfig[roleKey];
        if (usernameLabel) usernameLabel.innerText = config.label;
        if (usernameInput) usernameInput.placeholder = config.placeholder;
        if (roleDescEl) roleDescEl.innerText = config.desc;
    }

    roleChips.forEach(chip => {
        chip.addEventListener('click', function () {
            const role = this.getAttribute('data-role');
            setRole(role);
        });
    });

    // Sync labels/placeholders with the active role from the server query string.
    const initialRole = roleInput ? roleInput.value : 'student';
    setRole(initialRole);
    // Demo Autofill Buttons
    demoBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const role = this.getAttribute('data-fill-role');
            setRole(role);
            const config = roleConfig[role];
            if (usernameInput) usernameInput.value = config.demoUser;
            if (passwordInput) passwordInput.value = config.demoPass;

            showToast(`Auto-filled demo credentials for ${role.toUpperCase()}`, 'success');
        });
    });

    // Toggle Password Visibility
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';

            togglePasswordBtn.innerHTML = isPassword
                ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        });
    }

    // Allow real form POST to Laravel; only show loading state.
    loginForm.addEventListener('submit', function () {
        const submitBtn = document.getElementById('loginSubmitBtn');
        if (!submitBtn) return;

        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="animate-spin" style="animation: spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
            <span>Signing In...</span>
        `;
    });
}

/**
 * Lightweight Toast Notification Helper
 */
function showToast(message, type = 'success') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icon = type === 'success'
        ? '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>'
        : '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

    toast.innerHTML = `
        <div>${icon}</div>
        <div style="font-size: 0.925rem; font-weight: 600; color: #0f172a;">${message}</div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        toast.style.transition = 'all 0.35s ease';
        setTimeout(() => toast.remove(), 350);
    }, 4500);
}

window.PinnacleSchool = {
    showToast: showToast
};

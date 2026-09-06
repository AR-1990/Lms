@extends('layouts.school')

@section('title', 'Pinnacle International Academy | Inspiring Global Leaders of Tomorrow')
@section('meta_description', 'Discover Pinnacle International Academy. World-class STEM & robotics labs, Cambridge & BISE academic streams, athletic facilities, and holistic character building.')

@section('content')

<!-- Edge-to-Edge Photo-Integrated Multi-Slide Hero Slider -->
<section class="hero-slider-wrapper">
    <!-- Slide 1: Global Leadership -->
    <div class="hero-slide active" data-slide-index="0">
        <div class="hero-slide-bg" style="background-image: url('{{ asset('images/hero-leadership.jpg') }}');"></div>
        <div class="hero-slide-overlay"></div>

        <div class="hero-slide-inner">
            <div class="container">
                <div class="hero-slide-grid">
                    <div>
                        <div class="slide-pill">
                            <span class="slide-pill-dot"></span>
                            <span>Session 2026-27 &bull; Merit Admissions Open</span>
                        </div>

                        <h1 class="slide-title">
                            Inspiring <span class="text-amber-gradient">Global Leaders</span> & Visionary Minds
                        </h1>

                        <p class="slide-desc">
                            Cultivating intellectual curiosity, ethical character, and 21st-century technological mastery. Where young minds discover their true potential and rise to lead the world.
                        </p>

                        <div class="slide-actions">
                            <a href="{{ route('admissions') }}" class="btn btn-primary btn-lg">
                                Apply For Admission
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                            <a href="{{ route('academics') }}" class="btn btn-white btn-lg">
                                Explore Curriculum
                            </a>
                        </div>
                    </div>

                    <!-- Slide 1 Frosted Glass Card -->
                    <div>
                        <div class="slide-visual-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                <span class="badge badge-amber">Academic Excellence</span>
                                <span style="font-size: 0.85rem; color: var(--primary); font-weight: 800;">Est. 1998</span>
                            </div>

                            <div class="slide-metric-row">
                                <div class="slide-metric-icon" style="background: var(--primary-light); color: var(--primary);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                                <div>
                                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--midnight);">100% Scholarships</div>
                                    <div style="font-size: 0.8rem; color: #64748b;">Awarded to top board position holders</div>
                                </div>
                            </div>

                            <div class="slide-metric-row">
                                <div class="slide-metric-icon" style="background: var(--amber-light); color: var(--amber);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                                </div>
                                <div>
                                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--midnight);">Cambridge & BISE</div>
                                    <div style="font-size: 0.8rem; color: #64748b;">Dual international & national pathways</div>
                                </div>
                            </div>

                            <div class="slide-metric-row" style="margin-bottom: 0;">
                                <div class="slide-metric-icon" style="background: var(--emerald-light); color: var(--emerald);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <div>
                                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--midnight);">Safe Smart Campus</div>
                                    <div style="font-size: 0.8rem; color: #64748b;">24/7 Monitored CCTV & RFID buses</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Slide 2: STEM & Robotics Innovation -->
    <div class="hero-slide" data-slide-index="1">
        <div class="hero-slide-bg" style="background-image: url('{{ asset('images/hero-robotics.jpg') }}');"></div>
        <div class="hero-slide-overlay"></div>

        <div class="hero-slide-inner">
            <div class="container">
                <div class="hero-slide-grid">
                    <div>
                        <div class="slide-pill" style="border-color: rgba(234, 88, 12, 0.5); color: #fed7aa;">
                            <span class="slide-pill-dot" style="background-color: #ea580c;"></span>
                            <span>STEM & Future Technologies Hub</span>
                        </div>

                        <h1 class="slide-title">
                            Empowering Innovators with <span class="text-amber-gradient">Robotics & AI</span>
                        </h1>

                        <p class="slide-desc">
                            From Arduino circuitry and 3D prototyping to Python coding, our students build real-world technological solutions that win national championships.
                        </p>

                        <div class="slide-actions">
                            <a href="{{ route('academics') }}#stem-program" class="btn btn-primary btn-lg">
                                Explore STEM Labs
                            </a>
                            <a href="{{ route('campus') }}" class="btn btn-white btn-lg">
                                View Innovation Studio
                            </a>
                        </div>
                    </div>

                    <div>
                        <div class="slide-visual-card">
                            <span class="badge badge-amber" style="margin-bottom: 1rem;">National Olympiad Champions</span>
                            <h3 style="color: var(--midnight); margin-bottom: 0.75rem; font-size: 1.35rem;">Hands-on 21st Century Tech</h3>
                            <p style="color: #64748b; font-size: 0.9rem; line-height: 1.6; margin-bottom: 1.25rem;">
                                Every student from Grade 3 onwards undergoes weekly practical lab projects in coding, mechanics, and design thinking.
                            </p>
                            <div style="background: var(--primary-light); border-radius: 12px; padding: 1rem; border-left: 4px solid var(--primary);">
                                <strong style="color: var(--primary); display: block; margin-bottom: 0.25rem;">150+ Working Prototypes Built Yearly</strong>
                                <p style="font-size: 0.825rem; color: #57534e; margin-bottom: 0;">Showcased annually at the Pinnacle National Science & Tech Fair.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Slide 3: Sports, Arts & Character -->
    <div class="hero-slide" data-slide-index="2">
        <div class="hero-slide-bg" style="background-image: url('{{ asset('images/hero-campus.jpg') }}');"></div>
        <div class="hero-slide-overlay"></div>

        <div class="hero-slide-inner">
            <div class="container">
                <div class="hero-slide-grid">
                    <div>
                        <div class="slide-pill" style="border-color: rgba(245, 158, 11, 0.5); color: #fde68a;">
                            <span class="slide-pill-dot" style="background-color: #f59e0b;"></span>
                            <span>Holistic Development & Athletics</span>
                        </div>

                        <h1 class="slide-title">
                            Championing <span class="text-amber-gradient">Sports, Arts & Values</span>
                        </h1>

                        <p class="slide-desc">
                            Spacious 5-acre sports arena, turf pitch, cricket nets, fine arts studios, and theatrical debate societies nurturing well-rounded champions.
                        </p>

                        <div class="slide-actions">
                            <a href="{{ route('campus') }}" class="btn btn-primary btn-lg">
                                Campus Facilities
                            </a>
                            <a href="{{ route('contact') }}" class="btn btn-white btn-lg">
                                Book a Visit
                            </a>
                        </div>
                    </div>

                    <div>
                        <div class="slide-visual-card">
                            <span class="badge badge-amber" style="margin-bottom: 1rem;">5-Acre Athletic Arena</span>
                            <div class="slide-metric-row">
                                <div class="slide-metric-icon" style="background: var(--amber-light); color: var(--amber);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/></svg>
                                </div>
                                <div>
                                    <div style="font-size: 1.1rem; font-weight: 800; color: var(--midnight);">8+ Sports Disciplines</div>
                                    <div style="font-size: 0.8rem; color: #64748b;">Professional coaching staff</div>
                                </div>
                            </div>
                            <div class="slide-metric-row" style="margin-bottom: 0;">
                                <div class="slide-metric-icon" style="background: var(--primary-light); color: var(--primary);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                </div>
                                <div>
                                    <div style="font-size: 1.1rem; font-weight: 800; color: var(--midnight);">Model UN & Debate Club</div>
                                    <div style="font-size: 0.8rem; color: #64748b;">Oratory & leadership training</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Slider Controls Bar -->
    <div class="slider-controls-bar">
        <div class="container">
            <div class="slider-controls-inner">
                <div class="slider-dots" id="heroSliderDots"></div>

                <div class="slider-progress-track">
                    <div class="slider-progress-bar" id="heroProgressBar"></div>
                </div>

                <div class="slider-nav-btns">
                    <button class="slider-btn" id="heroPrevBtn" aria-label="Previous Slide">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <button class="slider-btn" id="heroNextBtn" aria-label="Next Slide">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Live Announcements Bar -->
<div class="container" style="margin-top: -1.35rem; position: relative; z-index: 25;">
    <div class="notice-pill-bar" data-reveal="fade-up">
        <span class="notice-tag">Notice Board</span>
        <div class="notice-text">
            @foreach($announcements as $notice)
                <span><strong>[{{ $notice['tag'] }}]</strong> {{ $notice['text'] }} &nbsp;&nbsp;&bull;&nbsp;&nbsp;</span>
            @endforeach
        </div>
    </div>
</div>

<!-- Animated Metrics Banner -->
<section class="stats-banner" data-reveal="fade-up">
    <div class="container">
        <div class="stats-grid">
            @foreach($stats as $stat)
                <div class="stat-item">
                    <div class="stat-icon-box">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                        </svg>
                    </div>
                    <div>
                        <div class="stat-number" data-counter data-target="{{ $stat['target'] }}" data-suffix="{{ $stat['suffix'] }}">
                            0{{ $stat['suffix'] }}
                        </div>
                        <div class="stat-label">{{ $stat['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Campus Facilities Photo Showcase -->
<section class="section section-white">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">World-Class Environment</span>
            <h2 class="section-title">Explore Our Modern Campus Facilities</h2>
            <p class="section-desc">Designed with natural light, ergonomic warmth, and cutting-edge educational tools.</p>
        </div>

        <div class="grid-3">
            <!-- Facility 1: Smart Class -->
            <div class="facility-card" data-reveal="fade-up">
                <div class="facility-img-wrapper">
                    <span class="badge badge-amber facility-badge">Interactive Tech</span>
                    <img src="{{ asset('images/facility-smart-class.jpg') }}" alt="Smart Classroom" class="facility-img" loading="lazy">
                </div>
                <div class="facility-body">
                    <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Interactive Smart Classrooms</h4>
                    <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">
                        Equipped with 75" 4K interactive digital screens, climate control, and ergonomic collaborative seating.
                    </p>
                    <a href="{{ route('campus') }}" class="btn btn-sm btn-outline">Explore Classroom</a>
                </div>
            </div>

            <!-- Facility 2: Science Lab -->
            <div class="facility-card" data-reveal="fade-up" style="transition-delay: 0.15s;">
                <div class="facility-img-wrapper">
                    <span class="badge badge-primary facility-badge">Research Suites</span>
                    <img src="{{ asset('images/facility-science-lab.jpg') }}" alt="Science Laboratory" class="facility-img" loading="lazy">
                </div>
                <div class="facility-body">
                    <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Modern Science Laboratories</h4>
                    <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">
                        Safety-certified chemistry, physics, and biology lab workstations with digital microscopes and glassware.
                    </p>
                    <a href="{{ route('campus') }}" class="btn btn-sm btn-outline">Explore Labs</a>
                </div>
            </div>

            <!-- Facility 3: Library -->
            <div class="facility-card" data-reveal="fade-up" style="transition-delay: 0.3s;">
                <div class="facility-img-wrapper">
                    <span class="badge badge-amber facility-badge">15,000+ Titles</span>
                    <img src="{{ asset('images/facility-library.jpg') }}" alt="School Library" class="facility-img" loading="lazy">
                </div>
                <div class="facility-body">
                    <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Digital Learning Commons</h4>
                    <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">
                        Spacious library sanctuary with global digital databases, study cubicles, and quiet reading areas.
                    </p>
                    <a href="{{ route('campus') }}" class="btn btn-sm btn-outline">Explore Library</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Academic Programs Section with Real Images -->
<section class="section section-light-alt">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Academic Wings</span>
            <h2 class="section-title">A Prestigious Educational Continuum</h2>
            <p class="section-desc">
                From playful early sensory exploration to rigorous pre-university college preparation, our progressive structure ensures every child excels.
            </p>
        </div>

        <div class="grid-3">
            <!-- Wing 1: Montessori -->
            <div class="program-card" data-reveal="fade-up">
                <div class="program-img-wrapper">
                    <span class="badge badge-amber program-badge">Ages 3 - 5</span>
                    <img src="{{ asset('images/montessori-kids.jpg') }}" alt="Montessori Wing" class="program-img" loading="lazy">
                </div>
                <div class="program-header">
                    <h3 class="card-title">Early Years & Montessori</h3>
                    <p class="card-body">Sensory discovery, Jolly Phonics immersion, early numeracy, and social-emotional confidence building.</p>
                </div>
                <div class="program-body">
                    <ul class="program-features">
                        <li class="program-feature-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Dedicated Montessori practical life apparatus
                        </li>
                        <li class="program-feature-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Phonetics, motor skills & indoor soft gym
                        </li>
                    </ul>
                </div>
                <div class="program-footer">
                    <span style="font-size: 0.85rem; font-weight: 700; color: #57534e;">Playgroup &bull; Nursery &bull; Prep</span>
                    <a href="{{ route('academics') }}#early-years" class="btn btn-sm btn-outline">Explore Wing</a>
                </div>
            </div>

            <!-- Wing 2: Primary & Middle -->
            <div class="program-card" data-reveal="fade-up" style="transition-delay: 0.15s;">
                <div class="program-img-wrapper">
                    <span class="badge badge-primary program-badge">Grades 1 - 8</span>
                    <img src="{{ asset('images/facility-smart-class.jpg') }}" alt="Primary Wing" class="program-img" loading="lazy">
                </div>
                <div class="program-header">
                    <h3 class="card-title">Primary & Middle School</h3>
                    <p class="card-body">Singapore Math, Oxford Science curriculum, critical thinking, bilingual fluency, and coding foundations.</p>
                </div>
                <div class="program-body">
                    <ul class="program-features">
                        <li class="program-feature-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            STEM integration & laboratory research
                        </li>
                        <li class="program-feature-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Public speaking & debate society
                        </li>
                    </ul>
                </div>
                <div class="program-footer">
                    <span style="font-size: 0.85rem; font-weight: 700; color: #57534e;">Grades 1 through 8</span>
                    <a href="{{ route('academics') }}#primary-wing" class="btn btn-sm btn-outline">Explore Wing</a>
                </div>
            </div>

            <!-- Wing 3: Matric & O-Levels -->
            <div class="program-card" data-reveal="fade-up" style="transition-delay: 0.3s;">
                <div class="program-img-wrapper">
                    <span class="badge badge-dark program-badge">Grades 9 - 12</span>
                    <img src="{{ asset('images/hero-leadership.jpg') }}" alt="Senior Wing" class="program-img" loading="lazy">
                </div>
                <div class="program-header">
                    <h3 class="card-title">Matric & Cambridge O-Levels</h3>
                    <p class="card-body">High-performance academic streams with test-series bootcamps, university counseling, and career preparation.</p>
                </div>
                <div class="program-body">
                    <ul class="program-features">
                        <li class="program-feature-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Pre-Medical, Pre-Engineering & Computer Science
                        </li>
                        <li class="program-feature-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            100% Top grades & university admissions
                        </li>
                    </ul>
                </div>
                <div class="program-footer">
                    <span style="font-size: 0.85rem; font-weight: 700; color: #57534e;">Matric &bull; O-Levels &bull; HSSC</span>
                    <a href="{{ route('academics') }}#matric-o-levels" class="btn btn-sm btn-outline">Explore Wing</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Interactive Range-Slider Fee Estimator & Fast Inquiry -->
<section class="section section-white">
    <div class="container">
        <div class="grid-2" style="align-items: center; gap: 3.5rem;">
            <!-- Range Slider Fee Tool -->
            <div data-reveal="fade-right">
                <span class="section-subtitle">Instant Fee Calculator</span>
                <h2 class="section-title">Transparent Tuition Estimator</h2>
                <p class="section-desc" style="margin-bottom: 1.5rem;">
                    Drag the range slider to see instant fee estimates for each academic wing with sibling concessions and transport add-on.
                </p>

                <div class="fee-calc-box">
                    <div class="range-slider-group">
                        <label style="display: block; font-weight: 800; font-size: 1rem; color: var(--midnight); margin-bottom: 0.5rem;">
                            Selected Level: <span id="calcSelectedGradeName" style="color: var(--primary); font-weight: 800;">Primary Wing (Grades 1 to 5)</span>
                        </label>
                        <input type="range" id="calcGradeRange" class="fee-range-input" min="0" max="4" value="1" step="1">
                        <div class="range-slider-labels">
                            <span>Montessori</span>
                            <span>Primary</span>
                            <span>Middle</span>
                            <span>Matric/O-Lvl</span>
                            <span>College</span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" id="calcSibling" style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span>15% Sibling Concession</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" id="calcTransport" style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span>AC Bus Route (+ Rs. 3,500)</span>
                        </label>
                    </div>

                    <div class="calc-breakdown-card">
                        <div class="calc-breakdown-row">
                            <span>Standard Monthly Tuition:</span>
                            <span id="calcTuitionOutput" style="font-weight: 700;">Rs. 11,000/mo</span>
                        </div>
                        <div class="calc-breakdown-row">
                            <span>Admission & Registration:</span>
                            <span id="calcRegOutput" style="font-weight: 700;">Rs. 18,000 (One-time)</span>
                        </div>
                        <div class="calc-breakdown-row">
                            <span>Sibling Concession Rebate:</span>
                            <span id="calcDiscountOutput" style="color: #059669; font-weight: 700;">Rs. 0</span>
                        </div>
                        <div class="calc-breakdown-row">
                            <span>Transport Service:</span>
                            <span id="calcTransportOutput" style="font-weight: 700;">Not included</span>
                        </div>
                        <div class="calc-breakdown-row total-row">
                            <span>Net Estimated Monthly:</span>
                            <span id="calcTotalOutput">Rs. 11,000/month</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fast Inquiry Card -->
            <div data-reveal="fade-left">
                <div class="card" style="padding: 2.75rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl);">
                    <span class="badge badge-amber" style="margin-bottom: 0.75rem;">Fast Response</span>
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.45rem;">Request Admission Prospectus</h3>
                    <p style="font-size: 0.925rem; margin-bottom: 1.5rem;">Fill out the form below to lock your child's priority registration.</p>

                    <form data-ajax-inquiry action="{{ route('inquiry.submit') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="inqName">Parent / Guardian Name</label>
                            <input type="text" id="inqName" name="name" class="form-control" placeholder="e.g. Tariq Mehmood" required>
                        </div>

                        <div class="grid-2" style="gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="inqPhone">WhatsApp / Mobile</label>
                                <input type="tel" id="inqPhone" name="phone" class="form-control" placeholder="0300-XXXXXXX" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="inqEmail">Email Address</label>
                                <input type="email" id="inqEmail" name="email" class="form-control" placeholder="parent@email.com" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="inqGrade">Applying For Grade</label>
                            <select id="inqGrade" name="grade" class="form-control">
                                <option value="Playgroup / Nursery">Montessori / Nursery</option>
                                <option value="Primary (1-5)">Primary Wing (Grades 1-5)</option>
                                <option value="Middle (6-8)">Middle Wing (Grades 6-8)</option>
                                <option value="Matric / O-Levels">Matric / O-Levels (Grades 9-10)</option>
                                <option value="HSSC / College">HSSC / Intermediate (Grades 11-12)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="inqMessage">Your Inquiry or Tour Date</label>
                            <textarea id="inqMessage" name="message" class="form-control" rows="3" placeholder="Tell us any specific requirements or preferred campus tour date..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Submit Admission Inquiry
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Interactive Testimonials Slider Carousel -->
<section class="section section-light-alt">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Community Endorsements</span>
            <h2 class="section-title">Trusted by Generations of Families</h2>
            <p class="section-desc">Read how Pinnacle Academy empowers students and satisfies parents.</p>
        </div>

        <div class="testimonial-slider-container" data-reveal="fade-up">
            <div class="testimonial-track">
                @foreach($testimonials as $item)
                    <div class="testimonial-slide-item">
                        <div class="testimonial-bubble">
                            <div class="testimonial-stars">
                                &#9733;&#9733;&#9733;&#9733;&#9733;
                            </div>
                            <p class="testimonial-text">"{{ $item['quote'] }}"</p>
                            <div class="testimonial-person">
                                <div class="person-avatar">{{ $item['initials'] }}</div>
                                <div>
                                    <h5 style="margin-bottom: 0.15rem; font-size: 1.05rem;">{{ $item['name'] }}</h5>
                                    <p style="font-size: 0.85rem; color: #57534e; margin-bottom: 0;">{{ $item['role'] }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Testimonial Slider Controls -->
            <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 2rem;">
                <button class="slider-btn" id="testPrevBtn" style="background: #ffffff; color: var(--primary); border: 1px solid var(--border-color);" aria-label="Previous review">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <button class="slider-btn" id="testNextBtn" style="background: #ffffff; color: var(--primary); border: 1px solid var(--border-color);" aria-label="Next review">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Upcoming Events & Calendar -->
<section class="section section-white">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Campus Life</span>
            <h2 class="section-title">Upcoming Activities & Events</h2>
            <p class="section-desc">Key milestones and community gatherings on our calendar.</p>
        </div>

        <div class="grid-3">
            @foreach($upcomingEvents as $event)
                <div class="event-card" data-reveal="fade-up">
                    <div class="event-date">
                        <span class="event-day">{{ $event['day'] }}</span>
                        <span class="event-month">{{ $event['month'] }}</span>
                    </div>
                    <div class="event-info">
                        <div style="font-size: 0.8rem; color: #57534e; font-weight: 600; margin-bottom: 0.35rem;">
                            {{ $event['time'] }}
                        </div>
                        <h4 style="font-size: 1.1rem; margin-bottom: 0.4rem;">{{ $event['title'] }}</h4>
                        <div style="font-size: 0.85rem; color: var(--primary); font-weight: 600;">
                            &bull; {{ $event['venue'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Floating WhatsApp Quick Connect Widget -->
<a href="https://wa.me/923001234567" target="_blank" rel="noopener" class="floating-action-btn" aria-label="Chat on WhatsApp">
    <div class="floating-action-pulse"></div>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15c-1.49 0-2.95-.4-4.23-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a8.19 8.19 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.28-8.24 2.21 0 4.29.86 5.85 2.43 1.56 1.56 2.42 3.64 2.42 5.86 0 4.54-3.7 8.21-8.27 8.21zm4.53-6.17c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.65.81-.79.98-.15.17-.3.19-.54.07-.25-.13-1.04-.38-1.99-1.22-.73-.65-1.23-1.46-1.38-1.71-.14-.25-.01-.39.11-.51.11-.11.25-.28.37-.43.13-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43l-.48-.01c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.78 2.72 4.31 3.81.6.26 1.07.42 1.44.54.61.19 1.16.17 1.6.1.49-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.1-.23-.17-.48-.29z"/>
    </svg>
    <span>Admissions Helpline</span>
</a>

<!-- Reusable Admissions CTA -->
@include('partials.cta-banner')

@endsection

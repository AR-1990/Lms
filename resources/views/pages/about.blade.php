@extends('layouts.school')

@section('title', 'About Our Academy | Pinnacle International Academy')
@section('meta_description', 'Discover Pinnacle Academy’s 28-year legacy, leadership, vision, and mission of academic excellence and character development.')

@section('content')

<!-- About Hero Banner -->
<section class="page-hero-banner" style="background-image: linear-gradient(135deg, rgba(28,25,23,0.92) 0%, rgba(28,25,23,0.78) 100%), url('{{ asset('images/hero-campus.jpg') }}');">
    <div class="container">
        <div class="hero-banner-content">
            <div class="slide-pill">
                <span class="slide-pill-dot"></span>
                <span>28 Years of Academic Excellence</span>
            </div>
            <h1 class="hero-banner-title">
                Nurturing Visionaries, <span class="text-amber-gradient">Scholars & Leaders</span>
            </h1>
            <p class="hero-banner-desc">
                Founded in 1998, Pinnacle International Academy stands as a benchmark of holistic education, combining rigorous Cambridge & National curricula with state-of-the-art STEM laboratories.
            </p>
        </div>
    </div>
</section>

<!-- Principal's Message with Professional Portrait -->
<section class="section section-white">
    <div class="container">
        <div class="grid-2" style="align-items: center; gap: 4rem;">
            <div data-reveal="fade-right">
                <div style="position: relative; max-width: 440px; margin: 0 auto;">
                    <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl); border: 4px solid #ffffff;">
                        <img src="{{ asset('images/principal-portrait.jpg') }}" alt="Prof. Dr. Richard Sterling, Principal" style="width: 100%; height: auto; display: block;">
                    </div>
                    <div style="position: absolute; bottom: -1.5rem; right: -1.5rem; background: var(--bg-card); padding: 1.25rem 1.75rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
                        <strong style="display: block; font-size: 1.1rem; color: var(--midnight);">Dr. Richard Sterling</strong>
                        <span style="font-size: 0.825rem; color: var(--primary); font-weight: 700;">Principal & Academic Director</span>
                    </div>
                </div>
            </div>

            <div data-reveal="fade-left">
                <span class="section-subtitle">Leadership Perspective</span>
                <h2 class="section-title">A Message From The Principal</h2>
                <p style="font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin-bottom: 1.25rem;">
                    "At Pinnacle, education extends far beyond memorization and textbooks. We cultivate dynamic thinkers who ask probing questions, embrace ethical leadership, and create positive impact in an interconnected global community."
                </p>
                <p style="font-size: 0.95rem; line-height: 1.75; color: var(--text-secondary); margin-bottom: 1.75rem;">
                    Our faculty is dedicated to fostering curiosity through project-based STEM investigations, sportsmanship across our 5-acre sports facilities, and personal character development that equips young minds for university triumph.
                </p>
                <div style="display: flex; gap: 1.5rem; align-items: center;">
                    <a href="{{ route('admissions') }}" class="btn btn-primary">Join Our Community</a>
                    <a href="{{ route('campus') }}" class="btn btn-outline">Explore Campus Life</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Vision, Mission, and Core Pillars -->
<section class="section section-light-alt">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Guiding Philosophy</span>
            <h2 class="section-title">Our Mission, Vision & Core Values</h2>
            <p class="section-desc">The enduring principles that define our educational philosophy and shape our students into global changemakers.</p>
        </div>

        <div class="grid-3">
            <!-- Vision -->
            <div class="glow-card" data-reveal="fade-up" style="padding: 2.5rem;">
                <div class="card-icon" style="background: var(--primary-light); color: var(--primary);">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/></svg>
                </div>
                <h3 class="card-title">Our Vision</h3>
                <p class="card-body">
                    To be the leading center of academic and ethical leadership, producing confident, compassionate global citizens capable of navigating future frontiers in technology and society.
                </p>
            </div>

            <!-- Mission -->
            <div class="glow-card" data-reveal="fade-up" style="padding: 2.5rem; transition-delay: 0.15s;">
                <div class="card-icon" style="background: var(--amber-light); color: var(--amber);">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <h3 class="card-title">Our Mission</h3>
                <p class="card-body">
                    To provide a challenging, supportive, and technology-rich learning environment that fosters critical thinking, intellectual curiosity, artistic expression, and moral integrity.
                </p>
            </div>

            <!-- Legacy -->
            <div class="glow-card" data-reveal="fade-up" style="padding: 2.5rem; transition-delay: 0.3s;">
                <div class="card-icon" style="background: var(--emerald-light); color: var(--emerald);">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <h3 class="card-title">28-Year Legacy</h3>
                <p class="card-body">
                    Over two and a half decades of consistent 100% board positions, national robotics Olympiad victories, and alumni flourishing in world-renowned universities.
                </p>
            </div>
        </div>
    </div>
</section>

@include('partials.cta-banner')

@endsection

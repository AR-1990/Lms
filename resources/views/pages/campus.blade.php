@extends('layouts.school')

@section('title', 'Campus Life & Facilities | Pinnacle International Academy')
@section('meta_description', 'Discover Pinnacle Academy campus life, smart classrooms, digital library, sports arena, STEM labs, and safe RFID transport network.')

@section('content')

<!-- Campus Hero Banner -->
<section class="page-hero-banner" style="background-image: linear-gradient(135deg, rgba(28,25,23,0.92) 0%, rgba(28,25,23,0.78) 100%), url('{{ asset('images/hero-campus.jpg') }}');">
    <div class="container">
        <div class="hero-banner-content">
            <div class="slide-pill">
                <span class="slide-pill-dot"></span>
                <span>5-Acre World-Class Campus</span>
            </div>
            <h1 class="hero-banner-title">
                Infrastructure Designed for <span class="text-amber-gradient">Inspiration & Safety</span>
            </h1>
            <p class="hero-banner-desc">
                Spread across 5 lush green acres, our campus provides an energetic environment equipped with interactive classrooms, multi-sports complexes, and certified STEM laboratories.
            </p>
        </div>
    </div>
</section>

<!-- Facilities Photo Showcase -->
<section class="section section-white">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Campus Amenities</span>
            <h2 class="section-title">Everything a Student Needs to Flourish</h2>
            <p class="section-desc">We invest in premium infrastructure to ensure your child experiences learning in comfortable, stimulating surroundings.</p>
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
                        75" 4K touch interactive flat panels, climate control, ergonomic modular seating, and high-speed fiber connectivity.
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-sm btn-outline">Schedule a Visit</a>
                </div>
            </div>

            <!-- Facility 2: Science Lab -->
            <div class="facility-card" data-reveal="fade-up" style="transition-delay: 0.15s;">
                <div class="facility-img-wrapper">
                    <span class="badge badge-primary facility-badge">Safety-Certified</span>
                    <img src="{{ asset('images/facility-science-lab.jpg') }}" alt="Science Laboratory" class="facility-img" loading="lazy">
                </div>
                <div class="facility-body">
                    <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Modern Science Laboratories</h4>
                    <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">
                        Safety-certified physics, chemistry, and biology suites with digital microscopes and chemical fume hoods.
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-sm btn-outline">Schedule a Visit</a>
                </div>
            </div>

            <!-- Facility 3: Library -->
            <div class="facility-card" data-reveal="fade-up" style="transition-delay: 0.3s;">
                <div class="facility-img-wrapper">
                    <span class="badge badge-amber facility-badge">15,000+ Volumes</span>
                    <img src="{{ asset('images/facility-library.jpg') }}" alt="Learning Commons" class="facility-img" loading="lazy">
                </div>
                <div class="facility-body">
                    <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">The Learning Commons & Library</h4>
                    <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">
                        A sanctuary with over 15,000 physical volumes, international digital research databases, and quiet study pods.
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-sm btn-outline">Schedule a Visit</a>
                </div>
            </div>

            <!-- Facility 4: Sports Arena -->
            <div class="facility-card" data-reveal="fade-up">
                <div class="facility-img-wrapper">
                    <span class="badge badge-primary facility-badge">5-Acre Turf</span>
                    <img src="{{ asset('images/sports-gala.jpg') }}" alt="Sports Complex" class="facility-img" loading="lazy">
                </div>
                <div class="facility-body">
                    <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Athletic Arena & Sports Turf</h4>
                    <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">
                        Turf football pitch, synthetic basketball court, indoor badminton arena, table tennis hall, and athletic coaching.
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-sm btn-outline">Schedule a Visit</a>
                </div>
            </div>

            <!-- Facility 5: STEM Robotics Hub -->
            <div class="facility-card" data-reveal="fade-up" style="transition-delay: 0.15s;">
                <div class="facility-img-wrapper">
                    <span class="badge badge-amber facility-badge">Maker Studio</span>
                    <img src="{{ asset('images/hero-robotics.jpg') }}" alt="Robotics Studio" class="facility-img" loading="lazy">
                </div>
                <div class="facility-body">
                    <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">STEM & Robotics Innovation Studio</h4>
                    <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">
                        Arduino prototyping, 3D printers, electronics workbenches, and coding stations for Olympiad preparation.
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-sm btn-outline">Schedule a Visit</a>
                </div>
            </div>

            <!-- Facility 6: Montessori Soft Gym -->
            <div class="facility-card" data-reveal="fade-up" style="transition-delay: 0.3s;">
                <div class="facility-img-wrapper">
                    <span class="badge badge-emerald facility-badge">Early Years</span>
                    <img src="{{ asset('images/montessori-kids.jpg') }}" alt="Montessori Activity Zone" class="facility-img" loading="lazy">
                </div>
                <div class="facility-body">
                    <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">Montessori Discovery Play Zone</h4>
                    <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">
                        Padded soft play apparatus, sensory exploration activity tables, and storytelling amphitheater.
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-sm btn-outline">Schedule a Visit</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Safety & Secure Campus Protocols -->
<section class="section section-light-alt">
    <div class="container">
        <div class="grid-2" style="align-items: center; gap: 3.5rem;">
            <div data-reveal="fade-right">
                <span class="section-subtitle">Student Safety First</span>
                <h2 class="section-title">Uncompromised Security & Wellbeing</h2>
                <p style="font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin-bottom: 1.5rem;">
                    We treat campus safety with the highest gravity. Our comprehensive multi-layered security infrastructure ensures your peace of mind while your child is in our care.
                </p>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: bold;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div>
                            <h5 style="font-size: 1.05rem; margin-bottom: 0.2rem;">24/7 Monitored CCTV Coverage</h5>
                            <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 0;">Over 120 high-definition security cameras covering boundary perimeters, corridors, and gates.</p>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--amber-light); color: var(--amber); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: bold;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        </div>
                        <div>
                            <h5 style="font-size: 1.05rem; margin-bottom: 0.2rem;">Full-Time Medical Bay & Nursing Staff</h5>
                            <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 0;">Equipped infirmary with certified nursing staff for first aid, health screening, and emergency triage.</p>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem; align-items: flex-start;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--emerald-light); color: var(--emerald); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: bold;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/><path d="M15 9h6"/></svg>
                        </div>
                        <div>
                            <h5 style="font-size: 1.05rem; margin-bottom: 0.2rem;">Live GPS & RFID School Transport</h5>
                            <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 0;">Air-conditioned school buses with female attendants and real-time tracking notifications for parents.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div data-reveal="fade-left">
                <div class="card" style="padding: 2.75rem; border-radius: var(--radius-xl); background: #ffffff; box-shadow: var(--shadow-xl);">
                    <span class="badge badge-amber" style="margin-bottom: 1rem;">Experience Pinnacle</span>
                    <h3 style="margin-bottom: 1rem; font-size: 1.45rem;">Experience Our Campus Firsthand</h3>
                    <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.75rem;">
                        We invite parents and prospective students to take a guided personalized tour of our smart classrooms, science suites, and sports grounds.
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-primary btn-lg" style="width: 100%;">
                        Book a Personalized Campus Tour
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

@include('partials.cta-banner')

@endsection

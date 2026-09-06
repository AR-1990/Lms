@extends('layouts.school')

@section('title', 'Academic Curriculum & Wings | Pinnacle International Academy')
@section('meta_description', 'Explore Montessori, Primary, Middle, Cambridge O-Levels, and Federal Board streams at Pinnacle Academy with integrated STEM & robotics.')

@section('content')

<!-- Academics Hero Banner -->
<section class="page-hero-banner" style="background-image: linear-gradient(135deg, rgba(28,25,23,0.92) 0%, rgba(28,25,23,0.78) 100%), url('{{ asset('images/facility-science-lab.jpg') }}');">
    <div class="container">
        <div class="hero-banner-content">
            <div class="slide-pill">
                <span class="slide-pill-dot"></span>
                <span>Curriculum & Pedagogy</span>
            </div>
            <h1 class="hero-banner-title">
                Intellectual Rigor & <span class="text-amber-gradient">Future-Ready Skills</span>
            </h1>
            <p class="hero-banner-desc">
                Our dual-stream curriculum merges Cambridge International assessment standards with the national Federal Board framework, enriched with hands-on STEM robotics and arts.
            </p>
        </div>
    </div>
</section>

<!-- Academic Wings Section with Real Images -->
<section class="section section-white">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Academic Continuum</span>
            <h2 class="section-title">Structured for Every Developmental Stage</h2>
            <p class="section-desc">Click through our academic wings to explore tailored curricula, learning methodologies, and student milestones.</p>
        </div>

        <div class="tabs-nav" data-tabs data-reveal="fade-up">
            <button class="tab-btn active" data-tab-target="tab-early">Early Years & Montessori</button>
            <button class="tab-btn" data-tab-target="tab-primary">Primary Wing (Grades 1-5)</button>
            <button class="tab-btn" data-tab-target="tab-middle">Middle School (Grades 6-8)</button>
            <button class="tab-btn" data-tab-target="tab-senior">Cambridge & Matric (9-12)</button>
        </div>

        <div class="tab-content" style="margin-top: 2.5rem;">
            <!-- Tab 1: Early Years -->
            <div class="tab-pane active" id="tab-early">
                <div class="grid-2" style="align-items: center; gap: 3.5rem;">
                    <div data-reveal="fade-right">
                        <span class="badge badge-amber" style="margin-bottom: 0.75rem;">Ages 3 to 5 Years</span>
                        <h3 style="font-size: 1.85rem; margin-bottom: 1rem;">Early Years & Sensory Montessori</h3>
                        <p style="font-size: 1rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem;">
                            Our Montessori environment inspires spontaneous inquiry and joyful discovery. Certified teachers use multisensory apparatus to nurture phonological awareness, early numeracy, and fine motor skills.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                            <div class="program-feature-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <strong>Jolly Phonics & Dual Fluency:</strong> Systematic phonemic reading and bilingual conversation.
                            </div>
                            <div class="program-feature-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <strong>Sensorial & Practical Life:</strong> Cultivating spatial reasoning, focus, and social independence.
                            </div>
                        </div>
                        <a href="{{ route('admissions') }}" class="btn btn-primary">Enroll in Early Years</a>
                    </div>
                    <div data-reveal="fade-left">
                        <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl);">
                            <img src="{{ asset('images/montessori-kids.jpg') }}" alt="Montessori Early Childhood" style="width: 100%; height: auto;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Primary Wing -->
            <div class="tab-pane" id="tab-primary">
                <div class="grid-2" style="align-items: center; gap: 3.5rem;">
                    <div>
                        <span class="badge badge-primary" style="margin-bottom: 0.75rem;">Grades 1 to 5</span>
                        <h3 style="font-size: 1.85rem; margin-bottom: 1rem;">Primary Wing Curriculum</h3>
                        <p style="font-size: 1rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem;">
                            Building a resilient foundation in analytical mathematics, inquiry-based scientific investigations, and expressive English writing through modern Oxford & Cambridge primary frameworks.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                            <div class="program-feature-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <strong>Singapore Math Conceptual Model:</strong> Focus on problem-solving and mental arithmetic.
                            </div>
                            <div class="program-feature-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <strong>Weekly STEM Studio:</strong> Introductory robotics, Scratch visual coding, and science experiments.
                            </div>
                        </div>
                        <a href="{{ route('admissions') }}" class="btn btn-primary">Apply For Primary Wing</a>
                    </div>
                    <div>
                        <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl);">
                            <img src="{{ asset('images/facility-smart-class.jpg') }}" alt="Primary Classroom" style="width: 100%; height: auto;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Middle Wing -->
            <div class="tab-pane" id="tab-middle">
                <div class="grid-2" style="align-items: center; gap: 3.5rem;">
                    <div>
                        <span class="badge badge-amber" style="margin-bottom: 0.75rem;">Grades 6 to 8</span>
                        <h3 style="font-size: 1.85rem; margin-bottom: 1rem;">Middle School Transition</h3>
                        <p style="font-size: 1rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem;">
                            The transitional bridge where students master advanced sciences, coding in Python, public debate, and exploratory research to choose between O-Levels and Matric tracks.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                            <div class="program-feature-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <strong>Dedicated Physics, Chem & Bio Labs:</strong> Hands-on weekly laboratory sessions.
                            </div>
                            <div class="program-feature-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <strong>Model UN & Oratory Society:</strong> Cultivating public speaking and diplomacy.
                            </div>
                        </div>
                        <a href="{{ route('admissions') }}" class="btn btn-primary">Apply For Middle School</a>
                    </div>
                    <div>
                        <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl);">
                            <img src="{{ asset('images/facility-library.jpg') }}" alt="Middle School Learning" style="width: 100%; height: auto;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Senior Wing -->
            <div class="tab-pane" id="tab-senior">
                <div class="grid-2" style="align-items: center; gap: 3.5rem;">
                    <div>
                        <span class="badge badge-dark" style="margin-bottom: 0.75rem;">Grades 9 to 12</span>
                        <h3 style="font-size: 1.85rem; margin-bottom: 1rem;">Cambridge O-Levels & Federal Board</h3>
                        <p style="font-size: 1rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem;">
                            Intensive pre-university tracks with rigorous mock exam series, individual career counseling, and university placement guidance producing top nationwide results.
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                            <div class="program-feature-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <strong>Pre-Medical, Pre-Engineering & Computer Science:</strong> State-of-the-art facilities.
                            </div>
                            <div class="program-feature-item">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <strong>University Placement Counseling:</strong> SAT prep, personal statement workshops & scholarships.
                            </div>
                        </div>
                        <a href="{{ route('admissions') }}" class="btn btn-primary">Apply For Senior Wing</a>
                    </div>
                    <div>
                        <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl);">
                            <img src="{{ asset('images/hero-leadership.jpg') }}" alt="Senior Wing Students" style="width: 100%; height: auto;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STEM & Robotics Studio Feature -->
<section class="section section-light-alt" id="stem-program">
    <div class="container">
        <div class="grid-2" style="align-items: center; gap: 3.5rem;">
            <div data-reveal="fade-right">
                <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl);">
                    <img src="{{ asset('images/hero-robotics.jpg') }}" alt="STEM Robotics Lab" style="width: 100%; height: auto;">
                </div>
            </div>

            <div data-reveal="fade-left">
                <span class="section-subtitle">Future Technologies Hub</span>
                <h2 class="section-title">Robotics, AI & Rapid Prototyping</h2>
                <p style="font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin-bottom: 1.5rem;">
                    In an era defined by artificial intelligence, we equip students with real-world technical skills. Our innovation lab features Arduino workstations, 3D printers, Python programming, and sensor integration.
                </p>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;">
                    <span class="badge badge-amber">Arduino & Microcontrollers</span>
                    <span class="badge badge-primary">Python Coding</span>
                    <span class="badge badge-emerald">3D Rapid Prototyping</span>
                    <span class="badge badge-dark">AI & Automation</span>
                </div>
                <a href="{{ route('admissions') }}" class="btn btn-primary btn-lg">Join STEM Program</a>
            </div>
        </div>
    </div>
</section>

@include('partials.cta-banner')

@endsection

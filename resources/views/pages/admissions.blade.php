@extends('layouts.school')

@section('title', 'Admissions & Fee Guide | Pinnacle International Academy')
@section('meta_description', 'Apply online for admissions at Pinnacle International Academy. Explore our admission roadmap, transparent fee estimator, and criteria.')

@section('content')

<!-- Admissions Hero Banner -->
<section class="page-hero-banner" style="background-image: linear-gradient(135deg, rgba(28,25,23,0.92) 0%, rgba(28,25,23,0.78) 100%), url('{{ asset('images/admissions-meeting.jpg') }}');">
    <div class="container">
        <div class="hero-banner-content">
            <div class="slide-pill">
                <span class="slide-pill-dot"></span>
                <span>Session 2026-27 &bull; Applications Open</span>
            </div>
            <h1 class="hero-banner-title">
                Begin Your Child's Journey of <span class="text-amber-gradient">Excellence</span>
            </h1>
            <p class="hero-banner-desc">
                We welcome curious, ambitious students into our vibrant academic family. Explore our simple 4-step roadmap, transparent fee guide, and submit your registration online.
            </p>
        </div>
    </div>
</section>

<!-- 4-Step Admission Roadmap -->
<section class="section section-white">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Streamlined Process</span>
            <h2 class="section-title">4 Simple Steps to Enrollment</h2>
            <p class="section-desc">Our admissions team provides comprehensive guidance throughout the application lifecycle.</p>
        </div>

        <div class="grid-4">
            <!-- Step 1 -->
            <div class="glow-card" data-reveal="fade-up" style="padding: 2.25rem 1.75rem;">
                <div style="font-size: 2.5rem; font-weight: 900; color: var(--primary); font-family: var(--font-heading); margin-bottom: 0.5rem; line-height: 1;">
                    01
                </div>
                <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Online Registration</h4>
                <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6;">Submit the simple admission inquiry form online or visit our campus front desk for a prospectus.</p>
            </div>

            <!-- Step 2 -->
            <div class="glow-card" data-reveal="fade-up" style="padding: 2.25rem 1.75rem; transition-delay: 0.1s;">
                <div style="font-size: 2.5rem; font-weight: 900; color: var(--amber); font-family: var(--font-heading); margin-bottom: 0.5rem; line-height: 1;">
                    02
                </div>
                <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Aptitude Assessment</h4>
                <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6;">Age-appropriate diagnostic assessment in English and Math to understand your child's baseline strengths.</p>
            </div>

            <!-- Step 3 -->
            <div class="glow-card" data-reveal="fade-up" style="padding: 2.25rem 1.75rem; transition-delay: 0.2s;">
                <div style="font-size: 2.5rem; font-weight: 900; color: var(--primary); font-family: var(--font-heading); margin-bottom: 0.5rem; line-height: 1;">
                    03
                </div>
                <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Parent Interaction</h4>
                <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6;">A warm 20-minute conversation with our academic principal to align on goals and expectations.</p>
            </div>

            <!-- Step 4 -->
            <div class="glow-card" data-reveal="fade-up" style="padding: 2.25rem 1.75rem; transition-delay: 0.3s;">
                <div style="font-size: 2.5rem; font-weight: 900; color: var(--emerald); font-family: var(--font-heading); margin-bottom: 0.5rem; line-height: 1;">
                    04
                </div>
                <h4 style="font-size: 1.15rem; margin-bottom: 0.5rem;">Offer & Welcome</h4>
                <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6;">Issuance of official admission offer, fee settlement, uniform orientation, and LMS credential onboarding.</p>
            </div>
        </div>
    </div>
</section>

<!-- Counselor Consultation Feature + Online Admission Form -->
<section class="section section-light-alt">
    <div class="container">
        <div class="grid-2" style="align-items: flex-start; gap: 3.5rem;">
            <!-- Counselor Consultation Info -->
            <div data-reveal="fade-right">
                <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl); margin-bottom: 2rem;">
                    <img src="{{ asset('images/admissions-meeting.jpg') }}" alt="Admissions Counselor" style="width: 100%; height: auto;">
                </div>

                <span class="section-subtitle">Dedicated Guidance</span>
                <h3 style="font-size: 1.75rem; margin-bottom: 1rem;">Meet Our Admission Counselors</h3>
                <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem;">
                    Unsure which academic stream (Cambridge O-Levels or Federal Board) is right for your child? Our counselors provide personalized assessments, curriculum comparisons, and campus tour bookings.
                </p>

                <div style="background: #ffffff; border-radius: var(--radius-lg); padding: 1.5rem; border: 1px solid var(--border-color); display: flex; gap: 1.25rem; align-items: center;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <div>
                        <div style="font-weight: 800; font-size: 1.05rem;">Admissions Direct Line</div>
                        <div style="font-size: 0.9rem; color: var(--primary); font-weight: 700;">+92 300 1234567 / (051) 8899770</div>
                    </div>
                </div>
            </div>

            <!-- Online Application Form -->
            <div data-reveal="fade-left">
                <div class="card" style="padding: 2.75rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); background: #ffffff;">
                    <span class="badge badge-amber" style="margin-bottom: 0.75rem;">Fast Online Enrollment</span>
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.45rem;">Submit Online Application</h3>
                    <p style="font-size: 0.925rem; margin-bottom: 1.5rem;">Fill out the student details to reserve an assessment interview slot.</p>

                    <form data-ajax-inquiry action="{{ route('inquiry.submit') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="admStudentName">Student's Full Name</label>
                            <input type="text" id="admStudentName" name="name" class="form-control" placeholder="e.g. Daniyal Khan" required>
                        </div>

                        <div class="grid-2" style="gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="admPhone">Parent WhatsApp / Phone</label>
                                <input type="tel" id="admPhone" name="phone" class="form-control" placeholder="0300-XXXXXXX" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="admEmail">Parent Email Address</label>
                                <input type="email" id="admEmail" name="email" class="form-control" placeholder="parent@email.com" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admGrade">Select Grade Level</label>
                            <select id="admGrade" name="grade" class="form-control">
                                <option value="Early Years / Montessori">Playgroup / Nursery / Prep</option>
                                <option value="Primary (1-5)">Primary Wing (Grades 1-5)</option>
                                <option value="Middle (6-8)">Middle Wing (Grades 6-8)</option>
                                <option value="Cambridge O-Levels">Cambridge O-Levels (Grades 9-10)</option>
                                <option value="Federal Board Matric">Federal Board Matric (Grades 9-10)</option>
                                <option value="College HSSC">Intermediate / HSSC (Grades 11-12)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="admMessage">Previous School & Any Special Notes</label>
                            <textarea id="admMessage" name="message" class="form-control" rows="3" placeholder="Previous school name, current grade, and any special inquiries..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Submit Admission Application
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

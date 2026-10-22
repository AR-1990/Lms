@extends('layouts.school')

@section('title', 'Contact & Campus Visit | Pinnacle International Academy')
@section('meta_description', 'Contact Pinnacle International Academy. Get in touch with our admissions office, accounts department, or submit an online inquiry.')

@section('content')

<!-- Contact Hero Banner -->
<section class="page-hero-banner" style="background-image: linear-gradient(135deg, rgba(28,25,23,0.92) 0%, rgba(28,25,23,0.78) 100%), url('{{ asset('images/facility-library.jpg') }}');">
    <div class="container">
        <div class="hero-banner-content">
            <div class="slide-pill">
                <span class="slide-pill-dot"></span>
                <span>Get in Touch &bull; Admissions Helpdesk</span>
            </div>
            <h1 class="hero-banner-title">
                We're Here to Guide Your <span class="text-amber-gradient">Child's Future</span>
            </h1>
            <p class="hero-banner-desc">
                Have questions about our academic pathways, admission deadlines, fee structures, or campus visits? Our counselors and administration are at your service.
            </p>
        </div>
    </div>
</section>

<!-- Direct Contact Details & Interactive Inquiry Form -->
<section class="section section-white">
    <div class="container">
        <div class="grid-2" style="gap: 3.5rem; align-items: flex-start;">
            <!-- Contact Channels -->
            <div data-reveal="fade-right">
                <span class="section-subtitle">Direct Communication</span>
                <h2 class="section-title">Campus Helplines & Location</h2>
                <p class="section-desc" style="margin-bottom: 2rem;">
                    Visit our main campus during working hours or connect with dedicated department counselors.
                </p>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <!-- Channel 1 -->
                    <div class="card" style="padding: 1.75rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                        <div class="card-icon card-icon-sm tone-orange">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <div>
                            <h4 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Campus Address</h4>
                            <p style="font-size: 0.925rem; color: #64748b; margin-bottom: 0;">Plot 42-A, Education Avenue, Sector F-8, Islamabad, Pakistan</p>
                        </div>
                    </div>

                    <!-- Channel 2 -->
                    <div class="card" style="padding: 1.75rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                        <div class="card-icon card-icon-sm tone-blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </div>
                        <div>
                            <h4 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Phone & WhatsApp</h4>
                            <p style="font-size: 0.925rem; color: #64748b; margin-bottom: 0;">
                                Admissions: <a href="tel:+923001234567" style="font-weight: 700; color: var(--primary);">+92 300 1234567</a><br>
                                Front Desk: <a href="tel:+92518899770" style="font-weight: 700; color: var(--primary);">(051) 8899770</a>
                            </p>
                        </div>
                    </div>

                    <!-- Channel 3 -->
                    <div class="card" style="padding: 1.75rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                        <div class="card-icon card-icon-sm tone-emerald">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 16 14"/></svg>
                        </div>
                        <div>
                            <h4 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Office Visiting Hours</h4>
                            <p style="font-size: 0.925rem; color: #64748b; margin-bottom: 0;">
                                Monday - Friday: 08:00 AM to 03:30 PM<br>
                                Saturday: 09:00 AM to 01:30 PM (Admissions Desk)
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inquiry Submission Form -->
            <div data-reveal="fade-left">
                <div class="card" style="padding: 2.75rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl);">
                    <span class="badge badge-amber" style="margin-bottom: 0.75rem;">Fast Response</span>
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.45rem;">Send Us a Message</h3>
                    <p style="font-size: 0.925rem; margin-bottom: 1.5rem;">Fill out the details below and our counseling department will get back to you promptly.</p>

                    <form data-ajax-inquiry action="{{ route('inquiry.submit') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="cntName">Your Full Name</label>
                            <input type="text" id="cntName" name="parent_name" class="form-control" placeholder="e.g. Asim Rauf" required>
                        </div>

                        <div class="grid-2" style="gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="cntPhone">Mobile / WhatsApp Number</label>
                                <input type="tel" id="cntPhone" name="phone" class="form-control" placeholder="0300-XXXXXXX" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="cntEmail">Email Address</label>
                                <input type="email" id="cntEmail" name="email" class="form-control" placeholder="asim@example.com">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="cntDept">Inquiry Type / Department</label>
                            <select id="cntDept" name="topic_id" class="form-control">
                                @foreach ($topics as $topic)
                                    <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="cntMessage">Message or Query</label>
                            <textarea id="cntMessage" name="message" class="form-control" rows="4" placeholder="How can we assist you today?" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Send Inquiry Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Frequently Asked Questions (FAQ Accordion) -->
<section class="section section-light-alt">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Got Questions?</span>
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-desc">Quick answers to common questions about admissions, fees, curriculum, and policies.</p>
        </div>

        <div class="faq-list" style="max-width: 840px; margin: 0 auto;" data-reveal="fade-up">
            @foreach ($faqs as $faq)
                <div class="faq-item">
                    <button class="faq-trigger" type="button">
                        <span>{{ $faq->question }}</span>
                        <span class="faq-icon">&darr;</span>
                    </button>
                    <div class="faq-content">
                        {{ $faq->answer }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

@endsection

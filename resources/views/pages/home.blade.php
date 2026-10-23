@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')

@if ($slides = $page->section('hero_slides'))
<!-- Hero Slider -->
<section class="hero-slider-wrapper">
    @foreach ($slides->items as $slide)
        <div class="hero-slide {{ $loop->first ? 'active' : '' }}" data-slide-index="{{ $loop->index }}">
            <div class="hero-slide-bg" style="background-image: url('{{ $slide->imageUrl() }}');"></div>
            <div class="hero-slide-overlay"></div>

            <div class="hero-slide-inner">
                <div class="container">
                    <div class="hero-slide-grid">
                        <div>
                            @if ($slide->badge)
                                <div class="slide-pill" @if ($slide->tone === 'blue') style="border-color: rgba(36, 103, 107, 0.5); color: #f3ead8;" @elseif ($slide->tone === 'amber') style="border-color: rgba(233, 168, 58, 0.5); color: #f3ead8;" @endif>
                                    <span class="slide-pill-dot" @if ($slide->tone === 'blue') style="background-color: #24676b;" @elseif ($slide->tone === 'amber') style="background-color: #e9a83a;" @endif></span>
                                    <span>{{ $slide->badge }}</span>
                                </div>
                            @endif

                            <h1 class="slide-title">{{ \App\Models\WebsitePage::highlight($slide->title) }}</h1>
                            <p class="slide-desc">{{ $slide->body }}</p>

                            <div class="slide-actions">
                                @if ($slide->link_label)
                                    <a href="{{ \App\Models\WebsitePage::link($slide->link_url) }}" class="btn btn-primary btn-lg">
                                        {{ $slide->link_label }}
                                        @if ($loop->first)<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>@endif
                                    </a>
                                @endif
                                @if ($slide->link2_label)
                                    <a href="{{ \App\Models\WebsitePage::link($slide->link2_url) }}" class="btn btn-white btn-lg">{{ $slide->link2_label }}</a>
                                @endif
                            </div>
                        </div>

                        @if ($slide->subtitle || $slide->children->isNotEmpty())
                            <div>
                                <div class="slide-visual-card">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                        @if ($slide->subtitle)<span class="badge badge-amber">{{ $slide->subtitle }}</span>@endif
                                        @if ($slide->value)<span style="font-size: 0.85rem; color: var(--primary); font-weight: 800;">{{ $slide->value }}</span>@endif
                                    </div>

                                    @foreach ($slide->children as $row)
                                        <div class="slide-metric-row" @if ($loop->last) style="margin-bottom: 0;" @endif>
                                            <div class="slide-metric-icon" style="{{ $row->toneStyle() }}">@include('partials.site-icon', ['name' => $row->icon, 'size' => 22])</div>
                                            <div>
                                                <div style="font-size: 1.15rem; font-weight: 800; color: var(--midnight);">{{ $row->title }}</div>
                                                <div style="font-size: 0.8rem; color: #64748b;">{{ $row->body }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

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
@endif

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

@if ($section = $page->section('facilities'))
<!-- Campus Facilities -->
<section class="section section-white">
    <div class="container">
        @include('partials.section-header', ['section' => $section])

        <div class="grid-3">
            @foreach ($section->items as $item)
                @include('partials.facility-card', ['item' => $item, 'loop' => $loop])
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($section = $page->section('wings'))
<!-- Academic Wings -->
<section class="section section-light-alt">
    <div class="container">
        @include('partials.section-header', ['section' => $section])

        <div class="grid-3">
            @foreach ($section->items as $wing)
                <div class="program-card" data-reveal="fade-up" style="transition-delay: {{ ($loop->index % 3) * 0.15 }}s;">
                    <div class="program-img-wrapper">
                        @if ($wing->badge)<span class="badge {{ $wing->badgeClass() }} program-badge">{{ $wing->badge }}</span>@endif
                        @if ($wing->image_path)<img src="{{ $wing->imageUrl() }}" alt="{{ $wing->title }}" class="program-img" loading="lazy">@endif
                    </div>
                    <div class="program-header">
                        <h3 class="card-title">{{ $wing->title }}</h3>
                        <p class="card-body">{{ $wing->body }}</p>
                    </div>
                    <div class="program-body">
                        <ul class="program-features">
                            @foreach ($wing->children as $feature)
                                <li class="program-feature-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24676b" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    {{ $feature->title }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="program-footer">
                        <span style="font-size: 0.85rem; font-weight: 700; color: #57534e;">{{ $wing->value }}</span>
                        @if ($wing->link_label)<a href="{{ \App\Models\WebsitePage::link($wing->link_url) }}" class="btn btn-sm btn-outline">{{ $wing->link_label }}</a>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Interactive Range-Slider Fee Estimator & Fast Inquiry -->
<section class="section section-white">
    <div class="container">
        <div class="grid-2" style="align-items: center; gap: 3.5rem;">
            <!-- Range Slider Fee Tool -->
            <div data-reveal="fade-right">
                @php($fee = $page->section('fee_estimator'))
                <span class="section-subtitle">{{ $fee?->subtitle }}</span>
                <h2 class="section-title">{{ \App\Models\WebsitePage::highlight($fee?->title ?? 'Fee Estimator') }}</h2>
                <p class="section-desc" style="margin-bottom: 1.5rem;">{{ $fee?->description }}</p>

                <div class="fee-calc-box">
                    <div class="range-slider-group">
                        <label for="calcGrade" style="display: block; font-weight: 800; font-size: 1rem; color: var(--midnight); margin-bottom: 0.5rem;">
                            Selected Class: <span id="calcSelectedGradeName" style="color: var(--primary); font-weight: 800;">{{ $feeEstimates->first()?->class_name }}</span>
                        </label>
                        <select id="calcGrade" class="form-control">
                            @foreach ($feeEstimates as $estimate)
                                <option value="{{ $estimate->class_id }}" data-monthly="{{ (int) $estimate->monthly_total }}" data-one-time="{{ (int) $estimate->one_time_total }}" data-optional="{{ (int) $estimate->optional_total }}">{{ $estimate->class_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; font-weight: 600;">
                            <span>Concession</span>
                            <select id="calcConcession" class="form-control" style="width: auto;">
                                <option value="" data-percent="0">None</option>
                                @foreach ($estimatorConcessions as $concession)
                                    <option value="{{ $concession->id }}" data-percent="{{ $concession->default_value }}">{{ $concession->name }} ({{ $concession->default_value / 100 }}%)</option>
                                @endforeach
                            </select>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" id="calcTransport" style="accent-color: var(--primary); width: 18px; height: 18px;">
                            <span>Optional services (transport, lab)</span>
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
                    @php($inquiry = $page->section('inquiry'))
                    @if ($inquiry?->badge)<span class="badge badge-amber" style="margin-bottom: 0.75rem;">{{ $inquiry->badge }}</span>@endif
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.45rem;">{{ $inquiry?->title ?? 'Admission Inquiry' }}</h3>
                    <p style="font-size: 0.925rem; margin-bottom: 1.5rem;">{{ $inquiry?->description }}</p>

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
                                @foreach ($classes as $class)
                                    <option value="{{ $class->name }}">{{ $class->name }}</option>
                                @endforeach
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
        @if ($testimonialSection = $page->section('testimonials'))
            @include('partials.section-header', ['section' => $testimonialSection])
        @endif

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
        @if ($eventSection = $page->section('events'))
            @include('partials.section-header', ['section' => $eventSection])
        @endif

        <div class="grid-3">
            @forelse($upcomingEvents as $event)
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
            @empty
                <p style="grid-column: 1 / -1; text-align: center; color: #57534e;">New events will be announced soon.</p>
            @endforelse
        </div>
    </div>
</section>

<!-- Floating WhatsApp Quick Connect Widget -->
@if ($settings->whatsapp_number)
<a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $settings->whatsapp_number) }}" target="_blank" rel="noopener" class="floating-action-btn" aria-label="Chat on WhatsApp">
    <div class="floating-action-pulse"></div>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15c-1.49 0-2.95-.4-4.23-1.16l-.3-.18-3.13.82.84-3.05-.2-.31a8.19 8.19 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.28-8.24 2.21 0 4.29.86 5.85 2.43 1.56 1.56 2.42 3.64 2.42 5.86 0 4.54-3.7 8.21-8.27 8.21zm4.53-6.17c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.65.81-.79.98-.15.17-.3.19-.54.07-.25-.13-1.04-.38-1.99-1.22-.73-.65-1.23-1.46-1.38-1.71-.14-.25-.01-.39.11-.51.11-.11.25-.28.37-.43.13-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43l-.48-.01c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1 0 1.24.9 2.44 1.03 2.61.13.17 1.78 2.72 4.31 3.81.6.26 1.07.42 1.44.54.61.19 1.16.17 1.6.1.49-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.1-.23-.17-.48-.29z"/>
    </svg>
    <span>{{ $page->section('whatsapp')?->title ?? 'WhatsApp' }}</span>
</a>
@endif

<!-- Reusable Admissions CTA -->
@include('partials.cta-banner')

@endsection

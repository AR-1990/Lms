@extends('layouts.school')

@section('title', 'News, Events & Happenings | Pinnacle International Academy')
@section('meta_description', 'Latest news, sports galas, robotics championships, academic achievements, and community events at Pinnacle International Academy.')

@section('content')

<!-- News & Events Hero Banner -->
<section class="page-hero-banner" style="background-image: linear-gradient(135deg, rgba(28,25,23,0.92) 0%, rgba(28,25,23,0.75) 100%), url('{{ asset('images/sports-gala.jpg') }}');">
    <div class="container">
        <div class="hero-banner-content">
            <div class="slide-pill">
                <span class="slide-pill-dot"></span>
                <span>Campus Pulse &bull; News & Events</span>
            </div>
            <h1 class="hero-banner-title">
                Happenings, Triumphs & <span class="text-amber-gradient">Campus Life</span>
            </h1>
            <p class="hero-banner-desc">
                Stay connected with the latest academic milestones, robotics tournament victories, sports galas, and announcements from Pinnacle International Academy.
            </p>
        </div>
    </div>
</section>

<!-- News & Events Section with Filters -->
<section class="section section-white">
    <div class="container">
        <!-- Category Filter Tabs -->
        <div class="tabs-nav" data-tabs data-reveal="fade-up">
            <button class="tab-btn active" data-tab-target="all-news">All News & Events</button>
            <button class="tab-btn" data-tab-target="achievements">STEM & Achievements</button>
            <button class="tab-btn" data-tab-target="sports">Sports Gala</button>
            <button class="tab-btn" data-tab-target="academics">Academic Milestones</button>
            <button class="tab-btn" data-tab-target="campus">Campus & Admissions</button>
        </div>

        <!-- Featured Article Highlight -->
        @if(count($articles) > 0)
            @php $featured = $articles[0]; @endphp
            <div class="glow-card" data-reveal="fade-up" style="margin-bottom: 3.5rem; overflow: hidden;">
                <div class="grid-2" style="gap: 0; align-items: stretch;">
                    <div style="min-height: 320px; position: relative;">
                        <img src="{{ $featured['image'] }}" alt="{{ $featured['title'] }}" style="width: 100%; height: 100%; object-fit: cover;">
                        <span class="badge badge-amber" style="position: absolute; top: 1.25rem; left: 1.25rem; font-size: 0.85rem;">
                            ★ Featured Story
                        </span>
                    </div>
                    <div style="padding: 3rem; display: flex; flex-direction: column; justify-content: center;">
                        <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                            <span class="badge badge-primary">{{ $featured['category'] }}</span>
                            <span style="font-size: 0.85rem; color: #78716c;">{{ $featured['date'] }} &bull; {{ $featured['read_time'] }}</span>
                        </div>
                        <h3 style="font-size: 1.65rem; margin-bottom: 1rem; line-height: 1.3;">
                            {{ $featured['title'] }}
                        </h3>
                        <p style="font-size: 1rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.75rem;">
                            {{ $featured['excerpt'] }}
                        </p>
                        <div>
                            <a href="{{ route('contact') }}" class="btn btn-primary btn-sm">
                                Read Full Coverage
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- News Grid -->
        <div class="grid-3">
            @foreach($articles as $article)
                <div class="facility-card" data-reveal="fade-up">
                    <div class="facility-img-wrapper">
                        <span class="badge badge-amber facility-badge">{{ $article['category'] }}</span>
                        <img src="{{ $article['image'] }}" alt="{{ $article['title'] }}" class="facility-img" loading="lazy">
                    </div>
                    <div class="facility-body">
                        <div style="font-size: 0.8rem; color: #78716c; font-weight: 600; margin-bottom: 0.5rem;">
                            {{ $article['date'] }} &bull; {{ $article['read_time'] }}
                        </div>
                        <h4 style="font-size: 1.15rem; margin-bottom: 0.65rem; line-height: 1.4;">
                            {{ $article['title'] }}
                        </h4>
                        <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem; line-height: 1.6;">
                            {{ $article['excerpt'] }}
                        </p>
                        <a href="{{ route('contact') }}" class="btn btn-sm btn-outline" style="width: 100%;">
                            Read Story
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Upcoming Academic Calendar & Highlights -->
<section class="section section-light-alt">
    <div class="container">
        <div class="section-header" data-reveal="fade-up">
            <span class="section-subtitle">Official Schedule</span>
            <h2 class="section-title">Upcoming Term Calendar</h2>
            <p class="section-desc">Key milestones, exams, sports fixtures, and community parent meetings.</p>
        </div>

        <div class="grid-3">
            @forelse ($calendar as $event)
                <div class="card" data-reveal="fade-up" style="padding: 2rem; transition-delay: {{ $loop->index * 0.15 }}s;">
                    <span class="badge {{ ['badge-amber', 'badge-primary', 'badge-dark'][$loop->index % 3] }}" style="margin-bottom: 1rem;">{{ $event->starts_on->format('d F Y') }}</span>
                    <h4 style="margin-bottom: 0.75rem;">{{ $event->title }}</h4>
                    @if ($event->description)
                        <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1rem;">{{ $event->description }}</p>
                    @endif
                    @if ($event->venue)
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--primary);">Venue: {{ $event->venue }}</div>
                    @endif
                </div>
            @empty
                <p style="grid-column: 1 / -1; text-align: center; color: #57534e;">The next term calendar will be published soon.</p>
            @endforelse
        </div>
    </div>
</section>

@include('partials.cta-banner')

@endsection

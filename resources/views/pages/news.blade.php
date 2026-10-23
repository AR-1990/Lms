@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')

@include('partials.page-hero', ['page' => $page])

<!-- News & Events with category filters -->
<section class="section section-white">
    <div class="container">
        @php($categories = collect($articles)->pluck('category')->filter()->unique()->values())
        @if ($categories->count() > 1)
            <div class="tabs-nav" data-news-filters data-reveal="fade-up">
                <button type="button" class="tab-btn active" data-news-filter="">All News & Events</button>
                @foreach ($categories as $category)
                    <button type="button" class="tab-btn" data-news-filter="{{ $category }}">{{ $category }}</button>
                @endforeach
            </div>
        @endif

        <!-- Featured Article Highlight -->
        @if(count($articles) > 0)
            @php($featured = $articles[0])
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
                <div class="facility-card" data-reveal="fade-up" data-news-category="{{ $article['category'] }}">
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
        @if ($calendarSection = $page->section('calendar'))
            @include('partials.section-header', ['section' => $calendarSection])
        @endif

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

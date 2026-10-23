@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')

@include('partials.page-hero', ['page' => $page])

@if ($section = $page->section('facilities'))
<!-- Facilities -->
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

@php($safety = $page->section('safety'))
@php($tour = $page->section('tour'))
@if ($safety || $tour)
<!-- Safety & Campus Tour -->
<section class="section section-light-alt">
    <div class="container">
        <div class="grid-2" style="align-items: center; gap: 3.5rem;">
            @if ($safety)
                <div data-reveal="fade-right">
                    <span class="section-subtitle">{{ $safety->subtitle }}</span>
                    <h2 class="section-title">{{ \App\Models\WebsitePage::highlight($safety->title) }}</h2>
                    <p style="font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin-bottom: 1.5rem;">{{ $safety->description }}</p>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        @foreach ($safety->items as $item)
                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <div style="width: 40px; height: 40px; border-radius: 10px; {{ $item->toneStyle() }} display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: bold;">
                                    @include('partials.site-icon', ['name' => $item->icon, 'size' => 20])
                                </div>
                                <div>
                                    <h5 style="font-size: 1.05rem; margin-bottom: 0.2rem;">{{ $item->title }}</h5>
                                    <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 0;">{{ $item->body }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($tour)
                <div data-reveal="fade-left">
                    <div class="card" style="padding: 2.75rem; border-radius: var(--radius-xl); background: #ffffff; box-shadow: var(--shadow-xl);">
                        @if ($tour->badge)<span class="badge badge-amber" style="margin-bottom: 1rem;">{{ $tour->badge }}</span>@endif
                        <h3 style="margin-bottom: 1rem; font-size: 1.45rem;">{{ $tour->title }}</h3>
                        <p style="font-size: 0.95rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.75rem;">{{ $tour->description }}</p>
                        @if ($tour->cta_label)
                            <a href="{{ \App\Models\WebsitePage::link($tour->cta_url) }}" class="btn btn-primary btn-lg" style="width: 100%;">{{ $tour->cta_label }}</a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

@include('partials.cta-banner')

@endsection

@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')

@include('partials.page-hero', ['page' => $page])

@if ($section = $page->section('principal'))
<!-- Principal's Message -->
<section class="section section-white">
    <div class="container">
        <div class="grid-2" style="align-items: center; gap: 4rem;">
            <div data-reveal="fade-right">
                <div style="position: relative; max-width: 440px; margin: 0 auto;">
                    @if ($section->image_path)
                        <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl); border: 4px solid #ffffff;">
                            <img src="{{ $section->imageUrl() }}" alt="{{ $section->image_caption }}" style="width: 100%; height: auto; display: block;">
                        </div>
                    @endif
                    @if ($section->image_caption)
                        <div style="position: absolute; bottom: -1.5rem; right: -1.5rem; background: var(--bg-card); padding: 1.25rem 1.75rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
                            <strong style="display: block; font-size: 1.1rem; color: var(--midnight);">{{ $section->image_caption }}</strong>
                            <span style="font-size: 0.825rem; color: var(--primary); font-weight: 700;">{{ $section->image_note }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div data-reveal="fade-left">
                <span class="section-subtitle">{{ $section->subtitle }}</span>
                <h2 class="section-title">{{ \App\Models\WebsitePage::highlight($section->title) }}</h2>
                <p style="font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin-bottom: 1.25rem;">{{ $section->description }}</p>
                @if ($section->body)
                    <p style="font-size: 0.95rem; line-height: 1.75; color: var(--text-secondary); margin-bottom: 1.75rem;">{{ $section->body }}</p>
                @endif
                <div style="display: flex; gap: 1.5rem; align-items: center;">
                    @if ($section->cta_label)<a href="{{ \App\Models\WebsitePage::link($section->cta_url) }}" class="btn btn-primary">{{ $section->cta_label }}</a>@endif
                    @if ($section->cta2_label)<a href="{{ \App\Models\WebsitePage::link($section->cta2_url) }}" class="btn btn-outline">{{ $section->cta2_label }}</a>@endif
                </div>
            </div>
        </div>
    </div>
</section>
@endif

@if ($section = $page->section('philosophy'))
<!-- Vision, Mission and Core Pillars -->
<section class="section section-light-alt">
    <div class="container">
        @include('partials.section-header', ['section' => $section])

        <div class="grid-3">
            @foreach ($section->items as $item)
                <div class="glow-card" data-reveal="fade-up" style="padding: 2.5rem; transition-delay: {{ ($loop->index % 3) * 0.15 }}s;">
                    <div class="card-icon" style="{{ $item->toneStyle() }}">@include('partials.site-icon', ['name' => $item->icon, 'size' => 28])</div>
                    <h3 class="card-title">{{ $item->title }}</h3>
                    <p class="card-body">{{ $item->body }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@include('partials.cta-banner')

@endsection

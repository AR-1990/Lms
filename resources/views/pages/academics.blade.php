@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')

@include('partials.page-hero', ['page' => $page])

@if ($section = $page->section('wings'))
<!-- Academic Wings -->
<section class="section section-white">
    <div class="container">
        @include('partials.section-header', ['section' => $section])

        <div class="tabs-nav" data-tabs data-reveal="fade-up">
            @foreach ($section->items as $wing)
                <button class="tab-btn {{ $loop->first ? 'active' : '' }}" data-tab-target="{{ $wing->value ?: 'wing-'.$wing->id }}">{{ $wing->subtitle ?: $wing->title }}</button>
            @endforeach
        </div>

        <div class="tab-content" style="margin-top: 2.5rem;">
            @foreach ($section->items as $wing)
                <div class="tab-pane {{ $loop->first ? 'active' : '' }}" id="{{ $wing->value ?: 'wing-'.$wing->id }}">
                    <div class="grid-2" style="align-items: center; gap: 3.5rem;">
                        <div @if ($loop->first) data-reveal="fade-right" @endif>
                            @if ($wing->badge)<span class="badge {{ $wing->badgeClass() }}" style="margin-bottom: 0.75rem;">{{ $wing->badge }}</span>@endif
                            <h3 style="font-size: 1.85rem; margin-bottom: 1rem;">{{ $wing->title }}</h3>
                            <p style="font-size: 1rem; color: var(--text-secondary); line-height: 1.7; margin-bottom: 1.5rem;">{{ $wing->body }}</p>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                                @foreach ($wing->children as $feature)
                                    <div class="program-feature-item">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24676b" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <strong>{{ $feature->title }}</strong> {{ $feature->body }}
                                    </div>
                                @endforeach
                            </div>
                            @if ($wing->link_label)<a href="{{ \App\Models\WebsitePage::link($wing->link_url) }}" class="btn btn-primary">{{ $wing->link_label }}</a>@endif
                        </div>
                        @if ($wing->image_path)
                            <div @if ($loop->first) data-reveal="fade-left" @endif>
                                <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl);">
                                    <img src="{{ $wing->imageUrl() }}" alt="{{ $wing->title }}" style="width: 100%; height: auto;">
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($section = $page->section('stem'))
<!-- STEM & Robotics Studio -->
<section class="section section-light-alt" id="stem-program">
    <div class="container">
        <div class="grid-2" style="align-items: center; gap: 3.5rem;">
            @if ($section->image_path)
                <div data-reveal="fade-right">
                    <div style="border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-xl);">
                        <img src="{{ $section->imageUrl() }}" alt="{{ $section->title }}" style="width: 100%; height: auto;">
                    </div>
                </div>
            @endif

            <div data-reveal="fade-left">
                <span class="section-subtitle">{{ $section->subtitle }}</span>
                <h2 class="section-title">{{ \App\Models\WebsitePage::highlight($section->title) }}</h2>
                <p style="font-size: 1.05rem; line-height: 1.8; color: var(--text-secondary); margin-bottom: 1.5rem;">{{ $section->description }}</p>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;">
                    @foreach ($section->items as $tag)
                        <span class="badge {{ $tag->badgeClass() }}">{{ $tag->title }}</span>
                    @endforeach
                </div>
                @if ($section->cta_label)<a href="{{ \App\Models\WebsitePage::link($section->cta_url) }}" class="btn btn-primary btn-lg">{{ $section->cta_label }}</a>@endif
            </div>
        </div>
    </div>
</section>
@endif

@include('partials.cta-banner')

@endsection

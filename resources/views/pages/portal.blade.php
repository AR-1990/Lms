@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')

@include('partials.page-hero', ['page' => $page])

@if ($section = $page->section('gateways'))
<!-- Portal Gateways -->
<section class="section section-white">
    <div class="container">
        @include('partials.section-header', ['section' => $section])

        <div class="grid-3">
            @foreach ($section->items as $item)
                <div class="portal-card" data-reveal="fade-up" style="transition-delay: {{ ($loop->index % 3) * 0.15 }}s;">
                    <div class="portal-icon" style="{{ $item->toneStyle() }}">@include('partials.site-icon', ['name' => $item->icon, 'size' => 32])</div>
                    <h3 class="card-title">{{ $item->title }}</h3>
                    <p class="card-body" style="margin-bottom: 1.5rem;">{{ $item->body }}</p>
                    @if ($item->link_label)
                        <div style="margin-top: auto;">
                            <a href="{{ \App\Models\WebsitePage::link($item->link_url) }}" class="btn {{ $item->value === 'outline' ? 'btn-outline' : 'btn-primary' }}" style="width: 100%;">{{ $item->link_label }}</a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection

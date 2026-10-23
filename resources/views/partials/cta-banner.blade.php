@php($cta = $site['page']->section('cta'))
@if ($cta)
<div class="container" style="margin-top: 2rem; margin-bottom: 2rem;">
    <div class="cta-box">
        <div class="cta-content">
            @if ($cta->badge)<span class="badge badge-amber" style="margin-bottom: 1rem;">{{ $cta->badge }}</span>@endif
            <h2 class="cta-title">{{ \App\Models\WebsitePage::highlight($cta->title) }}</h2>
            <p class="cta-desc">{{ $cta->description }}</p>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                @if ($cta->cta_label)
                    <a href="{{ \App\Models\WebsitePage::link($cta->cta_url) }}" class="btn btn-white btn-lg">
                        {{ $cta->cta_label }}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                @endif
                @if ($cta->cta2_label)
                    <a href="{{ \App\Models\WebsitePage::link($cta->cta2_url) }}" class="btn btn-outline btn-lg" style="color: #ffffff; border-color: rgba(255,255,255,0.4);">{{ $cta->cta2_label }}</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

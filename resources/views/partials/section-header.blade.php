{{-- Centered block heading (subtitle, title, description). Param: section. --}}
<div class="section-header" data-reveal="fade-up">
    @if ($section->subtitle)<span class="section-subtitle">{{ $section->subtitle }}</span>@endif
    <h2 class="section-title">{{ \App\Models\WebsitePage::highlight($section->title) }}</h2>
    @if ($section->description)<p class="section-desc">{{ $section->description }}</p>@endif
</div>

{{-- A facility card with picture, badge, text and link. Param: item. --}}
<div class="facility-card" data-reveal="fade-up" style="transition-delay: {{ ($loop->index ?? 0) % 3 * 0.15 }}s;">
    <div class="facility-img-wrapper">
        @if ($item->badge)<span class="badge {{ $item->badgeClass() }} facility-badge">{{ $item->badge }}</span>@endif
        @if ($item->image_path)<img src="{{ $item->imageUrl() }}" alt="{{ $item->title }}" class="facility-img" loading="lazy">@endif
    </div>
    <div class="facility-body">
        <h4 style="font-size: 1.25rem; margin-bottom: 0.5rem;">{{ $item->title }}</h4>
        <p style="font-size: 0.9rem; color: #57534e; margin-bottom: 1.25rem;">{{ $item->body }}</p>
        @if ($item->link_label)<a href="{{ \App\Models\WebsitePage::link($item->link_url) }}" class="btn btn-sm btn-outline">{{ $item->link_label }}</a>@endif
    </div>
</div>

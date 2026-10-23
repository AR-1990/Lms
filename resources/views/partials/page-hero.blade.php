{{-- Hero banner of an inner page from its website_pages row. Param: page. --}}
<section class="page-hero-banner" style="background-image: linear-gradient(135deg, rgba(28,25,23,0.92) 0%, rgba(28,25,23,0.78) 100%), url('{{ $page->hero_image_path ? \App\Models\WebsitePage::imageUrl($page->hero_image_path) : asset('images/hero-campus.jpg') }}');">
    <div class="container">
        <div class="hero-banner-content">
            @if ($page->hero_pill)
                <div class="slide-pill">
                    <span class="slide-pill-dot"></span>
                    <span>{{ $page->hero_pill }}</span>
                </div>
            @endif
            <h1 class="hero-banner-title">{{ \App\Models\WebsitePage::highlight($page->hero_title) }}</h1>
            @if ($page->hero_text)
                <p class="hero-banner-desc">{{ $page->hero_text }}</p>
            @endif
        </div>
    </div>
</section>

@php($settings = $site['settings'])
@php($sitePage = $site['page'])
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-brand">
                <a href="{{ route('home') }}" class="brand" style="margin-bottom: 1rem;">
                    <div class="brand-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    </div>
                    <div class="brand-text">
                        <span class="brand-name" style="color: #ffffff;">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::before($settings->school_name ?: 'Pinnacle', ' ')) }}</span>
                        <span class="brand-tagline" style="color: #fde047;">{{ $settings->school_tagline }}</span>
                    </div>
                </a>
                <p>{{ $settings->footer_text }}</p>
                @if ($social = $sitePage->section('social'))
                    <div class="footer-socials">
                        @foreach ($social->items as $link)
                            <a href="{{ \App\Models\WebsitePage::link($link->link_url) }}" class="social-link" aria-label="{{ $link->title }}" @if (! str_starts_with((string) $link->link_url, '/') && $link->link_url !== '#') target="_blank" rel="noopener" @endif>
                                @include('partials.site-icon', ['name' => $link->icon, 'size' => 18])
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @foreach (['footer_links_1', 'footer_links_2'] as $key)
                @if ($column = $sitePage->section($key))
                    <div>
                        <h4 class="footer-heading">{{ $column->title }}</h4>
                        <ul class="footer-links">
                            @foreach ($column->items as $link)
                                <li><a href="{{ \App\Models\WebsitePage::link($link->link_url) }}" class="footer-link" @if ($link->tone === 'amber') style="color: #fde047; font-weight: 700;" @endif>{{ $link->title }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach

            <!-- Contact Information -->
            <div>
                <h4 class="footer-heading">Campus Address</h4>
                <div class="footer-contact-item">
                    <div class="footer-contact-icon">@include('partials.site-icon', ['name' => 'map', 'size' => 18])</div>
                    <span>{{ $settings->school_address }}</span>
                </div>
                <div class="footer-contact-item">
                    <div class="footer-contact-icon">@include('partials.site-icon', ['name' => 'phone', 'size' => 18])</div>
                    <span>@if ($settings->admissions_phone)Admissions: {{ $settings->admissions_phone }}<br>@endif Office: {{ $settings->school_phone }}</span>
                </div>
                @if ($settings->contact_email)
                    <div class="footer-contact-item">
                        <div class="footer-contact-icon">@include('partials.site-icon', ['name' => 'mail', 'size' => 18])</div>
                        <span>{{ $settings->contact_email }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="footer-bottom">
            <div>
                &copy; {{ date('Y') }} {{ $settings->school_name }}. All Rights Reserved.
            </div>
            @if ($bottom = $sitePage->section('footer_bottom'))
                <div style="display: flex; gap: 1.5rem;">
                    @foreach ($bottom->items as $link)
                        <a href="{{ \App\Models\WebsitePage::link($link->link_url) }}" class="footer-link">{{ $link->title }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</footer>

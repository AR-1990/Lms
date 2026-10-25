@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')
@php
    $channels = $page->section('channels');
    $form = $page->section('form');
    $admissionsPhone = $settings->admissions_phone ?: $settings->school_phone;
    $whatsapp = preg_replace('/[^0-9]/', '', (string) $settings->whatsapp_number);
    $email = $settings->admissions_email ?: $settings->contact_email;
    $mapQuery = urlencode((string) $settings->school_address);
@endphp

@include('partials.page-hero', ['page' => $page])

<!-- Quick ways to reach the school -->
<section class="contact-quick-wrap">
    <div class="container">
        <div class="contact-quick" data-reveal="fade-up">
            @if ($admissionsPhone)
                <a class="contact-quick-card tone-blue" href="tel:{{ preg_replace('/[^0-9+]/', '', $admissionsPhone) }}">
                    <span class="contact-quick-icon">@include('partials.site-icon', ['name' => 'phone', 'size' => 22])</span>
                    <span class="contact-quick-text"><small>Call admissions</small><strong>{{ $admissionsPhone }}</strong></span>
                    <span class="contact-quick-go" aria-hidden="true">&rarr;</span>
                </a>
            @endif
            @if ($whatsapp)
                <a class="contact-quick-card tone-emerald" href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener">
                    <span class="contact-quick-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm4.53 11.98c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.65.81-.79.98-.15.17-.3.19-.54.07-.25-.13-1.04-.38-1.99-1.22-.73-.65-1.23-1.46-1.38-1.71-.14-.25-.01-.39.11-.51.11-.11.25-.28.37-.43.13-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.44 1.03 2.61c.13.17 1.78 2.72 4.31 3.81.6.26 1.07.42 1.44.54.61.19 1.16.17 1.6.1.49-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.1-.23-.17-.48-.29z"/></svg></span>
                    <span class="contact-quick-text"><small>WhatsApp</small><strong>Chat with us</strong></span>
                    <span class="contact-quick-go" aria-hidden="true">&rarr;</span>
                </a>
            @endif
            @if ($email)
                <a class="contact-quick-card tone-orange" href="mailto:{{ $email }}">
                    <span class="contact-quick-icon">@include('partials.site-icon', ['name' => 'mail', 'size' => 22])</span>
                    <span class="contact-quick-text"><small>Email</small><strong>{{ $email }}</strong></span>
                    <span class="contact-quick-go" aria-hidden="true">&rarr;</span>
                </a>
            @endif
            @if ($settings->school_address)
                <a class="contact-quick-card tone-violet" href="https://www.google.com/maps/dir/?api=1&destination={{ $mapQuery }}" target="_blank" rel="noopener">
                    <span class="contact-quick-icon">@include('partials.site-icon', ['name' => 'map', 'size' => 22])</span>
                    <span class="contact-quick-text"><small>Visit the campus</small><strong>Get directions</strong></span>
                    <span class="contact-quick-go" aria-hidden="true">&rarr;</span>
                </a>
            @endif
        </div>
    </div>
</section>

<!-- Message form and campus details -->
<section class="section section-white contact-main" id="contact-form">
    <div class="container">
        <div class="contact-layout">
            <div class="contact-form-card" data-reveal="fade-up" data-contact>
                <div class="contact-form-head">
                    @if ($form?->badge)<span class="badge badge-amber">{{ $form->badge }}</span>@endif
                    <h2>{{ $form?->title ?? 'Send us a message' }}</h2>
                    @if ($form?->description)<p>{{ $form->description }}</p>@endif
                </div>

                <form data-ajax-inquiry data-inline-errors action="{{ route('inquiry.submit') }}" method="POST" novalidate>
                    @csrf

                    @if ($topics->isNotEmpty())
                        <fieldset class="contact-topics">
                            <legend class="form-label">What is it about?</legend>
                            <div class="contact-chips">
                                @foreach ($topics as $topic)
                                    <label class="contact-chip">
                                        <input type="radio" name="topic_id" value="{{ $topic->id }}" @checked($loop->first)>
                                        <span>{{ $topic->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <div class="contact-grid">
                        <div class="form-group contact-field">
                            <label class="form-label" for="cntName">Your full name</label>
                            <input type="text" id="cntName" name="parent_name" class="form-control" placeholder="e.g. Asim Rauf" autocomplete="name" maxlength="255" required>
                            <p class="contact-error" data-error-for="parent_name" hidden></p>
                        </div>
                        <div class="form-group contact-field">
                            <label class="form-label" for="cntPhone">Mobile / WhatsApp</label>
                            <input type="tel" id="cntPhone" name="phone" class="form-control" placeholder="0300 1234567" autocomplete="tel" inputmode="tel" maxlength="30" required>
                            <p class="contact-error" data-error-for="phone" hidden></p>
                        </div>
                        <div class="form-group contact-field contact-span">
                            <label class="form-label" for="cntEmail">Email <span class="contact-optional">optional</span></label>
                            <input type="email" id="cntEmail" name="email" class="form-control" placeholder="you@example.com" autocomplete="email" maxlength="255">
                            <p class="contact-error" data-error-for="email" hidden></p>
                        </div>
                    </div>

                    <label class="contact-toggle">
                        <input type="checkbox" data-child-toggle>
                        <span class="contact-toggle-track" aria-hidden="true"><span></span></span>
                        <span>I am asking about admission for my child</span>
                    </label>

                    <div class="contact-child" data-child-fields hidden>
                        <div class="contact-grid">
                            <div class="form-group contact-field">
                                <label class="form-label" for="cntChild">Child's name</label>
                                <input type="text" id="cntChild" name="child_name" class="form-control" placeholder="e.g. Ayesha" maxlength="255">
                                <p class="contact-error" data-error-for="child_name" hidden></p>
                            </div>
                            <div class="form-group contact-field">
                                <label class="form-label" for="cntClass">Class wanted</label>
                                <select id="cntClass" name="class_sought_id" class="form-control">
                                    <option value="">Not sure yet</option>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                                    @endforeach
                                </select>
                                <p class="contact-error" data-error-for="class_sought_id" hidden></p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group contact-field">
                        <label class="form-label" for="cntMessage">Your message</label>
                        <textarea id="cntMessage" name="message" class="form-control" rows="5" maxlength="2000" placeholder="How can we help you?" required data-count></textarea>
                        <div class="contact-under">
                            <p class="contact-error" data-error-for="message" hidden></p>
                            <span class="contact-count" data-count-for="cntMessage">0 / 2000</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg contact-submit">
                        <span class="contact-submit-text">Send message</span>
                        <span class="contact-spinner" aria-hidden="true"></span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                    </button>
                </form>

                <div class="contact-done" data-contact-done hidden>
                    <svg class="contact-done-check" width="64" height="64" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="29" /><path d="M20 33 l8 8 16-17" /></svg>
                    <h3>Message sent</h3>
                    <p data-contact-done-text></p>
                    <button type="button" class="btn btn-outline" data-contact-again>Send another message</button>
                </div>
            </div>

            <aside class="contact-side" data-reveal="fade-left">
                @if ($channels)
                    <span class="section-subtitle">{{ $channels->subtitle }}</span>
                    <h2 class="contact-side-title">{{ \App\Models\WebsitePage::highlight($channels->title) }}</h2>
                    @if ($channels->description)<p class="contact-side-desc">{{ $channels->description }}</p>@endif
                @endif

                <ul class="contact-details">
                    @if ($settings->school_address)
                        <li>
                            <span class="contact-detail-icon tone-orange">@include('partials.site-icon', ['name' => 'map', 'size' => 18])</span>
                            <div><small>Campus address</small><p>{{ $settings->school_address }}</p></div>
                        </li>
                    @endif
                    @if ($admissionsPhone || $settings->school_phone)
                        <li>
                            <span class="contact-detail-icon tone-blue">@include('partials.site-icon', ['name' => 'phone', 'size' => 18])</span>
                            <div>
                                <small>Phone</small>
                                <p>
                                    @if ($settings->admissions_phone)Admissions <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->admissions_phone) }}">{{ $settings->admissions_phone }}</a><br>@endif
                                    @if ($settings->school_phone)Front desk <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->school_phone) }}">{{ $settings->school_phone }}</a>@endif
                                </p>
                            </div>
                        </li>
                    @endif
                    @if ($settings->office_hours || $settings->visiting_hours)
                        <li>
                            <span class="contact-detail-icon tone-emerald">@include('partials.site-icon', ['name' => 'clock', 'size' => 18])</span>
                            <div><small>Office hours</small><p>{!! nl2br(e($settings->visiting_hours ?: $settings->office_hours)) !!}</p></div>
                        </li>
                    @endif
                </ul>

                @if ($settings->school_address)
                    <div class="contact-map" data-map>
                        <iframe title="Map of {{ $settings->school_name }}" data-src="https://maps.google.com/maps?q={{ $mapQuery }}&z=15&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        <button type="button" class="contact-map-cover" data-map-load>
                            @include('partials.site-icon', ['name' => 'map', 'size' => 26])
                            <span>Show the map</span>
                        </button>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</section>

@if ($faqs->isNotEmpty())
<!-- Frequently Asked Questions -->
<section class="section section-light-alt">
    <div class="container">
        @if ($faqSection = $page->section('faq'))
            @include('partials.section-header', ['section' => $faqSection])
        @endif

        <div class="faq-list" style="max-width: 840px; margin: 0 auto;" data-reveal="fade-up">
            @foreach ($faqs as $faq)
                <div class="faq-item">
                    <button class="faq-trigger" type="button">
                        <span>{{ $faq->question }}</span>
                        <span class="faq-icon">&darr;</span>
                    </button>
                    <div class="faq-content">
                        {{ $faq->answer }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@endsection

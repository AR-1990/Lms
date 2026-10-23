@extends('layouts.school')

@section('title', $page->meta_title ?: $page->name)
@section('meta_description', (string) $page->meta_description)

@section('content')

@include('partials.page-hero', ['page' => $page])

<!-- Contact Details & Inquiry Form -->
<section class="section section-white">
    <div class="container">
        <div class="grid-2" style="gap: 3.5rem; align-items: flex-start;">
            @php($channels = $page->section('channels'))
            <div data-reveal="fade-right">
                @if ($channels)
                    <span class="section-subtitle">{{ $channels->subtitle }}</span>
                    <h2 class="section-title">{{ \App\Models\WebsitePage::highlight($channels->title) }}</h2>
                    <p class="section-desc" style="margin-bottom: 2rem;">{{ $channels->description }}</p>
                @endif

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div class="card" style="padding: 1.75rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                        <div class="card-icon card-icon-sm tone-orange">@include('partials.site-icon', ['name' => 'map'])</div>
                        <div>
                            <h4 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Campus Address</h4>
                            <p style="font-size: 0.925rem; color: #64748b; margin-bottom: 0;">{{ $settings->school_address }}</p>
                        </div>
                    </div>

                    <div class="card" style="padding: 1.75rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                        <div class="card-icon card-icon-sm tone-blue">@include('partials.site-icon', ['name' => 'phone'])</div>
                        <div>
                            <h4 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Phone & WhatsApp</h4>
                            <p style="font-size: 0.925rem; color: #64748b; margin-bottom: 0;">
                                @if ($settings->admissions_phone)
                                    Admissions: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings->admissions_phone) }}" style="font-weight: 700; color: var(--primary);">{{ $settings->admissions_phone }}</a><br>
                                @endif
                                Front Desk: <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $settings->school_phone) }}" style="font-weight: 700; color: var(--primary);">{{ $settings->school_phone }}</a>
                            </p>
                        </div>
                    </div>

                    @if ($settings->visiting_hours)
                        <div class="card" style="padding: 1.75rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                            <div class="card-icon card-icon-sm tone-emerald">@include('partials.site-icon', ['name' => 'clock'])</div>
                            <div>
                                <h4 style="font-size: 1.15rem; margin-bottom: 0.25rem;">Office Visiting Hours</h4>
                                <p style="font-size: 0.925rem; color: #64748b; margin-bottom: 0;">{!! nl2br(e($settings->visiting_hours)) !!}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @php($form = $page->section('form'))
            <div data-reveal="fade-left">
                <div class="card" style="padding: 2.75rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl);">
                    @if ($form?->badge)<span class="badge badge-amber" style="margin-bottom: 0.75rem;">{{ $form->badge }}</span>@endif
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.45rem;">{{ $form?->title ?? 'Send Us a Message' }}</h3>
                    <p style="font-size: 0.925rem; margin-bottom: 1.5rem;">{{ $form?->description }}</p>

                    <form data-ajax-inquiry action="{{ route('inquiry.submit') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label" for="cntName">Your Full Name</label>
                            <input type="text" id="cntName" name="parent_name" class="form-control" placeholder="e.g. Asim Rauf" required>
                        </div>

                        <div class="grid-2" style="gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="cntPhone">Mobile / WhatsApp Number</label>
                                <input type="tel" id="cntPhone" name="phone" class="form-control" placeholder="0300-XXXXXXX" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="cntEmail">Email Address</label>
                                <input type="email" id="cntEmail" name="email" class="form-control" placeholder="asim@example.com">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="cntDept">Inquiry Type / Department</label>
                            <select id="cntDept" name="topic_id" class="form-control">
                                @foreach ($topics as $topic)
                                    <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="cntMessage">Message or Query</label>
                            <textarea id="cntMessage" name="message" class="form-control" rows="4" placeholder="How can we assist you today?" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            Send Inquiry Message
                        </button>
                    </form>
                </div>
            </div>
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

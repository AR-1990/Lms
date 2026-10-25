{{-- Admissions helpline: a small round button on every public page that opens WhatsApp / call / write-to-us. --}}
@php
    $helplinePhone = $settings->admissions_phone ?: $settings->school_phone;
    $helplineWhatsapp = preg_replace('/[^0-9]/', '', (string) $settings->whatsapp_number);
@endphp
@if ($helplinePhone || $helplineWhatsapp)
<div class="helpline" data-helpline>
    <div class="helpline-panel" id="helplinePanel" role="dialog" aria-label="Admissions helpline" aria-hidden="true">
        <div class="helpline-head">
            <span class="helpline-dot" aria-hidden="true"></span>
            <div>
                <strong>Admissions helpline</strong>
                <span>{{ $settings->office_hours ?: 'We usually reply within a working day' }}</span>
            </div>
        </div>
        <div class="helpline-actions">
            @if ($helplineWhatsapp)
                <a href="https://wa.me/{{ $helplineWhatsapp }}" target="_blank" rel="noopener" class="helpline-action is-whatsapp" tabindex="-1">
                    <span class="helpline-action-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm4.53 11.98c-.25-.13-1.47-.72-1.7-.81-.23-.08-.39-.13-.56.13-.17.25-.65.81-.79.98-.15.17-.3.19-.54.07-.25-.13-1.04-.38-1.99-1.22-.73-.65-1.23-1.46-1.38-1.71-.14-.25-.01-.39.11-.51.11-.11.25-.28.37-.43.13-.14.17-.25.25-.42.08-.17.04-.31-.02-.44-.06-.13-.56-1.35-.77-1.85-.2-.49-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.88.86-.88 2.1s.9 2.44 1.03 2.61c.13.17 1.78 2.72 4.31 3.81.6.26 1.07.42 1.44.54.61.19 1.16.17 1.6.1.49-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.07-.1-.23-.17-.48-.29z"/></svg></span>
                    <span><strong>Chat on WhatsApp</strong><small>Quick answers from the admissions desk</small></span>
                </a>
            @endif
            @if ($helplinePhone)
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $helplinePhone) }}" class="helpline-action is-call" tabindex="-1">
                    <span class="helpline-action-icon">@include('partials.site-icon', ['name' => 'phone', 'size' => 18])</span>
                    <span><strong>Call {{ $helplinePhone }}</strong><small>Talk to an admissions counsellor</small></span>
                </a>
            @endif
            <a href="{{ route('contact') }}#contact-form" class="helpline-action is-write" tabindex="-1">
                <span class="helpline-action-icon">@include('partials.site-icon', ['name' => 'mail', 'size' => 18])</span>
                <span><strong>Write to us</strong><small>We call you back</small></span>
            </a>
        </div>
    </div>

    <button type="button" class="helpline-toggle" aria-expanded="false" aria-controls="helplinePanel" aria-label="Admissions helpline">
        <svg class="helpline-icon-open" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.9-.9L3 21l1.9-5.1A8.4 8.4 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3h.5a8.5 8.5 0 0 1 8 8z"/><path d="M8.5 11.5h.01M12.5 11.5h.01M16.5 11.5h.01" stroke-width="2.6"/></svg>
        <svg class="helpline-icon-close" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        <span class="helpline-tip" aria-hidden="true">Need help?</span>
    </button>
</div>
@endif

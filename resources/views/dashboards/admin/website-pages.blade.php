@extends('layouts.dashboard')

@section('title', 'Website Pages')
@section('page_heading', 'Website')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>Website Pages</h1>
        <p>Every heading, paragraph, picture, card and link of the public website. Write [square brackets] around words to colour them in a title. Changes appear at once.</p>
    </div>
    <a class="dash-btn dash-btn-ghost" href="{{ route('home') }}" target="_blank" rel="noopener">@include('partials.dashboard-icon', ['name' => 'globe']) Open Website</a>
</section>

@include('partials.section-tabs', [
    'label' => 'Website',
    'current' => 'admin.website.pages',
    'tabs' => [
        'admin.website.pages' => ['Pages', 'layers', null],
        'admin.website.posts' => ['News & Notices', 'inbox', null],
        'admin.website.stats' => ['Stats', 'chart', null],
        'admin.website.testimonials' => ['Testimonials', 'heart', null],
        'admin.website.faqs' => ['FAQs', 'book', null],
    ],
])

<div class="dash-cms" data-cms data-api="{{ url('/api/erp/website') }}">
    <nav class="dash-cms-pages" data-cms-pages aria-label="Pages"><p class="dash-muted">Loading…</p></nav>

    <div class="dash-cms-editor" data-cms-editor>
        <section class="dash-panel" data-cms-page hidden>
            <form data-page-form novalidate>
                <div class="dash-panel-head">
                    <div>
                        <h2 data-page-name>Page</h2>
                        <p>Title and description for search engines, and the banner at the top of the page.</p>
                    </div>
                    <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save page</button>
                </div>
                <div class="dash-form-grid">
                @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Page name', 'type' => 'text', 'id' => 'Pagenameszmr'])
                @include('dashboards.partials.field', ['name' => 'meta_title', 'label' => 'Browser title', 'type' => 'text', 'id' => 'Browsertitleeibp'])
                @include('dashboards.partials.field', ['name' => 'meta_description', 'label' => 'Search description', 'type' => 'textarea', 'wide' => true, 'id' => 'Searchdescription25en'])
                @include('dashboards.partials.field', ['name' => 'hero_pill', 'label' => 'Banner tag', 'type' => 'text', 'id' => 'Bannertagqylb'])
                @include('dashboards.partials.field', ['name' => 'hero_title', 'label' => 'Banner title', 'type' => 'text', 'hint' => '[colour] words', 'id' => 'Bannertitle746j'])
                @include('dashboards.partials.field', ['name' => 'hero_text', 'label' => 'Banner text', 'type' => 'textarea', 'wide' => true, 'id' => 'Bannertextdsbh'])
                    <div class="dash-field dash-field-wide dash-cms-image" data-image-kind="page">
                        <label>Banner picture</label>
                        <img alt="" data-image-preview hidden>
                        <input type="file" accept="image/*" data-image-input aria-label="Upload banner picture">
                    </div>
                    <label class="dash-switch">
                        <input type="checkbox" name="is_published">
                        <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                        <span>Published</span>
                    </label>
                </div>
                <p class="dash-form-error" data-form-error hidden></p>
            </form>
        </section>

        <div data-cms-sections></div>
    </div>
</div>

<template data-section-template>
    <section class="dash-panel dash-cms-section">
        <form data-section-form novalidate>
            <div class="dash-panel-head">
                <div>
                    <h2 data-section-title>Block</h2>
                    <p class="dash-muted" data-section-key></p>
                </div>
                <div class="dash-head-actions">
                    <label class="dash-switch">
                        <input type="checkbox" name="is_active">
                        <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                        <span>Shown</span>
                    </label>
                    <button type="submit" class="dash-btn dash-btn-ghost" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save block</button>
                </div>
            </div>
            <div class="dash-form-grid">
                <div class="dash-field"><label>Small heading</label><input name="subtitle"></div>
                <div class="dash-field"><label>Title</label><input name="title"></div>
                <div class="dash-field dash-field-wide"><label>Text</label><textarea name="description" rows="2"></textarea></div>
                <div class="dash-field dash-field-wide"><label>More text <span class="dash-optional">optional</span></label><textarea name="body" rows="2"></textarea></div>
                <div class="dash-field"><label>Badge</label><input name="badge"></div>
                <div class="dash-field"><label>Picture caption</label><input name="image_caption"></div>
                <div class="dash-field"><label>Caption note</label><input name="image_note"></div>
                <div class="dash-field"><label>Button</label><input name="cta_label"></div>
                <div class="dash-field"><label>Button link</label><input name="cta_url" placeholder="/admissions"></div>
                <div class="dash-field"><label>Second button</label><input name="cta2_label"></div>
                <div class="dash-field"><label>Second button link</label><input name="cta2_url" placeholder="/contact"></div>
                <div class="dash-field dash-field-wide dash-cms-image" data-image-kind="section">
                    <label>Picture</label>
                    <img alt="" data-image-preview hidden>
                    <input type="file" accept="image/*" data-image-input aria-label="Upload picture">
                </div>
            </div>
            <p class="dash-form-error" data-form-error hidden></p>
        </form>
        <div class="dash-cms-items">
            <div class="dash-cms-items-head">
                <strong>Cards</strong>
                <button type="button" class="dash-btn dash-btn-ghost" data-add-item>@include('partials.dashboard-icon', ['name' => 'plus']) Add card</button>
            </div>
            <ul class="dash-cms-item-list" data-item-list></ul>
        </div>
    </section>
</template>

<dialog class="dash-modal dash-form-modal dash-modal-wide" id="cmsItemModal" aria-labelledby="cmsItemTitle">
    <form data-item-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'layers'])</span>
            <div>
                <h3 id="cmsItemTitle" data-item-title>Card</h3>
                <p>Fill only what this card shows; empty fields are simply left out.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            <div class="dash-field"><label for="cmsTitle">Title</label><input id="cmsTitle" name="title"><p class="dash-field-error" data-error-for="title" hidden></p></div>
            <div class="dash-field"><label for="cmsSubtitle">Sub-title / tab name</label><input id="cmsSubtitle" name="subtitle"></div>
            <div class="dash-field dash-field-wide"><label for="cmsBody">Text</label><textarea id="cmsBody" name="body" rows="3"></textarea></div>
            <div class="dash-field"><label for="cmsBadge">Badge</label><input id="cmsBadge" name="badge"></div>
            <div class="dash-field"><label for="cmsValue">Extra (number, footer text, anchor)</label><input id="cmsValue" name="value"></div>
            <div class="dash-field">
                <label for="cmsIcon">Icon</label>
                <select id="cmsIcon" name="icon">
                    <option value="">—</option>
                    @foreach (['graduation', 'shield', 'compass', 'users', 'heart', 'bus', 'list', 'help', 'code', 'sparkles', 'dollar', 'book', 'star', 'map', 'phone', 'mail', 'clock', 'facebook', 'instagram', 'youtube', 'linkedin'] as $icon)
                        <option value="{{ $icon }}">{{ ucfirst($icon) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dash-field">
                <label for="cmsTone">Colour</label>
                <select id="cmsTone" name="tone">
                    <option value="">—</option>
                    @foreach ($data['tones'] as $tone)
                        <option value="{{ $tone }}">{{ ucfirst($tone) }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="tone" hidden></p>
            </div>
            <div class="dash-field"><label for="cmsLink">Button</label><input id="cmsLink" name="link_label"></div>
            <div class="dash-field"><label for="cmsLinkUrl">Button link</label><input id="cmsLinkUrl" name="link_url" placeholder="/admissions"><p class="dash-field-error" data-error-for="link_url" hidden></p></div>
            <div class="dash-field"><label for="cmsLink2">Second button</label><input id="cmsLink2" name="link2_label"></div>
            <div class="dash-field"><label for="cmsLink2Url">Second button link</label><input id="cmsLink2Url" name="link2_url"><p class="dash-field-error" data-error-for="link2_url" hidden></p></div>
            <div class="dash-field"><label for="cmsOrder">Order</label><input id="cmsOrder" name="sort_order" type="number" min="0"></div>
            <label class="dash-switch">
                <input type="checkbox" name="is_active" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Shown</span>
            </label>
            <div class="dash-field dash-field-wide dash-cms-image" data-image-kind="item">
                <label>Picture</label>
                <img alt="" data-image-preview hidden>
                <input type="file" accept="image/*" data-image-input aria-label="Upload card picture">
            </div>
            <div class="dash-field dash-field-wide" data-children-box hidden>
                <span class="dash-label">Bullet points / rows inside this card</span>
                <ul class="dash-cms-children" data-children></ul>
                <button type="button" class="dash-link-btn" data-add-child>+ Add a point</button>
            </div>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save card</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
@include('partials.datatable-assets')
<script src="{{ asset('js/admin-website-pages.js') }}?v={{ filemtime(public_path('js/admin-website-pages.js')) }}" defer></script>
@endsection

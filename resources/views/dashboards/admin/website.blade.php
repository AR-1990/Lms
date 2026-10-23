@extends('layouts.dashboard')

@php
    $type = $data['type'];
    $screens = [
        'posts' => ['News & Notices', 'Articles on the News page; tick "ticker" to show the title in the home-page notice strip.', 'Add Post'],
        'stats' => ['Headline Stats', 'Counters in the home-page banner.', 'Add Stat'],
        'testimonials' => ['Testimonials', 'Parent and alumni quotes on the home page.', 'Add Testimonial'],
        'faqs' => ['FAQs', 'Questions and answers on the Contact page.', 'Add FAQ'],
    ];
    [$heading, $intro, $addLabel] = $screens[$type];
@endphp

@section('title', $heading)
@section('page_heading', 'Website')

@section('extra_css')
@include('partials.datatable-assets')
@endsection

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>{{ $heading }}</h1>
        <p>{{ $intro }} Changes appear on the public website at once.</p>
    </div>
    <button type="button" class="dash-btn dash-btn-primary" data-resource-create>
        @include('partials.dashboard-icon', ['name' => 'plus'])
        {{ $addLabel }}
    </button>
</section>

@include('partials.section-tabs', [
    'label' => 'Website',
    'current' => 'admin.website.'.$type,
    'tabs' => [
        'admin.website.pages' => ['Pages', 'layers', null],
        'admin.website.posts' => ['News & Notices', 'inbox', null],
        'admin.website.stats' => ['Stats', 'chart', null],
        'admin.website.testimonials' => ['Testimonials', 'heart', null],
        'admin.website.faqs' => ['FAQs', 'book', null],
    ],
])

<section class="dash-panel" data-website="{{ $type }}" data-datatable-url="{{ url('/api/erp/website/'.$type.'/datatable') }}" data-resource-url="{{ url('/api/erp/website/'.$type) }}">
    <div class="dash-grid-wrap">
        <table class="dash-table dash-datatable" data-grid>
            <thead>
                <tr>
                    @switch($type)
                        @case('posts')
                            <th>Post</th><th class="dash-hide-md">Published</th><th>Shown</th>
                            @break
                        @case('stats')
                            <th>Stat</th><th>Value</th><th>Status</th>
                            @break
                        @case('testimonials')
                            <th>Person</th><th class="dash-hide-md">Quote</th><th>Status</th>
                            @break
                        @default
                            <th>Question</th><th class="dash-hide-md">Answer</th><th>Status</th>
                    @endswitch
                    <th class="dash-col-actions"><span class="dash-visually-hidden">Actions</span></th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<dialog class="dash-modal dash-form-modal {{ $type === 'posts' ? 'dash-modal-wide' : '' }}" id="websiteModal" aria-labelledby="websiteModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'globe'])</span>
            <div>
                <h3 id="websiteModalTitle" data-modal-title>{{ $addLabel }}</h3>
                <p>{{ $intro }}</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid">
            @switch($type)
                @case('posts')
                    @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Title', 'required' => true, 'wide' => true])
                    @include('dashboards.partials.select', ['name' => 'category_id', 'label' => 'Category', 'options' => $data['news_categories']])
                    @include('dashboards.partials.field', ['name' => 'published_on', 'label' => 'Publish on', 'type' => 'date'])
                    @include('dashboards.partials.field', ['name' => 'excerpt', 'label' => 'Summary', 'type' => 'textarea', 'wide' => true])
                    @include('dashboards.partials.field', ['name' => 'body', 'label' => 'Full story', 'type' => 'textarea', 'wide' => true])
                    @include('dashboards.partials.field', ['name' => 'read_minutes', 'label' => 'Reading time (minutes)', 'type' => 'number', 'min' => 1])
                    <div class="dash-field">
                        <label for="postImage">Picture <span class="dash-optional">jpg / png, up to 4 MB</span></label>
                        <input type="file" id="postImage" accept="image/*" data-post-image>
                        <p class="dash-field-error" data-error-for="image" hidden></p>
                    </div>
                    @foreach (['is_published' => ['Published', true], 'is_featured' => ['Featured story', false], 'show_in_ticker' => ['Show in home-page ticker', false]] as $switchName => [$switchLabel, $switchOn])
                        <label class="dash-switch">
                            <input type="checkbox" name="{{ $switchName }}" @checked($switchOn)>
                            <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                            <span>{{ $switchLabel }}</span>
                        </label>
                    @endforeach
                    @break
                @case('stats')
                    @include('dashboards.partials.field', ['name' => 'label', 'label' => 'Label', 'required' => true, 'wide' => true])
                    @include('dashboards.partials.field', ['name' => 'value', 'label' => 'Number', 'type' => 'number', 'min' => 0, 'required' => true])
                    @include('dashboards.partials.field', ['name' => 'suffix', 'label' => 'Suffix', 'placeholder' => '+ or %'])
                    @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
                    @break
                @case('testimonials')
                    @include('dashboards.partials.field', ['name' => 'name', 'label' => 'Name', 'required' => true])
                    @include('dashboards.partials.field', ['name' => 'role_title', 'label' => 'Who they are', 'placeholder' => 'Parent of Grade 5 student'])
                    @include('dashboards.partials.field', ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'required' => true, 'wide' => true])
                    @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
                    @break
                @default
                    @include('dashboards.partials.field', ['name' => 'question', 'label' => 'Question', 'required' => true, 'wide' => true])
                    @include('dashboards.partials.field', ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'required' => true, 'wide' => true])
                    @include('dashboards.partials.field', ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'min' => 0])
            @endswitch
            @if ($type !== 'posts')
                <label class="dash-switch">
                    <input type="checkbox" name="is_active" checked>
                    <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                    <span>Show on website</span>
                </label>
            @endif
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save</button>
        </div>
    </form>
</dialog>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-website.js') }}?v={{ filemtime(public_path('js/admin-website.js')) }}" defer></script>
@endsection

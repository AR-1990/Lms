@extends('layouts.dashboard')

@section('title', 'Notices')
@section('page_heading', 'Notices')

@section('content')
<section class="dash-page-intro">
    <h1>Notices</h1>
    <p>Announcements from the school for you{{ $user->hasRole('parent') ? ' and your children' : '' }}. Open one to read it in full.</p>
</section>

<section class="dash-notices" data-notices data-read-url="{{ url('/api/erp/my-announcements') }}" data-open-id="{{ $data['open_id'] }}">
    @forelse ($data['announcements'] as $announcement)
        @php($myRead = $announcement->reads->first())
        <article @class(['dash-notice', 'is-unread' => $myRead === null, 'is-urgent' => $announcement->is_urgent, 'is-pinned' => $announcement->is_pinned]) data-notice="{{ $announcement->id }}" data-needs-ack="{{ $announcement->requires_acknowledgement && ! $myRead?->acknowledged_at ? '1' : '0' }}">
            <header class="dash-notice-head">
                <span class="dash-notice-badge" style="--badge: {{ $announcement->category?->color ?? '#1d2b45' }}">{{ $announcement->category?->name ?? 'Notice' }}</span>
                @if ($announcement->is_pinned)
                    <span class="dash-notice-flag">@include('partials.dashboard-icon', ['name' => 'pin']) Pinned</span>
                @endif
                @if ($announcement->is_urgent)
                    <span class="dash-notice-flag is-urgent">@include('partials.dashboard-icon', ['name' => 'alert']) Urgent</span>
                @endif
                <time class="dash-muted" datetime="{{ $announcement->publish_at?->toIso8601String() }}">{{ $announcement->publish_at?->format('d M Y, h:i A') }}</time>
            </header>
            <h2 class="dash-notice-title">{{ $announcement->title }}</h2>
            @if ($announcement->summary)
                <p class="dash-notice-summary">{{ $announcement->summary }}</p>
            @endif
            <div class="dash-notice-body" data-notice-body hidden>{!! nl2br(e($announcement->body)) !!}</div>
            <footer class="dash-notice-foot">
                <button type="button" class="dash-btn dash-btn-ghost" data-notice-toggle aria-expanded="false">Read more</button>
                @if ($announcement->requires_acknowledgement)
                    @if ($myRead?->acknowledged_at)
                        <span class="dash-badge dash-badge-ok">Confirmed {{ $myRead->acknowledged_at->format('d M') }}</span>
                    @else
                        <button type="button" class="dash-btn dash-btn-primary" data-notice-ack>@include('partials.dashboard-icon', ['name' => 'check']) I have read this</button>
                    @endif
                @endif
            </footer>
        </article>
    @empty
        <div class="dash-empty-note">
            @include('partials.dashboard-icon', ['name' => 'megaphone'])
            <p>No notices for you right now.</p>
        </div>
    @endforelse
</section>

@include('partials.dashboard-pagination', ['paginator' => $data['announcements']])
@endsection

@section('extra_js')
<script src="{{ asset('js/notices.js') }}?v={{ filemtime(public_path('js/notices.js')) }}" defer></script>
@endsection

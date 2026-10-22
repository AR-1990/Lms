@extends('layouts.dashboard')

@section('title', 'My ID Card')
@section('page_heading', 'My ID Card')

@section('content')
<section class="dash-page-intro">
    <h1>My ID Card</h1>
    <p>Cards the school has issued to you{{ $user->hasRole('parent') ? ' and your children' : '' }}. Open one to view, save or print it.</p>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Active cards</h2>
    </div>
    <ul class="dash-list">
        @forelse ($data['cards'] as $card)
            <li>
                <div>
                    <strong>{{ $card->holder()?->fullName() }}</strong>
                    <span class="dash-muted">{{ $card->card_no }} · {{ $card->template?->name }} · valid until {{ $card->valid_until?->format('d M Y') ?? '—' }}</span>
                </div>
                <a class="dash-btn dash-btn-ghost" href="{{ route('erp.my-id-cards.download', $card->id) }}" target="_blank" rel="noopener">View / Download</a>
            </li>
        @empty
            <li><span class="dash-muted">No card has been issued yet. Please contact the school office.</span></li>
        @endforelse
    </ul>
</section>
@endsection

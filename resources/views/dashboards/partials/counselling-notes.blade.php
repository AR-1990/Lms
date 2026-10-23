{{-- Non-confidential counselling sessions shared with a student and their parents (CounsellingService::shared). --}}
@if ($notes !== [])
<section class="dash-panel">
    <div class="dash-panel-head"><h2>{{ $title }}</h2></div>
    <ul class="dash-list dash-counselling-shared">
        @foreach ($notes as $note)
            <li>
                <div>
                    <strong>{{ \Illuminate\Support\Carbon::parse($note['session_date'])->format('d M Y') }}{{ $note['concern'] ? ' · '.$note['concern'] : '' }}</strong>
                    <span class="dash-muted">{{ $note['counsellor'] }}{{ $note['follow_up_on'] ? ' · next meeting '.\Illuminate\Support\Carbon::parse($note['follow_up_on'])->format('d M Y') : '' }}</span>
                    <p>{{ $note['notes'] }}</p>
                    @if ($note['action_plan'])
                        <p class="dash-muted">Plan: {{ $note['action_plan'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
@endif

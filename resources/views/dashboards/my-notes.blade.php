@extends('layouts.dashboard')

@section('title', 'My Notes')
@section('page_heading', 'My Notes')

@section('content')
<section class="dash-page-intro">
    <h1>My notes</h1>
    <p>Your private reminders. Done notes move to "Done" and are archived after 7 days.</p>
</section>

@include('partials.section-tabs', [
    'label' => 'Notes',
    'current' => 'my-notes'.($data['view'] === 'active' ? '' : '.'.$data['view']),
    'tabs' => [
        'my-notes' => ['On the board', 'pin', null],
        'my-notes.done' => ['Done', 'check', null],
        'my-notes.archived' => ['Archived', 'inbox', null],
    ],
])

@include('partials.sticky-notes', ['limit' => null, 'view' => $data['view']])
@endsection

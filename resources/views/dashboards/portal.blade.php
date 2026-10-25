@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('page_heading', 'Dashboard')

@section('content')
<section class="dash-page-intro">
    <h1>Welcome, {{ $user->name }}</h1>
    <p>Open a screen from the menu to get started.</p>
</section>

@include('partials.attendance-qr')

@include('partials.sticky-notes', ['limit' => 8])
@endsection

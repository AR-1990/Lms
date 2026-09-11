@extends('layouts.dashboard')

@section('title', 'Dashboard')
@section('page_heading', 'Dashboard')

@section('content')
<section class="dash-page-intro">
    <h1>Welcome to LMS</h1>
    <p>Your dashboard is ready. We will add each module step by step from the database-backed flows.</p>
</section>

<section class="dash-panel">
    <div class="dash-panel-head">
        <h2>Current Status</h2>
    </div>
    <p class="dash-text-muted">
        The dashboard is intentionally minimal right now. System settings remain available and connected to the database.
    </p>
</section>
@endsection

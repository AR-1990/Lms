@extends('layouts.dashboard')

@section('title', 'My Children')
@section('page_heading', 'My Children')

@section('content')
<section class="dash-page-intro">
    <h1>My Children</h1>
    <p>Class, attendance this session, latest result and fee balance.</p>
</section>

<section class="dash-panel">
    @include('dashboards.parent.children-table', ['children' => $data['children']])
</section>
@endsection

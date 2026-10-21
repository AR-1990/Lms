@extends('layouts.dashboard')

@section('title', 'Add User')
@section('page_heading', 'Add User')

@section('extra_css')
<link rel="stylesheet" href="{{ asset('css/admin-settings.css') }}">
@endsection

@section('content')
<a href="{{ route('admin.users') }}" class="dash-back-link">
    @include('partials.dashboard-icon', ['name' => 'arrow-left'])
    Back to users
</a>

@include('dashboards.admin.users.form', ['roles' => $data['roles']])
@endsection

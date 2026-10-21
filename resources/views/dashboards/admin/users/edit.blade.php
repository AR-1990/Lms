@extends('layouts.dashboard')

@section('title', 'Edit '.$data['account']->name)
@section('page_heading', 'Edit User')

@section('extra_css')
<link rel="stylesheet" href="{{ asset('css/admin-settings.css') }}">
@endsection

@section('content')
<a href="{{ route('admin.users.show', $data['account']) }}" class="dash-back-link">
    @include('partials.dashboard-icon', ['name' => 'arrow-left'])
    Back to {{ $data['account']->name }}
</a>

@include('dashboards.admin.users.form', ['account' => $data['account'], 'roles' => $data['roles']])
@endsection

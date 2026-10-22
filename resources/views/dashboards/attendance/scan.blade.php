@extends('layouts.dashboard')

@section('title', 'QR Attendance')
@section('page_heading', 'Attendance')

@section('content')
<section class="dash-page-head">
    <div class="dash-page-intro">
        <h1>QR Attendance</h1>
        <p>Scan ID cards at the gate. Students are marked present; staff are checked in, and a second scan checks them out. A handheld scanner types into the box below.</p>
    </div>
</section>

@include('dashboards.attendance.tabs', ['current' => 'admin.attendance.scan'])

<section class="dash-panel dash-scan" data-attendance="scan" data-scan-url="{{ route('erp.attendance.scan') }}">
    <div class="dash-scan-camera">
        <video data-video playsinline muted hidden></video>
        <div class="dash-scan-placeholder" data-camera-placeholder>
            @include('partials.dashboard-icon', ['name' => 'eye'])
            <p data-camera-note>Use the camera on this device, or a handheld scanner.</p>
            <button type="button" class="dash-btn dash-btn-primary" data-start-camera>Start camera</button>
        </div>
    </div>
    <form class="dash-scan-form" data-scan-form novalidate>
        <label for="scanCode">Card code</label>
        <input id="scanCode" name="code" autocomplete="off" placeholder="Scan or paste the QR text" autofocus>
        <button type="submit" class="dash-btn dash-btn-primary">Mark</button>
    </form>
    <ol class="dash-scan-log" data-scan-log aria-live="polite"></ol>
</section>
@endsection

@section('extra_js')
<script src="{{ asset('js/admin-attendance.js') }}?v={{ filemtime(public_path('js/admin-attendance.js')) }}" defer></script>
@endsection

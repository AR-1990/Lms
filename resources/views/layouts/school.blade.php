<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    <title>@yield('title', 'Pinnacle International Academy | Excellence in Modern Education')</title>
    <meta name="description" content="@yield('meta_description', 'Pinnacle International Academy provides world-class education from Early Years to Higher Secondary with STEM labs, sports, and holistic character building.')">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%231e3a8a'><path d='M12 3L1 9l11 6 9-4.91V17h2V9L12 3z'/><path d='M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z'/></svg>">

    <!-- External Theme CSS (Separated from Blade) -->
    <link rel="stylesheet" href="{{ asset('css/school-theme.css') }}">
    
    @yield('extra_css')
</head>
<body>

    <!-- Modular Header Partial -->
    @include('partials.header')

    <!-- Main Dynamic Content -->
    <main>
        @yield('content')
    </main>

    <!-- Modular Footer Partial -->
    @include('partials.footer')

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- External App JavaScript (Separated from Blade) -->
    <script src="{{ asset('js/school-app.js') }}"></script>
    
    @yield('extra_js')
</body>
</html>

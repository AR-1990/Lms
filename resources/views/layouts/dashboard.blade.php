<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'Portal Dashboard') | Pinnacle Academy</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ea580c'><path d='M12 3L1 9l11 6 9-4.91V17h2V9L12 3z'/></svg>">
    <link rel="stylesheet" href="{{ asset('css/school-theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    @yield('extra_css')
</head>
<body class="dash-body">
    <div class="dash-shell">
        @include('partials.dashboard-sidebar')

        <div class="dash-main">
            @include('partials.dashboard-topbar')

            <div class="dash-content">
                @yield('content')
            </div>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>
    <script src="{{ asset('js/school-app.js') }}"></script>
    <script src="{{ asset('js/dashboard.js') }}"></script>
    @yield('extra_js')
</body>
</html>

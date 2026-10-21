{{-- Grid assets for screens that use PinnacleGrid / PinnacleResource. Deferred so they run after dashboard.js. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-dt@2.1.8/css/dataTables.dataTables.min.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js" defer></script>
<script src="{{ asset('js/data-grid.js') }}?v={{ filemtime(public_path('js/data-grid.js')) }}" defer></script>
<script src="{{ asset('js/resource-screen.js') }}?v={{ filemtime(public_path('js/resource-screen.js')) }}" defer></script>

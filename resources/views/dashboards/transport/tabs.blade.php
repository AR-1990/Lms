@include('partials.section-tabs', [
    'label' => 'Transport',
    'current' => $current,
    'tabs' => [
        'admin.transport.routes' => ['Routes & Stops', 'map', null],
        'admin.transport.riders' => ['Riders', 'student', null],
        'admin.transport.vehicles' => ['Vehicles', 'server', null],
    ],
])

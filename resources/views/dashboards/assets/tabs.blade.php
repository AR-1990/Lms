@include('partials.section-tabs', [
    'label' => 'Assets',
    'current' => $current,
    'tabs' => [
        'admin.assets.index' => ['Register', 'server', null],
        'admin.assets.handovers' => ['Who Holds What', 'users', null],
        'admin.assets.repairs' => ['Repairs', 'settings', 'manage-assets'],
        'admin.assets.verification' => ['Verification', 'check', 'manage-assets'],
        'admin.assets.depreciation' => ['Depreciation', 'trending-up', 'manage-assets'],
        'admin.assets.disposals' => ['Disposals', 'eraser', 'manage-assets'],
        'admin.assets.categories' => ['Categories', 'layers', 'manage-assets'],
        'admin.assets.locations' => ['Locations', 'building', 'manage-assets'],
    ],
])

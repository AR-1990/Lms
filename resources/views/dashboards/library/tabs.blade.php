@include('partials.section-tabs', [
    'label' => 'Library',
    'current' => $current,
    'tabs' => [
        'admin.library.books' => ['Books', 'book', 'manage-library'],
        'admin.library.issues' => ['Issue & Return', 'check', 'manage-library'],
    ],
])

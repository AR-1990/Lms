@include('partials.section-tabs', [
    'label' => 'ID Cards',
    'current' => $current,
    'tabs' => [
        'admin.cards.index' => ['Issued Cards', 'key', 'manage-cards'],
        'admin.cards.templates' => ['Card Templates', 'layers', 'manage-cards'],
    ],
])

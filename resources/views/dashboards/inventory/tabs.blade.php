@include('partials.section-tabs', [
    'label' => 'Inventory',
    'current' => $current,
    'tabs' => [
        'admin.inventory.index' => ['Stock', 'inbox', 'manage-inventory'],
        'admin.inventory.orders' => ['Purchase Orders', 'send', 'manage-inventory'],
        'admin.inventory.receipts' => ['Goods Received', 'check', 'manage-inventory'],
        'admin.inventory.issues' => ['Issues & Sales', 'users', null],
        'admin.inventory.transfers' => ['Transfers', 'arrow-right', 'manage-inventory'],
        'admin.inventory.counts' => ['Stock Counts', 'hash', 'manage-inventory'],
        'admin.inventory.ledger' => ['Stock Ledger', 'book', 'manage-inventory'],
        'admin.inventory.categories' => ['Categories', 'layers', 'manage-inventory'],
    ],
])

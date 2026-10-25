@include('partials.section-tabs', [
    'label' => 'Fees',
    'current' => $current,
    'tabs' => [
        'accounts.fees.challans' => ['Challans', 'hash', 'manage-fees'],
        'accounts.fees.receipts' => ['Receipts', 'check', 'manage-fees'],
        'accounts.fees.refunds' => ['Refunds', 'wallet', 'issue-refunds'],
        'accounts.fees.structures' => ['Fee Structures', 'layers', 'manage-fee-setup'],
        'accounts.fees.concessions' => ['Concessions', 'heart', 'manage-fee-setup'],
        'accounts.fees.heads' => ['Fee Heads', 'book', 'manage-fee-setup'],
        'accounts.fees.concession-types' => ['Concession Types', 'key', 'manage-fee-setup'],
        'accounts.fees.late-fees' => ['Late Fees & Automation', 'clock', 'manage-fee-setup'],
    ],
])

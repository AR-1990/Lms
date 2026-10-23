@include('partials.section-tabs', [
    'label' => 'Accounts',
    'current' => $current,
    'tabs' => [
        'accounts.chart' => ['Chart of Accounts', 'layers', ['view-ledger', 'manage-accounts']],
        'accounts.vouchers' => ['Vouchers', 'pen', ['view-ledger', 'manage-vouchers', 'post-vouchers']],
        'accounts.trial-balance' => ['Trial Balance', 'chart', 'view-ledger'],
        'accounts.expenses' => ['Expenses', 'inbox', 'manage-expenses'],
        'accounts.vendor-payments' => ['Vendor Payments', 'send', 'manage-expenses'],
        'accounts.bank-reconciliation' => ['Bank Reconciliation', 'check', 'reconcile-bank'],
        'accounts.expense-categories' => ['Expense Categories', 'layers', 'manage-expenses'],
        'accounts.payment-methods' => ['Payment Methods', 'key', 'manage-accounts'],
        'accounts.bank-accounts' => ['Bank Accounts', 'building', 'manage-accounts'],
        'accounts.vendors' => ['Vendors', 'users', 'manage-accounts'],
        'accounts.fiscal-years' => ['Fiscal Years', 'calendar', 'manage-accounts'],
        'accounts.voucher-types' => ['Voucher Types', 'hash', 'manage-accounts'],
    ],
])

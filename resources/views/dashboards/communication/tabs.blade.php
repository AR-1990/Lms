{{-- Tabs of the Announcements area. Expects $tabCurrent (route name). --}}
@include('partials.section-tabs', [
    'label' => 'Announcements',
    'current' => $tabCurrent,
    'tabs' => [
        'admin.announcements.index' => ['Announcements', 'megaphone', null],
        'admin.announcements.categories' => ['Categories', 'layers', null],
        'admin.announcements.templates' => ['Message Templates', 'mail', null],
        'admin.announcements.delivery-log' => ['Delivery Log', 'activity', null],
    ],
])

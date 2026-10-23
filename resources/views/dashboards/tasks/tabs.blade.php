{{-- Tabs of the Tasks area. Expects $tabCurrent. --}}
@include('partials.section-tabs', [
    'label' => 'Tasks',
    'current' => $tabCurrent,
    'tabs' => [
        'tasks.index' => ['My Tasks', 'check', null],
        'tasks.boards' => ['Boards', 'layers', null],
    ],
])

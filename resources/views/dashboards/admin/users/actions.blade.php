<div class="dash-actions">
    <a href="{{ route('admin.users.show', $account) }}" class="dash-action dash-action-view" data-tooltip="View" aria-label="View {{ $account->name }}">
        @include('partials.dashboard-icon', ['name' => 'book'])
    </a>
    <a href="{{ route('admin.users.edit', $account) }}" class="dash-action dash-action-edit" data-tooltip="Edit" aria-label="Edit {{ $account->name }}">
        @include('partials.dashboard-icon', ['name' => 'pen'])
    </a>
    @if ($account->is($user))
        <span class="dash-action dash-action-delete is-disabled" data-tooltip="This is you" aria-label="You cannot delete your own account">
            @include('partials.dashboard-icon', ['name' => 'eraser'])
        </span>
    @else
        <form method="POST" action="{{ route('admin.users.destroy', $account) }}" data-confirm-delete data-confirm-name="{{ $account->name }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="dash-action dash-action-delete" data-tooltip="Delete" aria-label="Delete {{ $account->name }}">
                @include('partials.dashboard-icon', ['name' => 'eraser'])
            </button>
        </form>
    @endif
</div>

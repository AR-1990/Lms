{{-- Session + class filters for per-session grids; the current session is selected by default. --}}
<div class="dash-filters-inline">
    <label class="dash-select">
        <span class="dash-visually-hidden">Session</span>
        <select data-filter="academic_session_id">
            @foreach ($data['sessions'] as $session)
                <option value="{{ $session->id }}" @selected($session->id === $data['current_session_id'])>{{ $session->name }}{{ $session->is_current ? ' (current)' : '' }}</option>
            @endforeach
        </select>
    </label>
    <label class="dash-select">
        <span class="dash-visually-hidden">Class</span>
        <select data-filter="class_id">
            <option value="">All classes</option>
            @foreach ($data['all_classes'] as $class)
                <option value="{{ $class->id }}">{{ $class->name }}</option>
            @endforeach
        </select>
    </label>
</div>

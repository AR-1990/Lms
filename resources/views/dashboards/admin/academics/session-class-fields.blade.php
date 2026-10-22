{{-- Session + class pickers shared by the section and curriculum popups. --}}
<div class="dash-field">
    <label for="formSession">Session</label>
    <select id="formSession" name="academic_session_id" required>
        @foreach ($data['sessions'] as $session)
            <option value="{{ $session->id }}">{{ $session->name }}</option>
        @endforeach
    </select>
    <p class="dash-field-error" data-error-for="academic_session_id" hidden></p>
</div>
<div class="dash-field">
    <label for="formClass">Class</label>
    <select id="formClass" name="class_id" required>
        @foreach ($data['classes'] as $class)
            <option value="{{ $class->id }}">{{ $class->name }}</option>
        @endforeach
    </select>
    <p class="dash-field-error" data-error-for="class_id" hidden></p>
</div>

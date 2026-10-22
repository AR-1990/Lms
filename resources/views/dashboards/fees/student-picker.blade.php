{{-- Search-then-pick student field for fee popups. Params: id. --}}
<div class="dash-field dash-field-wide" data-student-picker>
    <label for="{{ $id }}">Student</label>
    <div class="dash-picker">
        <input type="search" placeholder="Type a name or admission no…" autocomplete="off" aria-label="Search students" data-student-search>
        <select id="{{ $id }}" name="student_id" required data-student-select>
            <option value="">Search, then pick a student</option>
        </select>
    </div>
    <p class="dash-field-error" data-error-for="student_id" hidden></p>
</div>

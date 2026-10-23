{{-- Task popup shared by My Tasks and the Kanban board: details, checklist and comments. Driven by public/js/tasks.js. --}}
<dialog class="dash-modal dash-form-modal dash-modal-wide dash-task-modal" id="taskModal" aria-labelledby="taskModalTitle">
    <form data-task-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'check'])</span>
            <div>
                <h3 id="taskModalTitle" data-task-title>New task</h3>
                <p data-task-meta>Assign it, give it a due date, add a checklist.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>
        <div class="dash-form-grid dash-form-scroll">
            @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Task', 'required' => true, 'wide' => true, 'id' => 'taskTitle'])
            <div class="dash-field dash-field-wide">
                <label for="taskDescription">Details <span class="dash-optional">optional</span></label>
                <textarea id="taskDescription" name="description" rows="3" maxlength="20000"></textarea>
            </div>
            <div class="dash-field">
                <label for="taskBoard">Board</label>
                <select id="taskBoard" name="task_board_id" data-task-board><option value="">Personal (no board)</option></select>
                <p class="dash-field-error" data-error-for="task_board_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="taskStatus">Column</label>
                <select id="taskStatus" name="task_status_id" data-task-status></select>
                <p class="dash-field-error" data-error-for="task_status_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="taskPriority">Priority</label>
                <select id="taskPriority" name="priority_id" data-task-priority><option value="">—</option></select>
            </div>
            <div class="dash-field">
                <label for="taskCategory">Category <span class="dash-optional">optional</span></label>
                <select id="taskCategory" name="category_id" data-task-category><option value="">—</option></select>
            </div>
            @include('dashboards.partials.field', ['name' => 'start_at', 'label' => 'Start', 'type' => 'datetime-local', 'id' => 'taskStart'])
            @include('dashboards.partials.field', ['name' => 'due_at', 'label' => 'Due', 'type' => 'datetime-local', 'id' => 'taskDue'])
            <div class="dash-field">
                <label for="taskRecurrence">Repeats</label>
                <select id="taskRecurrence" name="recurrence">
                    <option value="none">Does not repeat</option>
                    <option value="daily">Every day</option>
                    <option value="weekly">Every week</option>
                    <option value="monthly">Every month</option>
                </select>
            </div>
            @include('dashboards.partials.field', ['name' => 'estimated_minutes', 'label' => 'Estimate (minutes)', 'type' => 'number', 'min' => 1, 'id' => 'taskEstimate'])
            <label class="dash-audience-list dash-field-wide">
                <span>Assigned to <span class="dash-optional">ctrl / ⌘ to pick several · empty = you</span></span>
                <select multiple size="5" data-task-assignees></select>
            </label>
            <div class="dash-field dash-field-wide">
                <span class="dash-label">Checklist</span>
                <ul class="dash-sticky-checklist" data-task-checklist></ul>
                <button type="button" class="dash-link-btn" data-task-add-item>+ Add step</button>
            </div>
            <section class="dash-field dash-field-wide dash-task-comments" data-task-comments hidden>
                <span class="dash-label">Comments</span>
                <ol class="dash-task-thread" data-task-thread></ol>
                <div class="dash-task-reply">
                    <textarea rows="2" maxlength="5000" placeholder="Write a comment…" data-task-comment></textarea>
                    <select multiple size="3" data-task-mentions aria-label="Mention people"></select>
                    <button type="button" class="dash-btn dash-btn-ghost" data-task-send>Send</button>
                </div>
            </section>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-task-delete hidden>@include('partials.dashboard-icon', ['name' => 'eraser']) Delete</button>
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Save task</button>
        </div>
    </form>
</dialog>

{{-- Application popup, shared by Applications (new / edit) and Inquiries (convert). --}}
<dialog class="dash-modal dash-form-modal dash-modal-wide" id="studentsModal" aria-labelledby="studentsModalTitle">
    <form data-resource-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'user-plus'])</span>
            <div>
                <h3 id="studentsModalTitle" data-modal-title>New application</h3>
                <p>Submitting raises the registration challan when the class fee structure has one.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid dash-form-grid-3">
            <div class="dash-field" data-create-only>
                <label for="appSession">Session</label>
                <select id="appSession" name="academic_session_id">
                    @foreach ($data['sessions'] as $session)
                        <option value="{{ $session->id }}" @selected($session->id === $data['current_session_id'])>{{ $session->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="academic_session_id" hidden></p>
            </div>
            <div class="dash-field" data-create-only>
                <label for="appClass">Class applied for</label>
                <select id="appClass" name="class_applied_id">
                    @foreach ($data['classes'] as $class)
                        <option value="{{ $class->id }}">{{ $class->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="class_applied_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'first_name', 'label' => 'First name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'last_name', 'label' => 'Last name'])
            @include('dashboards.partials.select', ['name' => 'gender_id', 'label' => 'Gender', 'options' => $data['lookups']['gender'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'b_form_no', 'label' => 'B-Form number', 'placeholder' => '35202-1234567-1'])
            @include('dashboards.partials.field', ['name' => 'previous_school', 'label' => 'Previous school'])
            @include('dashboards.partials.field', ['name' => 'previous_class', 'label' => 'Previous class'])
            @include('dashboards.partials.field', ['name' => 'city', 'label' => 'City'])
            @include('dashboards.partials.field', ['name' => 'address_line', 'label' => 'Address', 'wide' => true])
        </div>

        <div class="dash-form-grid dash-form-grid-3">
            <h4 class="dash-form-section">Father / first guardian</h4>
            @include('dashboards.partials.select', ['name' => 'g0_relation_id', 'label' => 'Relation', 'options' => $data['lookups']['guardian_relation'], 'required' => true])
            @include('dashboards.partials.field', ['name' => 'g0_name', 'label' => 'Name', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'g0_phone', 'label' => 'Phone', 'type' => 'tel', 'required' => true])
            @include('dashboards.partials.field', ['name' => 'g0_cnic', 'label' => 'CNIC'])
            <h4 class="dash-form-section">Mother / second guardian <span class="dash-optional">optional</span></h4>
            @include('dashboards.partials.select', ['name' => 'g1_relation_id', 'label' => 'Relation', 'options' => $data['lookups']['guardian_relation']])
            @include('dashboards.partials.field', ['name' => 'g1_name', 'label' => 'Name'])
            @include('dashboards.partials.field', ['name' => 'g1_phone', 'label' => 'Phone', 'type' => 'tel'])
            @include('dashboards.partials.field', ['name' => 'g1_cnic', 'label' => 'CNIC'])
            <p class="dash-field-error dash-field-wide" data-error-for="guardians" hidden></p>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'check']) Submit Application</button>
        </div>
    </form>
</dialog>

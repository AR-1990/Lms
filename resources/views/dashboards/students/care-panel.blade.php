{{-- Student profile: health, discipline, achievements and certificates (loads from /api/erp/students/{id}/records). --}}
<section class="dash-panel" data-care
         data-url="{{ route('erp.students.records.index', $student) }}"
         data-store-url="{{ url('/api/erp/students/'.$student->id.'/records') }}"
         data-record-url="{{ url('/api/erp/student-records') }}"
         data-certificate-url="{{ route('erp.students.certificates.store', $student) }}"
         data-certificate-pdf-url="{{ url('/api/erp/student-certificates') }}">
    <div class="dash-panel-head">
        <div>
            <h2>Health, discipline &amp; achievements</h2>
            <p>Parents see discipline notes and achievements on their child's page; health records stay with the school.</p>
        </div>
    </div>
    <nav class="dash-section-tabs" role="tablist" aria-label="Student records">
        @foreach (['medical' => ['Health', 'heart'], 'discipline' => ['Discipline', 'alert'], 'achievements' => ['Achievements', 'sparkles'], 'certificates' => ['Certificates', 'file']] as $key => [$label, $icon])
            <button type="button" role="tab" @class(['dash-section-tab', 'is-active' => $loop->first]) aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-care-tab="{{ $key }}">
                @include('partials.dashboard-icon', ['name' => $icon])<span>{{ $label }}</span> <span class="dash-muted" data-care-count="{{ $key }}"></span>
            </button>
        @endforeach
    </nav>
    <div class="dash-care-body">
        <div class="dash-care-actions">
            <button type="button" class="dash-btn dash-btn-primary dash-btn-sm" data-care-add>@include('partials.dashboard-icon', ['name' => 'plus']) <span data-care-add-label>Add health record</span></button>
        </div>
        <ul class="dash-list" data-care-list><li class="dash-muted">Loading…</li></ul>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="careModal" aria-labelledby="careModalTitle">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'heart'])</span>
        <div>
            <h3 id="careModalTitle" data-care-title>Health record</h3>
            <p>{{ $student->fullName() }} · {{ $student->admission_no }}</p>
        </div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>

    <form data-care-form="medical" novalidate>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="careCondition">Condition <span class="dash-optional">optional</span></label>
                <select id="careCondition" name="condition_id"><option value="">—</option>@foreach ($data['lookups']['medical_condition'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
                <p class="dash-field-error" data-error-for="condition_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'checked_on', 'label' => 'Checked on', 'type' => 'date', 'id' => 'careChecked'])
            @include('dashboards.partials.field', ['name' => 'height_cm', 'label' => 'Height (cm)', 'type' => 'number', 'step' => '0.1', 'id' => 'careHeight'])
            @include('dashboards.partials.field', ['name' => 'weight_kg', 'label' => 'Weight (kg)', 'type' => 'number', 'step' => '0.1', 'id' => 'careWeight'])
            @include('dashboards.partials.field', ['name' => 'vision', 'label' => 'Vision', 'id' => 'careVision'])
            @include('dashboards.partials.field', ['name' => 'doctor_name', 'label' => 'Family doctor', 'id' => 'careDoctor'])
            @include('dashboards.partials.field', ['name' => 'doctor_phone', 'label' => 'Doctor phone', 'id' => 'careDoctorPhone'])
            @include('dashboards.partials.field', ['name' => 'allergies', 'label' => 'Allergies', 'type' => 'textarea', 'wide' => true, 'id' => 'careAllergies'])
            @include('dashboards.partials.field', ['name' => 'medications', 'label' => 'Medicines', 'type' => 'textarea', 'wide' => true, 'id' => 'careMedicines'])
            @include('dashboards.partials.field', ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'wide' => true, 'id' => 'careNotes'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions"><button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button><button type="submit" class="dash-btn dash-btn-primary">Save</button></div>
    </form>

    <form data-care-form="discipline" novalidate hidden>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'incident_date', 'label' => 'Date', 'type' => 'date', 'required' => true, 'id' => 'careIncident'])
            <div class="dash-field">
                <label for="careCategory">Category</label>
                <select id="careCategory" name="category_id" required>@foreach ($data['lookups']['discipline_category'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
                <p class="dash-field-error" data-error-for="category_id" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'What happened', 'type' => 'textarea', 'required' => true, 'wide' => true, 'id' => 'careDescription'])
            @include('dashboards.partials.field', ['name' => 'action_taken', 'label' => 'Action taken', 'type' => 'textarea', 'wide' => true, 'id' => 'careAction'])
            <label class="dash-switch dash-field-wide">
                <input type="checkbox" name="notify_parents" checked>
                <span class="dash-switch-track" aria-hidden="true"><span class="dash-switch-thumb"></span></span>
                <span>Tell the parents now (notification)</span>
            </label>
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions"><button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button><button type="submit" class="dash-btn dash-btn-primary">Save</button></div>
    </form>

    <form data-care-form="achievements" novalidate hidden>
        <div class="dash-form-grid">
            @include('dashboards.partials.field', ['name' => 'title', 'label' => 'Achievement', 'required' => true, 'wide' => true, 'id' => 'careTitle', 'placeholder' => 'First position, inter-school debate'])
            <div class="dash-field">
                <label for="careAchCategory">Category <span class="dash-optional">optional</span></label>
                <select id="careAchCategory" name="category_id"><option value="">—</option>@foreach ($data['lookups']['achievement_category'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
            </div>
            <div class="dash-field">
                <label for="careLevel">Level <span class="dash-optional">optional</span></label>
                <select id="careLevel" name="level"><option value="">—</option>@foreach (\App\Models\StudentAchievement::LEVELS as $level)<option value="{{ $level }}">{{ ucfirst($level) }}</option>@endforeach</select>
            </div>
            @include('dashboards.partials.field', ['name' => 'achieved_on', 'label' => 'Date', 'type' => 'date', 'id' => 'careAchieved'])
            @include('dashboards.partials.field', ['name' => 'description', 'label' => 'Details', 'type' => 'textarea', 'wide' => true, 'id' => 'careAchDetails'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions"><button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button><button type="submit" class="dash-btn dash-btn-primary">Save</button></div>
    </form>

    <form data-care-form="certificates" novalidate hidden>
        <div class="dash-form-grid">
            <div class="dash-field">
                <label for="careCertType">Certificate</label>
                <select id="careCertType" name="certificate_type">@foreach (\App\Models\StudentCertificate::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                <p class="dash-field-error" data-error-for="certificate_type" hidden></p>
            </div>
            @include('dashboards.partials.field', ['name' => 'issued_on', 'label' => 'Date of issue', 'type' => 'date', 'id' => 'careIssued'])
            <div class="dash-field">
                <label for="careConduct">Conduct (character certificate) <span class="dash-optional">optional</span></label>
                <select id="careConduct" name="conduct_id"><option value="">—</option>@foreach ($data['lookups']['conduct'] as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select>
            </div>
            @include('dashboards.partials.field', ['name' => 'purpose', 'label' => 'Purpose', 'id' => 'carePurpose', 'placeholder' => 'Passport, scholarship, bank account…'])
            @include('dashboards.partials.field', ['name' => 'remarks', 'label' => 'Remarks', 'type' => 'textarea', 'wide' => true, 'id' => 'careCertRemarks'])
        </div>
        <p class="dash-form-error" data-form-error hidden></p>
        <div class="dash-modal-actions"><button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button><button type="submit" class="dash-btn dash-btn-primary">Issue &amp; print</button></div>
    </form>
</dialog>

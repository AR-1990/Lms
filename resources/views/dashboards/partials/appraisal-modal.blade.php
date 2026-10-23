{{-- One appraisal: self-review, reviewer scoring, the employee's acknowledgement and HR's reviewer change / reopen. Driven by js/appraisal-form.js. --}}
<dialog class="dash-modal dash-modal-wide" id="appraisalModal" aria-labelledby="appraisalModalTitle"
    data-appraisal-url="{{ url('/api/erp/appraisals') }}" data-reviewers-url="{{ url('/api/erp/appraisal-reviewers') }}">
    <div class="dash-modal-head">
        <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'trending-up'])</span>
        <div><h3 id="appraisalModalTitle" data-appraisal-title>Appraisal</h3><p data-appraisal-sub></p></div>
        <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
    </div>
    <form data-appraisal-form novalidate>
        <div class="dash-form-scroll" data-appraisal-body></div>
        <p class="dash-form-error" data-form-error hidden></p>
    </form>
    <div class="dash-modal-actions" data-appraisal-actions></div>
</dialog>
<script src="{{ asset('js/appraisal-form.js') }}?v={{ filemtime(public_path('js/appraisal-form.js')) }}" defer></script>

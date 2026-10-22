{{-- Documents of one record (owner key from AttachmentService::OWNERS). Params: owner, ownerId, documentTypes. Needs data-grid.js + admin-users.js on the page. --}}
<section
    class="dash-panel"
    data-documents
    data-url="{{ route('erp.attachments.index') }}"
    data-owner="{{ $owner }}"
    data-owner-id="{{ $ownerId }}"
>
    <div class="dash-panel-head">
        <div>
            <h2>Documents</h2>
            <p>CNIC, certificates, photos and other files. Links to open a file expire after 10 minutes.</p>
        </div>
        <div class="dash-filters-inline">
            <span class="dash-badge" data-doc-count>0 files</span>
            <button type="button" class="dash-btn dash-btn-primary" data-doc-add>
                @include('partials.dashboard-icon', ['name' => 'upload'])
                Upload
            </button>
        </div>
    </div>
    <div class="dash-panel-body">
        <ul class="dash-docs" data-doc-list></ul>
        <div class="dash-empty" data-doc-empty>
            @include('partials.dashboard-icon', ['name' => 'paperclip'])
            <strong>No documents yet</strong>
            <span>Upload a file to keep it with this account.</span>
        </div>
    </div>
</section>

<dialog class="dash-modal dash-form-modal" id="documentModal" aria-labelledby="documentModalTitle">
    <form data-document-form novalidate>
        <div class="dash-modal-head">
            <span class="dash-modal-icon dash-modal-icon-brand">@include('partials.dashboard-icon', ['name' => 'paperclip'])</span>
            <div>
                <h3 id="documentModalTitle">Upload document</h3>
                <p>Images, PDF, Word or Excel — up to 10 MB.</p>
            </div>
            <button type="button" class="dash-icon-btn" data-modal-close aria-label="Close">@include('partials.dashboard-icon', ['name' => 'close'])</button>
        </div>

        <div class="dash-form-grid">
            <label class="dash-file-drop dash-field-wide" data-file-drop>
                <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" required>
                @include('partials.dashboard-icon', ['name' => 'upload'])
                <strong data-file-name>Choose a file or drop it here</strong>
                <span class="dash-muted">The original file is kept private.</span>
            </label>
            <p class="dash-field-error dash-field-wide" data-error-for="file" hidden></p>

            <div class="dash-field">
                <label for="documentType">Document type <span class="dash-optional">optional</span></label>
                <select id="documentType" name="document_type_id">
                    <option value="">Not specified</option>
                    @foreach ($documentTypes as $documentType)
                        <option value="{{ $documentType->id }}">{{ $documentType->name }}</option>
                    @endforeach
                </select>
                <p class="dash-field-error" data-error-for="document_type_id" hidden></p>
            </div>
            <div class="dash-field">
                <label for="documentTitle">Title <span class="dash-optional">optional</span></label>
                <input id="documentTitle" name="title" maxlength="255" autocomplete="off">
                <p class="dash-field-error" data-error-for="title" hidden></p>
            </div>
            <div class="dash-field">
                <label for="documentNumber">Document number <span class="dash-optional">optional</span></label>
                <input id="documentNumber" name="document_number" maxlength="100" autocomplete="off">
                <p class="dash-field-error" data-error-for="document_number" hidden></p>
            </div>
            <div class="dash-field">
                <label for="documentExpires">Expires on <span class="dash-optional">optional</span></label>
                <input id="documentExpires" name="expires_at" type="date">
                <p class="dash-field-error" data-error-for="expires_at" hidden></p>
            </div>
            <div class="dash-field dash-field-wide">
                <label for="documentRemarks">Remarks <span class="dash-optional">optional</span></label>
                <textarea id="documentRemarks" name="remarks" rows="2" maxlength="1000"></textarea>
                <p class="dash-field-error" data-error-for="remarks" hidden></p>
            </div>
        </div>

        <p class="dash-form-error" data-form-error hidden></p>

        <div class="dash-modal-actions">
            <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Cancel</button>
            <button type="submit" class="dash-btn dash-btn-primary" data-submit>@include('partials.dashboard-icon', ['name' => 'upload']) Upload</button>
        </div>
    </form>
</dialog>

{{-- My attendance QR (the code on my printed ID card) for scanning at the gate kiosk. Hidden until a card exists. Data: /api/erp/my-attendance-qr. --}}
<section class="dash-panel dash-my-qr" data-my-qr data-url="{{ url('/api/erp/my-attendance-qr') }}" hidden>
    <button type="button" class="dash-my-qr-code" data-my-qr-zoom aria-label="Show the QR code full screen">
        <img data-my-qr-image alt="My attendance QR code" width="132" height="132">
    </button>
    <div class="dash-my-qr-text">
        <h2>@include('partials.dashboard-icon', ['name' => 'grid']) My attendance QR</h2>
        <p>Show this at the gate kiosk to mark your attendance. It is the same code as on your ID card.</p>
        <dl>
            <div><dt>Name</dt><dd data-my-qr-name></dd></div>
            <div><dt>Card</dt><dd data-my-qr-card></dd></div>
            <div><dt>Valid until</dt><dd data-my-qr-valid></dd></div>
        </dl>
    </div>
</section>

<dialog class="dash-modal dash-my-qr-full" id="myQrFull" aria-label="My attendance QR code">
    <img data-my-qr-full alt="My attendance QR code">
    <button type="button" class="dash-btn dash-btn-ghost" data-modal-close>Close</button>
</dialog>

<script src="{{ asset('js/attendance-qr.js') }}?v={{ filemtime(public_path('js/attendance-qr.js')) }}" defer></script>

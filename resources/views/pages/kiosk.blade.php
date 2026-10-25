<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Attendance Kiosk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --teal: #24676b; --teal-dark: #143f42; --amber: #e9a83a; --ok: #059669; --bad: #b91c1c; --ink: #1c1917; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body { display: flex; flex-direction: column; min-height: 100vh; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; color: #fff; background: radial-gradient(120vmax 90vmax at 100% 0%, rgba(233,168,58,.25), transparent 55%), linear-gradient(160deg, var(--teal-dark), var(--teal)); }
        header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; }
        header strong { display: block; font: 700 1.15rem 'Outfit', sans-serif; }
        header span { font-size: .85rem; opacity: .8; }
        .clock { font: 800 2rem 'Outfit', sans-serif; letter-spacing: .02em; font-variant-numeric: tabular-nums; }
        main { flex: 1; display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 1.25rem; padding: 0 1.25rem 1.25rem; }
        .camera { position: relative; overflow: hidden; border-radius: 1.25rem; background: #0b2426; min-height: 50vh; }
        video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .frame { position: absolute; inset: 18%; border: 3px solid rgba(255,255,255,.85); border-radius: 1rem; box-shadow: 0 0 0 100vmax rgba(0,0,0,.25); pointer-events: none; }
        .start { position: absolute; inset: 0; display: grid; place-items: center; text-align: center; padding: 1.5rem; }
        .start button, form button { border: 0; border-radius: .8rem; padding: .9rem 1.5rem; font: 700 1rem 'Outfit', sans-serif; color: var(--teal-dark); background: var(--amber); cursor: pointer; }
        .side { display: flex; flex-direction: column; gap: 1rem; }
        .result { flex: 1; display: grid; place-items: center; text-align: center; padding: 1.5rem; border-radius: 1.25rem; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16); transition: transform .2s ease, opacity .2s ease; }
        .result.flash { transform: scale(1.02); }
        .result .icon { width: 5rem; height: 5rem; margin: 0 auto .75rem; border-radius: 50%; display: grid; place-items: center; font: 800 2.5rem 'Outfit', sans-serif; background: rgba(255,255,255,.12); }
        .result.ok .icon { background: var(--ok); }
        .result.bad .icon { background: var(--bad); }
        .result h1 { margin: 0; font: 800 clamp(1.5rem, 3.5vw, 2.4rem) 'Outfit', sans-serif; }
        .result p { margin: .5rem 0 0; font-size: 1.05rem; opacity: .9; }
        form { display: flex; gap: .5rem; }
        form input { flex: 1; min-width: 0; border: 0; border-radius: .8rem; padding: .9rem 1rem; font-size: 1rem; }
        ol { list-style: none; margin: 0; padding: 0; display: grid; gap: .4rem; max-height: 30vh; overflow: auto; }
        ol li { display: flex; justify-content: space-between; gap: .75rem; padding: .55rem .8rem; border-radius: .6rem; background: rgba(255,255,255,.08); font-size: .9rem; }
        ol li.bad { background: rgba(185,28,28,.35); }
        .closed { margin: auto; max-width: 28rem; padding: 2rem; text-align: center; }
        @media (max-width: 760px) { main { grid-template-columns: 1fr; } .camera { min-height: 42vh; } .clock { font-size: 1.4rem; } }
        @media (prefers-reduced-motion: reduce) { .result { transition: none; } }
    </style>
</head>
<body data-kiosk data-url="{{ url('/api/kiosk/'.$token) }}">
    <header>
        <div><strong data-school>Attendance</strong><span data-kiosk-name>Loading…</span></div>
        <div class="clock" data-clock>--:--</div>
    </header>
    <main data-main>
        <section class="camera">
            <video data-video playsinline muted></video>
            <div class="frame" aria-hidden="true"></div>
            <div class="start" data-start><div><p>Show your ID card's QR code to the camera.</p><button type="button" data-start-button>Start camera</button></div></div>
        </section>
        <section class="side">
            <div class="result" data-result aria-live="assertive">
                <div><div class="icon" data-result-icon>▣</div><h1 data-result-title>Ready</h1><p data-result-text>Students are marked present; staff check in, and scan again to check out.</p></div>
            </div>
            <form data-manual autocomplete="off">
                <input name="code" placeholder="Card scanner or code" aria-label="Card code" autofocus>
                <button type="submit">Mark</button>
            </form>
            <ol data-log></ol>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js" defer></script>
    <script src="{{ asset('js/kiosk.js') }}?v={{ filemtime(public_path('js/kiosk.js')) }}" defer></script>
</body>
</html>

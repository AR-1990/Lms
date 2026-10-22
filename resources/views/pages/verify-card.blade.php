{{-- Public page opened by an ID card's QR code: photo, name, card status only. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Card Check | {{ $card['school_name'] }}</title>
    <style>
        :root { --ink: #1f2937; --muted: #6b7280; --paper: #fffdf8; --ok: #15803d; --bad: #b91c1c; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: #f3efe6; color: var(--ink); font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .card { width: 100%; max-width: 360px; padding: 28px 24px; border-radius: 6px; background: var(--paper); box-shadow: 0 1px 0 rgb(0 0 0 / 0.04), 0 12px 30px rgb(60 40 10 / 0.12); text-align: center; }
        .school { margin: 0 0 18px; color: var(--muted); font-size: 0.85rem; letter-spacing: 0.04em; text-transform: uppercase; }
        .photo { width: 112px; height: 136px; margin: 0 auto 16px; border-radius: 4px; background: #e5e7eb center / cover no-repeat; }
        h1 { margin: 0 0 4px; font-size: 1.35rem; }
        .code { margin: 0 0 18px; color: var(--muted); }
        .status { display: inline-block; padding: 6px 14px; border-radius: 999px; font-weight: 700; }
        .status.ok { background: #dcfce7; color: var(--ok); }
        .status.bad { background: #fee2e2; color: var(--bad); }
        .meta { margin: 16px 0 0; color: var(--muted); font-size: 0.85rem; }
    </style>
</head>
<body>
    <main class="card">
        <p class="school">{{ $card['school_name'] }}</p>
        <div class="photo" @if ($card['photo_url']) style="background-image: url('{{ $card['photo_url'] }}');" @endif role="img" aria-label="Photo"></div>
        <h1>{{ $card['name'] ?? 'Unknown holder' }}</h1>
        <p class="code">{{ ucfirst($card['holder_type']) }} · {{ $card['code'] }}</p>
        <span class="status {{ $card['status'] === 'active' ? 'ok' : 'bad' }}">{{ $card['status'] === 'active' ? 'Valid card' : 'Card '.$card['status'] }}</span>
        <p class="meta">Card {{ $card['card_no'] }}@if ($card['valid_until']) · valid until {{ \Illuminate\Support\Carbon::parse($card['valid_until'])->format('d M Y') }}@endif</p>
    </main>
</body>
</html>

{{--
    Shown for every public URL while System Settings → Maintenance Mode is on.

    Self-contained on purpose, like theme-missing: it is also rendered from the
    exception handler, where a theme layout (and whatever it queries) is exactly
    the kind of thing that may be broken. No links into the site either — every
    one of them would land back here.
--}}
@php
    // Name, monogram and logo all come from Branding & Site Identity; Branding
    // itself falls back to its defaults when a field was never saved.
    $brand = \App\Helpers\Branding::all();
    $logo = $brand['use_image_logo'] ? $brand['logo_url'] : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} — {{ $brand['site_name'] }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f8fafc; color: #0f172a; padding: 1.5rem; box-sizing: border-box;
        }
        main { max-width: 34rem; text-align: center; }
        .mark {
            width: 3.5rem; height: 3.5rem; margin: 0 auto 1.5rem; border-radius: 0.75rem;
            display: flex; align-items: center; justify-content: center;
            background: #034ea2; color: #fff; font-weight: 700; font-size: 1.5rem;
        }
        .logo { max-height: 4rem; max-width: 12rem; margin: 0 auto 1.5rem; display: block; }
        h1 { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em; margin: 0 0 0.75rem; }
        p { margin: 0 0 0.75rem; line-height: 1.6; color: #475569; font-size: 0.9375rem; white-space: pre-line; }
        .site { margin-top: 1.5rem; font-size: 0.8125rem; color: #94a3b8; }
        @media (prefers-color-scheme: dark) {
            body { background: #0a1424; color: #e2e8f0; }
            p { color: #94a3b8; }
            .site { color: #64748b; }
        }

        /* An uploaded background: the photo fills the screen behind a dark
           veil, and the text sits on it in white whatever the colour scheme,
           since the image is the same in light and dark. */
        body.has-bg {
            background: #0a1424 var(--bg) center / cover no-repeat fixed;
            color: #fff;
        }
        body.has-bg::before {
            content: ""; position: fixed; inset: 0;
            background: linear-gradient(180deg, rgba(10, 20, 36, 0.55), rgba(10, 20, 36, 0.8));
        }
        body.has-bg main { position: relative; }
        body.has-bg p { color: rgba(255, 255, 255, 0.85); }
        body.has-bg .site { color: rgba(255, 255, 255, 0.6); }
        body.has-bg h1, body.has-bg p { text-shadow: 0 1px 12px rgba(0, 0, 0, 0.4); }
    </style>
</head>
<body @if ($background ?? null) class="has-bg" style="--bg: url('{{ $background }}')" @endif>
<main>
    @if ($logo)
        <img class="logo" src="{{ $logo }}" alt="{{ $brand['site_name'] }}">
    @else
        <div class="mark">{{ $brand['monogram'] }}</div>
    @endif
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    <div class="site">{{ $brand['site_name'] }}</div>
</main>
</body>
</html>

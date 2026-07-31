{{--
    Shown when no frontend theme is installed at all.

    Themes are deletable folders, so this is a reachable state rather than a
    theoretical one. It exists so that deleting the last theme produces a page
    that says what happened and what to do, instead of a stack trace on every
    public URL. Deliberately self-contained: it cannot extend a theme layout,
    since the point is that there is none.
--}}
@php
    $brand = \App\Helpers\Branding::all();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $brand['site_name'] ?? 'Faculty Directory' }} — temporarily unavailable</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: #f8fafc; color: #0f172a; padding: 1.5rem;
        }
        main { max-width: 34rem; }
        h1 { font-size: 1.375rem; font-weight: 700; letter-spacing: -0.02em; margin: 0 0 0.75rem; }
        p { margin: 0 0 0.75rem; line-height: 1.6; color: #475569; font-size: 0.9375rem; }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.8125rem;
               background: #e2e8f0; padding: 0.1rem 0.35rem; border-radius: 0.25rem; }
        hr { border: 0; border-top: 1px solid #e2e8f0; margin: 1.5rem 0; }
        a { color: #034ea2; }
        @media (prefers-color-scheme: dark) {
            body { background: #0a1424; color: #e2e8f0; }
            p { color: #94a3b8; }
            code { background: #1e2f4d; }
            hr { border-top-color: #1e2f4d; }
            a { color: #7dd3fc; }
        }
    </style>
</head>
<body>
<main>
    <h1>The directory has no theme installed</h1>
    <p>
        The public site renders through a theme, and none was found in
        <code>resources/views/frontend/themes</code>. Nothing else is wrong — the
        data and the admin panel are untouched.
    </p>
    <p>
        Restore a theme folder, or install one, and this page goes away. A theme
        must ship its own layout and page views to be recognised.
    </p>
    <hr>
    <p><a href="{{ url('/admin') }}">Open the admin panel</a></p>
</main>
</body>
</html>

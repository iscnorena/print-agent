<!DOCTYPE html>
<html lang="es-MX">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Impresiones') · {{ config('app.name') }}</title>
    {{-- Plain CSS on purpose: no Node/Vite build step needed on the shop PC. --}}
    <style>
        :root {
            --bg: #f4f5f7; --surface: #fff; --text: #1d2330; --muted: #667085; --border: #e2e5ea;
            --primary: #2456d6; --primary-text: #fff; --ok: #0f7b3e; --ok-bg: #e5f6ec;
            --warn: #9a5b00; --warn-bg: #fff3dc; --error: #b42318; --error-bg: #fdecea; --info-bg: #e9f0ff;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #12151b; --surface: #1b2029; --text: #e7e9ee; --muted: #98a2b3; --border: #2c3340;
                --primary: #5b86f0; --ok: #5ad08b; --ok-bg: #173323; --warn: #f2b84b; --warn-bg: #3a2d12;
                --error: #f28b82; --error-bg: #3b1d1b; --info-bg: #1d2a47;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .75rem 1rem; background: var(--surface); border-bottom: 1px solid var(--border); }
        header strong { font-size: 1.05rem; }
        main { max-width: 960px; margin: 0 auto; padding: 1rem; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; margin-bottom: 1rem; }
        h1, h2 { margin: 0 0 1rem; font-size: 1.2rem; }
        label { display: block; font-weight: 600; margin-bottom: .35rem; }
        .hint { color: var(--muted); font-size: .875rem; font-weight: 400; }
        input[type=email], input[type=password], input[type=number], input[type=text], select {
            width: 100%; padding: .6rem .7rem; font: inherit; color: inherit; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
        }
        input[type=file] { width: 100%; padding: 1.25rem; border: 2px dashed var(--border); border-radius: 10px; background: var(--bg); font: inherit; color: inherit; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .field { margin-bottom: 1rem; }
        .check { display: flex; align-items: center; gap: .5rem; font-weight: 600; }
        button, .btn { display: inline-block; padding: .7rem 1.25rem; font: inherit; font-weight: 600; border: 0; border-radius: 8px; cursor: pointer; background: var(--primary); color: var(--primary-text); }
        button:disabled { opacity: .6; cursor: wait; }
        .btn-link { background: none; color: var(--muted); padding: .4rem .6rem; font-weight: 500; }
        .btn-block { width: 100%; padding: .9rem; font-size: 1.05rem; }
        .alert { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .alert-ok { background: var(--ok-bg); color: var(--ok); }
        .alert-error { background: var(--error-bg); color: var(--error); }
        .error { color: var(--error); font-size: .875rem; margin-top: .25rem; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .925rem; }
        th, td { text-align: left; padding: .55rem .5rem; border-bottom: 1px solid var(--border); white-space: nowrap; }
        th { color: var(--muted); font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; }
        td.name { white-space: normal; word-break: break-word; min-width: 10rem; }
        .badge { display: inline-block; padding: .1rem .55rem; border-radius: 999px; font-size: .8rem; font-weight: 600; background: var(--info-bg); }
        .badge-printed { background: var(--ok-bg); color: var(--ok); }
        .badge-failed, .badge-cancelled, .badge-expired { background: var(--error-bg); color: var(--error); }
        .badge-queued, .badge-printing { background: var(--warn-bg); color: var(--warn); }
        .dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; margin-right: .3rem; background: var(--error); }
        .dot-on { background: var(--ok); }
        .muted { color: var(--muted); }
    </style>
</head>
<body>
    <header>
        <strong>{{ config('app.name') }}</strong>
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <span class="muted">{{ auth()->user()->name }}</span>
                <button type="submit" class="btn-link">Salir</button>
            </form>
        @endauth
    </header>
    <main>
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>

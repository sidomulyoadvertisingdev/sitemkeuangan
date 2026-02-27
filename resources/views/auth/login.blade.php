<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - RAMS Finance Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #dbe3ef;
            --accent-org: #2563eb;
            --accent-coop: #059669;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Manrope", sans-serif;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 18px;
        }
        .wrap {
            width: 100%;
            max-width: 460px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            padding: 22px;
        }
        .mode-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 10px;
        }
        .mode-org { background: linear-gradient(140deg, #2563eb, #1d4ed8); }
        .mode-coop { background: linear-gradient(140deg, #10b981, #047857); }
        h1 { margin: 0 0 4px; font-size: 1.5rem; }
        p { margin: 0 0 18px; color: var(--muted); font-size: 0.9rem; }
        label { display: block; font-weight: 700; margin-bottom: 7px; font-size: 0.9rem; }
        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 11px 12px;
            font: inherit;
            margin-bottom: 12px;
            outline: none;
        }
        input:focus { border-color: #9fb6df; }
        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            font-size: 0.86rem;
        }
        .remember-row a { color: var(--muted); text-decoration: none; font-weight: 700; }
        .remember-row a:hover { color: var(--text); }
        .btn {
            width: 100%;
            border: 0;
            color: #fff;
            border-radius: 10px;
            padding: 11px 12px;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
        }
        .btn-org { background: linear-gradient(140deg, #2563eb, #1d4ed8); }
        .btn-coop { background: linear-gradient(140deg, #10b981, #047857); }
        .btn-google {
            display: block;
            text-align: center;
            margin-top: 10px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            text-decoration: none;
            color: var(--text);
            font-weight: 700;
        }
        .foot {
            margin-top: 14px;
            text-align: center;
            font-size: 0.86rem;
            color: var(--muted);
        }
        .foot a { color: var(--text); text-decoration: none; font-weight: 700; }
        .alerts { margin-bottom: 12px; font-size: 0.86rem; }
        .alert { border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
        .alert-error { border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; }
        .alert-ok { border: 1px solid #bbf7d0; background: #f0fdf4; color: #166534; }
    </style>
</head>
<body>
@php
    $mode = old('login_mode', $mode ?? 'organization');
    $mode = in_array($mode, ['organization', 'cooperative'], true) ? $mode : 'organization';
    $isCoop = $mode === 'cooperative';
@endphp
<div class="wrap">
    <span class="mode-badge {{ $isCoop ? 'mode-coop' : 'mode-org' }}">
        {{ $isCoop ? 'Cooperative Finance' : 'Organizational Finance' }}
    </span>
    <h1>Masuk ke Sistem</h1>
    <p>Gunakan akun {{ $isCoop ? 'koperasi' : 'organisasi' }} Anda.</p>

    @if ($errors->any() || session('status'))
        <div class="alerts">
            @if ($errors->any())
                <div class="alert alert-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @if (session('status'))
                <div class="alert alert-ok">{{ session('status') }}</div>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <input type="hidden" name="login_mode" value="{{ $mode }}">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <div class="remember-row">
            <label style="display:flex;align-items:center;gap:7px;margin:0;font-weight:600;">
                <input type="checkbox" name="remember" style="width:auto;margin:0;">
                Ingat saya
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">Lupa password?</a>
            @endif
        </div>

        <button class="btn {{ $isCoop ? 'btn-coop' : 'btn-org' }}" type="submit">
            Login {{ $isCoop ? 'Cooperative' : 'Organization' }}
        </button>
    </form>

    <a class="btn-google" href="{{ route('auth.google.redirect', ['mode' => $mode]) }}">
        Masuk dengan Google
    </a>

    <div class="foot">
        Belum punya akun?
        <a href="{{ route('register', ['mode' => $mode]) }}">Daftar</a>
        • <a href="{{ url('/') }}">Kembali ke Home</a>
    </div>
</div>
</body>
</html>

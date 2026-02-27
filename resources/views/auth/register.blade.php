<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar - RAMS Finance Solutions</title>
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
            max-width: 500px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            padding: 22px;
        }
        .mode {
            display: inline-block;
            color: #fff;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
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
        .btn {
            width: 100%;
            border: 0;
            color: #fff;
            border-radius: 10px;
            padding: 11px 12px;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            background: linear-gradient(140deg, #2563eb, #1d4ed8);
        }
        .btn-coop { background: linear-gradient(140deg, #10b981, #047857); }
        .alerts { margin-bottom: 12px; font-size: 0.86rem; }
        .alert { border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
        .alert-error { border: 1px solid #fecaca; background: #fef2f2; color: #b91c1c; }
        .note {
            margin-top: 12px;
            border-radius: 10px;
            padding: 10px 12px;
            border: 1px solid #fef08a;
            background: #fefce8;
            color: #854d0e;
            font-size: 0.83rem;
        }
        .foot {
            margin-top: 12px;
            text-align: center;
            color: var(--muted);
            font-size: 0.86rem;
        }
        .foot a { color: var(--text); text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
@php
    $mode = old('account_mode', $mode ?? 'organization');
    $mode = in_array($mode, ['organization', 'cooperative'], true) ? $mode : 'organization';
    $isCoop = $mode === 'cooperative';
@endphp
<div class="wrap">
    <span class="mode {{ $isCoop ? 'mode-coop' : 'mode-org' }}">
        {{ $isCoop ? 'Cooperative Finance' : 'Organizational Finance' }}
    </span>
    <h1>Pendaftaran Akun</h1>
    <p>Buat akun baru untuk {{ $isCoop ? 'koperasi' : 'organisasi' }}.</p>

    @if ($errors->any())
        <div class="alerts">
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <input type="hidden" name="account_mode" value="{{ $mode }}">

        <label for="name">Nama Lengkap</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus>

        <label for="organization_name">{{ $isCoop ? 'Nama Koperasi' : 'Nama Organisasi' }}</label>
        <input id="organization_name" name="organization_name" type="text" value="{{ old('organization_name') }}" required>

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <label for="password_confirmation">Konfirmasi Password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required>

        <button class="btn {{ $isCoop ? 'btn-coop' : '' }}" type="submit">
            Daftar {{ $isCoop ? 'Cooperative' : 'Organization' }}
        </button>
    </form>

    <div class="note">
        Akun baru akan aktif setelah disetujui admin platform.
    </div>

    <div class="foot">
        Sudah punya akun?
        <a href="{{ route('login', ['mode' => $mode]) }}">Login</a>
        • <a href="{{ url('/') }}">Kembali ke Home</a>
    </div>
</div>
</body>
</html>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RAMS Finance Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f1f5f9;
            --text: #0f172a;
            --muted: #64748b;
            --border: #cbd5e1;
            --org: #2563eb;
            --coop: #059669;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Manrope", sans-serif;
            background: var(--bg);
            color: var(--text);
            position: relative;
            overflow-x: hidden;
        }
        .shape-a, .shape-b {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        .shape-a {
            width: 240px;
            height: 240px;
            background: rgba(37, 99, 235, 0.10);
            top: -70px;
            right: -50px;
        }
        .shape-b {
            width: 180px;
            height: 180px;
            background: rgba(5, 150, 105, 0.12);
            bottom: -65px;
            left: -40px;
        }
        .wrap {
            position: relative;
            z-index: 1;
            max-width: 1040px;
            margin: 0 auto;
            padding: 48px 20px 70px;
        }
        .brand {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: linear-gradient(140deg, #2563eb, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
        }
        .brand img { width: 24px; height: 24px; object-fit: contain; }
        h1 {
            margin: 0;
            text-align: center;
            font-size: clamp(1.5rem, 2.7vw, 2.1rem);
            font-weight: 800;
        }
        .subtitle {
            margin: 10px 0 34px;
            text-align: center;
            color: var(--muted);
            font-size: 0.95rem;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            max-width: 780px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06);
        }
        .icon {
            width: 42px;
            height: 42px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .icon-org { background: linear-gradient(140deg, #2563eb, #1d4ed8); }
        .icon-coop { background: linear-gradient(140deg, #10b981, #059669); }
        .title {
            margin: 0 0 8px;
            font-size: 1.1rem;
            font-weight: 800;
        }
        .desc {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: 0.85rem;
            line-height: 1.45;
        }
        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }
        .chip {
            font-size: 0.72rem;
            padding: 5px 9px;
            border-radius: 999px;
            font-weight: 700;
        }
        .chip-org { background: rgba(37, 99, 235, 0.1); color: #1d4ed8; }
        .chip-coop { background: rgba(5, 150, 105, 0.12); color: #047857; }
        .btn {
            display: inline-flex;
            justify-content: center;
            width: 100%;
            text-decoration: none;
            color: #fff;
            padding: 10px 12px;
            border-radius: 10px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .btn-org { background: linear-gradient(140deg, #2563eb, #1d4ed8); }
        .btn-coop { background: linear-gradient(140deg, #10b981, #059669); }
        .sub-link {
            display: block;
            text-align: center;
            text-decoration: none;
            font-size: 0.82rem;
            color: var(--muted);
            font-weight: 700;
        }
        .sub-link:hover { color: var(--text); }
        @media (max-width: 860px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="shape-a"></div>
    <div class="shape-b"></div>
    <main class="wrap">
        <div class="brand">
            <img src="{{ asset('logo-finance.png') }}" alt="Logo">
        </div>
        <h1>Financial Management System</h1>
        <p class="subtitle">Pilih sistem sesuai kebutuhan operasional Anda</p>

        <section class="grid">
            <article class="card">
                <div class="icon icon-org">OF</div>
                <h2 class="title">Organizational Finance</h2>
                <p class="desc">
                    Kelola pemasukan organisasi, pengeluaran, budget, proyek, iuran, dan laporan keuangan umum.
                </p>
                <div class="chips">
                    <span class="chip chip-org">Analytics Dashboard</span>
                    <span class="chip chip-org">Budget Management</span>
                    <span class="chip chip-org">Multi-User</span>
                </div>
                <a class="btn btn-org" href="{{ route('login', ['mode' => 'organization']) }}">Login as Organization</a>
                <a class="sub-link" href="{{ route('register', ['mode' => 'organization']) }}">Daftar Akun Organization</a>
            </article>

            <article class="card">
                <div class="icon icon-coop">KF</div>
                <h2 class="title">Cooperative Finance</h2>
                <p class="desc">
                    Kelola member koperasi, simpanan, pinjaman, angsuran, dashboard analitik koperasi, dan laporan.
                </p>
                <div class="chips">
                    <span class="chip chip-coop">Savings & Loans</span>
                    <span class="chip chip-coop">Member Management</span>
                    <span class="chip chip-coop">Repayment Analytics</span>
                </div>
                <a class="btn btn-coop" href="{{ route('login', ['mode' => 'cooperative']) }}">Login as Cooperative</a>
                <a class="sub-link" href="{{ route('register', ['mode' => 'cooperative']) }}">Daftar Akun Cooperative</a>
            </article>
        </section>
    </main>
</body>
</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Rams Finance')</title>

    {{-- Fonts --}}
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- ADMINLTE CSS (CDN ONLY) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">

    {{-- JANGAN LOAD CSS DARI VITE --}}
    {{-- @vite(['resources/css/app.css']) --}}

    <style>
        :root {
            --accent: #2563eb;
            --accent-2: #22c55e;
            --sidebar-grad: linear-gradient(180deg, #0f172a 0%, #111827 50%, #0b1224 100%);
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #6b7280;
            --shadow: 0 18px 50px rgba(15,23,42,0.07);
            --header-bg: rgba(255,255,255,0.9);
        }
        [data-theme="dark"] {
            --bg: #0b1224;
            --card: #111827;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --shadow: 0 18px 40px rgba(0,0,0,0.55);
            --header-bg: rgba(17,24,39,0.9);
        }
        body { font-family: 'Manrope', system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text);}
        .main-sidebar { background: var(--sidebar-grad); }
        .brand-link { border-bottom: 1px solid rgba(255,255,255,0.08); }
        .nav-sidebar .nav-link { border-radius: 10px; margin: 4px 8px; }
        .nav-sidebar .nav-link.active { background: var(--accent); color: #fff; box-shadow: 0 8px 18px rgba(37,99,235,0.25); }
        .nav-sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,0.06); }
        .small-box { border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); background: linear-gradient(135deg, rgba(37,99,235,0.12), rgba(34,197,94,0.12)); color: var(--text);}
        .card { border: 0; box-shadow: var(--shadow); border-radius: 14px; background: var(--card); color: var(--text);}
        .content-wrapper { background: var(--bg); color: var(--text);}
        .main-header { border: 0; box-shadow: 0 10px 25px rgba(15,23,42,0.08); position: sticky; top: 0; z-index: 1030; background: var(--header-bg) !important; backdrop-filter: blur(8px);}
        .navbar-light .navbar-nav .nav-link { color: var(--text); }
        .navbar-light .navbar-nav .nav-link:hover { color: var(--accent); }
        .theme-toggle { border: 1px solid rgba(0,0,0,0.05); border-radius: 999px; padding: 6px 10px; background: var(--card); color: var(--text); display: inline-flex; align-items: center; gap: 6px; }
        .theme-toggle i { color: var(--accent); }
        .brand-logo { width: 34px; height: 34px; object-fit: contain; border-radius: 8px; background: #fff; padding: 2px; }
        .main-header .dropdown-toggle {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .content-header h1 { margin: 0; font-size: 1.5rem; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table th, .table td { vertical-align: middle; }
        img { max-width: 100%; height: auto; }
        /* Hilangkan skip-link yang muncul dari environment/server */
        a[href^="#skip"], a[href^="#main-content"], a[href^="#navigation"] {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0,0,0,0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        @media (max-width: 991.98px) {
            .content .container-fluid,
            .content-header .container-fluid { padding-left: 0.75rem; padding-right: 0.75rem; }
            .content-header { padding: 0.6rem 0; }
            .content-header h1 { font-size: 1.25rem; }
            .main-header .navbar-nav .nav-link { padding-left: 0.45rem; padding-right: 0.45rem; }
            .small-box .inner h3 { font-size: 1.2rem; line-height: 1.2; }
            .small-box .inner p { font-size: 0.85rem; margin-bottom: 0; }
            .small-box .icon { display: none; }
            .card-header, .card-body { padding: 0.75rem; }
            .btn { margin-bottom: 0.35rem; }
            .form-inline { display: flex; flex-wrap: wrap; gap: 0.45rem; align-items: stretch; }
            .form-inline .form-control { width: 100%; margin-right: 0 !important; }
            .form-inline .btn { margin-right: 0 !important; }
            .pagination { flex-wrap: wrap; }
            .main-header .dropdown-toggle { max-width: 120px; }
        }
        @media (max-width: 575.98px) {
            .brand-link { justify-content: flex-start !important; padding-left: 0.75rem; }
            .brand-text { font-size: 0.95rem; }
            .main-header .dropdown-toggle { max-width: 84px; }
            .theme-toggle { padding: 0.3rem 0.55rem; }
            #themeLabel { display: none; }
            .content-header h1 { font-size: 1.1rem; }
            .nav-sidebar .nav-link p { font-size: 0.9rem; }
            .table td, .table th { padding: 0.45rem; font-size: 0.85rem; }
        }
    </style>

    @stack('styles')
</head>

<body class="hold-transition sidebar-mini layout-fixed" data-theme="light">
<div class="wrapper">

    {{-- ================= NAVBAR ================= --}}
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item mr-2">
                <button class="theme-toggle" id="themeToggle" type="button">
                    <i class="fas fa-moon"></i> <span id="themeLabel">Mode Gelap</span>
                </button>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                    {{ auth()->user()->name }}
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item text-danger"
                       href="{{ route('logout') }}"
                       onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    {{-- ================= SIDEBAR ================= --}}
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('dashboard') }}" class="brand-link text-center d-flex align-items-center justify-content-center">
            <img src="{{ asset('logo-finance.png') }}" alt="Logo Keuangan Pribadi" class="brand-logo mr-2">
            <span class="brand-text font-weight-light">
                Rams Finance
            </span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column"
                    data-widget="treeview"
                    role="menu"
                    data-accordion="false">

                    <li class="nav-item">
                        <a href="{{ route('dashboard') }}"
                           class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="nav-icon fas fa-home"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    @if(auth()->user()->hasPermission('transactions.manage'))
                        <li class="nav-item">
                            <a href="{{ route('transactions.index') }}"
                               class="nav-link {{ request()->routeIs('transactions*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-exchange-alt"></i>
                                <p>Transaksi</p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('bank_accounts.manage'))
                        <li class="nav-item">
                            <a href="{{ route('bank-accounts.index') }}"
                               class="nav-link {{ request()->routeIs('bank-accounts*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-university"></i>
                                <p>Rekening Bank</p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('projects.manage'))
                        <li class="nav-item">
                            <a href="{{ route('projects.index') }}"
                               class="nav-link {{ request()->routeIs('projects*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-diagram-project"></i>
                                <p>Proyek</p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('investments.manage'))
                        <li class="nav-item">
                            <a href="{{ route('investments.index') }}"
                               class="nav-link {{ request()->routeIs('investments*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-chart-line"></i>
                                <p>Investasi</p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('budgets.manage'))
                        <li class="nav-item">
                            <a href="{{ route('budgets.index') }}"
                               class="nav-link {{ request()->routeIs('budgets*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-wallet"></i>
                                <p>Budget</p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('debts.manage'))
                        <li class="nav-item">
                            <a href="{{ route('debts.index') }}"
                               class="nav-link {{ request()->routeIs('debts*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-hand-holding-usd"></i>
                                <p>Hutang & Piutang</p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('iuran.manage'))
                        <li class="nav-item">
                            <a href="{{ route('iuran.index') }}"
                               class="nav-link {{ request()->routeIs('iuran*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-users"></i>
                                <p>Iuran Pemuda</p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('reports.view'))
                        <li class="nav-item">
                            <a href="{{ route('reports.index') }}"
                               class="nav-link {{ request()->routeIs('reports*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-file-alt"></i>
                                <p>Laporan Lengkap</p>
                            </a>
                        </li>
                    @endif

                    @if(auth()->user()->hasPermission('users.manage'))
                        <li class="nav-item">
                            <a href="{{ route('users.index') }}"
                               class="nav-link {{ request()->routeIs('users*') ? 'active' : '' }}">
                                <i class="nav-icon fas fa-user-shield"></i>
                                <p>Manajemen User</p>
                            </a>
                        </li>
                    @endif
                    <li class="nav-item mt-3">
                        <a href="{{ route('logout') }}"
                           class="nav-link text-danger"
                           onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>Logout</p>
                        </a>
                    </li>

                </ul>
            </nav>
        </div>
    </aside>

    {{-- ================= CONTENT ================= --}}
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1>@yield('title')</h1>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </section>
    </div>

</div>

{{-- LOGOUT FORM --}}
<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf
</form>

{{-- ADMINLTE JS (CDN ONLY) --}}
<script src="https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

{{-- VITE (JS SAJA, TANPA CSS) --}}
@vite(['resources/js/app.js'])

@stack('scripts')

<script>
    (() => {
        const body = document.body;
        const btn  = document.getElementById('themeToggle');
        const label= document.getElementById('themeLabel');

        function setTheme(mode) {
            body.setAttribute('data-theme', mode);
            localStorage.setItem('theme', mode);
            if (mode === 'dark') {
                label.textContent = 'Mode Terang';
                btn.querySelector('i').className = 'fas fa-sun';
            } else {
                label.textContent = 'Mode Gelap';
                btn.querySelector('i').className = 'fas fa-moon';
            }
        }

        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        setTheme(saved || (prefersDark ? 'dark' : 'light'));

        btn?.addEventListener('click', () => {
            const current = body.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    })();
    // Force-hide skip links that disisipkan environment
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('a').forEach(el => {
            const text = (el.textContent || '').trim().toLowerCase();
            if (text.startsWith('skip to main content') || text.startsWith('skip to navigation') || text === 'main content') {
                el.style.display = 'none';
            }
        });

        // Bungkus tabel yang belum responsive agar tetap bisa di-scroll di mobile.
        document.querySelectorAll('.content table.table').forEach(table => {
            if (table.closest('.table-responsive')) {
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        });

        // Default collapse sidebar saat layar kecil supaya konten lebih lebar.
        if (window.innerWidth < 992) {
            document.body.classList.add('sidebar-collapse');
        }
    });
</script>

</body>
</html>

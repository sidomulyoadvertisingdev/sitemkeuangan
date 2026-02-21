<aside class="main-sidebar sidebar-dark-primary elevation-4">

    {{-- BRAND --}}
    <a href="{{ route('dashboard') }}" class="brand-link">
        <span class="brand-text font-weight-light ml-2">
            RAMS Finance Solutions
        </span>
    </a>

    {{-- SIDEBAR --}}
    <div class="sidebar">

        {{-- USER PANEL --}}
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <i class="fas fa-user-circle fa-2x text-white"></i>
            </div>
            <div class="info">
                <a href="#" class="d-block">
                    {{ auth()->user()->name }}
                </a>
            </div>
        </div>

        {{-- MENU --}}
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column"
                data-widget="treeview"
                role="menu"
                data-accordion="false">

                <li class="nav-item">
                    <a href="{{ route('dashboard') }}" class="nav-link">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('transactions.index') }}" class="nav-link">
                        <i class="nav-icon fas fa-exchange-alt"></i>
                        <p>Transaksi</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('budgets.index') }}" class="nav-link">
                        <i class="nav-icon fas fa-wallet"></i>
                        <p>Budget</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('debts.index') }}" class="nav-link">
                        <i class="nav-icon fas fa-hand-holding-usd"></i>
                        <p>Hutang & Piutang</p>
                    </a>
                </li>

            </ul>
        </nav>

    </div>
</aside>

<nav class="main-header navbar navbar-expand navbar-white navbar-light">

    {{-- LEFT NAVBAR --}}
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#">
                <i class="fas fa-bars"></i>
            </a>
        </li>
    </ul>

    {{-- RIGHT NAVBAR --}}
    <ul class="navbar-nav ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#">
                <i class="far fa-user"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <span class="dropdown-item-text">
                    {{ auth()->user()->name }}
                </span>
                <div class="dropdown-divider"></div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="dropdown-item text-danger">
                        Logout
                    </button>
                </form>
            </div>
        </li>
    </ul>

</nav>

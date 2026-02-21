<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAMS Finance Solutions - Pendaftaran</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        .input-focus:focus { box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2); }
        .btn-gold { background: linear-gradient(135deg, #D4AF37 0%, #B8962E 50%, #D4AF37 100%); background-size: 200% 200%; transition: all 0.3s ease; }
        .btn-gold:hover { background-position: 100% 100%; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4); transform: translateY(-2px); }
        .card-shadow { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.02); }
        .hero-bg { background: linear-gradient(135deg, #0a1628 0%, #1a2d4a 55%, #0f1d32 100%); }
    </style>
</head>
<body class="h-full overflow-auto bg-gray-50">
<div class="w-full h-full flex flex-col lg:flex-row">

    <div class="w-full lg:w-1/2 h-64 lg:h-screen relative overflow-hidden flex items-center justify-center p-8 lg:p-12 hero-bg">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-20 left-10 w-64 h-64 rounded-full" style="background: radial-gradient(circle, rgba(212, 175, 55, 0.12) 0%, transparent 70%);"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 rounded-full" style="background: radial-gradient(circle, rgba(212, 175, 55, 0.08) 0%, transparent 70%);"></div>
        </div>
        <div class="relative z-10 text-white text-center lg:text-left max-w-lg">
            <h1 class="font-display text-2xl lg:text-4xl font-bold leading-tight">RAMS Finance Solutions</h1>
            <p class="mt-4 text-sm lg:text-base opacity-90">
                Transparansi kepada anggota Anda dengan pencatatan keuangan yang transparan
            </p>
            <p class="mt-3 text-sm opacity-75">
                Setelah daftar, akun akan diverifikasi admin platform terlebih dahulu.
            </p>
        </div>
    </div>

    <div class="w-full lg:w-1/2 h-auto lg:h-screen flex items-center justify-center p-6 lg:p-12" style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);">
        <div class="w-full max-w-md">
            <div class="card-shadow rounded-3xl p-8 lg:p-10 bg-white">
                <div class="flex items-center justify-center mb-8">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, #0a1628 0%, #1a2d4a 100%);">
                            <img src="{{ asset('logo-finance.png') }}" alt="Logo" class="w-8 h-8 object-contain">
                        </div>
                        <span class="font-display text-2xl font-bold" style="color: #0a1628;">RAMS Finance Solutions</span>
                    </div>
                </div>

                <div class="text-center mb-6">
                    <h2 class="text-2xl font-semibold mb-2" style="color: #0a1628;">Pendaftaran Pengguna</h2>
                    <p class="text-sm" style="color: #6b7280;">Isi data di bawah untuk membuat akun baru</p>
                </div>

                @if ($errors->any())
                    <div class="mb-4 text-sm text-red-600">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('register') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="block text-sm font-medium mb-2" style="color: #374151;">Nama Lengkap</label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name') }}"
                               required
                               autofocus
                               autocomplete="name"
                               placeholder="Masukkan nama lengkap"
                               class="input-focus w-full px-4 py-3.5 rounded-xl border-2 transition-all duration-200 text-sm"
                               style="border-color: #e5e7eb; background: #f9fafb; color: #1f2937;">
                    </div>

                    <div>
                        <label for="organization_name" class="block text-sm font-medium mb-2" style="color: #374151;">Nama Perkumpulan</label>
                        <input type="text"
                               name="organization_name"
                               id="organization_name"
                               value="{{ old('organization_name') }}"
                               required
                               autocomplete="organization"
                               placeholder="Contoh: Pemuda RT 03"
                               class="input-focus w-full px-4 py-3.5 rounded-xl border-2 transition-all duration-200 text-sm"
                               style="border-color: #e5e7eb; background: #f9fafb; color: #1f2937;">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium mb-2" style="color: #374151;">Email</label>
                        <input type="email"
                               name="email"
                               id="email"
                               value="{{ old('email') }}"
                               required
                               autocomplete="username"
                               placeholder="nama@email.com"
                               class="input-focus w-full px-4 py-3.5 rounded-xl border-2 transition-all duration-200 text-sm"
                               style="border-color: #e5e7eb; background: #f9fafb; color: #1f2937;">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-2" style="color: #374151;">Password</label>
                        <div class="relative">
                            <input type="password"
                                   name="password"
                                   id="password"
                                   required
                                   autocomplete="new-password"
                                   placeholder="Minimal 8 karakter"
                                   class="input-focus w-full pl-4 pr-12 py-3.5 rounded-xl border-2 transition-all duration-200 text-sm"
                                   style="border-color: #e5e7eb; background: #f9fafb; color: #1f2937;">
                            <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-500 text-xs font-semibold">
                                LIHAT
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium mb-2" style="color: #374151;">Konfirmasi Password</label>
                        <div class="relative">
                            <input type="password"
                                   name="password_confirmation"
                                   id="password_confirmation"
                                   required
                                   autocomplete="new-password"
                                   placeholder="Ulangi password"
                                   class="input-focus w-full pl-4 pr-12 py-3.5 rounded-xl border-2 transition-all duration-200 text-sm"
                                   style="border-color: #e5e7eb; background: #f9fafb; color: #1f2937;">
                            <button type="button" id="toggle-password-confirmation" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-500 text-xs font-semibold">
                                LIHAT
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-gold w-full py-4 rounded-xl text-white font-semibold text-base tracking-wide">
                        Daftar Sekarang
                    </button>
                </form>

                <p class="mt-4 text-xs text-gray-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Akun baru akan aktif setelah persetujuan admin platform.
                </p>

                <p class="text-center mt-6 text-sm" style="color: #6b7280;">
                    Sudah punya akun?
                    <a href="{{ route('login') }}" class="font-semibold text-amber-500 hover:underline">Masuk di sini</a>
                </p>
            </div>

            <p class="text-center mt-4 text-xs text-gray-400">
                © {{ date('Y') }} RAMS Finance Solutions
            </p>
        </div>
    </div>
</div>

<script>
    function bindPasswordToggle(buttonId, inputId) {
        const button = document.getElementById(buttonId);
        const input = document.getElementById(inputId);
        if (!button || !input) return;

        button.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.textContent = isPassword ? 'SEMBUNYIKAN' : 'LIHAT';
        });
    }

    bindPasswordToggle('toggle-password', 'password');
    bindPasswordToggle('toggle-password-confirmation', 'password_confirmation');
</script>
</body>
</html>

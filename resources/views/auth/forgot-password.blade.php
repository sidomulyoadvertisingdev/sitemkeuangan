<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAMS Finance Solutions - Lupa Password</title>
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
        <div class="relative z-10 text-white text-center lg:text-left max-w-lg">
            <h1 class="font-display text-2xl lg:text-4xl font-bold leading-tight">Lupa Password</h1>
            <p class="mt-4 text-sm lg:text-base opacity-90">
                Transparansi kepada anggota Anda dengan pencatatan keuangan yang transparan
            </p>
            <p class="mt-3 text-sm opacity-75">
                Masukkan email akun Anda, kami akan mengirimkan link reset password ke email tersebut.
            </p>
        </div>
    </div>

    <div class="w-full lg:w-1/2 h-auto lg:h-screen flex items-center justify-center p-6 lg:p-12" style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);">
        <div class="w-full max-w-md">
            <div class="card-shadow rounded-3xl p-8 lg:p-10 bg-white">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-semibold mb-2" style="color: #0a1628;">Reset Password via Email</h2>
                    <p class="text-sm" style="color: #6b7280;">Link reset akan dikirim ke email terdaftar</p>
                </div>

                @if (session('status'))
                    <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 text-sm text-red-600">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium mb-2" style="color: #374151;">Email</label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               placeholder="nama@email.com"
                               class="input-focus w-full px-4 py-3.5 rounded-xl border-2 transition-all duration-200 text-sm"
                               style="border-color: #e5e7eb; background: #f9fafb; color: #1f2937;">
                    </div>

                    <button type="submit" class="btn-gold w-full py-4 rounded-xl text-white font-semibold text-base tracking-wide">
                        Kirim Link Reset Password
                    </button>
                </form>

                <p class="text-center mt-6 text-sm" style="color: #6b7280;">
                    Kembali ke halaman login?
                    <a href="{{ route('login') }}" class="font-semibold text-amber-500 hover:underline">Masuk di sini</a>
                </p>
            </div>

            <p class="text-center mt-4 text-xs text-gray-400">
                © {{ date('Y') }} RAMS Finance Solutions
            </p>
        </div>
    </div>
</div>
</body>
</html>

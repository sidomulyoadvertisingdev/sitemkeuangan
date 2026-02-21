<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAMS Finance Solutions - Login</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        @keyframes float { 0%,100% {transform: translateY(0);} 50% {transform: translateY(-10px);} }
        @keyframes pulse-slow { 0%,100% {opacity:.3;} 50% {opacity:.6;} }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-pulse-slow { animation: pulse-slow 4s ease-in-out infinite; }
        .chart-line { stroke-dasharray: 1000; stroke-dashoffset: 1000; animation: draw 3s ease-out forwards; }
        .input-focus:focus { box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2); }
        .btn-gold { background: linear-gradient(135deg, #D4AF37 0%, #B8962E 50%, #D4AF37 100%); background-size: 200% 200%; transition: all 0.3s ease; }
        .btn-gold:hover { background-position: 100% 100%; box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4); transform: translateY(-2px); }
        .card-shadow { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.02); }
    </style>
</head>
<body class="h-full overflow-auto bg-gray-50">
<div class="w-full h-full flex flex-col lg:flex-row">

    {{-- Left Panel --}}
    <div class="w-full lg:w-1/2 h-64 lg:h-screen relative overflow-hidden flex items-center justify-center p-8 lg:p-12" style="background: linear-gradient(135deg, #0a1628 0%, #1a2d4a 50%, #0f1d32 100%);">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute top-20 left-10 w-64 h-64 rounded-full animate-pulse-slow" style="background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);"></div>
            <div class="absolute bottom-20 right-10 w-96 h-96 rounded-full animate-pulse-slow" style="background: radial-gradient(circle, rgba(212, 175, 55, 0.08) 0%, transparent 70%); animation-delay: 2s;"></div>
            <svg class="absolute inset-0 w-full h-full opacity-5" xmlns="http://www.w3.org/2000/svg">
                <defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/></pattern></defs>
                <rect width="100%" height="100%" fill="url(#grid)"/>
            </svg>
        </div>

        <div class="relative z-10 w-full max-w-lg animate-float">
            <svg viewBox="0 0 400 300" class="w-full h-auto" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="chartGradient" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" style="stop-color:#D4AF37;stop-opacity:0.3"/><stop offset="100%" style="stop-color:#D4AF37;stop-opacity:0"/></linearGradient>
                    <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" style="stop-color:#B8962E"/><stop offset="100%" style="stop-color:#D4AF37"/></linearGradient>
                </defs>
                <g stroke="rgba(255,255,255,0.1)" stroke-width="0.5">
                    <line x1="50" y1="50" x2="350" y2="50"/><line x1="50" y1="100" x2="350" y2="100"/><line x1="50" y1="150" x2="350" y2="150"/><line x1="50" y1="200" x2="350" y2="200"/><line x1="50" y1="250" x2="350" y2="250"/>
                </g>
                <path d="M50,220 Q100,200 150,180 T250,120 T350,60 L350,250 L50,250 Z" fill="url(#chartGradient)" opacity="0.5"/>
                <path class="chart-line" d="M50,220 Q100,200 150,180 T250,120 T350,60" fill="none" stroke="url(#lineGradient)" stroke-width="3" stroke-linecap="round"/>
                <circle cx="50" cy="220" r="5" fill="#D4AF37" class="animate-pulse-slow"/>
                <circle cx="150" cy="180" r="5" fill="#D4AF37" class="animate-pulse-slow" style="animation-delay: .5s;"/>
                <circle cx="250" cy="120" r="5" fill="#D4AF37" class="animate-pulse-slow" style="animation-delay: 1s;"/>
                <circle cx="350" cy="60" r="6" fill="#D4AF37" class="animate-pulse-slow" style="animation-delay: 1.5s;">
                    <animate attributeName="r" values="6;8;6" dur="2s" repeatCount="indefinite"/>
                </circle>
                <g transform="translate(320, 40)">
                    <circle cx="15" cy="15" r="18" fill="rgba(212, 175, 55, 0.2)"/>
                    <path d="M15,22 L15,8 M9,14 L15,8 L21,14" stroke="#D4AF37" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
                <text x="350" y="45" fill="#D4AF37" font-size="12" font-weight="600" text-anchor="end">+24.5%</text>
            </svg>
            <div class="mt-8 text-center lg:text-left text-white">
                <h1 class="font-display text-2xl lg:text-4xl font-bold leading-tight">RAMS Finance Solutions</h1>
                <p class="mt-4 text-sm lg:text-base opacity-80">Transparansi kepada anggota Anda dengan pencatatan keuangan yang transparan</p>
            </div>
        </div>
    </div>

    {{-- Right Panel --}}
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

                <div class="text-center mb-8">
                    <h2 class="text-2xl font-semibold mb-2" style="color: #0a1628;">Selamat Datang Kembali</h2>
                    <p class="text-sm" style="color: #6b7280;">Transparansi kepada anggota Anda dengan pencatatan keuangan yang transparan</p>
                </div>

                {{-- Error --}}
                @if ($errors->any())
                    <div class="mb-4 text-sm text-red-600">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium mb-2" style="color: #374151;">Email</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 5L10 11L17 5" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="2" y="4" width="16" height="12" rx="2" stroke="#9CA3AF" stroke-width="1.5"/></svg>
                            </div>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                                   placeholder="nama@email.com"
                                   class="input-focus w-full pl-12 pr-4 py-3.5 rounded-xl border-2 transition-all duration-200 text-sm"
                                   style="border-color: #e5e7eb; background: #f9fafb; color: #1f2937;">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium mb-2" style="color: #374151;">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="9" width="14" height="9" rx="2" stroke="#9CA3AF" stroke-width="1.5"/><path d="M6 9V6C6 3.79086 7.79086 2 10 2C12.2091 2 14 3.79086 14 6V9" stroke="#9CA3AF" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="13.5" r="1.5" fill="#9CA3AF"/></svg>
                            </div>
                            <input type="password" name="password" id="password" required
                                   placeholder="••••••••"
                                   class="input-focus w-full pl-12 pr-12 py-3.5 rounded-xl border-2 transition-all duration-200 text-sm"
                                   style="border-color: #e5e7eb; background: #f9fafb; color: #1f2937;">
                            <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                <svg id="eye-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 4C4 4 1 10 1 10C1 10 4 16 10 16C16 16 19 10 19 10C19 10 16 4 10 4Z" stroke="#9CA3AF" stroke-width="1.5"/>
                                    <circle cx="10" cy="10" r="3" stroke="#9CA3AF" stroke-width="1.5"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <div class="relative">
                                <input type="checkbox" id="remember" name="remember" class="sr-only peer">
                                <div class="w-5 h-5 rounded border-2 transition-all duration-200 peer-checked:border-amber-500 peer-checked:bg-amber-500" style="border-color: #d1d5db;">
                                    <svg class="w-full h-full text-white opacity-0 peer-checked:opacity-100 transition-opacity" viewBox="0 0 20 20" fill="none">
                                        <path d="M5 10L8 13L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </div>
                            <span class="text-sm" style="color: #6b7280;">Ingat saya</span>
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm font-medium transition-colors hover:underline" style="color: #D4AF37;">
                                Lupa password?
                            </a>
                        @endif
                    </div>

                    <button type="submit" id="submit-btn" class="btn-gold w-full py-4 rounded-xl text-white font-semibold text-base tracking-wide">
                        Masuk
                    </button>
                </form>

                <div class="flex items-center gap-4 my-6">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-xs font-medium text-gray-400">ATAU</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('auth.google.redirect') }}"
                       class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl border-2 transition-all duration-200 hover:border-gray-300 hover:bg-gray-50"
                       style="border-color: #e5e7eb;">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M18.171 8.368h-.67v-.035H10v3.333h4.709A4.998 4.998 0 015 10a5 5 0 015-5c1.275 0 2.434.48 3.317 1.266l2.357-2.357A8.295 8.295 0 0010 1.667a8.333 8.333 0 100 16.666 8.333 8.333 0 008.171-9.965z" fill="#FFC107"/><path d="M2.628 6.121l2.74 2.009A4.998 4.998 0 0110 5c1.275 0 2.434.48 3.317 1.266l2.357-2.357A8.295 8.295 0 0010 1.667a8.329 8.329 0 00-7.372 4.454z" fill="#FF3D00"/><path d="M10 18.333a8.294 8.294 0 005.587-2.163l-2.579-2.183A4.964 4.964 0 0110 15a4.998 4.998 0 01-4.701-3.306l-2.72 2.095A8.328 8.328 0 0010 18.333z" fill="#4CAF50"/><path d="M18.171 8.368h-.67v-.035H10v3.333h4.709a5.015 5.015 0 01-1.703 2.321l2.58 2.183c-.183.166 2.747-2.003 2.747-6.17 0-.559-.057-1.104-.162-1.632z" fill="#1976D2"/></svg>
                        <span class="text-sm font-medium" style="color: #374151;">Masuk dengan Google</span>
                    </a>
                </div>

                <p class="text-center mt-6 text-sm" style="color: #6b7280;">
                    Belum punya akun?
                    <a href="{{ route('register') }}" class="font-semibold text-amber-500 hover:underline">Daftar sekarang</a>
                </p>
            </div>
            <p class="text-center mt-4 text-xs text-gray-400">
                © {{ date('Y') }} RAMS Finance Solutions
            </p>
        </div>
    </div>
</div>

<script>
    document.getElementById('toggle-password').addEventListener('click', () => {
        const input = document.getElementById('password');
        const eye   = document.getElementById('eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            eye.innerHTML = '<path d="M2 10C2 10 5 4 10 4C15 4 18 10 18 10" stroke="#D4AF37" stroke-width="1.5" stroke-linecap="round"/><path d="M3 3L17 17" stroke="#D4AF37" stroke-width="1.5" stroke-linecap="round"/>';
        } else {
            input.type = 'password';
            eye.innerHTML = '<path d="M10 4C4 4 1 10 1 10C1 10 4 16 10 16C16 16 19 10 19 10C19 10 16 4 10 4Z" stroke="#9CA3AF" stroke-width="1.5"/><circle cx="10" cy="10" r="3" stroke="#9CA3AF" stroke-width="1.5"/>';
        }
    });
</script>
</body>
</html>

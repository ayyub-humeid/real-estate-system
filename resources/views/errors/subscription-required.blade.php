<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Required - Real Estate System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-[#f8fafc] flex items-center justify-center min-h-screen p-6">
    <div class="max-w-md w-full glass rounded-3xl shadow-2xl p-10 text-center relative overflow-hidden">
        <!-- Decoration -->
        <div class="absolute -top-10 -right-10 w-32 h-32 bg-amber-100 rounded-full blur-3xl opacity-50"></div>
        <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-sky-100 rounded-full blur-3xl opacity-50"></div>

        <div class="relative">
            <div class="w-20 h-20 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-8 shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
            </div>

            <h1 class="text-3xl font-bold text-slate-800 mb-4 tracking-tight">Access Restricted</h1>
            
            <p class="text-slate-600 mb-10 leading-relaxed">
                {{ $message ?? 'Your company requires an active subscription to access the management dashboard.' }}
            </p>

            <div class="space-y-4">
                <button onclick="window.location.reload()" class="w-full bg-slate-800 hover:bg-slate-900 text-white font-semibold py-4 rounded-2xl transition-all shadow-lg hover:shadow-xl active:scale-[0.98]">
                    Refresh Status
                </button>
                
                <form action="{{ route('filament.admin.auth.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-slate-500 hover:text-slate-800 font-medium py-2 transition-colors">
                        Sign Out
                    </button>
                </form>
            </div>

            <div class="mt-10 pt-8 border-t border-slate-100">
                <p class="text-sm text-slate-400">
                    Need help? Contact your administrator or <a href="#" class="text-amber-600 hover:underline">Support Team</a>.
                </p>
            </div>
        </div>
    </div>
</body>
</html>

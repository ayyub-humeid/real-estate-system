<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full antialiased">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EstateHub B2B — Enterprise Administration & Agency Management</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <!-- Typography: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        slate: {
                            850: '#151e2e',
                            900: '#0F172A',
                            950: '#090D16',
                        },
                        ocean: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0EA5E9',
                            600: '#0284C7',
                            700: '#0369a1',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="h-full bg-slate-50 text-slate-700 flex flex-col font-sans selection:bg-ocean-500 selection:text-white">

    <!-- Header Navigation -->
    <header class="w-full bg-slate-900 border-b border-slate-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Branding -->
                <div class="flex items-center space-x-3">
                    <div
                        class="h-10 w-10 rounded-lg bg-gradient-to-br from-ocean-500 to-ocean-600 flex items-center justify-center text-white shadow-md shadow-ocean-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <span class="text-xl font-extrabold text-white tracking-tight">EstateHub <span
                                class="text-ocean-500 font-semibold text-lg">B2B</span></span>
                        <span class="block text-xs text-slate-400 font-medium tracking-wider uppercase">Enterprise
                            Gateway</span>
                    </div>
                </div>

                <!-- Top Right Action CTA -->
                <div class="flex items-center space-x-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/admin') }}"
                                class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-ocean-600 hover:bg-ocean-500 rounded-lg transition-all duration-200 shadow-sm shadow-ocean-600/30">
                                <span>Go to Dashboard</span>
                                <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                            </a>
                        @else
                            <a href="{{ url('/admin/login') }}"
                                class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-slate-200 hover:text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg transition-all duration-200">
                                Sign In
                            </a>
                            <a href="{{ url('/admin') }}"
                                class="hidden sm:inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-ocean-600 hover:bg-ocean-500 rounded-lg transition-all duration-200 shadow-sm shadow-ocean-600/30">
                                Access Control Panel
                                <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                            </a>
                        @endauth
                    @else
                        <a href="{{ url('/admin') }}"
                            class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-ocean-600 hover:bg-ocean-500 rounded-lg transition-all duration-200 shadow-sm shadow-ocean-600/30">
                            Access Control Panel
                            <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow">

        <!-- Hero Section -->
        <section
            class="relative bg-slate-900 text-white pt-16 pb-24 lg:pt-24 lg:pb-32 overflow-hidden border-b border-slate-800">
            <!-- Background Subtle Grid Overlay -->
            <div
                class="absolute inset-0 opacity-10 bg-[radial-gradient(#38bdf8_1px,transparent_1px)] [background-size:24px_24px]">
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="max-w-3xl mx-auto text-center">

                    <!-- Pill Tag -->
                    <div
                        class="inline-flex items-center space-x-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700/80 text-ocean-500 text-xs font-semibold uppercase tracking-wider mb-6">
                        <span class="w-2 h-2 rounded-full bg-ocean-500 animate-pulse"></span>
                        <span>Core Backend Infrastructure</span>
                    </div>

                    <!-- Main Headline -->
                    <h1
                        class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white tracking-tight leading-tight mb-6">
                        EstateHub B2B — Backend Administration & Agency Management
                    </h1>

                    <!-- Sub-headline -->
                    <p class="text-lg sm:text-xl text-slate-300 font-normal leading-relaxed mb-10 max-w-2xl mx-auto">
                        The central backend portal to control real estate operations, manage agencies, and streamline
                        enterprise workflows.
                    </p>

                    <!-- Primary Action CTA -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <a href="{{ url('/admin') }}"
                            class="w-full sm:w-auto inline-flex items-center justify-center px-8 py-4 text-base font-bold text-white bg-ocean-600 hover:bg-ocean-500 rounded-xl transition-all duration-200 shadow-lg shadow-ocean-600/30 hover:-translate-y-0.5">
                            <span>Access Control Panel</span>
                            <svg class="ml-2.5 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                        <div class="text-xs text-slate-400 sm:text-left text-center">
                            <span class="block text-slate-300 font-semibold">Authorized Personnel Only</span>
                            <span>Restricted Administrative Access</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Core Backend Features Section -->
        <section class="py-20 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div class="text-center max-w-2xl mx-auto mb-16">
                    <h2 class="text-xs font-bold text-ocean-600 uppercase tracking-widest mb-2">Administrative
                        Capabilities</h2>
                    <p class="text-3xl font-extrabold text-slate-900 tracking-tight sm:text-4xl">Enterprise Operating
                        System</p>
                    <p class="mt-3 text-base text-slate-600">Built to power high-volume multi-agency real estate
                        networks with strict security standards.</p>
                </div>

                <!-- 3-Column Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                    <!-- Feature Card 1 -->
                    <div
                        class="bg-white rounded-2xl p-8 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow duration-200 flex flex-col justify-between">
                        <div>
                            <div
                                class="w-12 h-12 rounded-xl bg-slate-900 text-ocean-500 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 mb-3">Agency & Broker Network</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Centralized management of real estate agencies, user roles, and operational permissions
                                across all administrative levels.
                            </p>
                        </div>
                        <div
                            class="mt-6 pt-6 border-t border-slate-100 flex items-center text-xs font-semibold text-ocean-600">
                            <span>Role-Based Access Control</span>
                        </div>
                    </div>

                    <!-- Feature Card 2 -->
                    <div
                        class="bg-white rounded-2xl p-8 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow duration-200 flex flex-col justify-between">
                        <div>
                            <div
                                class="w-12 h-12 rounded-xl bg-slate-900 text-ocean-500 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                    </path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 mb-3">Property Data Engine & Analytics</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                System-wide listing management, data synchronization, financial audits, and operational
                                reporting engines.
                            </p>
                        </div>
                        <div
                            class="mt-6 pt-6 border-t border-slate-100 flex items-center text-xs font-semibold text-ocean-600">
                            <span>Real-Time Audit & Sync</span>
                        </div>
                    </div>

                    <!-- Feature Card 3 -->
                    <div
                        class="bg-white rounded-2xl p-8 border border-slate-200/80 shadow-sm hover:shadow-md transition-shadow duration-200 flex flex-col justify-between">
                        <div>
                            <div
                                class="w-12 h-12 rounded-xl bg-slate-900 text-ocean-500 flex items-center justify-center mb-6">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 mb-3">System Configurations & API Services</h3>
                            <p class="text-sm text-slate-600 leading-relaxed">
                                Engine configurations, backend integration services, Next.js API endpoints, and
                                enterprise security workflows.
                            </p>
                        </div>
                        <div
                            class="mt-6 pt-6 border-t border-slate-100 flex items-center text-xs font-semibold text-ocean-600">
                            <span>Headless Integration Ready</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

    </main>

    <!-- Footer Section -->
    <footer class="bg-slate-900 text-slate-400 border-t border-slate-800 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-sm">
                <!-- Copyright -->
                <div>
                    <p>© {{ date('Y') }} <span class="text-white font-semibold">EstateHub B2B</span>. All rights
                        reserved.</p>
                </div>

                <!-- Developer Attribution -->
                <div class="flex items-center space-x-2">
                    <span class="text-slate-500">Engineered & Developed by</span>
                    <span class="text-white font-semibold hover:text-ocean-500 transition-colors">Ayoub Humeid</span>
                    <span class="text-white font-semibold hover:text-ocean-500 transition-colors">Abd Alaziz
                        Almufti</span>
                </div>
            </div>
        </div>
    </footer>

</body>

</html>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full antialiased">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EstateHub — Central Backend & Agency Management Portal</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <!-- Typography: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Inter"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace'],
                    },
                    colors: {
                        slate: {
                            50: '#F8FAFC',
                            100: '#F1F5F9',
                            200: '#E2E8F0',
                            800: '#1E293B',
                            900: '#0F172A',
                        },
                        blue: {
                            600: '#2563EB',
                            700: '#1D4ED8',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="h-full bg-slate-50 text-slate-900 font-sans selection:bg-blue-600 selection:text-white flex flex-col">

    <!-- Header Navigation -->
    <header class="w-full bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Branding -->
                <div class="flex items-center space-x-3">
                    <span class="text-xl font-bold tracking-tight text-slate-900">EstateHub B2B</span>
                    <span
                        class="px-2.5 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-xs font-medium text-slate-600 uppercase tracking-wider hidden sm:inline-block">
                        Backend System
                    </span>
                </div>

                <!-- Action CTA -->
                <div class="flex items-center space-x-4">
                    <a href="{{ url('/admin') }}"
                        class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md transition-colors duration-150">
                        Sign In / Control Panel
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-grow">
        <!-- Hero / Introduction -->
        <section class="bg-white border-b border-slate-200 pt-20 pb-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h1 class="text-4xl sm:text-5xl font-extrabold text-slate-900 tracking-tight mb-6">
                    EstateHub — Central Backend &<br class="hidden sm:block" /> Agency Management Portal
                </h1>
                <p class="mt-4 text-lg sm:text-xl text-slate-600 max-w-3xl mx-auto leading-relaxed mb-10">
                    The primary administrative infrastructure for orchestrating real estate agencies, data
                    synchronization, and enterprise operations.
                </p>
                <div class="flex justify-center">
                    <a href="{{ url('/admin') }}"
                        class="inline-flex items-center justify-center px-6 py-3 text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 border border-transparent rounded-md transition-colors duration-150">
                        Open Control Panel
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Section 1: Live System Operational Status -->
        <section class="py-12 bg-slate-50 border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Metric 1 -->
                    <div class="bg-white p-5 border border-slate-200 rounded-lg flex items-center space-x-4">
                        <div class="flex-shrink-0 w-3 h-3 rounded-full bg-green-500"></div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">System Status</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">Operational (100% Uptime)</p>
                        </div>
                    </div>
                    <!-- Metric 2 -->
                    <div class="bg-white p-5 border border-slate-200 rounded-lg flex items-center space-x-4">
                        <div class="flex-shrink-0 w-3 h-3 rounded-full bg-green-500"></div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">API Bridge</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">Connected (Next.js Frontend)</p>
                        </div>
                    </div>
                    <!-- Metric 3 -->
                    <div class="bg-white p-5 border border-slate-200 rounded-lg flex items-center space-x-4">
                        <div class="flex-shrink-0 w-3 h-3 rounded-full bg-blue-500"></div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Database Engine</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">Active (PostgreSQL)</p>
                        </div>
                    </div>
                    <!-- Metric 4 -->
                    <div class="bg-white p-5 border border-slate-200 rounded-lg flex items-center space-x-4">
                        <div class="flex-shrink-0 w-3 h-3 rounded-full bg-purple-500"></div>
                        <div>
                            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Security Protocol</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">Encrypted B2B Auth</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section 2: Core Administrative Capabilities -->
        <section class="py-20 bg-white border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-12">
                    <h2 class="text-2xl font-bold text-slate-900">Core Administrative Capabilities</h2>
                    <p class="mt-2 text-slate-600 text-base">Enterprise tools designed for centralized control.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Capability 1 -->
                    <div class="bg-slate-50 border border-slate-200 p-6 rounded-lg">
                        <div class="w-10 h-10 mb-4 bg-blue-100 text-blue-600 rounded flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-2">Agency Network</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Manage registered real estate agencies, broker permissions, and operational accounts.
                        </p>
                    </div>
                    <!-- Capability 2 -->
                    <div class="bg-slate-50 border border-slate-200 p-6 rounded-lg">
                        <div class="w-10 h-10 mb-4 bg-blue-100 text-blue-600 rounded flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-2">Listing & Property Data</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Centralized database management for real estate listings, media assets, and status flows.
                        </p>
                    </div>
                    <!-- Capability 3 -->
                    <div class="bg-slate-50 border border-slate-200 p-6 rounded-lg">
                        <div class="w-10 h-10 mb-4 bg-blue-100 text-blue-600 rounded flex items-center justify-center">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-2">System Operations</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">
                            Operational logs, system configurations, and automated background jobs.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section 3: Developer & API Architecture -->
        <section class="py-20 bg-slate-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                    <div class="mb-10 lg:mb-0">
                        <h2 class="text-2xl font-bold text-slate-900 mb-4">Decoupled API Architecture</h2>
                        <p class="text-slate-600 text-base leading-relaxed mb-6">
                            This Laravel backend acts as the core business engine, securely managing data,
                            authentication, and enterprise logic. It exposes a robust RESTful API that seamlessly
                            synchronizes with our customer-facing Next.js frontend application.
                        </p>
                        <p class="text-slate-600 text-base leading-relaxed">
                            By decoupling the architecture, we ensure maximum scalability, secure B2B interactions, and
                            high-performance content delivery to the end consumer.
                        </p>
                    </div>
                    <!-- Code Preview Block -->
                    <div class="bg-[#1E293B] rounded-lg p-6 shadow-sm overflow-hidden text-sm font-mono text-slate-300">
                        <div class="flex items-center mb-4 space-x-2">
                            <div class="w-3 h-3 rounded-full bg-slate-600"></div>
                            <div class="w-3 h-3 rounded-full bg-slate-600"></div>
                            <div class="w-3 h-3 rounded-full bg-slate-600"></div>
                            <span class="ml-2 text-xs text-slate-500 font-mono">GET /api/v1/properties</span>
                        </div>
                        <pre class="overflow-x-auto"><code>{
  <span class="text-blue-400">"status"</span>: <span class="text-green-400">"success"</span>,
  <span class="text-blue-400">"data"</span>: {
    <span class="text-blue-400">"id"</span>: <span class="text-orange-400">1042</span>,
    <span class="text-blue-400">"type"</span>: <span class="text-green-400">"commercial"</span>,
    <span class="text-blue-400">"title"</span>: <span class="text-green-400">"Downtown Tech Hub"</span>,
    <span class="text-blue-400">"agency"</span>: {
      <span class="text-blue-400">"id"</span>: <span class="text-orange-400">89</span>,
      <span class="text-blue-400">"name"</span>: <span class="text-green-400">"Prime Real Estate Co."</span>
    },
    <span class="text-blue-400">"sync_status"</span>: <span class="text-green-400">"synced_to_nextjs"</span>
  }
}</code></pre>
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
                    <span class="text-white font-semibold hover:text-blue-500 transition-colors"> Ayoub Humeid </span>
                    <span class="text-white font-semibold hover:text-blue-500 transition-colors"> && Abd Alaziz
                        Almufti</span>
                </div>
            </div>
        </div>
    </footer>

</body>

</html>
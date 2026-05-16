{{-- ============================================================ --}}
{{-- resources/views/layouts/app.blade.php                       --}}
{{-- Layout principal de l'application HomeBase                  --}}
{{-- Inclut : navigation, sidebar, thème, notifications          --}}
{{-- ============================================================ --}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" x-data="{ darkMode: localStorage.getItem('theme') === 'dark' }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO --}}
    <title>@yield('title', 'HomeBase') — {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Votre plateforme personnelle intelligente')">

    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    {{-- PWA Manifest (évolution future) --}}
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#6366f1">

    {{-- Google Fonts : Geist pour le titre, Inter pour le corps --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    {{-- Assets compilés par Vite --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Styles spécifiques à la page --}}
    @stack('styles')
</head>

<body class="h-full bg-gray-50 dark:bg-gray-950 font-inter antialiased transition-colors duration-200"
      x-data="{
          sidebarOpen: localStorage.getItem('sidebar') !== 'false',
          toggleSidebar() { this.sidebarOpen = !this.sidebarOpen; localStorage.setItem('sidebar', this.sidebarOpen); }
      }">

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- SIDEBAR                                                  --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <aside class="fixed inset-y-0 left-0 z-50 flex flex-col transition-all duration-300"
           :class="sidebarOpen ? 'w-64' : 'w-16'">

        {{-- Logo --}}
        <div class="flex items-center gap-3 px-4 py-5 border-b border-gray-200 dark:border-gray-800">
            <div class="flex-shrink-0 w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
            <span class="font-geist font-bold text-gray-900 dark:text-white text-lg transition-opacity duration-200"
                  x-show="sidebarOpen" x-transition>HomeBase</span>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800">

            @php
                $navItems = [
                    ['route' => 'dashboard',     'icon' => 'home',         'label' => 'Tableau de bord'],
                    ['route' => 'projects.index', 'icon' => 'folder',       'label' => 'Projets'],
                    ['route' => 'tasks.index',    'icon' => 'check-square', 'label' => 'Tâches'],
                    ['route' => 'notes.index',    'icon' => 'file-text',    'label' => 'Notes IA'],
                    ['route' => 'chatbot.index',  'icon' => 'message-square','label' => 'Chatbot IA'],
                    ['route' => 'tools.index',    'icon' => 'tool',         'label' => 'Outils'],
                ];
            @endphp

            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group
                          {{ request()->routeIs(rtrim($item['route'], '.index') . '*')
                             ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
                             : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white' }}">

                    @include('components.icon', ['name' => $item['icon'], 'class' => 'w-5 h-5 flex-shrink-0'])

                    <span class="truncate transition-opacity duration-200" x-show="sidebarOpen" x-transition>
                        {{ $item['label'] }}
                    </span>
                </a>
            @endforeach

            {{-- Séparateur --}}
            <div class="my-3 border-t border-gray-200 dark:border-gray-800"></div>

            {{-- Admin (visible uniquement pour les admins) --}}
            @if(auth()->user()?->is_admin)
                <a href="{{ route('admin.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-all">
                    @include('components.icon', ['name' => 'shield', 'class' => 'w-5 h-5 flex-shrink-0'])
                    <span x-show="sidebarOpen" x-transition>Administration</span>
                </a>
            @endif
        </nav>

        {{-- Footer sidebar : profil utilisateur --}}
        <div class="border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3">
            <div class="flex items-center gap-3">
                {{-- Avatar --}}
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-bold">
                    {{ auth()->user()?->initials ?? 'U' }}
                </div>
                <div x-show="sidebarOpen" x-transition class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                        {{ auth()->user()?->name }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                        {{ auth()->user()?->email }}
                    </p>
                </div>
            </div>
        </div>
    </aside>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- CONTENU PRINCIPAL                                        --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div class="transition-all duration-300" :class="sidebarOpen ? 'pl-64' : 'pl-16'">

        {{-- Header / Topbar --}}
        <header class="sticky top-0 z-40 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md border-b border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between px-6 py-3">

                {{-- Toggle sidebar --}}
                <button @click="toggleSidebar()"
                        class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Titre de la page --}}
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white font-geist">
                    @yield('page-title', 'HomeBase')
                </h1>

                {{-- Actions droite --}}
                <div class="flex items-center gap-2">

                    {{-- Bascule Dark Mode --}}
                    <button @click="darkMode = !darkMode; localStorage.setItem('theme', darkMode ? 'dark' : 'light')"
                            class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <svg x-show="!darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                        <svg x-show="darkMode" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </button>

                    {{-- Notifications --}}
                    <button class="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </button>

                    {{-- Déconnexion --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Flash messages --}}
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mx-6 mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm flex items-center justify-between">
                <span>✓ {{ session('success') }}</span>
                <button @click="show = false" class="ml-4 text-green-500 hover:text-green-700">✕</button>
            </div>
        @endif

        @if(session('error'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                 class="mx-6 mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-400 text-sm flex items-center justify-between">
                <span>✕ {{ session('error') }}</span>
                <button @click="show = false" class="ml-4 text-red-500 hover:text-red-700">✕</button>
            </div>
        @endif

        {{-- Contenu de la page --}}
        <main class="p-6">
            @yield('content')
        </main>
    </div>

    {{-- Scripts Alpine.js (déjà inclus via Vite) --}}
    @stack('scripts')
</body>
</html>

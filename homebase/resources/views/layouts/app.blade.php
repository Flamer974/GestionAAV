<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'HomeBase') }}</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <script>
        // Setup Axios CSRF token
        document.addEventListener('DOMContentLoaded', () => {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            if (token && window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
            }
        });
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900">
    <div class="min-h-screen flex flex-col">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-200 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                            <span class="text-xl font-bold text-indigo-600">HomeBase</span>
                        </a>
                        <div class="hidden sm:ml-8 sm:flex sm:space-x-6">
                            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Dashboard
                            </a>
                            <a href="{{ route('chatbot.index') }}" class="{{ request()->routeIs('chatbot.*') ? 'border-indigo-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }} inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                                Chat IA
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        @auth
                            <span class="text-sm text-gray-600 hidden sm:inline">{{ Auth::user()->name }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-red-600 hover:text-red-800">Déconnexion</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Connexion</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="flex-grow">
            {{ $slot ?? '' }}
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 py-4 text-center text-sm text-gray-500">
            &copy; {{ date('Y') }} HomeBase • Laravel {{ app()->version() }} • PHP {{ phpversion() }}
        </footer>
    </div>
    
    @stack('scripts')
</body>
</html>
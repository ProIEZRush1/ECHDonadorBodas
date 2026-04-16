<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') - Rifa Boda</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: { bg: '#0f0f23', sidebar: '#1a1a2e', card: '#16213e' },
                        gold: '#D4A843',
                        primary: '#D4A843',
                    },
                    fontFamily: { inter: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
</head>
<body class="bg-dark-bg text-gray-200 font-inter min-h-screen flex">
    <aside class="w-64 bg-dark-sidebar border-r border-gray-800 min-h-screen flex flex-col fixed">
        <div class="p-6 border-b border-gray-800">
            <h1 class="text-xl font-bold text-gold">Rifa Boda</h1>
            <p class="text-xs text-gray-500 mt-1">Hajnasat Kala</p>
        </div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="/admin" class="flex items-center px-4 py-2.5 rounded-lg text-sm {{ request()->is('admin') && !request()->is('admin/*') ? 'bg-gold/10 text-gold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200' }}">
                Dashboard
            </a>
            <a href="/admin/donadores" class="flex items-center px-4 py-2.5 rounded-lg text-sm {{ request()->is('admin/donadores*') ? 'bg-gold/10 text-gold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200' }}">
                Donadores
            </a>
            <a href="/admin/contacts" class="flex items-center px-4 py-2.5 rounded-lg text-sm {{ request()->is('admin/contacts*') ? 'bg-gold/10 text-gold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200' }}">
                Contactos
            </a>
            <a href="/admin/donations" class="flex items-center px-4 py-2.5 rounded-lg text-sm {{ request()->is('admin/donations*') ? 'bg-gold/10 text-gold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200' }}">
                Comprobantes
            </a>
            <a href="/admin/campaigns" class="flex items-center px-4 py-2.5 rounded-lg text-sm {{ request()->is('admin/campaign*') ? 'bg-gold/10 text-gold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200' }}">
                Campanas
            </a>
            <a href="/admin/import" class="flex items-center px-4 py-2.5 rounded-lg text-sm {{ request()->is('admin/import*') ? 'bg-gold/10 text-gold' : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200' }}">
                Importar CSV
            </a>
            <a href="/admin/export/donadores" class="flex items-center px-4 py-2.5 rounded-lg text-sm text-gray-400 hover:bg-gray-800 hover:text-gray-200">
                Exportar Donadores
            </a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            @auth
                <p class="text-xs text-gray-500 mb-2 px-4">{{ Auth::user()->name }}</p>
            @endauth
            <form action="/logout" method="POST">
                @csrf
                <button type="submit" class="flex items-center w-full px-4 py-2 rounded-lg text-sm text-red-400 hover:bg-red-900/20">
                    Cerrar Sesion
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 ml-64 p-8">
        @if(session('success'))
            <div class="mb-6 bg-green-900/30 border border-green-700 text-green-300 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6 bg-red-900/30 border border-red-700 text-red-300 px-4 py-3 rounded-lg">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>

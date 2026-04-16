<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rifa Boda</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-[#0f0f23] text-gray-200 font-['Inter'] min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md bg-[#16213e] border border-gray-800 rounded-xl p-8">
        <h1 class="text-2xl font-bold text-[#D4A843] text-center mb-2">Rifa Boda</h1>
        <p class="text-gray-500 text-center text-sm mb-8">Hajnasat Kala - Panel de Administracion</p>

        @if($errors->any())
            <div class="mb-4 bg-red-900/30 border border-red-700 text-red-300 px-4 py-3 rounded-lg text-sm">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="/login" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm text-gray-400 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full bg-[#0f0f23] border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 focus:border-[#D4A843] focus:outline-none">
            </div>
            <div>
                <label class="block text-sm text-gray-400 mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full bg-[#0f0f23] border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 focus:border-[#D4A843] focus:outline-none">
            </div>
            <button type="submit" class="w-full bg-[#D4A843] text-[#0f0f23] font-semibold rounded-lg py-2.5 hover:bg-[#c49a3a] transition">
                Entrar
            </button>
        </form>
    </div>
</body>
</html>

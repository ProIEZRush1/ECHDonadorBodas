@extends('layouts.admin')
@section('title', 'Importar Contactos')

@section('content')
<h2 class="text-2xl font-bold text-gold mb-6">Importar Contactos CSV</h2>

<div class="bg-dark-card border border-gray-800 rounded-xl p-6 max-w-xl">
    <form method="POST" action="/admin/import" enctype="multipart/form-data" class="space-y-5">
        @csrf
        <div>
            <label class="block text-sm text-gray-400 mb-2">Archivo CSV</label>
            <input type="file" name="file" accept=".csv,.txt" required
                class="w-full bg-dark-bg border border-gray-700 rounded-lg px-4 py-2.5 text-sm text-gray-200 file:mr-4 file:py-1 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-gold file:text-dark-bg file:font-semibold hover:file:bg-gold/80">
        </div>

        <div class="bg-dark-bg border border-gray-700 rounded-lg p-4">
            <p class="text-sm text-gray-400 mb-2">Formato esperado:</p>
            <code class="text-xs text-gold">telefono,nombre,email</code>
            <p class="text-xs text-gray-500 mt-2">
                - telefono es obligatorio (10 digitos o con prefijo 52)<br>
                - nombre y email son opcionales<br>
                - Se agregan automaticamente el prefijo 52 a numeros de 10 digitos
            </p>
        </div>

        <button type="submit" class="w-full bg-gold text-dark-bg font-semibold rounded-lg py-3 hover:bg-gold/80 transition">
            Importar
        </button>
    </form>
</div>
@endsection

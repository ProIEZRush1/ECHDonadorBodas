@extends('layouts.admin')
@section('title', 'Nueva Campana')

@section('content')
<a href="/admin/campaigns" class="text-gray-500 hover:text-gray-300 text-sm">&larr; Campanas</a>
<h2 class="text-2xl font-bold text-gold mt-2 mb-6">Nueva Campana de Rifa</h2>

<div class="bg-dark-card border border-gray-800 rounded-xl p-6 max-w-2xl">
    <form method="POST" action="/admin/campaign/launch" class="space-y-5">
        @csrf
        <div>
            <label class="block text-sm text-gray-400 mb-1">Nombre de la campana</label>
            <input type="text" name="nombre" required placeholder="Ej: Rifa Boda - Abril 2026"
                class="w-full bg-dark-bg border border-gray-700 rounded-lg px-4 py-2.5 text-sm text-gray-200 focus:border-gold focus:outline-none">
        </div>

        <div>
            <label class="block text-sm text-gray-400 mb-2">Audiencia</label>
            <div class="space-y-2">
                <label class="flex items-center gap-3 p-3 bg-dark-bg rounded-lg border border-gray-700 cursor-pointer hover:border-gold/50">
                    <input type="radio" name="audiencia" value="nuevos" checked class="text-gold">
                    <div>
                        <p class="text-sm text-gray-200">Solo nuevos</p>
                        <p class="text-xs text-gray-500">{{ $contactCounts['nuevos'] }} contactos sin contactar</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 bg-dark-bg rounded-lg border border-gray-700 cursor-pointer hover:border-gold/50">
                    <input type="radio" name="audiencia" value="nunca_enviados" class="text-gold">
                    <div>
                        <p class="text-sm text-gray-200">Nunca enviados</p>
                        <p class="text-xs text-gray-500">{{ $neverSentCount }} contactos sin campana previa</p>
                    </div>
                </label>
                <label class="flex items-center gap-3 p-3 bg-dark-bg rounded-lg border border-gray-700 cursor-pointer hover:border-gold/50">
                    <input type="radio" name="audiencia" value="todos" class="text-gold">
                    <div>
                        <p class="text-sm text-gray-200">Todos</p>
                        <p class="text-xs text-gray-500">{{ $contactCounts['todos'] }} contactos en total</p>
                    </div>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm text-gray-400 mb-1">Limite aleatorio (opcional)</label>
            <input type="number" name="random_count" min="1" max="5000" placeholder="Dejar vacio para enviar a todos"
                class="w-full bg-dark-bg border border-gray-700 rounded-lg px-4 py-2.5 text-sm text-gray-200 focus:border-gold focus:outline-none">
            <p class="text-xs text-gray-500 mt-1">Si se define, selecciona N contactos al azar de la audiencia</p>
        </div>

        <button type="submit" class="w-full bg-gold text-dark-bg font-semibold rounded-lg py-3 hover:bg-gold/80 transition">
            Lanzar Campana
        </button>
    </form>
</div>
@endsection

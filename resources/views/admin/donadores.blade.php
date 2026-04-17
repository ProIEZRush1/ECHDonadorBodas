@extends('layouts.admin')
@section('title', 'Donadores')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-gold">Donadores</h2>
    <div class="flex gap-4 items-center">
        <span class="text-sm text-gray-400">{{ $totalBoletos }} boletos | ${{ number_format($totalRecaudado, 0) }} MXN</span>
        <a href="/admin/export/donadores" class="bg-gold text-dark-bg px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gold/80">Exportar CSV</a>
    </div>
</div>

<form method="GET" class="mb-6">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar donador..."
        class="bg-dark-card border border-gray-700 rounded-lg px-4 py-2 text-sm text-gray-200 focus:border-gold focus:outline-none w-80">
</form>

<div class="bg-dark-card border border-gray-800 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-gray-500 border-b border-gray-800 bg-dark-bg">
                <th class="text-left py-3 px-4">Nombre</th>
                <th class="text-left py-3 px-4">Telefono</th>
                <th class="text-left py-3 px-4">Boletos</th>
                <th class="text-left py-3 px-4">Monto</th>
                <th class="text-left py-3 px-4">Ultimo contacto</th>
                <th class="text-left py-3 px-4"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($donadores as $donador)
            <tr class="border-b border-gray-800/50 hover:bg-gray-800/30">
                <td class="py-3 px-4 font-medium text-green-300">{{ $donador->nombre_display }}</td>
                <td class="py-3 px-4 text-gray-400">{{ $donador->telefono }}</td>
                <td class="py-3 px-4 text-gold font-bold">{{ $donador->boletos }}</td>
                @php $monto = max((int) ($donador->monto_total ?? 0), $donador->boletos * 3000); @endphp
                <td class="py-3 px-4 text-green-400">${{ number_format($monto, 0) }}</td>
                <td class="py-3 px-4 text-gray-500">{{ $donador->ultimo_contacto?->diffForHumans() ?? '-' }}</td>
                <td class="py-3 px-4">
                    <a href="/admin/contacts/{{ $donador->id }}/chat" class="text-gold hover:underline text-xs">Chat</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="py-8 text-center text-gray-500">No hay donadores aun</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $donadores->withQueryString()->links() }}</div>
@endsection

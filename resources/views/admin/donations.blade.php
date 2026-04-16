@extends('layouts.admin')
@section('title', 'Comprobantes')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-gold">Comprobantes de Transferencia</h2>
    <div class="flex gap-4 text-sm">
        <span class="text-yellow-400">{{ $pendingCount }} pendientes</span>
        <span class="text-green-400">{{ $verifiedCount }} verificados</span>
    </div>
</div>

{{-- Filters --}}
<div class="flex gap-2 mb-6">
    <a href="/admin/donations" class="px-3 py-1.5 rounded-lg text-sm {{ !request('status') ? 'bg-gold text-dark-bg' : 'bg-dark-card text-gray-400 border border-gray-700 hover:text-gray-200' }}">Todos</a>
    <a href="/admin/donations?status=pending" class="px-3 py-1.5 rounded-lg text-sm {{ request('status') === 'pending' ? 'bg-yellow-600 text-white' : 'bg-dark-card text-gray-400 border border-gray-700 hover:text-gray-200' }}">Pendientes</a>
    <a href="/admin/donations?status=verified" class="px-3 py-1.5 rounded-lg text-sm {{ request('status') === 'verified' ? 'bg-green-600 text-white' : 'bg-dark-card text-gray-400 border border-gray-700 hover:text-gray-200' }}">Verificados</a>
    <a href="/admin/donations?status=rejected" class="px-3 py-1.5 rounded-lg text-sm {{ request('status') === 'rejected' ? 'bg-red-600 text-white' : 'bg-dark-card text-gray-400 border border-gray-700 hover:text-gray-200' }}">Rechazados</a>
</div>

<div class="space-y-4">
    @forelse($donations as $donation)
    <div class="bg-dark-card border border-gray-800 rounded-xl p-5">
        <div class="flex items-start justify-between">
            <div>
                <a href="/admin/contacts/{{ $donation->contact_id }}/chat" class="text-gold font-semibold hover:underline">
                    {{ $donation->contact->nombre_display ?? 'Desconocido' }}
                </a>
                <p class="text-sm text-gray-400">{{ $donation->contact->telefono ?? '' }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $donation->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <div class="text-right">
                @if($donation->status === 'pending')
                    <span class="inline-flex px-2 py-0.5 rounded text-xs bg-yellow-900/30 text-yellow-300 border border-yellow-700/50">Pendiente</span>
                @elseif($donation->status === 'verified')
                    <span class="inline-flex px-2 py-0.5 rounded text-xs bg-green-900/30 text-green-300 border border-green-700/50">Verificado</span>
                @else
                    <span class="inline-flex px-2 py-0.5 rounded text-xs bg-red-900/30 text-red-300 border border-red-700/50">Rechazado</span>
                @endif
            </div>
        </div>

        <div class="mt-3 grid grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-gray-500 text-xs">Monto detectado</p>
                <p class="text-white font-semibold">${{ $donation->amount ? number_format($donation->amount, 0) : '?' }} MXN</p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">Boletos</p>
                <p class="text-gold font-semibold">{{ $donation->boletos }}</p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">Referencia</p>
                <p class="text-gray-300">{{ $donation->reference ?? '-' }}</p>
            </div>
            <div>
                <p class="text-gray-500 text-xs">Confianza IA</p>
                <p class="{{ $donation->confidence === 'high' ? 'text-green-400' : ($donation->confidence === 'medium' ? 'text-yellow-400' : 'text-red-400') }}">
                    {{ ucfirst($donation->confidence) }}
                </p>
            </div>
        </div>

        @if($donation->status === 'pending')
        <div class="mt-4 flex gap-3">
            <form method="POST" action="/admin/donations/{{ $donation->id }}/verify" class="flex gap-2 items-center">
                @csrf
                <input type="number" name="boletos" value="{{ max(1, $donation->amount ? (int)floor($donation->amount / 3000) : 1) }}" min="1"
                    class="w-20 bg-dark-bg border border-gray-700 rounded-lg px-2 py-1.5 text-sm text-gray-200 focus:border-gold focus:outline-none">
                <button type="submit" class="bg-green-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-green-700">Verificar</button>
            </form>
            <form method="POST" action="/admin/donations/{{ $donation->id }}/reject">
                @csrf
                <button type="submit" class="bg-red-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-red-700">Rechazar</button>
            </form>
        </div>
        @endif
    </div>
    @empty
    <div class="bg-dark-card border border-gray-800 rounded-xl p-8 text-center text-gray-500">
        No hay comprobantes {{ request('status') ? 'con este filtro' : '' }}
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $donations->withQueryString()->links() }}</div>
@endsection

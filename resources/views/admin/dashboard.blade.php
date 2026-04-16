@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
<h2 class="text-2xl font-bold text-gold mb-6">Dashboard - Rifa Solidaria</h2>

{{-- Key Raffle Metrics --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-dark-card border border-green-800/30 rounded-xl p-5">
        <p class="text-green-400 text-sm">Boletos Vendidos</p>
        <p class="text-3xl font-bold text-green-300 mt-1">{{ number_format($financial['total_boletos']) }}</p>
    </div>
    <div class="bg-dark-card border border-gold/30 rounded-xl p-5">
        <p class="text-gold text-sm">Recaudado</p>
        <p class="text-3xl font-bold text-gold mt-1">${{ number_format($financial['total_recaudado'], 0) }}</p>
        <p class="text-xs text-gray-500">MXN</p>
    </div>
    <div class="bg-dark-card border border-purple-800/30 rounded-xl p-5">
        <p class="text-purple-400 text-sm">Donadores</p>
        <p class="text-3xl font-bold text-purple-300 mt-1">{{ number_format($stats['donador']) }}</p>
    </div>
    <div class="bg-dark-card border border-cyan-800/30 rounded-xl p-5">
        <p class="text-cyan-400 text-sm">Dias para Sorteo</p>
        <p class="text-3xl font-bold text-cyan-300 mt-1">{{ max(0, $financial['dias_restantes']) }}</p>
        <p class="text-xs text-gray-500">30 Enero 2027</p>
    </div>
</div>

{{-- Contact Status Cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 mb-8">
    <div class="bg-dark-card border border-gray-800 rounded-xl p-4">
        <p class="text-gray-500 text-xs">Total</p>
        <p class="text-2xl font-bold text-white mt-1">{{ number_format($stats['total']) }}</p>
    </div>
    <div class="bg-dark-card border border-blue-800/30 rounded-xl p-4">
        <p class="text-blue-400 text-xs">Nuevos</p>
        <p class="text-2xl font-bold text-blue-300 mt-1">{{ number_format($stats['nuevo']) }}</p>
    </div>
    <div class="bg-dark-card border border-yellow-800/30 rounded-xl p-4">
        <p class="text-yellow-400 text-xs">Contactados</p>
        <p class="text-2xl font-bold text-yellow-300 mt-1">{{ number_format($stats['contactado']) }}</p>
    </div>
    <div class="bg-dark-card border border-cyan-800/30 rounded-xl p-4">
        <p class="text-cyan-400 text-xs">Leido</p>
        <p class="text-2xl font-bold text-cyan-300 mt-1">{{ number_format($stats['leido']) }}</p>
    </div>
    <div class="bg-dark-card border border-orange-800/30 rounded-xl p-4">
        <p class="text-orange-400 text-xs">Interesados</p>
        <p class="text-2xl font-bold text-orange-300 mt-1">{{ number_format($stats['interesado']) }}</p>
    </div>
    <div class="bg-dark-card border border-purple-800/30 rounded-xl p-4">
        <p class="text-purple-400 text-xs">Datos Env.</p>
        <p class="text-2xl font-bold text-purple-300 mt-1">{{ number_format($stats['datos_enviados']) }}</p>
    </div>
    <div class="bg-dark-card border border-green-800/30 rounded-xl p-4">
        <p class="text-green-400 text-xs">Donadores</p>
        <p class="text-2xl font-bold text-green-300 mt-1">{{ number_format($stats['donador']) }}</p>
    </div>
    <div class="bg-dark-card border border-red-800/30 rounded-xl p-4">
        <p class="text-red-400 text-xs">No interes.</p>
        <p class="text-2xl font-bold text-red-300 mt-1">{{ number_format($stats['no_interesado']) }}</p>
    </div>
</div>

{{-- Pending Donations Alert --}}
@if($financial['pending_donations'] > 0)
<div class="bg-orange-900/20 border border-orange-700/50 rounded-xl p-4 mb-8 flex items-center justify-between">
    <div>
        <p class="text-orange-400 font-semibold">{{ $financial['pending_donations'] }} comprobantes pendientes de revision</p>
        <p class="text-sm text-gray-500">Requieren verificacion manual</p>
    </div>
    <a href="/admin/donations?status=pending" class="bg-orange-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-orange-700">Revisar</a>
</div>
@endif

{{-- Cost Analytics --}}
<div class="bg-dark-card border border-gray-800 rounded-xl p-6 mb-8">
    <h3 class="text-lg font-semibold text-gold mb-4">Costos API</h3>
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-dark-bg border border-red-800/30 rounded-lg p-4">
            <p class="text-red-400 text-xs uppercase">Anthropic AI</p>
            <p class="text-xl font-bold text-red-300 mt-1">${{ number_format($financial['anthropic_cost'], 2) }} USD</p>
        </div>
        <div class="bg-dark-bg border border-red-800/30 rounded-lg p-4">
            <p class="text-red-400 text-xs uppercase">WhatsApp API</p>
            <p class="text-xl font-bold text-red-300 mt-1">${{ number_format($financial['whatsapp_cost'], 2) }} USD</p>
        </div>
        <div class="bg-dark-bg border border-red-800/30 rounded-lg p-4">
            <p class="text-red-400 text-xs uppercase">Total Costos</p>
            <p class="text-xl font-bold text-red-200 mt-1">${{ number_format($financial['total_costs_usd'], 2) }} USD</p>
        </div>
    </div>
</div>

{{-- Recent Contacts --}}
<div class="bg-dark-card border border-gray-800 rounded-xl p-6 mb-8">
    <h3 class="text-lg font-semibold text-gold mb-4">Ultimos contactos activos</h3>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-gray-500 border-b border-gray-800">
                    <th class="text-left py-3 px-2">Nombre</th>
                    <th class="text-left py-3 px-2">Telefono</th>
                    <th class="text-left py-3 px-2">Estado</th>
                    <th class="text-left py-3 px-2">Boletos</th>
                    <th class="text-left py-3 px-2">Ultimo contacto</th>
                    <th class="text-left py-3 px-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentContacts as $contact)
                <tr class="border-b border-gray-800/50 hover:bg-gray-800/30">
                    <td class="py-3 px-2">{{ $contact->nombre_display }}</td>
                    <td class="py-3 px-2 text-gray-400">{{ $contact->telefono }}</td>
                    <td class="py-3 px-2">@include('admin._status-badge', ['status' => $contact->status])</td>
                    <td class="py-3 px-2 text-gold">{{ $contact->boletos > 0 ? $contact->boletos : '-' }}</td>
                    <td class="py-3 px-2 text-gray-500">{{ $contact->ultimo_contacto?->diffForHumans() ?? '-' }}</td>
                    <td class="py-3 px-2">
                        <a href="/admin/contacts/{{ $contact->id }}/chat" class="text-gold hover:underline text-xs">Ver chat</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Recent Campaigns --}}
@if($campaigns->count() > 0)
<div class="bg-dark-card border border-gray-800 rounded-xl p-6">
    <h3 class="text-lg font-semibold text-gold mb-4">Ultimas campanas</h3>
    <div class="space-y-3">
        @foreach($campaigns as $campaign)
        <a href="/admin/campaign/{{ $campaign->id }}" class="flex items-center justify-between p-4 bg-dark-bg rounded-lg hover:bg-gray-800">
            <div>
                <p class="font-medium text-gray-200">{{ $campaign->name }}</p>
                <p class="text-xs text-gray-500">{{ $campaign->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <div class="text-right">
                <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $campaign->status === 'completed' ? 'bg-green-900/30 text-green-300' : ($campaign->status === 'sending' ? 'bg-yellow-900/30 text-yellow-300' : 'bg-gray-900/30 text-gray-300') }}">
                    {{ $campaign->status }}
                </span>
                <p class="text-xs text-gray-500 mt-1">{{ $campaign->sent_count }}/{{ $campaign->total_contacts }}</p>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endif
@endsection

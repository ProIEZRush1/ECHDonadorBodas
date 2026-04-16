@extends('layouts.admin')
@section('title', 'Campanas')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-gold">Campanas</h2>
    <a href="/admin/campaign/create" class="bg-gold text-dark-bg px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gold/80">Nueva Campana</a>
</div>

<div class="space-y-3">
    @forelse($campaigns as $campaign)
    <a href="/admin/campaign/{{ $campaign->id }}" class="flex items-center justify-between p-5 bg-dark-card border border-gray-800 rounded-xl hover:bg-gray-800/50">
        <div>
            <p class="font-medium text-gray-200">{{ $campaign->name }}</p>
            <p class="text-xs text-gray-500">{{ $campaign->created_at->format('d/m/Y H:i') }} | Template: {{ $campaign->template_name }}</p>
        </div>
        <div class="text-right">
            <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $campaign->status === 'completed' ? 'bg-green-900/30 text-green-300' : ($campaign->status === 'sending' ? 'bg-yellow-900/30 text-yellow-300' : 'bg-gray-900/30 text-gray-300') }}">
                {{ $campaign->status }}
            </span>
            <p class="text-xs text-gray-400 mt-1">
                Enviados: {{ $campaign->sent_count }}/{{ $campaign->total_contacts }}
                | Entregados: {{ $campaign->delivered_count }}
                | Leidos: {{ $campaign->read_count }}
                | Fallidos: {{ $campaign->failed_count }}
            </p>
        </div>
    </a>
    @empty
    <div class="bg-dark-card border border-gray-800 rounded-xl p-8 text-center text-gray-500">
        No hay campanas. <a href="/admin/campaign/create" class="text-gold hover:underline">Crear una</a>
    </div>
    @endforelse
</div>

<div class="mt-4">{{ $campaigns->links() }}</div>
@endsection

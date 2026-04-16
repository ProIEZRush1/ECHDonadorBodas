@extends('layouts.admin')
@section('title', 'Campana: ' . $campaign->name)

@section('content')
<a href="/admin/campaigns" class="text-gray-500 hover:text-gray-300 text-sm">&larr; Campanas</a>
<h2 class="text-2xl font-bold text-gold mt-2 mb-2">{{ $campaign->name }}</h2>
<p class="text-gray-500 text-sm mb-6">{{ $campaign->created_at->format('d/m/Y H:i') }} | Template: {{ $campaign->template_name }}</p>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-dark-card border border-gray-800 rounded-xl p-4">
        <p class="text-gray-500 text-xs">Total</p>
        <p class="text-2xl font-bold text-white">{{ $campaign->total_contacts }}</p>
    </div>
    <div class="bg-dark-card border border-blue-800/30 rounded-xl p-4">
        <p class="text-blue-400 text-xs">Enviados</p>
        <p class="text-2xl font-bold text-blue-300">{{ $campaign->sent_count }}</p>
    </div>
    <div class="bg-dark-card border border-green-800/30 rounded-xl p-4">
        <p class="text-green-400 text-xs">Entregados</p>
        <p class="text-2xl font-bold text-green-300">{{ $campaign->delivered_count }}</p>
    </div>
    <div class="bg-dark-card border border-cyan-800/30 rounded-xl p-4">
        <p class="text-cyan-400 text-xs">Leidos</p>
        <p class="text-2xl font-bold text-cyan-300">{{ $campaign->read_count }}</p>
    </div>
    <div class="bg-dark-card border border-red-800/30 rounded-xl p-4">
        <p class="text-red-400 text-xs">Fallidos</p>
        <p class="text-2xl font-bold text-red-300">{{ $campaign->failed_count }}</p>
    </div>
</div>

<div class="bg-dark-card border border-gray-800 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-gray-500 border-b border-gray-800 bg-dark-bg">
                <th class="text-left py-3 px-4">Nombre</th>
                <th class="text-left py-3 px-4">Telefono</th>
                <th class="text-left py-3 px-4">Estado Envio</th>
                <th class="text-left py-3 px-4">Estado Contacto</th>
                <th class="text-left py-3 px-4"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($contacts as $contact)
            <tr class="border-b border-gray-800/50 hover:bg-gray-800/30">
                <td class="py-3 px-4">{{ $contact->nombre_display }}</td>
                <td class="py-3 px-4 text-gray-400">{{ $contact->telefono }}</td>
                <td class="py-3 px-4">
                    @php $pivotStatus = $contact->pivot->status; @endphp
                    <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $pivotStatus === 'read' ? 'bg-cyan-900/30 text-cyan-300' : ($pivotStatus === 'delivered' ? 'bg-green-900/30 text-green-300' : ($pivotStatus === 'sent' ? 'bg-blue-900/30 text-blue-300' : ($pivotStatus === 'failed' ? 'bg-red-900/30 text-red-300' : 'bg-gray-900/30 text-gray-300'))) }}">
                        {{ $pivotStatus }}
                    </span>
                </td>
                <td class="py-3 px-4">@include('admin._status-badge', ['status' => $contact->status])</td>
                <td class="py-3 px-4">
                    <a href="/admin/contacts/{{ $contact->id }}/chat" class="text-gold hover:underline text-xs">Chat</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $contacts->links() }}</div>
@endsection

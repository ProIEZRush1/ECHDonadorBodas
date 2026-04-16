@extends('layouts.admin')
@section('title', 'Chat - ' . $contact->nombre_display)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="/admin/contacts" class="text-gray-500 hover:text-gray-300 text-sm">&larr; Contactos</a>
        <h2 class="text-2xl font-bold text-gold mt-1">{{ $contact->nombre_display }}</h2>
        <p class="text-gray-400 text-sm">{{ $contact->telefono }} | @include('admin._status-badge', ['status' => $contact->status]) | Boletos: <span class="text-gold font-bold">{{ $contact->boletos }}</span></p>
    </div>
    <div class="flex gap-2">
        <form method="POST" action="/admin/contacts/{{ $contact->id }}/status" class="flex gap-2">
            @csrf
            <select name="status" class="bg-dark-bg border border-gray-700 rounded-lg px-3 py-1.5 text-sm text-gray-200">
                @foreach(['nuevo', 'contactado', 'leido', 'interesado', 'datos_enviados', 'donador', 'no_interesado'] as $s)
                    <option value="{{ $s }}" {{ $contact->status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="bg-gray-700 text-gray-200 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-600">Cambiar</button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Chat messages --}}
    <div class="lg:col-span-2">
        <div class="bg-dark-card border border-gray-800 rounded-xl p-4 h-[600px] overflow-y-auto flex flex-col gap-3" id="chat-messages">
            @foreach($messages as $msg)
            <div class="flex {{ $msg->direction === 'in' ? 'justify-start' : 'justify-end' }}">
                <div class="max-w-[75%] px-4 py-2.5 rounded-2xl text-sm {{ $msg->direction === 'in' ? 'bg-gray-800 text-gray-200 rounded-bl-md' : 'bg-gold/20 text-gold border border-gold/30 rounded-br-md' }}">
                    <p class="whitespace-pre-wrap">{{ $msg->content }}</p>
                    <p class="text-xs {{ $msg->direction === 'in' ? 'text-gray-500' : 'text-gold/50' }} mt-1">
                        {{ $msg->created_at->format('d/m H:i') }}
                        @if($msg->direction === 'out' && $msg->status)
                            | {{ $msg->status }}
                        @endif
                    </p>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Send message form --}}
        <form method="POST" action="/admin/contacts/{{ $contact->id }}/send" class="mt-3 flex gap-3">
            @csrf
            <input type="text" name="message" placeholder="Escribir mensaje..." required
                class="flex-1 bg-dark-card border border-gray-700 rounded-lg px-4 py-2.5 text-sm text-gray-200 focus:border-gold focus:outline-none">
            <button type="submit" class="bg-gold text-dark-bg px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-gold/80">Enviar</button>
        </form>
    </div>

    {{-- Contact info sidebar --}}
    <div class="space-y-4">
        <div class="bg-dark-card border border-gray-800 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-gold mb-3">Info del Contacto</h3>
            <div class="space-y-2 text-sm">
                <p><span class="text-gray-500">Nombre:</span> {{ $contact->nombre_display }}</p>
                <p><span class="text-gray-500">Telefono:</span> {{ $contact->telefono }}</p>
                <p><span class="text-gray-500">Pais:</span> {{ $contact->pais ?? '-' }}</p>
                <p><span class="text-gray-500">Boletos:</span> <span class="text-gold font-bold">{{ $contact->boletos }}</span></p>
                <p><span class="text-gray-500">Estado IA:</span> {{ $state?->current_step ?? 'N/A' }}</p>
            </div>
        </div>

        @if($donations->count() > 0)
        <div class="bg-dark-card border border-gray-800 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-gold mb-3">Donaciones</h3>
            @foreach($donations as $donation)
            <div class="p-3 bg-dark-bg rounded-lg mb-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm">${{ $donation->amount ? number_format($donation->amount, 0) : '?' }} MXN</span>
                    <span class="text-xs {{ $donation->status === 'verified' ? 'text-green-400' : ($donation->status === 'pending' ? 'text-yellow-400' : 'text-red-400') }}">
                        {{ ucfirst($donation->status) }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ $donation->created_at->format('d/m/Y H:i') }} | {{ $donation->boletos }} boletos</p>
            </div>
            @endforeach
        </div>
        @endif

        @if($state?->collected_data)
        <div class="bg-dark-card border border-gray-800 rounded-xl p-4">
            <h3 class="text-sm font-semibold text-gold mb-3">Datos Recopilados</h3>
            <pre class="text-xs text-gray-400 overflow-x-auto">{{ json_encode($state->collected_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
        @endif
    </div>
</div>

<script>
    document.getElementById('chat-messages').scrollTop = document.getElementById('chat-messages').scrollHeight;
</script>
@endsection

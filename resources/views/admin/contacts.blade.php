@extends('layouts.admin')
@section('title', 'Contactos')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-gold">Contactos</h2>
</div>

{{-- Filters --}}
<div class="bg-dark-card border border-gray-800 rounded-xl p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="text-xs text-gray-500 block mb-1">Buscar</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nombre o telefono..."
                class="bg-dark-bg border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:border-gold focus:outline-none w-60">
        </div>
        <div>
            <label class="text-xs text-gray-500 block mb-1">Estado</label>
            <select name="status[]" multiple class="bg-dark-bg border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:border-gold focus:outline-none">
                @foreach(['nuevo', 'contactado', 'leido', 'interesado', 'datos_enviados', 'donador', 'no_interesado'] as $s)
                    <option value="{{ $s }}" {{ in_array($s, (array)request('status', [])) ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-gold text-dark-bg px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gold/80">Filtrar</button>
        <a href="/admin/contacts" class="text-gray-500 text-sm hover:text-gray-300 px-2 py-2">Limpiar</a>
    </form>
</div>

{{-- Table --}}
<div class="bg-dark-card border border-gray-800 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-gray-500 border-b border-gray-800 bg-dark-bg">
                <th class="text-left py-3 px-4">Nombre</th>
                <th class="text-left py-3 px-4">Telefono</th>
                <th class="text-left py-3 px-4">Estado</th>
                <th class="text-left py-3 px-4">Boletos</th>
                <th class="text-left py-3 px-4">Ultimo contacto</th>
                <th class="text-left py-3 px-4"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($contacts as $contact)
            <tr class="border-b border-gray-800/50 hover:bg-gray-800/30">
                <td class="py-3 px-4">{{ $contact->nombre_display }}</td>
                <td class="py-3 px-4 text-gray-400">{{ $contact->telefono }}</td>
                <td class="py-3 px-4">@include('admin._status-badge', ['status' => $contact->status])</td>
                <td class="py-3 px-4 text-gold">{{ $contact->boletos > 0 ? $contact->boletos : '-' }}</td>
                <td class="py-3 px-4 text-gray-500">{{ $contact->ultimo_contacto?->diffForHumans() ?? '-' }}</td>
                <td class="py-3 px-4">
                    <a href="/admin/contacts/{{ $contact->id }}/chat" class="text-gold hover:underline text-xs">Chat</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="py-8 text-center text-gray-500">No hay contactos</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $contacts->withQueryString()->links() }}</div>
@endsection

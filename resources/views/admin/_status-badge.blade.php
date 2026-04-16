@php
$colors = [
    'nuevo' => 'bg-blue-900/30 text-blue-300 border-blue-700/50',
    'contactado' => 'bg-yellow-900/30 text-yellow-300 border-yellow-700/50',
    'leido' => 'bg-cyan-900/30 text-cyan-300 border-cyan-700/50',
    'interesado' => 'bg-orange-900/30 text-orange-300 border-orange-700/50',
    'datos_enviados' => 'bg-purple-900/30 text-purple-300 border-purple-700/50',
    'donador' => 'bg-green-900/30 text-green-300 border-green-700/50',
    'no_interesado' => 'bg-red-900/30 text-red-300 border-red-700/50',
];
$labels = [
    'nuevo' => 'Nuevo',
    'contactado' => 'Contactado',
    'leido' => 'Leido',
    'interesado' => 'Interesado',
    'datos_enviados' => 'Datos enviados',
    'donador' => 'Donador',
    'no_interesado' => 'No interesado',
];
@endphp
<span class="inline-flex px-2 py-0.5 rounded text-xs border {{ $colors[$status] ?? 'bg-gray-900/30 text-gray-300 border-gray-700/50' }}">
    {{ $labels[$status] ?? $status }}
</span>

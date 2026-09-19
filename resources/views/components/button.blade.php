@props(['variant' => 'primary', 'icon' => null])
@php
    $base = 'w-full inline-flex items-center justify-center gap-2 rounded-xl py-3.5 font-semibold text-base transition active:scale-[0.98] disabled:opacity-50 disabled:active:scale-100';
    $variants = [
        'primary' => 'bg-[var(--accent)] hover:bg-[var(--accent-hover)] text-white shadow-lg shadow-[var(--accent)]/25',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white shadow-lg shadow-red-600/25',
        'success' => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-lg shadow-emerald-600/25',
        'ghost' => 'bg-neutral-100 hover:bg-neutral-200 text-neutral-800',
        'dark-ghost' => 'bg-neutral-800 hover:bg-neutral-700 text-neutral-100',
    ];
    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp
<button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>
    @if($icon)<i class="fa-solid {{ $icon }}"></i>@endif
    {{ $slot }}
</button>

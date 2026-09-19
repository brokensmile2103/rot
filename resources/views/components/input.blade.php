@props(['label' => null, 'icon' => null, 'name', 'type' => 'text'])
<div>
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-neutral-700 mb-2">{{ $label }}</label>
    @endif
    <div class="relative">
        @if($icon)
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400 text-sm">
                <i class="fa-solid {{ $icon }}"></i>
            </span>
        @endif
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $name }}"
            {{ $attributes->merge([
                'autocomplete' => 'off',
                'class' => 'w-full rounded-xl border border-neutral-300 bg-white text-neutral-900 placeholder-neutral-400 py-3 text-base focus:border-[var(--accent-ring)] focus:ring-2 focus:ring-amber-500/30 focus:outline-none transition '
                    . ($icon ? 'pl-11 pr-4' : 'px-4'),
            ]) }}
        >
    </div>
    @error($name)
        <p class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
            <i class="fa-solid fa-circle-exclamation"></i>{{ $message }}
        </p>
    @enderror
</div>

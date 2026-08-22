@props(['title' => null, 'href' => null])
@php
    $classes = 'bg-surface-raised rounded-lg border border-ink/10 p-5 shadow-[0_1px_2px_rgba(33,32,28,0.04)]'
        . ($href ? ' block transition-colors hover:border-brass' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($title)
            <h3 class="mb-3 font-display text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ $title }}</h3>
        @endif
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
        @if ($title)
            <h3 class="mb-3 font-display text-xs font-semibold uppercase tracking-wide text-ink-soft">{{ $title }}</h3>
        @endif
        {{ $slot }}
    </div>
@endif

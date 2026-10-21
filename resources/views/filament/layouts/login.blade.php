@php
    $livewire ??= null;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <main class="madar-login-layout min-h-screen w-full">
        {{ $slot }}
    </main>
</x-filament-panels::layout.base>

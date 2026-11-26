<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
        <x-slot name="heading">{{ $heading }}</x-slot>
        <x-slot name="description">{{ __('contract_domain.readiness.explanation') }}</x-slot>

        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($gaps as $gap)
                <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                    <div>
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $gap['type'] }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $gap['problem'] }}</p>
                    </div>
                    @if ($gap['url'])
                        <x-filament::button tag="a" :href="$gap['url']" size="sm" color="warning">
                            {{ $gap['action'] }}
                        </x-filament::button>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('contract_domain.readiness.ask_admin') }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>

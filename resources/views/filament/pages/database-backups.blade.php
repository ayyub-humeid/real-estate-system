<x-filament-panels::page>
    @php($data = $this->listing())
    <div wire:poll.5s class="space-y-6">
        <x-filament::section :heading="__('backups.create_heading')" :description="__('backups.create_description')" icon="heroicon-o-circle-stack">
            @if ($this->isRunning())
                <p class="text-sm text-warning-600 dark:text-warning-400">{{ __('backups.running') }}</p>
            @else
                <x-filament::button wire:click="createBackup" icon="heroicon-o-arrow-down-on-square-stack">
                    {{ __('backups.create_button') }}
                </x-filament::button>
            @endif
        </x-filament::section>

        <x-filament::section :heading="__('backups.list_heading')" :description="__('backups.list_description')">
            @if ($data['error'])
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ __('backups.list_failed', ['detail' => $data['error']]) }}</p>
            @elseif (empty($data['backups']))
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('backups.empty') }}</p>
            @else
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($data['backups'] as $backup)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="backup-{{ $backup['name'] }}">
                            <div>
                                <div class="text-sm font-medium text-gray-950 dark:text-white">{{ $backup['date']->translatedFormat('Y-m-d H:i') }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $backup['name'] }} · {{ \Illuminate\Support\Number::fileSize($backup['size']) }}</div>
                            </div>
                            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="download('{{ $backup['path'] }}')">
                                {{ __('backups.download') }}
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>

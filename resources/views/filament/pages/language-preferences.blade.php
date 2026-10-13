<x-filament-panels::page>
    <div dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" class="max-w-2xl">
        <x-filament::section :heading="__('settings.language')" :description="__('settings.language_description')"
            icon="heroicon-o-language">
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['en' => 'English', 'ar' => 'العربية'] as $value => $label)
                    <label @class([
                        'flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition',
                        'border-primary-500 bg-primary-50 ring-1 ring-primary-500 dark:bg-primary-950/30' => $locale === $value,
                        'border-gray-200 hover:border-primary-300 dark:border-gray-700 dark:hover:border-primary-700' => $locale !== $value,
                    ])>
                        <input wire:model.live="locale" type="radio" value="{{ $value }}" class="fi-radio-input">
                        <span class="text-base font-medium text-gray-950 dark:text-white">{{ $label }}</span>
                    </label>
                @endforeach
            </div>

        </x-filament::section>

        <div class="mt-6 flex {{ $locale === 'ar' ? 'justify-start' : 'justify-end' }}">
            <x-filament::button wire:click="save" icon="heroicon-o-check">
                {{ __('settings.save_language') }}
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
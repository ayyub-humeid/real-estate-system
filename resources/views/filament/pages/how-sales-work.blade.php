<x-filament-panels::page>
    <div class="max-w-4xl space-y-6">
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('crm.guide.intro') }}</p>

            <div class="mt-6 flex flex-wrap items-stretch gap-2">
                @foreach (__('crm.guide.steps') as $index => $step)
                    <div class="flex items-stretch gap-2">
                        <div class="flex min-w-[9rem] flex-col justify-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $step['label'] }}</span>
                        </div>
                        @if (!$loop->last)
                            <div class="flex items-center text-gray-400 dark:text-gray-600">
                                <x-heroicon-o-arrow-{{ app()->getLocale() === 'ar' ? 'left' : 'right' }} class="h-5 w-5" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                @foreach (__('crm.guide.steps') as $step)
                    <div class="rounded-lg border border-gray-100 p-3 dark:border-gray-800">
                        <dt class="text-sm font-semibold text-gray-950 dark:text-white">{{ $step['label'] }}</dt>
                        <dd class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $step['desc'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        @foreach (__('crm.guide.sections') as $section)
            <x-filament::section :heading="$section['heading']">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $section['body'] }}</p>
            </x-filament::section>
        @endforeach

        <x-filament::section :heading="__('crm.guide.approvals_heading')" icon="heroicon-o-shield-check">
            <ul class="space-y-3">
                @foreach (__('crm.guide.approvals') as $row)
                    <li class="rounded-lg border border-gray-100 p-3 dark:border-gray-800">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['who'] }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $row['detail'] }}</p>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        <x-filament::section :heading="__('crm.guide.irreversible_heading')" icon="heroicon-o-exclamation-triangle">
            <ul class="list-disc space-y-2 ps-5 text-sm text-gray-600 dark:text-gray-400">
                @foreach (__('crm.guide.irreversible') as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>

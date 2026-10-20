<x-filament::page>
    <x-filament::section
        :heading="__('Company & Subscription')"
        :description="__('Current company subscription and plan details.')"
        collapsible
    >
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Company Name') }}</p>
                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $overview['company_name'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Subscription Status') }}</p>
                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $overview['subscription_status'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Plan Name') }}</p>
                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $overview['plan_name'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Plan Price') }}</p>
                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $overview['plan_price'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700 md:col-span-2">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Plan Limits') }}</p>
                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $overview['plan_limits'] }}</p>
            </div>

            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700 md:col-span-2">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Expiration Date') }}</p>
                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $overview['expiry_date'] }}</p>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section
        :heading="__('Remaining Usage Limits')"
        :description="__('Detailed usage analysis for your active plan quotas.')"
        class="mt-6"
        collapsible
        collapsed
    >
        @if (count($usageAnalysis) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No usage analysis available for this company.') }}</p>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach ($usageAnalysis as $usage)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $usage['label'] }}</p>
                            @php
                                $badgeClasses = match ($usage['status_level'] ?? null) {
                                    'danger' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20',
                                    'warning' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20 dark:bg-yellow-500/10 dark:text-yellow-300 dark:ring-yellow-500/20',
                                    default => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $badgeClasses }}">
                                {{ $usage['status'] }}
                            </span>
                        </div>

                        <div class="mt-3 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">{{ __('Used') }}</p>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $usage['used'] }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">{{ __('Limit') }}</p>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $usage['max'] ?? __('Unlimited') }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500 dark:text-gray-400">{{ __('Remaining') }}</p>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $usage['remaining'] ?? __('Unlimited') }}</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ __('Usage') }}: {{ $usage['usage_percent'] !== null ? $usage['usage_percent'] . '%' : __('N/A') }}
                            </p>
                            <div class="mt-1 h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div
                                    class="h-2 rounded-full bg-primary-600"
                                    style="width: {{ $usage['usage_percent'] !== null ? $usage['usage_percent'] : 0 }}%"
                                ></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament::page>

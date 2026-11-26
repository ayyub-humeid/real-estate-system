<div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5" x-data>
    <div class="mb-3 flex items-start gap-2">
        <x-filament::icon icon="heroicon-o-cursor-arrow-rays" class="mt-0.5 h-5 w-5 text-primary-500" />
        <div>
            <p class="font-semibold text-gray-950 dark:text-white">{{ __('Insert contract information') }}</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Place the cursor in the legal text, then choose the information to insert. The technical code stays hidden from employees.') }}</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('contract_domain.placeholders.help') }}</p>
        </div>
    </div>
    @foreach ($groups as $group => $variables)
        <p class="mb-2 mt-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $group }}</p>
        <div class="flex flex-wrap gap-2">
            @foreach ($variables as $token => $label)
                <button
                    type="button"
                    x-on:mousedown.prevent
                    x-on:click="$dispatch('contract-template-insert-variable', { token: @js($token) })"
                    class="inline-flex items-center rounded-lg border border-primary-200 bg-white px-3 py-2 text-sm font-medium text-primary-700 transition hover:border-primary-400 hover:bg-primary-50 dark:border-primary-500/30 dark:bg-gray-900 dark:text-primary-300 dark:hover:bg-primary-500/10"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endforeach
</div>

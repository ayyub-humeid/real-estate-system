<x-filament::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="col-span-1 space-y-6">
            <x-filament::section>
                <div class="flex flex-col items-center text-center">
                    <div class="relative h-40 w-40 mb-4 overflow-hidden rounded-full border-4 border-white shadow-lg dark:border-gray-700">
                        @if ($company->logo)
                            <img src="{{ Storage::url($company->logo) }}" 
                                 alt="{{ $company->name }}" 
                                 class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-gray-100 dark:bg-gray-800 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    <h2 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $company->name }}
                    </h2>
                    
                    <div class="mt-2">
                        @if($company->is_active)
                            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20">
                                {{ __('Active Company') }}
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">
                                {{ __('Inactive') }}
                            </span>
                        @endif
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <div class="flex justify-between py-2 border-b dark:border-gray-700">
                        <span>{{ __('Registered At:') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $company->created_at->format('d M, Y') }}</span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span>{{ __('Last Update:') }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $company->updated_at->diffForHumans() }}</span>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <div class="col-span-1 lg:col-span-2">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Company Details') }}
                </x-slot>
                
                <x-slot name="description">
                    {{ __('Contact information and location details.') }}
                </x-slot>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-primary-50 rounded-lg text-primary-600 dark:bg-gray-800 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Email Address') }}</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">
                                <a href="mailto:{{ $company->email }}" class="hover:underline">{{ $company->email }}</a>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-primary-50 rounded-lg text-primary-600 dark:bg-gray-800 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Phone Number') }}</p>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $company->phone ?? __('N/A') }}
                            </p>
                        </div>
                    </div>

                    <div class="col-span-1 md:col-span-2 flex items-start gap-3 pt-4 border-t dark:border-gray-700">
                        <div class="p-2 bg-primary-50 rounded-lg text-primary-600 dark:bg-gray-800 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Main Address') }}</p>
                            <p class="text-base text-gray-900 dark:text-white leading-relaxed">
                                {{ $company->address ?? __('No address provided.') }}
                            </p>
                        </div>
                    </div>

                </div>
            </x-filament::section>
            
            <div class="mt-6">
                 <x-filament::section collapsible collapsed>
                    <x-slot name="heading">{{ __('Internal Notes') }}</x-slot>
                    <p class="text-gray-500 italic">{{ __('No internal notes for this company.') }}</p>
                 </x-filament::section>
            </div>
        </div>
        
    </div>
</x-filament::page>
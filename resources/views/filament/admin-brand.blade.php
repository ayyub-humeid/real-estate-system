@php($isRtl = app()->getLocale() === 'ar')

<div class="flex items-center gap-x-3">
    <img src="{{ asset('madar logo.png') }}" alt="{{ __('auth_login.brand') }}" class="h-10 w-10 object-contain" />
    <span @class([
        'text-white font-extrabold leading-none',
        'text-xl tracking-normal' => $isRtl,
        'text-base uppercase tracking-[0.18em]' => ! $isRtl,
    ])>{{ __('auth_login.brand') }}</span>
</div>

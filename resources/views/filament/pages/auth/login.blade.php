@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
@endphp

<div class="madar-login" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
    <style>
        .madar-login { min-height: 100vh; background: #070b13; color: #f8fafc; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .madar-login[dir='rtl'] { font-family: Cairo, ui-sans-serif, system-ui, sans-serif; }
        .madar-login__shell { min-height: 100vh; display: flex; overflow: hidden; direction: ltr; }
        .madar-login[dir='rtl'] .madar-login__shell { flex-direction: row-reverse; }
        .madar-login__form-side, .madar-login__visual-side { width: 50%; min-height: 100vh; }
        .madar-login[dir='rtl'] .madar-login__form-side, .madar-login[dir='rtl'] .madar-login__visual-side { direction: rtl; }
        .madar-login__form-side { display: grid; place-items: center; padding: 1.5rem clamp(1.5rem, 7vw, 8rem); background: #0D0D0F; box-shadow: inset 1px 0 0 rgba(255,255,255,.05); }
        .madar-login__form { width: min(100%, 29rem); transform: translateY(-2rem); }
        .madar-login__utility { display: flex; justify-content: flex-end; margin-bottom: clamp(1.75rem, 4vh, 3.5rem); }
        .madar-login[dir='rtl'] .madar-login__utility { justify-content: flex-start; }
        .madar-login__language { border: 1px solid rgba(203,213,225,.26); background: rgba(15,23,42,.44); color: #dce7f8; border-radius: 999px; padding: .55rem .95rem; font-size: .8rem; font-weight: 700; cursor: pointer; transition: .2s ease; }
        .madar-login__language:hover { border-color: #60a5fa; color: #fff; background: rgba(37,99,235,.22); }
        .madar-login__mark { width: 3.35rem; height: 3.35rem; overflow: hidden; border: 1px solid rgba(229,183,100,.58); border-radius: 1rem; background: #050505; box-shadow: 0 14px 32px rgba(0,0,0,.32); margin-bottom: 1.25rem; }
        .madar-login__mark img { display: block; width: 100%; height: 100%; object-fit: cover; }
        .madar-login__eyebrow { color: #60a5fa; font-size: .72rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; margin: 0 0 .75rem; }
        .madar-login__heading { color: #f8fafc; font-size: clamp(2rem, 2.7vw, 2.55rem); font-weight: 800; letter-spacing: -.045em; line-height: 1.15; margin: 0; }
        .madar-login__copy { color: #9dadc3; line-height: 1.75; margin: .9rem 0 2.3rem; font-size: .95rem; }
        .madar-login .fi-fo-field-wrp-label, .madar-login .fi-checkbox-label { font-weight: 700; color: #eef4ff; }
        .madar-login .fi-input-wrp { border: 1px solid rgba(148,163,184,.25) !important; border-radius: .85rem !important; background: rgba(255,255,255,.045) !important; box-shadow: none !important; transition: border-color .2s ease, background .2s ease; }
        .madar-login .fi-input-wrp:focus-within { border-color: rgba(96,165,250,.85) !important; background: rgba(30,58,138,.16) !important; box-shadow: 0 0 0 3px rgba(37,99,235,.14) !important; }
        .madar-login .fi-input, .madar-login .fi-fo-select { border-radius: .8rem; color: #f8fafc !important; background: transparent !important; }
        .madar-login .fi-input::placeholder { color: #64748b !important; }
        .madar-login .fi-input-wrp-icon { color: #93a4bb !important; }
        .madar-login .fi-btn { border-radius: .85rem; min-height: 3.15rem; font-weight: 800; box-shadow: 0 12px 24px rgba(37,99,235,.25); }
        .madar-login__footer { color: #71829a; font-size: .78rem; margin-top: 2.5rem; }
        .madar-login__visual-side { position: relative; isolation: isolate; display: flex; padding: clamp(2rem, 5vw, 5.25rem); background: #08152a url('{{ asset('madar-login-background.png') }}') center / cover no-repeat; color: #fff; }
        .madar-login__visual-side::before { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(90deg, rgba(2,9,23,.62) 0%, rgba(5,17,39,.32) 54%, rgba(8,25,56,.1) 100%); }
        .madar-login__visual-side::after { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(0deg, rgba(2,7,17,.42) 0%, transparent 48%); }
        .madar-login__brand { width: 100%; max-width: 38rem; min-height: min(78vh, 52rem); display: flex; flex-direction: column; }
        .madar-login__brand-row { display: flex; align-items: center; gap: .75rem; }
        .madar-login__brand-icon { width: 3rem; height: 3rem; overflow: hidden; border: 1px solid rgba(229,183,100,.58); border-radius: .9rem; background: #050505; box-shadow: 0 8px 22px rgba(0,0,0,.3); }
        .madar-login__brand-icon img { display: block; width: 100%; height: 100%; object-fit: cover; }
        .madar-login__brand-name { font-size: 1.35rem; font-weight: 800; letter-spacing: -.03em; text-shadow: 0 2px 14px rgba(0,0,0,.7); }
        .madar-login__tagline { opacity: .9; font-size: .78rem; margin-top: .1rem; text-shadow: 0 2px 12px rgba(0,0,0,.76); }
        .madar-login__message { margin-top: clamp(3.5rem, 11vh, 7.5rem); }
        .madar-login__title { font-size: clamp(2.35rem, 4.5vw, 4.5rem); font-weight: 800; letter-spacing: -.055em; line-height: 1.08; margin: 0; max-width: 11ch; text-wrap: balance; text-shadow: 0 4px 26px rgba(0,0,0,.8); }
        .madar-login[dir='rtl'] .madar-login__title { letter-spacing: 0; }
        .madar-login__description { max-width: 31rem; font-size: 1rem; line-height: 1.85; color: rgba(255,255,255,.94); margin: 1.35rem 0 2.2rem; text-shadow: 0 2px 16px rgba(0,0,0,.85); }
        .madar-login__features { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; margin-top: auto; }
        .madar-login__feature { padding: 1rem; min-height: 7.5rem; border-top: 1px solid rgba(255,255,255,.28); border-radius: .8rem; background: linear-gradient(135deg, rgba(2,9,23,.43), rgba(15,23,42,.12)); backdrop-filter: blur(7px); }
        .madar-login__feature-icon { width: 2rem; height: 2rem; display: grid; place-items: center; margin-bottom: .75rem; border-radius: .6rem; color: #bfdbfe; background: rgba(37,99,235,.28); }
        .madar-login__feature-title { display: block; font-size: .83rem; font-weight: 800; line-height: 1.3; }
        .madar-login__feature-copy { display: block; opacity: .72; font-size: .7rem; line-height: 1.45; margin-top: .3rem; }
        .madar-login[dir='ltr'] .madar-login__form, .madar-login[dir='ltr'] .madar-login__brand { text-align: left; }
        .madar-login[dir='rtl'] .madar-login__form, .madar-login[dir='rtl'] .madar-login__brand { text-align: right; }
        .madar-login[dir='rtl'] .madar-login__brand-row { flex-direction: row-reverse; }
        .madar-login[dir='rtl'] .madar-login__feature { text-align: right; }
        @media (max-width: 960px) { .madar-login__form-side { padding-inline: clamp(1.5rem, 5vw, 3rem); } .madar-login__visual-side { padding: 2.5rem; } .madar-login__features { grid-template-columns: 1fr; } .madar-login__feature { min-height: 0; } .madar-login__feature-copy { display: none; } }
        @media (max-width: 720px) { .madar-login__shell, .madar-login[dir='rtl'] .madar-login__shell { flex-direction: column; } .madar-login__form-side, .madar-login__visual-side { width: 100%; min-height: auto; } .madar-login__form-side { order: 1; padding: 2rem 1.5rem 2.5rem; } .madar-login__visual-side { order: 0; min-height: 15rem; padding: 1.75rem 1.5rem; } .madar-login__form { transform: none; } .madar-login__utility { margin-bottom: 2.5rem; } .madar-login__brand { min-height: 0; } .madar-login__message { margin-top: 2.8rem; } .madar-login__title { font-size: 2rem; } .madar-login__description, .madar-login__features { display: none; } }
    </style>

    <div class="madar-login__shell">
        <section class="madar-login__form-side">
            <div class="madar-login__form">
                <div class="madar-login__utility">
                    <button type="button" wire:click="switchLocale('{{ $isRtl ? 'en' : 'ar' }}')" class="madar-login__language">
                        {{ $isRtl ? 'English' : 'العربية' }}
                    </button>
                </div>

                <div class="madar-login__mark">
                    <img src="{{ asset('madar logo.png') }}" alt="{{ __('auth_login.brand') }}">
                </div>
                <p class="madar-login__eyebrow">{{ __('auth_login.portal') }}</p>
                <h1 class="madar-login__heading">{{ __('auth_login.sign_in') }}</h1>
                <p class="madar-login__copy">{{ __('auth_login.form_intro') }}</p>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

                <x-filament-panels::form id="form" wire:submit="authenticate">
                    {{ $this->form }}
                    <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
                </x-filament-panels::form>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}

                <p class="madar-login__footer">{{ __('auth_login.secure_access') }}</p>
            </div>
        </section>

        <aside class="madar-login__visual-side" aria-label="{{ __('auth_login.brand_panel') }}">
            <div class="madar-login__brand">
                <div class="madar-login__brand-row">
                    <span class="madar-login__brand-icon"><img src="{{ asset('madar logo.png') }}" alt="{{ __('auth_login.brand') }}"></span>
                    <div><div class="madar-login__brand-name">{{ __('auth_login.brand') }}</div><div class="madar-login__tagline">{{ __('auth_login.tagline') }}</div></div>
                </div>
                <div class="madar-login__message">
                    <h2 class="madar-login__title">{{ __('auth_login.headline') }}</h2>
                    <p class="madar-login__description">{{ __('auth_login.description') }}</p>
                </div>
                <div class="madar-login__features">
                    @foreach (['projects', 'financials', 'crm'] as $feature)
                        <div class="madar-login__feature">
                            <span class="madar-login__feature-icon" aria-hidden="true">
                                @if ($feature === 'projects')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-4 w-4"><path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-5h6v5"/></svg>
                                @elseif ($feature === 'financials')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-4 w-4"><path d="M4 19V5m0 14h16M8 16v-4m4 4V8m4 8v-6"/></svg>
                                @else <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-4 w-4"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>@endif
                            </span>
                            <span class="madar-login__feature-title">{{ __('auth_login.features.' . $feature . '.title') }}</span>
                            <span class="madar-login__feature-copy">{{ __('auth_login.features.' . $feature . '.description') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>
</div>

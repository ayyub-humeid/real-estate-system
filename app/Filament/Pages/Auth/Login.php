<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';

    protected static string $layout = 'filament.layouts.login';

    public function hasLogo(): bool
    {
        return false;
    }

    public function getTitle(): string | Htmlable
    {
        return __('auth_login.sign_in');
    }

    public function switchLocale(string $locale): void
    {
        if (! in_array($locale, ['en', 'ar'], true)) {
            return;
        }

        session(['locale' => $locale]);
        app()->setLocale($locale);

        $this->redirect(Filament::getLoginUrl(), navigate: true);
    }
}

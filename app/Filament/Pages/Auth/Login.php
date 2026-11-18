<?php

namespace App\Filament\Pages\Auth;

use Filament\Http\Responses\Auth\Contracts\LoginResponse;
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

    public function authenticate(): ?LoginResponse
    {
        $chosenLocale = session('locale');

        $response = parent::authenticate();

        if (in_array($chosenLocale, ['en', 'ar'], true)) {
            $user = Filament::auth()->user();

            if ($user && $user->locale !== $chosenLocale) {
                $user->forceFill(['locale' => $chosenLocale])->save();
            }
        }

        return $response;
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

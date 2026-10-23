<?php

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LanguagePreferences extends Page
{
    protected static string $view = 'filament.pages.language-preferences';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Language Preferences');
    }

    public string $locale = 'ar';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        // Match the language already chosen for this browser/session instead
        // of showing a stale account preference as the selected option.
        $this->locale = app()->getLocale();
    }

    public function getTitle(): string
    {
        return __('settings.language');
    }

    public function save(): void
    {
        $this->validate([
            'locale' => ['required', 'in:en,ar'],
        ]);

        auth()->user()->update(['locale' => $this->locale]);
        session(['locale' => $this->locale]);
        app()->setLocale($this->locale);

        Notification::make()
            ->success()
            ->title(__('settings.language_saved'))
            ->send();

        $this->redirect(static::getUrl());
    }
}

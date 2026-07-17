<?php

namespace App\Filament\Resources\UnitResource\Pages;

use App\Filament\Resources\UnitResource;
use App\Services\PropertyDescriptionService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUnit extends EditRecord
{
    protected static string $resource = UnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ── AI Description Generator (header shortcut) ────────────────
            Action::make('generateAiDescription')
                ->label('✨ AI Description')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->hidden(fn () => ! empty($this->getRecord()->description))
                ->requiresConfirmation(false)
                ->modalHeading('AI-Generated Description')
                ->modalDescription('Review the description below. Click "Approve & Save" to use it, or "Cancel" to keep the existing description.')
                ->modalWidth('2xl')
                ->form(function (): array {
                    /** @var \App\Models\Unit $unit */
                    $unit = $this->getRecord();
                    $service = app(PropertyDescriptionService::class);

                    try {
                        $generated = $service->generate($unit);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('AI Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        $generated = '';
                    }

                    return [
                        Forms\Components\Placeholder::make('ai_preview_label')
                            ->label('')
                            ->content('The AI generated the following description based on the unit details and amenities:'),

                        Forms\Components\Textarea::make('ai_description')
                            ->label('Generated Description')
                            ->default($generated)
                            ->rows(7)
                            ->columnSpanFull()
                            ->readOnly(),
                    ];
                })
                ->modalSubmitActionLabel('✅ Approve & Save')
                ->action(function (array $data): void {
                    if (empty($data['ai_description'])) {
                        Notification::make()->title('Nothing to save')->warning()->send();
                        return;
                    }

                    /** @var \App\Models\Unit $unit */
                    $unit = $this->getRecord();
                    $service = app(PropertyDescriptionService::class);
                    $service->saveDescription($unit, $data['ai_description']);

                    // Refresh the form so the description textarea shows the new value
                    $this->fillForm();

                    Notification::make()
                        ->title('Description saved!')
                        ->body('AI-generated description has been applied.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\ListingResource\Pages;

use App\Enums\ListingStatus;
use App\Filament\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewListing extends ViewRecord
{
    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->workflowAction('publish', 'Publish', 'success'),
            $this->workflowAction('pause', 'Pause', 'warning'),
            $this->workflowAction('archive', 'Archive', 'danger'),
            $this->workflowAction('restore', 'Restore to draft', 'gray'),
            Actions\EditAction::make(),
        ];
    }

    private function workflowAction(string $method, string $label, string $color): Actions\Action
    {
        return Actions\Action::make($method)
            ->label($label)
            ->color($color)
            ->visible(fn () => match ($method) {
                'publish' => in_array($this->record->status, [ListingStatus::Draft, ListingStatus::Paused], true),
                'pause' => $this->record->status === ListingStatus::Published,
                'archive' => in_array($this->record->status, [ListingStatus::Published, ListingStatus::Paused], true),
                'restore' => $this->record->status === ListingStatus::Archived,
            })
            ->authorize($method)
            ->requiresConfirmation()
            ->action(function () use ($method, $label): void {
                try {
                    $this->record = app(ListingService::class)->{$method}(auth()->user(), $this->record);
                    Notification::make()->success()->title("Listing {$label}")->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title("Cannot {$label} Listing")->body(collect($exception->errors())->flatten()->first())->send();
                } catch (AuthorizationException) {
                    Notification::make()->danger()->title('Unauthorized')->send();
                }
            });
    }
}
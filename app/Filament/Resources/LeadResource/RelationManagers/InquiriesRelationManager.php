<?php

namespace App\Filament\Resources\LeadResource\RelationManagers;

use App\Enums\CrmInquirySource;
use App\Models\LeadInquiry;
use App\Services\LeadInquiryService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class InquiriesRelationManager extends RelationManager
{
    protected static string $relationship = 'inquiries';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('crm.fields.inquiries');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('source')
                ->label(__('crm.fields.source'))->options(CrmInquirySource::options())->required(),
            Forms\Components\Select::make('listing_id')
                ->label(__('crm.fields.listing'))
                ->options(fn() => \App\Models\Listing::withoutGlobalScopes()
                    ->where('company_id', $this->getOwnerRecord()->company_id)
                    ->orderBy('title')->pluck('title', 'id'))
                ->searchable(),
            Forms\Components\TextInput::make('contact_result')->label(__('crm.fields.contact_result')),
            Forms\Components\Textarea::make('message')->label(__('crm.fields.message'))->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('crm.empty.inquiries.heading'))
            ->emptyStateDescription(__('crm.empty.inquiries.description'))
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->columns([
                Tables\Columns\TextColumn::make('source')->label(__('crm.fields.source'))->badge()
                    ->formatStateUsing(fn($state) => $state instanceof CrmInquirySource ? $state->getLabel() : __('crm.sources.' . $state)),
                Tables\Columns\TextColumn::make('listing.title')->label(__('crm.fields.listing'))->placeholder(__('—')),
                Tables\Columns\TextColumn::make('contact_result')->label(__('crm.fields.contact_result')),
                Tables\Columns\TextColumn::make('created_at')->label(__('crm.fields.created_at'))->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('crm.actions.create_inquiry'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    })
                    ->using(fn(array $data): LeadInquiry => $this->run(
                        fn() => app(LeadInquiryService::class)->create(auth()->user(), $this->getOwnerRecord(), $data),
                        __('crm.fields.inquiries'),
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('crm.actions.details')),
                Tables\Actions\EditAction::make()
                    ->using(fn(LeadInquiry $record, array $data): LeadInquiry => $this->run(
                        fn() => app(LeadInquiryService::class)->update(auth()->user(), $record, $data),
                        __('crm.fields.inquiries'),
                    )),
            ]);
    }

    private function run(callable $operation, string $title): mixed
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title($title)
                ->body(collect($exception->errors())->flatten()->first())->send();
            throw $exception;
        } catch (AuthorizationException $exception) {
            Notification::make()->danger()->title(__('crm.errors.unauthorized'))->send();
            throw $exception;
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Explicit inheritance remains required for Super Admin child creation.
        $data['company_id'] = $this->getOwnerRecord()->company_id;

        return $data;
    }
}

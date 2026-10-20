<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Enums\ContractVersionStatus;
use App\Models\{ContractParty, ContractVersion, Document, DocumentVersion};
use App\Services\ContractWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    public function getTitle(): string
    {
        return $this->record->contract_number;
    }

    public function getContentTabLabel(): ?string
    {
        return __('Overview');
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return ContractResource::infolist($infolist);
    }

    protected function getHeaderActions(): array
    {
        $latest = fn (): ?ContractVersion => $this->record->versions()->latest('version_number')->first();
        $run = function (callable $callback, string $successTitle): void {
            try {
                $callback();
                Notification::make()->success()->title($successTitle)->send();
            } catch (ValidationException $exception) {
                Notification::make()->danger()
                    ->title(__('Cannot update contract'))
                    ->body(collect($exception->errors())->flatten()->first())
                    ->persistent()
                    ->send();
            } catch (AuthorizationException) {
                Notification::make()->danger()
                    ->title(__('Unauthorized'))
                    ->body(__('You do not have permission to perform this contract action.'))
                    ->persistent()
                    ->send();
            } catch (\Throwable $e) {
                Notification::make()->danger()
                    ->title(__('An unexpected error occurred'))
                    ->body($e->getMessage())
                    ->persistent()
                    ->send();
            }
        };

        return [
            Actions\Action::make('submit')
                ->label(__('Submit for review'))
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Draft && auth()->user()->can('submit_contract_version'))
                ->requiresConfirmation()
                ->modalHeading(__('Submit contract version for review'))
                ->modalDescription(__('Are you sure you want to submit this draft contract version for approval?'))
                ->modalSubmitActionLabel(__('Submit'))
                ->action(fn (): mixed => $run(fn () => app(ContractWorkflowService::class)->submit(auth()->user(), $latest()), __('Submitted for review'))),
            Actions\Action::make('approve')
                ->label(__('Approve version'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Submitted && auth()->user()->can('approve_contract_version'))
                ->modalHeading(__('Approve contract version'))
                ->modalSubmitActionLabel(__('Approve'))
                ->form([Forms\Components\Textarea::make('notes')->label(__('Approval notes'))])
                ->action(fn (array $data): mixed => $run(fn () => app(ContractWorkflowService::class)->approve(auth()->user(), $latest(), $data['notes'] ?? null), __('Version approved'))),
            Actions\Action::make('requestRevision')
                ->label(__('Request revision'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Submitted && auth()->user()->can('request_contract_revision'))
                ->modalHeading(__('Request contract revision'))
                ->modalSubmitActionLabel(__('Request revision'))
                ->form([Forms\Components\Textarea::make('notes')->label(__('Revision notes'))->required()])
                ->action(fn (array $data): mixed => $run(fn () => app(ContractWorkflowService::class)->requestRevision(auth()->user(), $latest(), $data['notes']), __('Revision requested'))),
            Actions\Action::make('createRevision')
                ->label(__('Create revision'))
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => in_array($latest()?->status, [ContractVersionStatus::Submitted, ContractVersionStatus::RevisionRequired, ContractVersionStatus::Approved], true) && auth()->user()->can('create_contract_revision'))
                ->requiresConfirmation()
                ->modalHeading(__('Create new draft revision'))
                ->modalDescription(__('This will clone the current contract state into a new draft version for editing.'))
                ->modalSubmitActionLabel(__('Create revision'))
                ->action(fn (): mixed => $run(fn () => app(ContractWorkflowService::class)->createRevision(auth()->user(), $latest()), __('Draft revision created'))),
            Actions\Action::make('attachDocument')
                ->label(__('Attach legal document'))
                ->icon('heroicon-o-paper-clip')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Approved && auth()->user()->can('attach_contract_document'))
                ->modalHeading(__('Attach contract legal document'))
                ->modalSubmitActionLabel(__('Attach document'))
                ->form([
                    Forms\Components\TextInput::make('title')->label(__('Document title'))->required(),
                    Forms\Components\Textarea::make('description')->label(__('Description')),
                    Forms\Components\FileUpload::make('file_path')
                        ->label(__('Contract file (PDF/Document)'))
                        ->required()
                        ->disk(config('filesystems.default'))
                        ->directory('documents/contracts')
                        ->storeFileNamesIn('file_name'),
                ])
                ->action(fn (array $data): mixed => $run(fn () => app(ContractWorkflowService::class)->attachDocument(auth()->user(), $latest(), $data), __('Legal document attached'))),
            Actions\Action::make('recordSignature')
                ->label(__('Record signature'))
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Approved && auth()->user()->can('record_contract_signature'))
                ->modalHeading(__('Record party signature'))
                ->modalSubmitActionLabel(__('Record signature'))
                ->form(function () use ($latest): array {
                    $version = $latest();
                    $roles = [
                        'buyer' => __('Buyer'),
                        'seller' => __('Seller'),
                        'landlord' => __('Landlord'),
                        'tenant' => __('Tenant'),
                        'guarantor' => __('Guarantor'),
                    ];

                    return [
                        Forms\Components\Select::make('contract_party_id')
                            ->label(__('Contract party'))
                            ->options($version?->parties()->with('party')->get()->mapWithKeys(fn ($party): array => [
                                $party->id => ($roles[$party->role] ?? ($party->role ?: __('Party'))).' — '.($party->snapshot['name'] ?? $party->party?->name ?? '—'),
                            ])->all() ?? [])
                            ->required(),
                        Forms\Components\Select::make('document_version_id')
                            ->label(__('Attached document'))
                            ->options($version?->documents()->with('latestVersion')->get()->mapWithKeys(fn (Document $document): array => [
                                $document->latestVersion?->id => $document->title.' — V'.($document->latestVersion?->version_number ?? 1),
                            ])->filter()->all() ?? [])
                            ->required(),
                        Forms\Components\TextInput::make('signatory_name')->label(__('Signatory name'))->required(),
                        Forms\Components\Select::make('signing_method')
                            ->label(__('Signing method'))
                            ->options(['manual' => __('Manual signature'), 'electronic' => __('Electronic evidence')])
                            ->required(),
                        Forms\Components\FileUpload::make('signature_image_path')
                            ->label(__('Signature image'))
                            ->image()
                            ->disk(config('filesystems.default'))
                            ->directory('signatures/contracts')
                            ->maxSize(5120),
                        Forms\Components\Textarea::make('evidence.notes')->label(__('Signature evidence notes')),
                    ];
                })
                ->action(fn (array $data): mixed => $run(fn () => app(ContractWorkflowService::class)->recordSignature(
                    auth()->user(),
                    $latest(),
                    ContractParty::withoutGlobalScopes()->findOrFail($data['contract_party_id']),
                    DocumentVersion::withoutGlobalScopes()->findOrFail($data['document_version_id']),
                    $data['signatory_name'],
                    $data['signing_method'],
                    $data['evidence'] ?? [],
                    $data['signature_image_path'] ?? null,
                ), __('Signature recorded'))),
            Actions\Action::make('activate')
                ->label(__('Activate contract'))
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $latest()?->status === ContractVersionStatus::Approved && auth()->user()->can('activate_contract'))
                ->requiresConfirmation()
                ->modalHeading(__('Activate contract'))
                ->modalDescription(__('Are you sure you want to activate this contract? This will activate the contract and conclude reservation obligations.'))
                ->modalSubmitActionLabel(__('Activate contract'))
                ->action(fn (): mixed => $run(fn () => app(ContractWorkflowService::class)->activate(auth()->user(), $this->record, $latest()), __('Contract activated'))),
        ];
    }
}

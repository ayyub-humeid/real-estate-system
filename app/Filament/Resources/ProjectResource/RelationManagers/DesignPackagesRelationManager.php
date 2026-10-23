<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\{DesignPackageAssignment, DesignPackageReview, DesignPackageRevision, DesignPackageScopeItem, DesignPackageSubmission, DesignReviewFinding, Document, DocumentVersion, Party, ProjectDesignPackage, User};
use App\Services\DesignEngineeringService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class DesignPackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'designPackages';
    protected static ?string $title = 'Design Packages';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Design Packages');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No design packages yet'))
            ->emptyStateDescription(__('Create a package, assign an engineering office, define its scope, then begin the formal review workflow.'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('Name'))->searchable()->weight('medium')->description(fn(ProjectDesignPackage $record) => $record->discipline),
                Tables\Columns\TextColumn::make('code')->label(__('Code'))->badge()->color('gray')->placeholder(__('No code')),
                Tables\Columns\TextColumn::make('status')->label(__('Status'))->badge()->colors(['gray' => 'planned', 'info' => ['assigned', 'submitted', 'resubmitted'], 'warning' => ['in_progress', 'under_review', 'revision_required'], 'success' => ['approved', 'closed']])
                    ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                Tables\Columns\TextColumn::make('activeAssignment.party.name')->label(__('Engineering office'))->placeholder(__('Not assigned')),
                Tables\Columns\TextColumn::make('submissions_count')->counts('submissions')->label(__('Rounds')),
                Tables\Columns\TextColumn::make('target_submission_date')->date()->label(__('Target'))->placeholder(__('—')),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createPackage')->label(__('Create Design Package'))->icon('heroicon-o-plus')->tooltip(__('Create a new design package'))
                    ->visible(fn() => auth()->user()->can('update', $this->getOwnerRecord()) && auth()->user()->can('create', ProjectDesignPackage::class))
                    ->form($this->packageForm())
                    ->action(fn(array $data) => $this->run(fn() => app(DesignEngineeringService::class)->createPackage(auth()->user(), $this->getOwnerRecord(), $data), __('Design package created'))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label(__('View'))->icon('heroicon-o-eye')
                    ->tooltip(__('View design package details and scope items'))
                    ->modalWidth('5xl')->infolist($this->packageInfolist()),
                Tables\Actions\Action::make('editPackage')->label(__('Edit'))->icon('heroicon-o-pencil-square')->tooltip(__('Edit package details'))
                    ->visible(fn(ProjectDesignPackage $record) => auth()->user()->can('update', $record))
                    ->fillForm(fn(ProjectDesignPackage $record) => $record->only(['name', 'code', 'discipline', 'description', 'target_submission_date']))->form($this->packageForm())
                    ->action(fn(ProjectDesignPackage $record, array $data) => $this->run(fn() => app(DesignEngineeringService::class)->updatePackage(auth()->user(), $record, $data), __('Design package updated'))),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('assignOffice')->label(__('Assign / replace office'))->icon('heroicon-o-building-office-2')
                        ->visible(fn(ProjectDesignPackage $record) => auth()->user()->can('assignOffice', $record))
                        ->form([Forms\Components\Select::make('party_id')->label(__('Engineering office'))->options(fn() => Party::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->where('is_active', true)->pluck('name', 'id'))->searchable()->required(), Forms\Components\DateTimePicker::make('assigned_at')->label(__('Assigned At'))->default(now()), Forms\Components\Textarea::make('notes')->label(__('Notes'))])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->assignOffice(auth()->user(), $record, Party::withoutGlobalScopes()->findOrFail($data['party_id']), $data), __('Engineering office assigned'));
                        }),
                    Tables\Actions\Action::make('addScope')->label(__('Add scope item'))->icon('heroicon-o-list-bullet')
                        ->visible(fn(ProjectDesignPackage $record) => $record->activeAssignment && auth()->user()->can('create', DesignPackageScopeItem::class))
                        ->form($this->scopeForm())
                        ->action(fn(ProjectDesignPackage $record, array $data) => $this->run(fn() => app(DesignEngineeringService::class)->addScopeItem(auth()->user(), $record->activeAssignment, $data), __('Scope item added'))),
                    Tables\Actions\Action::make('updateScope')->label(__('Update scope status'))->icon('heroicon-o-check-circle')
                        ->visible(fn(ProjectDesignPackage $record) => $record->activeAssignment && auth()->user()->can('update_design_package_scope_item'))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('scope_id')->label(__('Scope item'))->options($this->scopeOptions($record))->required(), Forms\Components\Select::make('status')->label(__('New status'))->options(['in_progress' => __('Start work'), 'ready' => __('Mark ready'), 'cancelled' => __('Cancel')])->required()])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->transitionScopeItem(auth()->user(), $this->scope($record, $data['scope_id']), $data['status']), __('Scope status updated'));
                        }),
                    Tables\Actions\Action::make('addDocumentVersion')->label(__('Add document version'))->icon('heroicon-o-document-plus')
                        ->visible(fn(ProjectDesignPackage $record) => auth()->user()->can('create', DocumentVersion::class))
                        ->form(fn(ProjectDesignPackage $record) => [
                            Forms\Components\Select::make('document_id')->label(__('Existing document'))->options($this->documentOptions($record))->searchable()->live()->helperText(__('Choose this only when uploading a new version of an existing document.')),
                            Forms\Components\TextInput::make('title')->label(__('New document title'))->required(fn(Forms\Get $get) => blank($get('document_id')))->visible(fn(Forms\Get $get) => blank($get('document_id'))),
                            Forms\Components\Textarea::make('document_description')->label(__('Document description'))->visible(fn(Forms\Get $get) => blank($get('document_id'))),
                            Forms\Components\FileUpload::make('file_path')->label(__('Version file'))->directory('design-documents')->preserveFilenames()->required()->downloadable()->openable()->afterStateUpdated(function ($state, Forms\Set $set): void {
                                if (is_string($state))
                                    $set('file_name', basename($state));
                            })->columnSpanFull(),
                            Forms\Components\Hidden::make('file_name'),
                            Forms\Components\TextInput::make('checksum')->label(__('Checksum')),
                            Forms\Components\Textarea::make('notes')->label(__('Notes')),
                        ])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $document = !empty($data['document_id']) ? Document::withoutGlobalScopes()->findOrFail($data['document_id']) : null;
                            unset($data['document_id']);
                            $this->run(fn() => app(DesignEngineeringService::class)->addDocumentVersion(auth()->user(), $record, $data, $document), __('Document version added'));
                        }),
                ])->label(__('Prepare'))->icon('heroicon-o-wrench-screwdriver')->tooltip(__('Prepare the package: assign office, scope, and documents')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('submit')->label(__('Create formal submission'))->icon('heroicon-o-paper-airplane')
                        ->visible(fn(ProjectDesignPackage $record) => auth()->user()->can('submit', $record))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('document_versions')->label(__('Document versions'))->multiple()->options($this->documentVersionOptions($record))->required(), Forms\Components\Textarea::make('notes')->label(__('Notes'))])
                        ->action(fn(ProjectDesignPackage $record, array $data) => $this->run(fn() => app(DesignEngineeringService::class)->submit(auth()->user(), $record, $data['document_versions'], $data['notes'] ?? null), __('Formal submission created'))),
                    Tables\Actions\Action::make('startReview')->label(__('Start review'))->icon('heroicon-o-magnifying-glass')
                        ->visible(fn(ProjectDesignPackage $record) => auth()->user()->can('review_design_submission'))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('submission_id')->label(__('Formal submission'))->options($this->submissionOptions($record, 'submitted'))->required(), Forms\Components\Select::make('reviewer_id')->label(__('Reviewer'))->options($this->companyUsers())->searchable()->required()])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->startReview(auth()->user(), $this->submission($record, $data['submission_id']), User::withoutGlobalScopes()->findOrFail($data['reviewer_id'])), __('Review started'));
                        }),
                    Tables\Actions\Action::make('addFinding')->label(__('Add finding'))->icon('heroicon-o-exclamation-triangle')->color('warning')
                        ->visible(fn() => auth()->user()->can('create_design_finding'))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('review_id')->label(__('Review'))->options($this->reviewOptions($record, 'in_review'))->required(), Forms\Components\Select::make('severity')->label(__('Severity'))->options(array_combine(DesignReviewFinding::SEVERITIES, DesignReviewFinding::SEVERITIES))->required(), Forms\Components\TextInput::make('title')->label(__('Title'))->required(), Forms\Components\Textarea::make('description')->label(__('Description'))->required(), Forms\Components\Select::make('design_package_scope_item_id')->label(__('Scope item'))->options($this->scopeOptions($record)), Forms\Components\Select::make('document_version_id')->label(__('Document version'))->options($this->documentVersionOptions($record))])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $review = DesignPackageReview::withoutGlobalScopes()->findOrFail($data['review_id']);
                            unset($data['review_id']);
                            $this->run(fn() => app(DesignEngineeringService::class)->createFinding(auth()->user(), $review, $data), __('Finding recorded'));
                        }),
                    Tables\Actions\Action::make('completeReview')->label(__('Complete review'))->icon('heroicon-o-check-badge')
                        ->visible(fn() => auth()->user()->can('review_design_submission'))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('review_id')->label(__('Review'))->options($this->reviewOptions($record, 'in_review'))->required(), Forms\Components\Textarea::make('summary')->label(__('Summary'))])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->completeReview(auth()->user(), DesignPackageReview::withoutGlobalScopes()->findOrFail($data['review_id']), $data['summary'] ?? null), __('Review completed'));
                        }),
                    Tables\Actions\Action::make('waiveFinding')->label(__('Waive finding'))->icon('heroicon-o-shield-check')->color('warning')
                        ->visible(fn() => auth()->user()->can('waive_design_finding'))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('finding_id')->label(__('Finding'))->options($this->findingOptions($record))->required(), Forms\Components\Textarea::make('notes')->label(__('Notes'))->required()])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->waiveFinding(auth()->user(), $this->finding($record, $data['finding_id']), $data['notes']), __('Finding waived'));
                        }),
                ])->label(__('Review'))->icon('heroicon-o-clipboard-document-check')->tooltip(__('Review submissions and manage findings')),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('requestRevision')->label(__('Request revision'))->icon('heroicon-o-arrow-path')
                        ->visible(fn() => auth()->user()->can('request_design_revision'))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('submission_id')->label(__('Reviewed submission'))->options($this->submissionOptions($record, 'reviewed'))->required(), Forms\Components\Select::make('finding_ids')->label(__('Findings to resolve'))->multiple()->options($this->findingOptions($record))->required(), Forms\Components\Textarea::make('description')->label(__('Description'))])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->requestRevision(auth()->user(), $this->submission($record, $data['submission_id']), $data['finding_ids'], $data['description'] ?? null), __('Revision requested'));
                        }),
                    Tables\Actions\Action::make('updateRevision')->label(__('Start / mark revision ready'))->icon('heroicon-o-arrow-uturn-right')
                        ->visible(fn() => auth()->user()->can('submit_design_revision'))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('revision_id')->label(__('Revision'))->options($this->revisionOptions($record))->required(), Forms\Components\Select::make('status')->label(__('New status'))->options(['in_progress' => __('Start revision'), 'ready' => __('Mark ready')])->required()])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->transitionRevision(auth()->user(), $this->revision($record, $data['revision_id']), $data['status']), __('Revision status updated'));
                        }),
                    Tables\Actions\Action::make('resubmit')->label(__('Resubmit ready revision'))->icon('heroicon-o-paper-airplane')
                        ->visible(fn(ProjectDesignPackage $record) => auth()->user()->can('submit', $record))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('revision_id')->label(__('Ready revision'))->options($this->revisionOptions($record, 'ready'))->required(), Forms\Components\Select::make('document_versions')->label(__('Document versions'))->multiple()->options($this->documentVersionOptions($record))->required(), Forms\Components\Textarea::make('notes')->label(__('Notes'))])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->submit(auth()->user(), $record, $data['document_versions'], $data['notes'] ?? null, $this->revision($record, $data['revision_id'])), __('Resubmission created'));
                        }),
                    Tables\Actions\Action::make('approve')->label(__('Approve exact submission'))->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                        ->visible(fn(ProjectDesignPackage $record) => auth()->user()->can('approve', $record))
                        ->form(fn(ProjectDesignPackage $record) => [Forms\Components\Select::make('submission_id')->label(__('Reviewed submission'))->options($this->submissionOptions($record, 'reviewed'))->required(), Forms\Components\Textarea::make('notes')->label(__('Notes'))])
                        ->action(function (ProjectDesignPackage $record, array $data) {
                            $this->run(fn() => app(DesignEngineeringService::class)->approve(auth()->user(), $record, $this->submission($record, $data['submission_id']), $data['notes'] ?? null), __('Design package approved'));
                        }),
                    Tables\Actions\Action::make('close')->label(__('Close package'))->icon('heroicon-o-lock-closed')->color('success')->requiresConfirmation()
                        ->visible(fn(ProjectDesignPackage $record) => auth()->user()->can('close', $record))
                        ->action(fn(ProjectDesignPackage $record) => $this->run(fn() => app(DesignEngineeringService::class)->close(auth()->user(), $record), __('Design package closed'))),
                ])->label(__('Formal workflow'))->icon('heroicon-o-arrow-right-circle')->tooltip(__('Manage revisions, approval, and closure')),
            ]);
    }

    private function packageForm(): array
    {
        return [
            Forms\Components\TextInput::make('name')->label(__('Name'))->required(),
            Forms\Components\TextInput::make('code')->label(__('Code')),
            Forms\Components\Select::make('discipline')->label(__('Discipline'))->options(ProjectDesignPackage::DISCIPLINES),
            Forms\Components\DatePicker::make('target_submission_date')->label(__('Target Submission Date')),
            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull()
        ];
    }
    private function scopeForm(): array
    {
        return [
            Forms\Components\TextInput::make('title')->label(__('Title'))->required(),
            Forms\Components\TextInput::make('code')->label(__('Code')),
            Forms\Components\DatePicker::make('target_date')->label(__('Target Date')),
            Forms\Components\TextInput::make('sort_order')->label(__('Sort Order'))->numeric()->default(0),
            Forms\Components\Textarea::make('description')->label(__('Description'))->columnSpanFull()
        ];
    }
    private function packageInfolist(): array
    {
        return [
            \Filament\Infolists\Components\Section::make(__('General Information'))
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('name')->label(__('Name'))->weight('bold'),
                    \Filament\Infolists\Components\TextEntry::make('code')->label(__('Code'))->placeholder(__('—')),
                    \Filament\Infolists\Components\TextEntry::make('discipline')->label(__('Discipline'))->badge(),
                    \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()
                        ->colors(['gray' => 'planned', 'info' => ['assigned', 'submitted', 'resubmitted'], 'warning' => ['in_progress', 'under_review', 'revision_required'], 'success' => ['approved', 'closed']])
                        ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                    \Filament\Infolists\Components\TextEntry::make('activeAssignment.party.name')->label(__('Engineering office'))->placeholder(__('Not assigned')),
                    \Filament\Infolists\Components\TextEntry::make('target_submission_date')->label(__('Target Submission Date'))->date()->placeholder(__('—')),
                    \Filament\Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull()->placeholder(__('No description')),
                ])->columns(3),

            \Filament\Infolists\Components\Tabs::make('Details')
                ->tabs([
                    \Filament\Infolists\Components\Tabs\Tab::make(__('Scope Items'))
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('scope_items')
                                ->label('')
                                ->state(fn(ProjectDesignPackage $record): array => $record->scopeItems()->orderBy('sort_order')->get()->map(fn(DesignPackageScopeItem $item): array => [
                                    'title' => $item->title,
                                    'code' => $item->code,
                                    'status' => $item->status,
                                    'target_date' => $item->target_date?->format('M j, Y'),
                                ])->all())
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('title')->label(__('Title'))->weight('bold'),
                                    \Filament\Infolists\Components\TextEntry::make('code')->label(__('Code'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors(['gray' => 'planned', 'warning' => 'in_progress', 'success' => 'ready', 'danger' => 'cancelled'])
                                        ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                                    \Filament\Infolists\Components\TextEntry::make('target_date')->label(__('Target Date'))->placeholder(__('—')),
                                ])->columns(4)
                        ]),
                    \Filament\Infolists\Components\Tabs\Tab::make(__('Documents'))
                        ->icon('heroicon-o-document-duplicate')
                        ->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('documents')
                                ->label('')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('title')->label(__('Title'))->weight('bold')->columnSpan(2),
                                    \Filament\Infolists\Components\TextEntry::make('description')->label(__('Description'))->placeholder(__('No description'))->columnSpan(2),
                                    \Filament\Infolists\Components\RepeatableEntry::make('versions')
                                        ->label(__('Versions'))
                                        ->schema([
                                            \Filament\Infolists\Components\TextEntry::make('version_number')->label(__('Version #'))->weight('bold'),
                                            \Filament\Infolists\Components\TextEntry::make('file_name')->label(__('File'))
                                                ->color('primary')
                                                ->url(fn($record) => \Illuminate\Support\Facades\Storage::url($record->file_path))
                                                ->openUrlInNewTab(),
                                            \Filament\Infolists\Components\TextEntry::make('notes')->label(__('Notes'))->placeholder(__('—')),
                                            \Filament\Infolists\Components\TextEntry::make('created_at')->date()->label(__('Uploaded')),
                                        ])->columns(4)->columnSpanFull()
                                ])->columns(4)
                        ]),
                    \Filament\Infolists\Components\Tabs\Tab::make(__('Submissions & Reviews'))
                        ->icon('heroicon-o-paper-airplane')
                        ->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('submissions')
                                ->label('')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('submission_number')->label(__('Submission #'))->weight('bold'),
                                    \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors(['info' => ['submitted', 'reviewed'], 'warning' => 'under_review', 'gray' => 'superseded', 'success' => 'approved'])
                                        ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                                    \Filament\Infolists\Components\TextEntry::make('submitted_at')->date()->label(__('Submitted At'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\TextEntry::make('notes')->label(__('Notes'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\RepeatableEntry::make('reviews')
                                        ->label(__('Reviews'))
                                        ->schema([
                                            \Filament\Infolists\Components\TextEntry::make('reviewer.name')->label(__('Reviewer'))->weight('bold'),
                                            \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors(['gray' => 'pending', 'warning' => 'in_review', 'success' => 'completed', 'danger' => 'cancelled'])
                                                ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                                            \Filament\Infolists\Components\TextEntry::make('started_at')->date()->label(__('Started At'))->placeholder(__('—')),
                                            \Filament\Infolists\Components\TextEntry::make('completed_at')->date()->label(__('Completed At'))->placeholder(__('—')),
                                            \Filament\Infolists\Components\RepeatableEntry::make('findings')
                                                ->label(__('Findings'))
                                                ->schema([
                                                    \Filament\Infolists\Components\TextEntry::make('severity')->label(__('Severity'))->badge()->colors(['info' => 'info', 'success' => 'minor', 'warning' => 'major', 'danger' => 'critical']),
                                                    \Filament\Infolists\Components\TextEntry::make('title')->label(__('Title'))->weight('bold'),
                                                    \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors(['warning' => 'open', 'info' => 'addressed', 'success' => 'accepted', 'gray' => 'waived'])
                                                        ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                                                    \Filament\Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull(),
                                                ])->columns(3)->columnSpanFull()
                                        ])->columns(4)->columnSpanFull()
                                ])->columns(4)
                        ]),
                    \Filament\Infolists\Components\Tabs\Tab::make(__('Revisions'))
                        ->icon('heroicon-o-arrow-path')
                        ->schema([
                            \Filament\Infolists\Components\RepeatableEntry::make('revisions')
                                ->label('')
                                ->schema([
                                    \Filament\Infolists\Components\TextEntry::make('revision_number')->label(__('Revision #'))->weight('bold'),
                                    \Filament\Infolists\Components\TextEntry::make('status')->label(__('Status'))->badge()->colors(['gray' => 'draft', 'warning' => 'in_progress', 'success' => ['ready', 'submitted'], 'danger' => 'cancelled'])
                                        ->formatStateUsing(fn (?string $state): string => $state ? __('project.statuses.'.$state) : __('—')),
                                    \Filament\Infolists\Components\TextEntry::make('sourceSubmission.submission_number')->label(__('Source Sub. #'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\TextEntry::make('started_at')->date()->label(__('Started At'))->placeholder(__('—')),
                                    \Filament\Infolists\Components\TextEntry::make('description')->label(__('Description'))->columnSpanFull()->placeholder(__('—')),
                                ])->columns(4)
                        ]),
                ])->columnSpanFull()
        ];
    }
    private function scopeOptions(ProjectDesignPackage $package): array
    {
        return DesignPackageScopeItem::withoutGlobalScopes()->whereIn('design_package_assignment_id', $package->assignments()->select('id'))->orderBy('sort_order')->get()->mapWithKeys(fn($item) => [$item->id => "{$item->title} ({$item->status})"])->all();
    }
    private function documentVersionOptions(ProjectDesignPackage $package): array
    {
        return DocumentVersion::withoutGlobalScopes()->where('company_id', $package->company_id)->whereIn('document_id', $package->documents()->select('id'))->with('document')->get()->mapWithKeys(fn($version) => [$version->id => "{$version->document->title} — V{$version->version_number}"])->all();
    }
    private function documentOptions(ProjectDesignPackage $package): array
    {
        return $package->documents()->orderBy('title')->pluck('title', 'id')->all();
    }
    private function submissionOptions(ProjectDesignPackage $package, ?string $status = null): array
    {
        $query = $package->submissions();
        if ($status)
            $query->where('status', $status);
        return $query->orderByDesc('submission_number')->get()->mapWithKeys(fn($submission) => [$submission->id => "Submission #{$submission->submission_number} ({$submission->status})"])->all();
    }
    private function reviewOptions(ProjectDesignPackage $package, ?string $status = null): array
    {
        $query = DesignPackageReview::withoutGlobalScopes()->whereIn('design_package_submission_id', $package->submissions()->select('id'));
        if ($status)
            $query->where('status', $status);
        return $query->get()->mapWithKeys(fn($review) => [$review->id => "Submission #{$review->submission->submission_number} — {$review->status}"])->all();
    }
    private function findingOptions(ProjectDesignPackage $package): array
    {
        return DesignReviewFinding::withoutGlobalScopes()->whereIn('design_package_review_id', DesignPackageReview::withoutGlobalScopes()->whereIn('design_package_submission_id', $package->submissions()->select('id'))->select('id'))->where('status', 'open')->get()->mapWithKeys(fn($finding) => [$finding->id => "{$finding->severity}: {$finding->title}"])->all();
    }
    private function revisionOptions(ProjectDesignPackage $package, ?string $status = null): array
    {
        $query = $package->revisions();
        if ($status)
            $query->where('status', $status);
        return $query->get()->mapWithKeys(fn($revision) => [$revision->id => "Revision #{$revision->revision_number} ({$revision->status})"])->all();
    }
    private function companyUsers(): array
    {
        return User::withoutGlobalScopes()->where('company_id', $this->getOwnerRecord()->company_id)->pluck('name', 'id')->all();
    }
    private function scope(ProjectDesignPackage $package, $id): DesignPackageScopeItem
    {
        $item = DesignPackageScopeItem::withoutGlobalScopes()->findOrFail($id);
        if (!$package->assignments()->whereKey($item->design_package_assignment_id)->exists())
            throw ValidationException::withMessages(['scope_id' => 'The selected scope item is outside this package.']);
        return $item;
    }
    private function submission(ProjectDesignPackage $package, $id): DesignPackageSubmission
    {
        $submission = DesignPackageSubmission::withoutGlobalScopes()->findOrFail($id);
        if ((int) $submission->project_design_package_id !== (int) $package->id)
            throw ValidationException::withMessages(['submission_id' => 'The selected submission is outside this package.']);
        return $submission;
    }
    private function revision(ProjectDesignPackage $package, $id): DesignPackageRevision
    {
        $revision = DesignPackageRevision::withoutGlobalScopes()->findOrFail($id);
        if ((int) $revision->project_design_package_id !== (int) $package->id)
            throw ValidationException::withMessages(['revision_id' => 'The selected revision is outside this package.']);
        return $revision;
    }
    private function finding(ProjectDesignPackage $package, $id): DesignReviewFinding
    {
        $finding = DesignReviewFinding::withoutGlobalScopes()->findOrFail($id);
        if ((int) $finding->review->submission->project_design_package_id !== (int) $package->id)
            throw ValidationException::withMessages(['finding_id' => 'The selected finding is outside this package.']);
        return $finding;
    }
    private function run(\Closure $action, string $success): void
    {
        try {
            $action();
            Notification::make()->success()->title($success)->send();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(__('Cannot complete workflow action'))->body(collect($e->errors())->flatten()->first())->send();
        } catch (AuthorizationException) {
            Notification::make()->danger()->title(__('Unauthorized'))->body(__('You do not have permission for this workflow action.'))->send();
        }
    }
}

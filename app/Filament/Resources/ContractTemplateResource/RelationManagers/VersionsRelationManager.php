<?php

namespace App\Filament\Resources\ContractTemplateResource\RelationManagers;

use Filament\Forms;
use App\Models\ContractTemplateVersion;
use App\Services\ContractTemplateService;
use App\Services\ContractTemplateTokenService;
use App\Support\ContractPdfContentLimits;
use Filament\Infolists;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public static function getModelLabel(): string
    {
        return __('Contract Template Version');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Contract Template Versions');
    }

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('Template versions');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_number')->label(__('Version'))->badge(),
                Tables\Columns\TextColumn::make('createdBy.name')->label(__('Created by'))->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label(__('Created at'))->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Create template version'))
                    ->visible(fn (): bool => auth()->user()->can('update', $this->getOwnerRecord()))
                    ->form([
                        Forms\Components\Section::make(__('Legal document content'))
                            ->description(__('This text is printed in every contract using this template. Use the placeholders below for contract-specific facts.'))
                            ->schema([
                                Forms\Components\RichEditor::make('body')
                                    ->label(__('Legal template text'))
                                    ->required()
                                    ->maxLength(ContractPdfContentLimits::TEMPLATE_BODY_MAX_CHARACTERS)
                                    ->helperText(__('Use the buttons below to insert contract information where your cursor is. Contract terms are printed automatically below this text.'))
                                    ->hint(__('contract_domain.content.max_template_hint', ['limit' => number_format(ContractPdfContentLimits::TEMPLATE_BODY_MAX_CHARACTERS)]))
                                    ->toolbarButtons([
                                        'bold', 'italic', 'underline', 'strike', 'bulletList', 'orderedList', 'h2', 'h3', 'undo', 'redo',
                                    ])
                                    ->extraAlpineAttributes([
                                        'x-on:contract-template-insert-variable.window' => '
                                            const token = $event.detail?.token
                                            if (! token || ! $refs.trix?.editor) return
                                            $refs.trix.editor.insertString(token)
                                            state = $refs.trix.value
                                            $refs.trix.dispatchEvent(new Event("trix-change", { bubbles: true }))
                                        ',
                                    ])
                                    ->columnSpanFull(),
                                Forms\Components\View::make('filament.forms.contract-template-variable-inserter')
                                    ->columnSpanFull(),
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('previewSample')
                                        ->label(__('Preview sample data'))
                                        ->icon('heroicon-o-eye')
                                        ->color('gray')
                                        ->modalHeading(__('Template sample preview'))
                                        ->modalSubmitAction(false)
                                        ->modalCancelActionLabel(__('Close'))
                                        ->modalContent(fn (Forms\Get $get) => view('filament.contract-template-sample-preview', [
                                            'body' => self::samplePreview(ContractTemplateTokenService::normalise($get('body'))),
                                        ])),
                                ])->alignEnd()->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                        Forms\Components\Section::make(__('Default contract terms'))
                            ->description(__('These terms are copied into a new contract draft. They remain editable only while that contract is still a draft.'))
                            ->schema([
                        Forms\Components\Repeater::make('clauses')
                            ->label(__('Template clauses'))
                            ->schema([
                                Forms\Components\TextInput::make('title')->label(__('Term title'))->required(),
                                Forms\Components\Textarea::make('text')
                                    ->label(__('Term text'))
                                    ->maxLength(ContractPdfContentLimits::TERM_TEXT_MAX_CHARACTERS),
                                Forms\Components\Select::make('type')->label(__('Term type'))
                                    ->options(['clause' => __('Clause'), 'commercial' => __('Commercial term'), 'legal' => __('Legal term')])
                                    ->default('clause'),
                            ])
                            ->columns(2)
                            ->maxItems(ContractPdfContentLimits::TERMS_MAX_COUNT)
                            ->addActionLabel(__('Add clause'))
                            ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;
                        $data['contract_template_id'] = $this->getOwnerRecord()->id;
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->using(fn (array $data): ContractTemplateVersion => app(ContractTemplateService::class)
                        ->createVersion(auth()->user(), $this->getOwnerRecord(), $data)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('Details'))
                    ->modalWidth('5xl')
                    ->infolist([
                        Infolists\Components\Section::make(__('Template version'))
                            ->schema([
                                Infolists\Components\TextEntry::make('version_number')->label(__('Version'))->badge(),
                                Infolists\Components\TextEntry::make('createdBy.name')->label(__('Created by'))->placeholder('—'),
                                Infolists\Components\TextEntry::make('created_at')->label(__('Created at'))->dateTime(),
                            ])->columns(3),
                        Infolists\Components\Section::make(__('Legal document content'))
                            ->schema([
                                Infolists\Components\TextEntry::make('body')
                                    ->label(__('Legal template text'))
                                    ->formatStateUsing(fn (?string $state): string => ContractTemplateTokenService::normalise($state))
                                    ->placeholder('—')
                                    ->html()
                                    ->columnSpanFull(),
                            ]),
                        Infolists\Components\Section::make(__('Default contract terms'))
                            ->schema([
                                Infolists\Components\TextEntry::make('clauses_summary')
                                    ->label(__('Template clauses'))
                                    ->state(fn (ContractTemplateVersion $record): string => collect($record->clauses ?? [])
                                        ->map(fn (array $clause): string => '<strong>'.e($clause['title'] ?? __('Clause')).'</strong><br>'.nl2br(e($clause['text'] ?? '')))
                                        ->implode('<hr>'))
                                    ->html()
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['company_id'] = $this->getOwnerRecord()->company_id;
        $data['contract_template_id'] = $this->getOwnerRecord()->id;

        return $data;
    }

    private static function samplePreview(?string $body): string
    {
        return strtr($body ?: '<p>'.__('Start writing the legal contract text, then use the insert buttons to add contract information.').'</p>', [
            '{{contract_number}}' => 'CON-2026-000001',
            '{{company_name}}' => e(__('Madar Real Estate Development')),
            '{{total_amount}}' => '250,000.00',
            '{{currency}}' => 'USD',
            '{{unit_number}}' => 'A-204',
            '{{property_name}}' => e(__('Madar Residence')),
            '{{project_name}}' => e(__('Madar Residence Project')),
            '{{parties}}' => e(__('Madar Real Estate Development')).' ('.e(__('Seller')).')<br>'.e(__('Ahmed Khalil')).' ('.e(__('Buyer')).')',
        ]);
    }
}

<?php
// app/Filament/Resources/LeaseResource/RelationManagers/DocumentsRelationManager.php

namespace App\Filament\Resources\LeaseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';
    protected static ?string $title = 'Documents';
    protected static ?string $icon = 'heroicon-o-document-text';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            // 🔥 CRITICAL: Eager load uploadedBy + compute file URL
            ->modifyQueryUsing(fn($query) => $query
                ->with('uploadedBy:id,name')
                ->selectRaw("documents.*, CONCAT('/storage/', file_path) as file_url_computed")
            )
            ->columns([
                Tables\Columns\ImageColumn::make('file_path')
                    ->label('Preview')
                    ->disk('public')
                    ->size(50)
                    ->visible(fn($record) => $record->is_image ?? false),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('document_type')
                    ->badge()
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', ucwords($state, '_'))),

                Tables\Columns\TextColumn::make('file_name')
                    ->label('File')
                    ->icon('heroicon-m-paper-clip')
                    // ✅ Use computed URL - zero disk access!
                    ->url(fn($record) => $record->file_url_computed ?? '#')
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('file_size_human')
                    ->label('Size'),

                Tables\Columns\TextColumn::make('uploadedBy.name')
                    ->label('Uploaded By'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')
                    ->options([
                        'contract' => 'Contract',
                        'receipt' => 'Receipt',
                        'invoice' => 'Invoice',
                        'other' => 'Other',
                    ])
                    ->native(false),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // ✅ Auto-link to current lease
                        $data['documentable_id'] = $this->getOwnerRecord()->id;
                        $data['documentable_type'] = get_class($this->getOwnerRecord());
                        $data['uploaded_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn($record) => $record->download()),
                
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No documents uploaded')
            ->emptyStateDescription('Upload contract, receipts, or other documents')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('document_type')
                    ->options([
                        'contract' => 'Contract',
                        'receipt' => 'Receipt',
                        'invoice' => 'Invoice',
                        'id_document' => 'ID Document',
                        'proof_of_income' => 'Proof of Income',
                        'other' => 'Other',
                    ])
                    ->required()
                    ->native(false),

                Forms\Components\DatePicker::make('document_date')
                    ->default(now())
                    ->native(false),

                Forms\Components\FileUpload::make('file_path')
                    ->label('File')
                    ->required()
                    ->directory('lease-documents')
                    ->preserveFilenames()
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->previewable()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state instanceof \Illuminate\Http\UploadedFile) {
                            $set('file_name', $state->getClientOriginalName());
                            $set('file_type', $state->getMimeType());
                            $set('file_size', $state->getSize());
                            $set('extension', $state->getClientOriginalExtension());
                        }
                    })
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Hidden::make('file_name'),
                Forms\Components\Hidden::make('file_type'),
                Forms\Components\Hidden::make('file_size'),
                Forms\Components\Hidden::make('extension'),
            ]);
    }
}
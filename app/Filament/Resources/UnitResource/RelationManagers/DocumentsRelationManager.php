<?php
namespace App\Filament\Resources\UnitResource\RelationManagers;
use App\Services\UnitSetupService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';
    protected static ?string $title = 'Documents';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable(),
            Tables\Columns\TextColumn::make('latestVersion.version_number')->label('Latest')->badge()->formatStateUsing(fn($s) => $s ? "V{$s}" : '—'),
            Tables\Columns\TextColumn::make('latestVersion.file_name')->label('File')->placeholder('—'),
            Tables\Columns\TextColumn::make('versions_count')->counts('versions')->label('Versions'),
        ])->headerActions([
                    Tables\Actions\Action::make('addDocument')->label('Add document')->icon('heroicon-o-document-plus')->tooltip('Create a logical document and its first immutable version')
                        ->visible(fn() => auth()->user()->can('manageDocuments', $this->getOwnerRecord()))
                        ->form([Forms\Components\TextInput::make('title')->required(), Forms\Components\Textarea::make('description'), Forms\Components\FileUpload::make('file_path')->directory('documents/units')->required()->storeFileNamesIn('file_name'), Forms\Components\Textarea::make('notes')])
                        ->action(function (array $data): void {
                            try {
                                app(UnitSetupService::class)->addDocumentVersion(auth()->user(), $this->getOwnerRecord(), $data + ['file_name' => $data['file_name'] ?? basename($data['file_path'])]);
                                Notification::make()->success()->title('Document version added')->send(); } catch (ValidationException $e) {
                                Notification::make()->danger()->title('Cannot add document')->body(collect($e->errors())->flatten()->first())->send(); } }),
                ])->actions([Tables\Actions\ViewAction::make()]);
    }
}

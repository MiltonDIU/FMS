<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup; // Added
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PublicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'publications';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $form): Schema
    {
        return \App\Filament\Resources\Publications\Schemas\PublicationForm::configure($form);
    }

    public function table(Table $table): Table
    {
        return \App\Filament\Resources\Publications\Tables\PublicationsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
                AttachAction::make()->preloadRecordSelect(),
            ])
            ->actions([
                EditAction::make(),
                DetachAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
 
<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
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
                Tables\Actions\CreateAction::make(),
                Tables\Actions\AttachAction::make()->preloadRecordSelect(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
 
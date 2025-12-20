<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TeachingAreasRelationManager extends RelationManager
{
    protected static string $relationship = 'teachingAreas';

    protected static ?string $recordTitleAttribute = 'area';

    public function form(Schema $form): Schema
    {
        return $form->components([
            TextInput::make('area')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('area'),
            Tables\Columns\TextColumn::make('description')
                ->limit(50),
        ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EducationsRelationManager extends RelationManager
{
    protected static string $relationship = 'educations';

    protected static ?string $recordTitleAttribute = 'degree';

    public function form(Schema $form): Schema
    {
        return $form->components([
            Select::make('level')
                ->options(['bachelor' => 'Bachelor', 'master' => 'Master', 'phd' => 'PhD'])
                ->required(),
            TextInput::make('degree')
                ->required(),
            TextInput::make('institution'),
            TextInput::make('passing_year')
                ->numeric(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('level')->badge(),
                Tables\Columns\TextColumn::make('degree'),
                Tables\Columns\TextColumn::make('institution'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TrainingExperiencesRelationManager extends RelationManager
{
    protected static string $relationship = 'trainingExperiences';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('organization')
                    ->required()
                    ->maxLength(255),
                TextInput::make('category')
                    ->maxLength(255),
                Select::make('country_id')
                    ->label('Country')
                    ->options(\App\Models\Country::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->default(fn () => \App\Models\Country::where('slug', 'bangladesh')->first()?->id),
                TextInput::make('year')
                    ->numeric()
                    ->maxLength(4),
                DatePicker::make('completion_date'),
                TextInput::make('duration_days')
                    ->numeric()
                    ->label('Duration (Days)'),
                Toggle::make('is_online')
                    ->label('Online Training'),
                Textarea::make('description')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('organization'),
                Tables\Columns\TextColumn::make('year'),
                Tables\Columns\TextColumn::make('country.name')->label('Country'),
            ])
            ->filters([
                //
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

<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class JobExperiencesRelationManager extends RelationManager
{
    protected static string $relationship = 'jobExperiences';

    protected static ?string $recordTitleAttribute = 'position';

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                TextInput::make('position')->required(),
                TextInput::make('organization')->required(),
                \Filament\Forms\Components\Select::make('country_id')
                    ->label('Country')
                    ->options(\App\Models\Country::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->default(fn () => \App\Models\Country::where('slug', 'bangladesh')->first()?->id),
                DatePicker::make('start_date')->required(),
                DatePicker::make('end_date'),
                \Filament\Forms\Components\Toggle::make('is_current')->label('Currently Working'),
                TextInput::make('department'),
                \Filament\Forms\Components\Textarea::make('responsibilities')->label('Responsibilities')->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('position')
            ->columns([
                Tables\Columns\TextColumn::make('position'),
                Tables\Columns\TextColumn::make('organization'),
                Tables\Columns\TextColumn::make('start_date')
                    ->date(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date(),
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

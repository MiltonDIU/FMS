<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AwardsRelationManager extends RelationManager
{
    protected static string $relationship = 'awards';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                TextInput::make('title')
                    ->required()
                    ->columnSpan(2),
                TextInput::make('awarding_body')
                    ->columnSpan(2),
                Select::make('type')
                    ->options([
                        'award' => 'Award',
                        'scholarship' => 'Scholarship',
                        'recognition' => 'Recognition',
                        'appreciation' => 'Appreciation',
                        'other' => 'Other',
                    ])
                    ->default('award')
                    ->required(),
                DatePicker::make('date'),
                TextInput::make('year')
                    ->numeric()
                    ->rules(['integer', 'min:1900', 'max:' . (date('Y') + 10)]),
                FileUpload::make('attachment')
                    ->directory('awards-attachments')
                    ->downloadable(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Textarea::make('remarks')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('awarding_body')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'award' => 'success',
                        'scholarship' => 'info',
                        'recognition' => 'warning',
                        'appreciation' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('year')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'award' => 'Award',
                        'scholarship' => 'Scholarship',
                        'recognition' => 'Recognition',
                        'appreciation' => 'Appreciation',
                        'other' => 'Other',
                    ]),
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


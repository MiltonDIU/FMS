<?php

namespace App\Filament\Resources\DegreeTypes\Schemas;

use App\Models\DegreeLevel;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DegreeTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->columnSpanFull()->schema([
                    Select::make('degree_level_id')
                        ->label('Degree Level')
                        ->options(DegreeLevel::all()->pluck('name', 'id')) // Using options mainly, or relationship
                        ->relationship('level', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    TextInput::make('name')
                        ->label('Degree Name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                    Section::make('Settings')
                        ->schema([
                            Toggle::make('is_active')
                                ->label('Active Status')
                                ->default(true)
                                ->required(),
                        ])->columns(1),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\DegreeLevels\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DegreeLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->columnSpanFull()->schema([
                    TextInput::make('name')
                        ->label('Level Name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->required()
                        ->default(0),
                    Textarea::make('description')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                    Section::make('Settings')
                        ->schema([
                            Toggle::make('is_active')
                                ->label('Active Status')
                                ->default(true)
                                ->required(),
                            Toggle::make('is_report')
                                ->label('Show in Reports')
                                ->default(true)
                                ->helperText('If enabled, this degree level will appear in the dashboard statistics.')
                                ->required(),
                        ])->columns(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\MembershipTypes\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MembershipTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)->schema([
                    TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Used for ordering in dropdowns'),
                    Textarea::make('description')
                        ->columnSpanFull()
                        ->rows(2),
                    Section::make('Settings')
                        ->schema([
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->helperText('Only active types appear in dropdowns')
                                ->required(),
                        ])->columns(1),
                ]),
            ]);
    }
}

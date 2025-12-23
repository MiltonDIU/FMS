<?php

namespace App\Filament\Resources\Genders\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GenderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(10),

                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn ($record) => $record !== null),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(fn () => (\App\Models\Gender::max('sort_order') ?? 0) + 1),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}

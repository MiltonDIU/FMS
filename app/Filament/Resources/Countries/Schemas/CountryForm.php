<?php

namespace App\Filament\Resources\Countries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('nationality')
                    ->maxLength(255)
                    ->placeholder('Bangladeshi')
                    ->helperText('The demonym the HR system sends. Imports match a teacher\'s nationality against this.'),
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn ($record) => $record !== null),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(fn () => (\App\Models\Country::max('sort_order') ?? 0) + 1),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}

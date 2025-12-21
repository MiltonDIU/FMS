<?php

namespace App\Filament\Resources\BloodGroups\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BloodGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn ($record) => $record !== null),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
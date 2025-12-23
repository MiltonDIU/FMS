<?php

namespace App\Filament\Resources\Faculties\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FacultyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('erp_id')
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('short_name'),
                TextInput::make('code')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(fn () => (\App\Models\Faculty::max('sort_order') ?? 0) + 1),
            ]);
    }
}

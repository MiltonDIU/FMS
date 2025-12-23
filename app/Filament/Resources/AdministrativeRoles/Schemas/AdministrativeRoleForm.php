<?php

namespace App\Filament\Resources\AdministrativeRoles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdministrativeRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('short_name'),
                Select::make('scope')
                    ->options([
            'faculty' => 'Faculty',
            'department' => 'Department',
            'program' => 'Program',
            'university' => 'University',
        ])
                    ->default('department')
                    ->required(),
                TextInput::make('rank')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(fn () => (\App\Models\AdministrativeRole::max('sort_order') ?? 0) + 1),
            ]);
    }
}

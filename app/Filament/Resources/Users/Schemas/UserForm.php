<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->minLength(3),

                TextInput::make('email')
                    ->required()
                    ->email()
                    ->unique('users', 'email', ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                    ->nullable()
                    ->minLength(8)
                    ->maxLength(255),

                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),

                DateTimePicker::make('email_verified_at')
                    ->label('Email Verified At')
                    ->default(now())
                    ->formatStateUsing(fn ($state) => $state ?? now())
                    ->nullable(),

                Toggle::make('is_active')
                    ->label('Is Active')
                    ->required()
                    ->default(true),
            ]);
    }
}

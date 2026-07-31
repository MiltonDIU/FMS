<?php

namespace App\Filament\Resources\Users\Schemas;

use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
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
                    // Only offer roles the editor already holds. Otherwise
                    // Update:User is enough to grant yourself super_admin.
                    ->relationship(
                        'roles',
                        'name',
                        fn (Builder $query) => $query->whereIn(
                            'name',
                            auth()->user()?->assignableRoleNames() ?? [],
                        ),
                    )
                    ->multiple()
                    ->preload()
                    ->searchable()
                    // Narrowing the options only hides choices; the submitted ids
                    // still have to be checked or a crafted request slips past.
                    ->rule(fn (): Closure => function (string $attribute, $value, Closure $fail) {
                        if (! auth()->user()?->canAssignRoleIds(Arr::wrap($value))) {
                            $fail('You can only assign roles you hold yourself.');
                        }
                    }),

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

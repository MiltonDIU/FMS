<?php

namespace App\Filament\Resources\MembershipOrganizations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MembershipOrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->rows(3),
                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Active organizations are visible to all users. Inactive organizations are only visible to their creators.')
                    ->default(false),
            ]);
    }
}

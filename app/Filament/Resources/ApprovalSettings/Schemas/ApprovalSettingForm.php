<?php

namespace App\Filament\Resources\ApprovalSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ApprovalSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('section_key')
                    ->required(),
                TextInput::make('section_label')
                    ->required(),
                Toggle::make('requires_approval')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('fields'),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\SocialMediaPlatforms\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Filament\Forms\Set;

class SocialMediaPlatformForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                    TextInput::make('name')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('icon_class')
                        ->label('Icon Class')
                        ->placeholder('fab fa-facebook')
                        ->helperText('FontAwesome class (e.g. fab fa-facebook)'),
                    TextInput::make('base_url')
                        ->label('Base URL')
                        ->url()
                        ->placeholder('https://facebook.com/')
                        ->helperText('Base URL for profile link generation'),
                    Toggle::make('is_active')
                        ->default(true),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(fn () => (\App\Models\SocialMediaPlatform::max('sort_order') ?? 0) + 1),

            ]);


    }
}

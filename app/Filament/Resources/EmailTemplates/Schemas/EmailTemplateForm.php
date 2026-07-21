<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Template Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Profile Verification Request'),

                TextInput::make('key')
                    ->label('Template Key / Identifier')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->placeholder('e.g. profile_verification_request'),

                TextInput::make('subject')
                    ->label('Email Subject Line')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->placeholder('e.g. Action Required: Please Review & Confirm Your Profile Data'),

                Textarea::make('body')
                    ->label('Email Content Body (Supports dynamic placeholders)')
                    ->required()
                    ->rows(10)
                    ->columnSpanFull()
                    ->helperText('Available placeholders: {teacher_name}, {employee_id}, {department}, {designation}, {profile_score}, {verification_link}'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}

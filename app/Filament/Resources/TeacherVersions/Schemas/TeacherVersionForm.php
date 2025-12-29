<?php

namespace App\Filament\Resources\TeacherVersions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TeacherVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('teacher_id')
                    ->relationship('teacher', 'id')
                    ->required(),
                TextInput::make('version_number')
                    ->required()
                    ->numeric(),
                TextInput::make('data')
                    ->required(),
                Textarea::make('change_summary')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(['draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                    ->default('draft')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('submitted_by')
                    ->numeric(),
                DateTimePicker::make('submitted_at'),
                TextInput::make('reviewed_by')
                    ->numeric(),
                DateTimePicker::make('reviewed_at'),
                Textarea::make('review_remarks')
                    ->columnSpanFull(),
            ]);
    }
}

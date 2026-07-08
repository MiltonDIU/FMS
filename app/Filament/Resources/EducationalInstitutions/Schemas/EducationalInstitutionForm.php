<?php

namespace App\Filament\Resources\EducationalInstitutions\Schemas;

use App\Models\Teacher;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class EducationalInstitutionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique('educational_institutions', 'name', ignoreRecord: true)
                    ->maxLength(255)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Is Active')
                    ->default(true),
                Select::make('created_by')
                    ->label('Created By Teacher')
                    ->relationship('creator', 'first_name',
                        modifyQueryUsing: fn ($query, $search) =>
                            $query->when($search, fn ($q) =>
                                $q->where(fn ($q) =>
                                    $q->where('first_name', 'like', "%{$search}%")
                                      ->orWhere('last_name', 'like', "%{$search}%")
                                )
                            )
                    )
                    ->getOptionLabelFromRecordUsing(fn (Teacher $record) => $record->full_name)
                    ->searchable()
                    ->placeholder('System / Bulk Imported'),
                Select::make('approved_by')
                    ->label('Approved By User')
                    ->relationship('approver', 'name')
                    ->searchable()
                    ->placeholder('System / Auto Approved'),
            ]);
    }
}

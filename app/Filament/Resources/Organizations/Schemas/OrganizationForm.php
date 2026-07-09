<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Models\Teacher;
use App\Models\Country;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('country_id')
                    ->label('Country')
                    ->relationship('country', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn () => Country::where('slug', 'bangladesh')->first()?->id),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique('organizations', 'name', ignoreRecord: true, modifyRuleUsing: fn ($rule, $get) => $rule->where('country_id', $get('country_id'))),
                Select::make('parent_id')
                    ->label('Parent Organization')
                    ->placeholder('None (standalone organization)')
                    ->relationship(
                        'parent',
                        'name',
                        modifyQueryUsing: fn ($query, $record) =>
                            $query->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('If this is a sub-department, club, or institute, select its parent organization here.'),
                Toggle::make('is_active')
                    ->label('Is Active')
                    ->default(true),
                Section::make('Organization Types')
                    ->schema([
                        Toggle::make('is_educational_institution')->label('Educational Institution'),
                        Toggle::make('is_employer')->label('Employer / Company'),
                        Toggle::make('is_training_center')->label('Training Center'),
                        Toggle::make('is_professional_body')->label('Professional Body / Membership Org'),
                        Toggle::make('is_awarding_body')->label('Awarding Body'),
                        Toggle::make('is_certifying_authority')->label('Certifying Authority'),
                        Toggle::make('is_funding_agency')->label('Funding Agency'),
                    ])
                    ->columns(2),
                Section::make('Metadata')
                    ->schema([
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
                    ])
                    ->columns(2),
            ]);
    }
}

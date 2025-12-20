<?php

namespace App\Filament\Resources\Teachers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class TeacherForm
{
    public static function configure(Schema $schema, bool $isOwnProfile = false): Schema
    {
        return $schema
            ->components([
                Tabs::make('Teacher Profile')
                    ->tabs([
                        Tab::make('Basic Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(3)->schema([
                                    SpatieMediaLibraryFileUpload::make('photo')
                                        ->collection('avatar')
                                        ->avatar()
                                        ->circleCropper()
                                        ->columnSpanFull()
                                        ->alignCenter(),
                                    Select::make('user_id')
                                        ->relationship('user', 'name')
                                        ->label('User Account')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Select::make('department_id')
                                        ->relationship('department', 'name')
                                        ->label('Department')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Select::make('designation_id')
                                        ->relationship('designation', 'name')
                                        ->label('Designation')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('employee_id')->label('Employee ID')
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    DatePicker::make('joining_date')
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    TextInput::make('work_location'),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('first_name')->required(),
                                    TextInput::make('middle_name'),
                                    TextInput::make('last_name')->required(),
                                ]),
                                Textarea::make('bio')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contact & Address')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('phone')->tel()->required(),
                                    TextInput::make('personal_phone')->tel(),
                                    TextInput::make('secondary_email')->email(),
                                    TextInput::make('office_room'),
                                ]),
                                Grid::make(2)->schema([
                                    Textarea::make('present_address')->rows(2),
                                    Textarea::make('permanent_address')->rows(2),
                                ]),
                                Repeater::make('socialLinks')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['platform'] ?? null)
                                    ->schema([
                                        Select::make('platform')
                                            ->options([
                                                'Facebook' => 'Facebook',
                                                'Twitter' => 'Twitter',
                                                'LinkedIn' => 'LinkedIn',
                                                'GitHub' => 'GitHub',
                                                'Website' => 'Website',
                                            ])
                                            ->required(),
                                        TextInput::make('url')->url()->required(),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),
                            ]),

                        Tab::make('Personal Details')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Grid::make(3)->schema([
                                    DatePicker::make('date_of_birth'),
                                    Select::make('gender')
                                        ->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other']),
                                    TextInput::make('blood_group'),
                                    TextInput::make('nationality')->default('Bangladeshi')->required(),
                                    TextInput::make('religion'),
                                ]),
                            ]),

                        Tab::make('Academic & Social')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Textarea::make('research_interest')->rows(2)->columnSpanFull(),
                                Grid::make(2)->schema([
                                    TextInput::make('personal_website')->url()->prefix('https://'),
                                    TextInput::make('google_scholar')->label('Google Scholar ID/URL'),
                                    TextInput::make('research_gate')->label('ResearchGate Profile'),
                                    TextInput::make('orcid')->label('ORCID'),
                                ]),
                                Section::make('Documents & Certificates')
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('documents')
                                            ->collection('documents')
                                            ->multiple()
                                            ->downloadable(),
                                    ])->collapsed(),
                            ]),

                        Tab::make('Education & Research')
                            ->icon('heroicon-o-book-open')
                            ->schema([
                                Repeater::make('educations')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => ($state['degree'] ?? '') . ' - ' . ($state['institution'] ?? ''))
                                    ->schema([
                                        Select::make('level_of_education')
                                            ->label('Level')
                                            ->options([
                                                'Doctorate' => 'Doctorate',
                                                'Masters' => 'Masters',
                                                'Bachelor' => 'Bachelor',
                                                'Higher Secondary' => 'Higher Secondary',
                                                'Secondary' => 'Secondary',
                                            ])
                                            ->required(),
                                        TextInput::make('degree')->required(),
                                        TextInput::make('field_of_study')->required(),
                                        TextInput::make('institution')->required(),
                                        TextInput::make('passing_year')->numeric(),
                                        TextInput::make('result_type'),
                                        TextInput::make('cgpa')->numeric()->label('CGPA/GPA'),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),

                                Repeater::make('publications')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->schema([
                                        TextInput::make('title')->required()->columnSpanFull(),
                                        TextInput::make('journal_name'),
                                        TextInput::make('publication_year')->numeric(),
                                        TextInput::make('doi')->label('DOI'),
                                        TextInput::make('url')->url(),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),
                            ]),

                        Tab::make('Experience & Skills')
                            ->icon('heroicon-o-briefcase')
                            ->schema([
                                Repeater::make('jobExperiences')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => ($state['position'] ?? '') . ' at ' . ($state['organization'] ?? ''))
                                    ->schema([
                                        TextInput::make('position')->required(),
                                        TextInput::make('organization')->required(),
                                        DatePicker::make('start_date')->required(),
                                        DatePicker::make('end_date'),
                                        Toggle::make('is_current')->label('Currently Working'),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),

                                Repeater::make('awards')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->schema([
                                        TextInput::make('title')->required(),
                                        TextInput::make('awarding_body'),
                                        TextInput::make('year')->numeric(),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),

                                Repeater::make('skills')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                    ->schema([
                                        TextInput::make('name')->required(),
                                        Select::make('proficiency')
                                            ->options([
                                                'Beginner' => 'Beginner',
                                                'Intermediate' => 'Intermediate',
                                                'Expert' => 'Expert',
                                            ]),
                                    ])
                                    ->columns(2)
                                    ->collapsed(),

                                Repeater::make('teachingAreas')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['area'] ?? null)
                                    ->schema([
                                        TextInput::make('area')->required(),
                                    ])
                                    ->collapsed(),
                            ]),

                        Tab::make('Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('profile_status')
                                        ->options([
                                            'draft' => 'Draft',
                                            'pending' => 'Pending Review',
                                            'approved' => 'Approved',
                                            'rejected' => 'Rejected',
                                        ])
                                        ->default('draft')
                                        ->required()
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Toggle::make('is_public')->label('Publicly Visible')
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Toggle::make('is_active')->label('Active Account')->default(true)
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    TextInput::make('sort_order')->numeric()->default(0)
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}

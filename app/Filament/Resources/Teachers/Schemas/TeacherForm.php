<?php

namespace App\Filament\Resources\Teachers\Schemas;

use Filament\Forms\Components\DatePicker;
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
    public static function configure(Schema $schema): Schema
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
                                        ->required(),
                                    Select::make('department_id')
                                        ->relationship('department', 'name')
                                        ->label('Department')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    Select::make('designation_id')
                                        ->relationship('designation', 'name')
                                        ->label('Designation')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                ]),
                                Grid::make(3)->schema([
                                    TextInput::make('employee_id')->label('Employee ID'),
                                    DatePicker::make('joining_date'),
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
                                        ->required(),
                                    Toggle::make('is_public')->label('Publicly Visible'),
                                    Toggle::make('is_active')->label('Active Account')->default(true),
                                    TextInput::make('sort_order')->numeric()->default(0),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}

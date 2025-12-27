<?php

namespace App\Filament\Resources\Teachers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
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
                    ->vertical()
                    ->extraAttributes(['class' => 'responsive-vertical-tabs'])
                    ->tabs([
                        Tab::make('Basic Info')
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
                                        ->hiddenOn('create')  // Auto-created by observer
                                        ->disabled(),  // Read-only reference on edit
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
                                    TextInput::make('employee_id')
                                        ->label('Employee ID')
                                        ->required()
                                        ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) use ($isOwnProfile) {
                                            if ($isOwnProfile && ($teacher = auth()->user()->teacher)) {
                                                return $rule->ignore($teacher->id);
                                            }
                                            return $rule;
                                        })
                                        ->maxLength(50)
                                        ->live(onBlur: true)
                                        ->hint(function ($state, $record) {
                                            if (empty($state)) return null;
                                            $exists = \App\Models\Teacher::where('employee_id', $state)
                                                ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                                ->exists();
                                            return $exists
                                                ? new \Illuminate\Support\HtmlString('<span class="text-danger-500">✗ Already taken</span>')
                                                : new \Illuminate\Support\HtmlString('<span class="text-success-500">✓ Available</span>');
                                        })
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    TextInput::make('email')
                                        ->label('Login Email')
                                        ->email()
                                        ->required(fn ($record): bool => $record === null)  // Required only on create
                                        ->unique('users', 'email', ignoreRecord: false, modifyRuleUsing: function ($rule, $record) use ($isOwnProfile) {
                                            if ($isOwnProfile) {
                                                return $rule->ignore(auth()->id());
                                            }

                                            // Manual ignore of User ID associated with Teacher
                                            if ($record && $record->user_id) {
                                                return $rule->ignore($record->user_id);
                                            }

                                            return $rule;
                                        })
                                        ->live(onBlur: true)
                                        ->hint(function ($state, $record) use ($isOwnProfile) {
                                            if (empty($state) || $isOwnProfile) return null;
                                            // Check if email exists for another user
                                            $query = \App\Models\User::where('email', $state);
                                            if ($record?->user_id) {
                                                $query->where('id', '!=', $record->user_id);
                                            }
                                            $exists = $query->exists();
                                            return $exists
                                                ? new \Illuminate\Support\HtmlString('<span class="text-danger-500">✗ Email already registered</span>')
                                                : new \Illuminate\Support\HtmlString('<span class="text-success-500">✓ Available</span>');
                                        })
                                        ->disabled($isOwnProfile)  // Teacher cannot edit own email
                                        ->dehydrated(! $isOwnProfile),  // Don't save when disabled
                                    TextInput::make('webpage')
                                        ->label('Profile URL Slug')
                                        ->required()
                                        ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) use ($isOwnProfile) {
                                            if ($isOwnProfile && ($teacher = auth()->user()->teacher)) {
                                                return $rule->ignore($teacher->id);
                                            }
                                            return $rule;
                                        })
                                        ->alphaDash()
                                        ->maxLength(100)
                                        ->live(onBlur: true)
                                        ->hint(function ($state, $record) {
                                            if (empty($state)) return null;
                                            $exists = \App\Models\Teacher::where('webpage', $state)
                                                ->when($record, fn($q) => $q->where('id', '!=', $record->id))
                                                ->exists();
                                            return $exists
                                                ? new \Illuminate\Support\HtmlString('<span class="text-danger-500">✗ URL slug taken</span>')
                                                : new \Illuminate\Support\HtmlString('<span class="text-success-500">✓ Available</span>');
                                        })
                                        ->helperText('Letters, numbers, dashes only')
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),

                                ]),
                                Grid::make(3)->schema([
                                    DatePicker::make('joining_date')
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),

                                    TextInput::make('work_location')->default('Main Campus')
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
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

                        Tab::make('Contact Info')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('phone')->tel()->required(),
                                    TextInput::make('personal_phone')->tel(),
                                    TextInput::make('extension_no')
                                        ->label('Extension No')
                                        ->maxLength(20),
                                    TextInput::make('office_room'),
                                    TextInput::make('secondary_email')->email(),

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
                                    Select::make('gender_id')
                                        ->relationship('gender', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order'))
                                        ->searchable()
                                        ->preload(),
                                    Select::make('blood_group_id')
                                        ->relationship('bloodGroup', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order'))
                                        ->searchable()
                                        ->preload(),
                                    Select::make('country_id')
                                        ->relationship('country', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order')) // Country relationship
                                        ->searchable()
                                        ->preload()
                                        ->default(fn () => \App\Models\Country::where('slug', 'bangladeshi')->first()?->id ?? \App\Models\Country::where('slug', 'bangladesh')->first()?->id),
                                    Select::make('religion_id')
                                        ->relationship('religion', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order'))
                                        ->searchable()
                                        ->preload(),
                                ]),
                            ]),

                        Tab::make('Academic Info')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Textarea::make('research_interest')->rows(2)->columnSpanFull(),
                            ]),

                        Tab::make('Educations')
                            ->icon('heroicon-o-book-open')
                            ->badge(fn ($record) => $record?->educations()->count())
                            ->schema([
                                Repeater::make('educations')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => \App\Models\DegreeType::find($state['degree_type_id'] ?? null)?->name . ' - ' . ($state['institution'] ?? ''))
                                    ->schema([

// Step 1: Degree Level (helper field, not saved to DB)
                                        Select::make('_degree_level_id')
                                            ->label('Degree Level')
                                            ->options(\App\Models\DegreeLevel::orderBy('sort_order')->pluck('name', 'id'))
                                            ->placeholder('Select level first')
                                            ->live()
                                            ->afterStateUpdated(fn (callable $set) => $set('degree_type_id', null))
                                            ->dehydrated(false)
                                            ->columnSpan(1),

// Step 2: Degree Type (filtered by level, SAVED to DB)
                                        Select::make('degree_type_id')
                                            ->label('Degree Type')
                                            ->relationship('degreeType', 'name', modifyQueryUsing: function ($query, $get) {
                                                $levelId = $get('_degree_level_id');
                                                if ($levelId) {
                                                    $query->where('degree_level_id', $levelId);
                                                }
                                                return $query->with('level')->orderBy('name');
                                            })
                                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->level->name} - {$record->name}")
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->afterStateHydrated(function ($state, callable $set) {
                                                if ($state) {
                                                    $degreeType = \App\Models\DegreeType::find($state);
                                                    if ($degreeType) {
                                                        $set('_degree_level_id', $degreeType->degree_level_id);
                                                    }
                                                }
                                            })
                                            ->disabled(fn ($get) => !$get('_degree_level_id'))
                                            ->createOptionForm([
                                                Select::make('degree_level_id')
                                                    ->label('Level')
                                                    ->relationship('level', 'name')
                                                    ->required(),
                                                TextInput::make('code')
                                                    ->required()
                                                    ->unique('degree_types', 'code', modifyRuleUsing: function ($rule, $get) {
                                                        return $rule->where('degree_level_id', $get('degree_level_id'));
                                                    }),
                                                TextInput::make('name')
                                                    ->required()
                                                    ->unique('degree_types', 'name', modifyRuleUsing: function ($rule, $get) {
                                                        return $rule->where('degree_level_id', $get('degree_level_id'));
                                                    }),
                                            ])
                                            ->columnSpan(1),

// Major / Field of Study (text input)
                                        TextInput::make('major')
                                            ->label('Major / Field of Study')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Computer Science, Mathematics')
                                            ->datalist([
                                                'Computer Science',
                                                'Electrical Engineering',
                                                'Mechanical Engineering',
                                                'Civil Engineering',
                                                'Mathematics',
                                                'Physics',
                                                'Chemistry',
                                                'Business Administration',
                                                'Economics',
                                                'English Literature',
                                                'Accounting',
                                                'Medicine',
                                            ])
                                            ->columnSpan(2),

// Institution
                                        TextInput::make('institution')
                                            ->required()
                                            ->maxLength(255),


// Country
                                        Select::make('country_id')
                                            ->label('Country')
                                            ->relationship('country', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->default(fn () => \App\Models\Country::where('slug', 'bangladesh')->first()?->id),

// Passing Year
                                        TextInput::make('passing_year')
                                            ->label('Passing Year')
                                            ->numeric()
                                            ->minValue(1950)
                                            ->maxValue(date('Y') + 5),

// Duration
                                        TextInput::make('duration')
                                            ->placeholder('e.g., 4 years')
                                            ->maxLength(50),

// Result Type (triggers conditional fields)
                                        Select::make('result_type_id')
                                            ->label('Result Type')
                                            ->relationship('resultType', 'type_name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->columnSpan(2),

// === CONDITIONAL RESULT FIELDS ===

// CGPA (for CGPA/GPA types)
                                        TextInput::make('cgpa')
                                            ->label('CGPA/GPA')
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->maxValue(fn ($get) => (float) ($get('scale') ?? 5.0))
                                            ->hidden(function ($get) {
                                                $resultTypeId = $get('result_type_id');
                                                if (!$resultTypeId) return true;
                                                $resultType = \App\Models\ResultType::find($resultTypeId);
                                                return !in_array($resultType?->type_name, ['CGPA', 'GPA']);
                                            }),

// Scale (for CGPA/GPA types)
                                        TextInput::make('scale')
                                            ->label('Out of (Scale)')
                                            ->numeric()
                                            ->step(0.1)
                                            ->default(4.0)
                                            ->minValue(1)
                                            ->maxValue(10)
                                            ->hidden(function ($get) {
                                                $resultTypeId = $get('result_type_id');
                                                if (!$resultTypeId) return true;
                                                $resultType = \App\Models\ResultType::find($resultTypeId);
                                                return !in_array($resultType?->type_name, ['CGPA', 'GPA']);
                                            }),

// Marks (for Percentage type)
                                        TextInput::make('marks')
                                            ->label('Marks/Percentage')
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->hidden(function ($get) {
                                                $resultTypeId = $get('result_type_id');
                                                if (!$resultTypeId) return true;
                                                $resultType = \App\Models\ResultType::find($resultTypeId);
                                                return $resultType?->type_name !== 'Percentage';
                                            })
                                            ->columnSpan(2),

// Grade (for Grade/Pass-Fail types)
                                        TextInput::make('grade')
                                            ->label('Grade/Division/Class')
                                            ->placeholder('e.g., First Class, A+, Pass')
                                            ->maxLength(50)
                                            ->hidden(function ($get) {
                                                $resultTypeId = $get('result_type_id');
                                                if (!$resultTypeId) return true;
                                                $resultType = \App\Models\ResultType::find($resultTypeId);
                                                return !in_array($resultType?->type_name, ['Grade', 'Pass/Fail']);
                                            })
                                            ->columnSpan(2),


])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->collapsed(),
                            ]),

                        Tab::make('Publications')
                            ->icon('heroicon-o-document-text')
                            ->badge(fn ($record) => $record?->publications()->count())
                            ->schema([
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
                                    ->defaultItems(0)
                                    ->collapsed(),
                            ]),

                        Tab::make('Job Experience')
                            ->icon('heroicon-o-briefcase')
                            ->badge(fn ($record) => $record?->jobExperiences()->count())
                            ->schema([
                                Repeater::make('jobExperiences')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => ($state['position'] ?? '') . ' at ' . ($state['organization'] ?? ''))
                                    ->schema([
                                        TextInput::make('position')->required(),
                                        TextInput::make('organization')->required(),
                                        Select::make('country_id')
                                            ->label('Country')
                                            ->options(\App\Models\Country::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->default(fn () => \App\Models\Country::where('slug', 'bangladesh')->first()?->id),
                                        DatePicker::make('start_date')->required(),
                                        DatePicker::make('end_date'),
                                        Toggle::make('is_current')->label('Currently Working'),
                                        TextInput::make('department'),
                                        Textarea::make('responsibilities')->label('Responsibilities')->columnSpanFull(),

                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->collapsed(),
                            ]),

                        Tab::make('Training Experience')
                            ->icon('heroicon-o-academic-cap')
                            ->badge(fn ($record) => $record?->trainingExperiences()->count())
                            ->schema([
                                Repeater::make('trainingExperiences')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->schema([
                                        TextInput::make('title')->required(),
                                        TextInput::make('organization')->required(),
                                        TextInput::make('category'),
                                        Select::make('country_id')
                                            ->label('Country')
                                            ->options(\App\Models\Country::pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->default(fn () => \App\Models\Country::where('slug', 'bangladesh')->first()?->id),
                                        TextInput::make('year')->numeric(),
                                        DatePicker::make('completion_date'),
                                        TextInput::make('duration_days')->numeric()->label('Duration (Days)'),
                                        Toggle::make('is_online')->label('Online'),
                                        Textarea::make('description')->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->collapsed(),
                            ]),

                        Tab::make('Awards')
                            ->icon('heroicon-o-trophy')
                            ->badge(fn ($record) => $record?->awards()->count())
                            ->schema([
                                Repeater::make('awards')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->schema([
                                        TextInput::make('title')->required(),
                                        TextInput::make('awarding_body'),
                                        TextInput::make('year')->numeric(),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->collapsed(),
                            ]),

                        Tab::make('Skills')
                            ->icon('heroicon-o-sparkles')
                            ->badge(fn ($record) => $record?->skills()->count())
                            ->schema([
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
                                    ->defaultItems(0)
                                    ->collapsed(),
                            ]),

                        Tab::make('Teaching Areas')
                            ->icon('heroicon-o-presentation-chart-line')
                            ->badge(fn ($record) => $record?->teachingAreas()->count())
                            ->schema([
                                Repeater::make('teachingAreas')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['area'] ?? null)
                                    ->schema([
                                        TextInput::make('area')->required(),
                                    ])
                                    ->defaultItems(0)
                                    ->collapsed(),
                            ]),

                        Tab::make('Social Links')
                            ->icon('heroicon-o-link')
                            ->badge(fn ($record) => $record?->socialLinks()->count())
                            ->schema([
                                Repeater::make('socialLinks')
                                    ->relationship()
                                    ->orderColumn('sort_order')
                                    ->reorderable()
                                    ->itemLabel(fn (array $state): ?string => \App\Models\SocialMediaPlatform::find($state['social_media_platform_id'] ?? null)?->name)
                                    ->schema([
                                        Select::make('social_media_platform_id')
                                            ->label('Platform')
                                            ->relationship('platform', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order'))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $username = $get('username');
                                                if ($state && $username) {
                                                    $platform = \App\Models\SocialMediaPlatform::find($state);
                                                    if ($platform && $platform->base_url) {
                                                        $set('url', rtrim($platform->base_url, '/') . '/' . ltrim($username, '/'));
                                                    }
                                                }
                                            })
                                            ->rules([
                                                function ($get) {
                                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                        $platform = \App\Models\SocialMediaPlatform::find($value);
                                                        if (!$platform || $platform->allow_multiple) return;

                                                        $rows = $get('../../socialLinks');
                                                        if (!is_array($rows)) return;
                                                        
                                                        // Count occurrences of this platform in the repeater
                                                        $count = collect($rows)->where('social_media_platform_id', $value)->count();
                                                        if ($count > 1) {
                                                             $fail("The {$platform->name} platform allows only one link.");
                                                        }
                                                    };
                                                }
                                            ]),

                                        TextInput::make('username')
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                $platformId = $get('social_media_platform_id');
                                                if ($platformId && $state) {
                                                    $platform = \App\Models\SocialMediaPlatform::find($platformId);
                                                    if ($platform && $platform->base_url) {
                                                        $set('url', rtrim($platform->base_url, '/') . '/' . ltrim($state, '/'));
                                                    }
                                                }
                                            }),

                                        TextInput::make('url')
                                            ->url()
                                            ->required()
                                            //->disabled() // URL field disabled করে দিন যাতে manually edit করা না যায়
                                            ->dehydrated(), // Database এ save হবে
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->collapsed(),
                            ]),

                        Tab::make('Documents')
                            ->icon('heroicon-o-document-duplicate')
                            ->schema([
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
                                        ->required()
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Select::make('employment_status')
                                        ->options([
                                            'active' => 'Active',
                                            'study_leave' => 'Study Leave',
                                            'on_leave' => 'On Leave',
                                            'deputation' => 'Deputation',
                                            'retired' => 'Retired',
                                            'resigned' => 'Resigned',
                                        ])
                                        ->default('active')
                                        ->required()
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Toggle::make('is_public')->label('Publicly Visible')
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Toggle::make('is_active')->label('Active Account')->default(true)
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Toggle::make('is_archived')->label('Archived')
                                        ->helperText('Archived teachers are hidden from main list')
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    TextInput::make('sort_order')->numeric()
                                        ->default(fn () => (\App\Models\Teacher::max('sort_order') ?? 0) + 1)
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}

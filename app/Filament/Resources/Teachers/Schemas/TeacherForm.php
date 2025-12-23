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
                                        ->unique('users', 'email', ignoreRecord: true, modifyRuleUsing: function ($rule) use ($isOwnProfile) {
                                            if ($isOwnProfile) {
                                                return $rule->ignore(auth()->id());
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
                                    Select::make('nationality_id')
                                        ->relationship('nationality', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order'))
                                        ->searchable()
                                        ->preload()
                                        ->default(fn () => \App\Models\Nationality::where('slug', 'bangladeshi')->first()?->id),
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
                                        DatePicker::make('start_date')->required(),
                                        DatePicker::make('end_date'),
                                        Toggle::make('is_current')->label('Currently Working'),
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
                                            ->relationship('platform', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order')) // Sort by order
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        TextInput::make('url')->url()->required(),
                                    ])
                                    ->columns(2)
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

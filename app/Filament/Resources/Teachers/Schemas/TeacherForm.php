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
use Filament\Schemas\Components\Utilities\Get;
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
                                        ->hiddenOn('create')
                                        ->disabled(),
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
                                        ->required(fn ($record): bool => $record === null)
                                        ->unique('users', 'email', ignoreRecord: false, modifyRuleUsing: function ($rule, $record) use ($isOwnProfile) {
                                            if ($isOwnProfile) {
                                                return $rule->ignore(auth()->id());
                                            }
                                            if ($record && $record->user_id) {
                                                return $rule->ignore($record->user_id);
                                            }
                                            return $rule;
                                        })
                                        ->live(onBlur: true)
                                        ->hint(function ($state, $record) use ($isOwnProfile) {
                                            if (empty($state) || $isOwnProfile) return null;
                                            $query = \App\Models\User::where('email', $state);
                                            if ($record?->user_id) {
                                                $query->where('id', '!=', $record->user_id);
                                            }
                                            $exists = $query->exists();
                                            return $exists
                                                ? new \Illuminate\Support\HtmlString('<span class="text-danger-500">✗ Email already registered</span>')
                                                : new \Illuminate\Support\HtmlString('<span class="text-success-500">✓ Available</span>');
                                        })
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
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
                                        ->relationship('country', 'name', modifyQueryUsing: fn (\Illuminate\Database\Eloquent\Builder $query) => $query->orderBy('sort_order'))
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
                                        Select::make('_degree_level_id')
                                            ->label('Degree Level')
                                            ->options(\App\Models\DegreeLevel::orderBy('sort_order')->pluck('name', 'id'))
                                            ->placeholder('Select level first')
                                            ->live()
                                            ->afterStateUpdated(fn (callable $set) => $set('degree_type_id', null))
                                            ->dehydrated(false)
                                            ->columnSpan(1),
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
                                        TextInput::make('institution')
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('country_id')
                                            ->label('Country')
                                            ->relationship('country', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->default(fn () => \App\Models\Country::where('slug', 'bangladesh')->first()?->id),
                                        TextInput::make('passing_year')
                                            ->label('Passing Year')
                                            ->numeric()
                                            ->minValue(1950)
                                            ->maxValue(date('Y') + 5),
                                        TextInput::make('duration')
                                            ->placeholder('e.g., 4 years')
                                            ->maxLength(50),
                                        Select::make('result_type_id')
                                            ->label('Result Type')
                                            ->relationship('resultType', 'type_name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->live()
                                            ->columnSpan(2),
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
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        // Delete removed items
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->educations()->whereNotIn('id', $existingIds)->delete();

                                        foreach ($state ?? [] as $item) {
                                            $data = [
                                                'degree_type_id' => $item['degree_type_id'],
                                                'major' => $item['major'],
                                                'institution' => $item['institution'],
                                                'country_id' => $item['country_id'] ?? null,
                                                'passing_year' => $item['passing_year'] ?? null,
                                                'duration' => $item['duration'] ?? null,
                                                'result_type_id' => $item['result_type_id'],
                                                'cgpa' => $item['cgpa'] ?? null,
                                                'scale' => $item['scale'] ?? null,
                                                'marks' => $item['marks'] ?? null,
                                                'grade' => $item['grade'] ?? null,
                                            ];
                                            if (isset($item['id'])) {
                                                $record->educations()->where('id', $item['id'])->update($data);
                                            } else {
                                                $record->educations()->create($data);
                                            }
                                        }
                                    }),
                            ]),

                        Tab::make('Publications')
                            ->icon('heroicon-o-document-text')
                            ->badge(fn ($record) => $record?->publications()->count())
                            ->schema([
                                Repeater::make('publications')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->schema([
                                        \Filament\Schemas\Components\Group::make()
                                            ->schema([
                                                \Filament\Schemas\Components\Section::make('Publication Details')
                                                    ->schema([
                                                        Select::make('faculty_id')
                                                            ->label('Faculty')
                                                            ->relationship('faculty', 'name')
                                                            ->searchable()
                                                            ->preload()
                                                            ->live()
                                                            ->afterStateUpdated(fn (callable $set) => $set('department_id', null)),
                                                        Select::make('department_id')
                                                            ->label('Department')
                                                            ->relationship('department', 'name', modifyQueryUsing: function ($query, callable $get) {
                                                                $facultyId = $get('faculty_id');
                                                                if (!$facultyId) {
                                                                    return $query;
                                                                }
                                                                return $query->where('faculty_id', $facultyId);
                                                            })
                                                            ->searchable()
                                                            ->preload(),
                                                        Select::make('publication_type_id')
                                                            ->relationship('type', 'name')
                                                            ->required(),
                                                        Select::make('publication_linkage_id')
                                                            ->relationship('linkage', 'name')
                                                            ->required(),
                                                        Select::make('publication_quartile_id')
                                                            ->relationship('quartile', 'name'),
                                                        Select::make('grant_type_id')
                                                            ->relationship('grant', 'name'),
                                                        Select::make('research_collaboration_id')
                                                            ->relationship('collaboration', 'name'),
                                                    ])->columns(3)->collapsible(),
                                                \Filament\Schemas\Components\Section::make('Core Information')
                                                    ->schema([
                                                        TextInput::make('title')
                                                            ->required()
                                                            ->columnSpanFull(),
                                                        Textarea::make('abstract')
                                                            ->columnSpanFull(),
                                                        TextInput::make('research_area')
                                                            ->columnSpanFull(),
                                                        Textarea::make('keywords')
                                                            ->columnSpanFull(),
                                                    ])->collapsible(),
                                            ])
                                            ->columnSpan(1),
                                        \Filament\Schemas\Components\Group::make()
                                            ->schema([


                                                \Filament\Schemas\Components\Section::make('Journal / Conference')
                                                    ->schema([
                                                        TextInput::make('journal_name'),
                                                        TextInput::make('journal_link')->url(),
                                                        DatePicker::make('publication_date'),
                                                        TextInput::make('publication_year')->numeric(),
                                                    ])->columns(2)->collapsible(),


                                                \Filament\Schemas\Components\Section::make('Authorship')
                                                    ->schema([
                                                        Select::make('first_author_id')
                                                            ->label('First Author')
                                                            ->options(\App\Models\Teacher::pluck('last_name', 'id')) // Simplified for now, should be searchable
                                                            ->searchable()
                                                            ->preload()
                                                            ->afterStateHydrated(fn ($component, $record) => $record && $component->state($record->teachers()->wherePivot('author_role', 'first')->first()?->id)),

                                                        Select::make('corresponding_author_id')
                                                            ->label('Corresponding Author')
                                                            ->options(\App\Models\Teacher::pluck('last_name', 'id'))
                                                            ->searchable()
                                                            ->preload()
                                                            ->afterStateHydrated(fn ($component, $record) => $record && $component->state($record->teachers()->wherePivot('author_role', 'corresponding')->first()?->id)),

                                                        Select::make('co_author_ids')
                                                            ->label('Co-Authors')
                                                            ->options(\App\Models\Teacher::pluck('last_name', 'id'))
                                                            ->searchable()
                                                            ->preload()
                                                            ->multiple()
                                                            ->afterStateHydrated(fn ($component, $record) => $record && $component->state($record->teachers()->wherePivot('author_role', 'co_author')->orderByPivot('sort_order')->pluck('teachers.id')->toArray())),
                                                    ])->columns(3),



                                                \Filament\Schemas\Components\Section::make('Metrics')
                                                    ->schema([
                                                        TextInput::make('h_index'),
                                                        TextInput::make('citescore')->numeric(),
                                                        TextInput::make('impact_factor')->numeric(),
                                                    ])->columns(3)->collapsible(),
                                                \Filament\Schemas\Components\Section::make('Status & Flags')
                                                    ->schema([
                                                        Toggle::make('student_involvement'),
                                                        Toggle::make('is_featured'),
                                                        // Status is set automatically based on approval settings
                                                        TextInput::make('sort_order')->numeric()->default(0),
                                                    ])->columns(3)->collapsible(),
                                            ])
                                            ->columnSpan(1),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        // Delete removed items - use table qualified ID for MorphToMany
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->publications()->whereNotIn('publications.id', $existingIds)->delete();

                                        foreach ($state ?? [] as $item) {
                                            // Determine status based on approval settings
                                            $requiresApproval = \App\Models\ApprovalSetting::requiresApproval('publication');
                                            $status = $requiresApproval ? 'pending' : 'approved';

                                            $data = [
                                                'faculty_id' => $item['faculty_id'] ?? null,
                                                'department_id' => $item['department_id'] ?? null,
                                                'publication_type_id' => $item['publication_type_id'],
                                                'publication_linkage_id' => $item['publication_linkage_id'],
                                                'publication_quartile_id' => $item['publication_quartile_id'] ?? null,
                                                'grant_type_id' => $item['grant_type_id'] ?? null,
                                                'research_collaboration_id' => $item['research_collaboration_id'] ?? null,
                                                'title' => $item['title'],
                                                'abstract' => $item['abstract'] ?? null,
                                                'research_area' => $item['research_area'] ?? null,
                                                'keywords' => $item['keywords'] ?? null,
                                                'journal_name' => $item['journal_name'] ?? null,
                                                'journal_link' => $item['journal_link'] ?? null,
                                                'publication_date' => $item['publication_date'] ?? null,
                                                'publication_year' => $item['publication_year'] ?? null,
                                                'h_index' => $item['h_index'] ?? null,
                                                'citescore' => $item['citescore'] ?? null,
                                                'impact_factor' => $item['impact_factor'] ?? null,
                                                'student_involvement' => $item['student_involvement'] ?? false,
                                                'is_featured' => $item['is_featured'] ?? false,
                                                // 'status' => $item['status'], // Field removed, handled below
                                                'sort_order' => $item['sort_order'] ?? 0,
                                            ];

                                            $publication = null;

                                            if (isset($item['id'])) {
                                                // Update
                                                $publication = \App\Models\Publication::find($item['id']);
                                                if ($publication) {
                                                    $publication->update($data);
                                                }
                                            } else {
                                                // Create
                                                $data['status'] = $requiresApproval ? 'pending' : 'approved';
                                                // Create via relation to link it to the teacher initially?
                                                // No, if we use `teachers()->sync` below, we can create it isolated first.
                                                // BUT, `saveRelationshipsUsing` hook implies we manage the relation.
                                                // If I create it via `$record->publications()->create()`, it auto-attaches `$record` (current teacher).
                                                // Then I will overwrite the attachments with `sync`.
                                                // This is fine.
                                                $publication = $record->publications()->create($data);
                                            }

                                            // Handle Authorship Sync
                                            if ($publication) {
                                                $syncData = [];

                                                // First Author
                                                if (!empty($item['first_author_id'])) {
                                                    $syncData[$item['first_author_id']] = ['author_role' => 'first', 'sort_order' => 1];
                                                }

                                                // Corresponding Author
                                                if (!empty($item['corresponding_author_id'])) {
                                                    // If already added (e.g. same as first), update role?
                                                    // Usually one person can be both, but pivot key is teacher_id.
                                                    // Pivot often handles multiple roles or unique teacher_id per publication.
                                                    // With standard `sync`, duplicate keys overwrite.
                                                    // We need to decide precedence or merging.
                                                    // Simplification: Prioritize roles?
                                                    // For now, if same person is First and Corresponding, the last one overwrites.
                                                    $existing = $syncData[$item['corresponding_author_id']] ?? [];
                                                    $syncData[$item['corresponding_author_id']] = array_merge($existing, ['author_role' => 'corresponding', 'sort_order' => 2]);
                                                }

                                                // Co-Authors
                                                if (!empty($item['co_author_ids']) && is_array($item['co_author_ids'])) {
                                                    foreach ($item['co_author_ids'] as $index => $coAuthorId) {
                                                        // Don't overwrite higher priority roles?
                                                        if (!isset($syncData[$coAuthorId])) {
                                                            $syncData[$coAuthorId] = ['author_role' => 'co_author', 'sort_order' => 3 + $index];
                                                        }
                                                    }
                                                }

                                                // Sync teachers
                                                if (!empty($syncData)) {
                                                    $publication->teachers()->sync($syncData);
                                                }
                                            }
                                        }
                                    }),
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
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->jobExperiences()->whereNotIn('id', $existingIds)->delete();

                                        foreach ($state ?? [] as $item) {
                                            $data = [
                                                'position' => $item['position'],
                                                'organization' => $item['organization'],
                                                'country_id' => $item['country_id'] ?? null,
                                                'start_date' => $item['start_date'],
                                                'end_date' => $item['end_date'] ?? null,
                                                'is_current' => $item['is_current'] ?? false,
                                                'department' => $item['department'] ?? null,
                                                'responsibilities' => $item['responsibilities'] ?? null,
                                            ];
                                            if (isset($item['id'])) {
                                                $record->jobExperiences()->where('id', $item['id'])->update($data);
                                            } else {
                                                $record->jobExperiences()->create($data);
                                            }
                                        }
                                    }),
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
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->trainingExperiences()->whereNotIn('id', $existingIds)->delete();

                                        foreach ($state ?? [] as $item) {
                                            $data = [
                                                'title' => $item['title'],
                                                'organization' => $item['organization'],
                                                'category' => $item['category'] ?? null,
                                                'country_id' => $item['country_id'] ?? null,
                                                'year' => $item['year'] ?? null,
                                                'completion_date' => $item['completion_date'] ?? null,
                                                'duration_days' => $item['duration_days'] ?? null,
                                                'is_online' => $item['is_online'] ?? false,
                                                'description' => $item['description'] ?? null,
                                            ];
                                            if (isset($item['id'])) {
                                                $record->trainingExperiences()->where('id', $item['id'])->update($data);
                                            } else {
                                                $record->trainingExperiences()->create($data);
                                            }
                                        }
                                    }),
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
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->awards()->whereNotIn('id', $existingIds)->delete();

                                        foreach ($state ?? [] as $item) {
                                            $data = [
                                                'title' => $item['title'],
                                                'awarding_body' => $item['awarding_body'] ?? null,
                                                'year' => $item['year'] ?? null,
                                            ];
                                            if (isset($item['id'])) {
                                                $record->awards()->where('id', $item['id'])->update($data);
                                            } else {
                                                $record->awards()->create($data);
                                            }
                                        }
                                    }),
                            ]),
                        Tab::make('Skills')
                            ->icon('heroicon-o-sparkles')
                            ->badge(fn ($record) => $record?->skills()->count())
                            ->schema([
                                Repeater::make('skills')
                                    ->relationship(
                                        name: 'skills',
                                        modifyQueryUsing: fn ($query) => $query->orderBy('id')
                                    )
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
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        // Get existing IDs
                                        $existingIds = collect($state)
                                            ->pluck('id')
                                            ->filter()
                                            ->toArray();

                                        // Delete removed items
                                        $record->skills()->whereNotIn('id', $existingIds)->delete();

                                        // Update or create items
                                        foreach ($state ?? [] as $item) {
                                            if (isset($item['id'])) {
                                                // Update existing
                                                $record->skills()->where('id', $item['id'])->update([
                                                    'name' => $item['name'],
                                                    'proficiency' => $item['proficiency'] ?? null,
                                                ]);
                                            } else {
                                                // Create new
                                                $record->skills()->create([
                                                    'name' => $item['name'],
                                                    'proficiency' => $item['proficiency'] ?? null,
                                                ]);
                                            }
                                        }
                                    }),
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
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->teachingAreas()->whereNotIn('id', $existingIds)->delete();

                                        foreach ($state ?? [] as $item) {
                                            $data = [
                                                'area' => $item['area'],
                                            ];
                                            if (isset($item['id'])) {
                                                $record->teachingAreas()->where('id', $item['id'])->update($data);
                                            } else {
                                                $record->teachingAreas()->create($data);
                                            }
                                        }
                                    }),
                            ]),

                        Tab::make('Memberships')
                            ->icon('heroicon-o-building-office-2')
                            ->badge(fn ($record) => $record?->memberships()->count())
                            ->schema([
                                Repeater::make('memberships')
                                    ->relationship()
                                    ->itemLabel(fn (array $state): ?string => \App\Models\MembershipOrganization::find($state['membership_organization_id'] ?? null)?->name)
                                    ->schema([
                                        Select::make('membership_organization_id')
                                            ->label('Organization')
                                            ->relationship(
                                                'membershipOrganization',
                                                'name',
                                                modifyQueryUsing: fn ($query, $get) => $query->where(function ($q) use ($get) {
                                                    $q->where('is_active', true)
                                                        ->orWhere('created_by', auth()->user()?->teacher?->id);
                                                })->orderBy('name')
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->required()
                                                    ->unique('membership_organizations', 'name')
                                                    ->maxLength(255),
                                                Textarea::make('description')
                                                    ->rows(2),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                $teacherId = auth()->user()?->teacher?->id;

                                                $org = \App\Models\MembershipOrganization::create([
                                                    'name' => $data['name'],
                                                    'description' => $data['description'] ?? null,
                                                    'is_active' => false,
                                                    'created_by' => $teacherId,
                                                ]);

                                                \App\Models\MembershipOrganization::checkAndActivateDuplicate($data['name'], $teacherId);

                                                return $org->id;
                                            })
                                            ->required(),
                                        Select::make('membership_type_id')
                                            ->label('Membership Type')
                                            ->relationship('membershipType', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('sort_order'))
                                            ->searchable()
                                            ->preload(),
                                        TextInput::make('membership_id')
                                            ->label('Membership ID'),
                                        DatePicker::make('start_date')
                                            ->label('Start Date'),
                                        DatePicker::make('end_date')
                                            ->label('End Date'),
                                        Select::make('status')
                                            ->options([
                                                'active' => 'Active',
                                                'expired' => 'Expired',
                                                'pending' => 'Pending',
                                            ])
                                            ->default('active'),
                                        Textarea::make('description')
                                            ->label('Notes')
                                            ->columnSpanFull()
                                            ->rows(2),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->memberships()->whereNotIn('id', $existingIds)->delete();

                                        foreach ($state ?? [] as $item) {
                                            $data = [
                                                'membership_organization_id' => $item['membership_organization_id'],
                                                'membership_type_id' => $item['membership_type_id'] ?? null,
                                                'membership_id' => $item['membership_id'] ?? null,
                                                'start_date' => $item['start_date'] ?? null,
                                                'end_date' => $item['end_date'] ?? null,
                                                'status' => $item['status'] ?? 'active',
                                                'description' => $item['description'] ?? null,
                                            ];
                                            if (isset($item['id'])) {
                                                $record->memberships()->where('id', $item['id'])->update($data);
                                            } else {
                                                $record->memberships()->create($data);
                                            }
                                        }
                                    }),
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
                                            ->dehydrated(),
                                    ])
                                    ->columns(3)
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->deletable(true)
                                    ->addable(true)
                                    ->saveRelationshipsUsing(function (Repeater $component, $state, $record) {
                                        $existingIds = collect($state)->pluck('id')->filter()->toArray();
                                        $record->socialLinks()->whereNotIn('id', $existingIds)->delete();

                                        foreach (array_values($state ?? []) as $index => $item) {
                                            $data = [
                                                'social_media_platform_id' => $item['social_media_platform_id'],
                                                'username' => $item['username'],
                                                'url' => $item['url'],
                                                'sort_order' => $index + 1,
                                            ];
                                            if (isset($item['id'])) {
                                                $record->socialLinks()->where('id', $item['id'])->update($data);
                                            } else {
                                                $record->socialLinks()->create($data);
                                            }
                                        }
                                    }),
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
                                    Select::make('employment_status_id')
                                        ->relationship('employmentStatus', 'name')
                                        ->label('Employment Status')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state) {
                                                $status = \App\Models\EmploymentStatus::find($state);
                                                if ($status) {
                                                    // Check specifically for 'retired' slug
                                                    if ($status->slug === 'retired') {
                                                        $set('is_archived', true);
                                                        $set('is_active', false);
                                                        $set('is_public', false);
                                                    } else {
                                                        // Strictly enforce is_active based on check_active for other statuses
                                                        $set('is_active', $status->check_active);
                                                        $set('is_public', $status->check_active);
                                                        // Ensure archived is false if status is active
                                                        if ($status->check_active) {
                                                            $set('is_archived', false);
                                                        }
                                                    }
                                                }
                                            }
                                        })
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Select::make('job_type_id')
                                        ->relationship('jobType', 'name')
                                        ->label('Job Type')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
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
                                    Toggle::make('is_public')
                                        ->label('Publicly Visible')
                                        ->helperText('Automatically disabled when account is inactive')
                                        ->live() // Make reactive to user input
                                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                            if ($state === true) {
                                                if (! $get('is_active')) {
                                                    $set('is_public', false);
                                                    \Filament\Notifications\Notification::make()
                                                        ->warning()
                                                        ->title('Cannot Make Public')
                                                        ->body('The teacher account must be active before it can be made publicly visible.')
                                                        ->send();
                                                }
                                            }
                                        })
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Toggle::make('is_active')
                                        ->label('Active Account')
                                        ->helperText('Controlled by Employment Status (retired/resigned/terminated/suspended = inactive)')
                                        ->default(true)
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                            if ($state === true) {
                                                // Check if current employment status allows activation
                                                $statusId = $get('employment_status_id');
                                                if ($statusId) {
                                                    $status = \App\Models\EmploymentStatus::find($statusId);
                                                    if ($status && !$status->check_active) {
                                                        // Status forbids activation - revert and warn
                                                        $set('is_active', false);
                                                        \Filament\Notifications\Notification::make()
                                                            ->warning()
                                                            ->title('Cannot Activate Account')
                                                            ->body("The current employment status '{$status->name}' requires the account to be inactive. Please change the status first.")
                                                            ->send();
                                                        return;
                                                    }
                                                }
                                                $set('is_archived', false);
                                            } else {
                                                $set('is_public', false);
                                            }
                                        })
                                        ->disabled($isOwnProfile)
                                        ->dehydrated(! $isOwnProfile),
                                    Toggle::make('is_archived')
                                        ->label('Archived')
                                        ->helperText('Cannot be archived if account is active')
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set) {
                                            if ($state === true) {
                                                $set('is_active', false);
                                                $set('is_public', false);
                                                // Auto-set to Retired status
                                                $retiredStatus = \App\Models\EmploymentStatus::where('slug', 'retired')->first();
                                                if ($retiredStatus) {
                                                    $set('employment_status_id', $retiredStatus->id);
                                                }
                                            }
                                        })
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

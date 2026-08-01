<?php

namespace App\Filament\Resources\Publications\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
class PublicationForm
{



    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                            ])->columns(3),



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
                            ]),


                    ]),
                \Filament\Schemas\Components\Group::make()
                    ->schema([


                \Filament\Schemas\Components\Section::make('Journal / Conference')
                    ->schema([
                        TextInput::make('journal_name'),
                        TextInput::make('journal_link')->url(),
                        \Filament\Forms\Components\DatePicker::make('publication_date'),
                        TextInput::make('publication_year')->numeric(),
                    ])->columns(2),

                \Filament\Schemas\Components\Section::make('Authorship')
                    ->schema([
                        Select::make('first_author_id')
                            ->label('First Author')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => static::searchAuthors($search))
                            ->getOptionLabelUsing(fn ($value) => static::authorLabel($value))
                            ->afterStateHydrated(function ($component, $record) {
                                if (!$record) return null;
                                $pivot = \DB::table('publication_authors')
                                    ->where('publication_id', $record->id)
                                    ->where('author_role', 'first')
                                    ->first();
                                if ($pivot) {
                                    $component->state($pivot->authorable_type . ':' . $pivot->authorable_id);
                                }
                            }),

                        Select::make('corresponding_author_id')
                            ->label('Corresponding Author')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => static::searchAuthors($search))
                            ->getOptionLabelUsing(fn ($value) => static::authorLabel($value))
                            ->afterStateHydrated(function ($component, $record) {
                                if (!$record) return null;
                                $pivot = \DB::table('publication_authors')
                                    ->where('publication_id', $record->id)
                                    ->where('author_role', 'corresponding')
                                    ->first();
                                if ($pivot) {
                                    $component->state($pivot->authorable_type . ':' . $pivot->authorable_id);
                                }
                            }),

                        Select::make('co_author_ids')
                            ->label('Co-Authors')
                            ->searchable()
                            ->multiple()
                            ->getSearchResultsUsing(fn (string $search) => static::searchAuthors($search))
                            ->getOptionLabelsUsing(fn (array $values) => static::authorLabels($values))
                            ->afterStateHydrated(function ($component, $record) {
                                if (!$record) return null;
                                $pivots = \DB::table('publication_authors')
                                    ->where('publication_id', $record->id)
                                    ->where('author_role', 'co_author')
                                    ->orderBy('sort_order')
                                    ->get();
                                $component->state($pivots->map(fn ($pivot) => $pivot->authorable_type . ':' . $pivot->authorable_id)->toArray());
                            }),
                    ])->columns(3),


                \Filament\Schemas\Components\Section::make('Metrics')
                    ->schema([
                        TextInput::make('h_index'),
                        TextInput::make('citescore')->numeric(),
                        TextInput::make('impact_factor')->numeric(),
                    ])->columns(3),

                \Filament\Schemas\Components\Section::make('Status & Flags')
                    ->schema([
                        Toggle::make('student_involvement'),
                        Toggle::make('is_featured')
                            ->disabled(function () {
                                $user = auth()->user();
                                if (!$user) return true;
                                if ($user->hasRole(['super_admin', 'admin', 'registrar', 'dean', 'head', 'research_team'])) return false;
                                if ($user->administrativeRoles()->where('administrative_role_user.is_active', true)->exists()) return false;
                                return $user->hasRole('teacher') || $user->isTeacher();
                            })
                            ->helperText(function () {
                                $user = auth()->user();
                                if (!$user) return null;
                                $isTeacherOnly = ($user->hasRole('teacher') || $user->isTeacher()) 
                                    && !$user->hasRole(['super_admin', 'admin', 'registrar', 'dean', 'head', 'research_team'])
                                    && !$user->administrativeRoles()->where('administrative_role_user.is_active', true)->exists();
                                return $isTeacherOnly ? 'Featured status can only be set by administrators or role managers.' : null;
                            }),
                        Select::make('status')
                            ->options(['draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                            ->default('draft')
                            ->required(),
                        Select::make('created_by')
                            ->label('Created By')
                            ->relationship('creator', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('System Generated'),
                       TextInput::make('sort_order')->numeric()->default(0),
                    ])->columns(5),
                    ]),
            ]);
    }

    /**
     * How many names one search may offer.
     *
     * There are 2,000 teachers and 1,600 external authors. This form used to
     * preload every eligible one into all three selects — 8,184 options in the
     * page — so the search is now run in the database and only the first
     * matches come back.
     */
    protected const SEARCH_LIMIT = 50;

    /**
     * Names matching what the person typed, current staff first.
     *
     * A teacher who has left is still offered, marked "Former". Somebody who
     * resigns keeps co-authoring with the people who are still here, and the
     * record has to be able to say so — an author list that silently ends at
     * the current payroll would describe papers that do not exist.
     *
     * @return array<string, string>
     */
    public static function searchAuthors(string $search): array
    {
        $search = trim($search);

        if ($search === '') {
            return [];
        }

        $like = '%' . $search . '%';

        $teachers = \App\Models\Teacher::query()
            ->where(fn ($q) => $q
                ->where('full_name', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('employee_id', 'like', $like))
            // Current staff first: they are who is being picked nearly every
            // time, and a departed colleague further down the list is no
            // trouble, whereas the reverse would be.
            ->orderBy('is_archived')
            ->orderBy('full_name')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn ($t) => [static::keyFor(\App\Models\Teacher::class, $t->id) => static::teacherLabel($t)]);

        $authors = \App\Models\Author::query()
            ->where('name', 'like', $like)
            ->with('authorType')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn ($a) => [static::keyFor(\App\Models\Author::class, $a->id) => static::externalLabel($a)]);

        return $teachers->merge($authors)->take(self::SEARCH_LIMIT)->toArray();
    }

    /**
     * The name behind one stored value, whatever its state.
     *
     * This is what the select shows for an author already on the record, and
     * what Filament validates the field against. Reading it from the options
     * list was the bug: an archived teacher was not in that list, so their name
     * rendered as the raw key and the form refused to save at all — across
     * 6,270 of the 17,510 publications.
     */
    public static function authorLabel(mixed $value): ?string
    {
        [$model, $id] = static::parseKey($value);

        if (! $model || ! $id) {
            return null;
        }

        if ($model === \App\Models\Teacher::class) {
            $teacher = \App\Models\Teacher::find($id);

            return $teacher ? static::teacherLabel($teacher) : null;
        }

        if ($model === \App\Models\Author::class) {
            $author = \App\Models\Author::with('authorType')->find($id);

            return $author ? static::externalLabel($author) : null;
        }

        return null;
    }

    /**
     * The same, for the multiple select — one query per kind, not per name.
     *
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    public static function authorLabels(array $values): array
    {
        $wanted = ['teachers' => [], 'authors' => []];

        foreach ($values as $value) {
            [$model, $id] = static::parseKey($value);

            if ($model === \App\Models\Teacher::class) {
                $wanted['teachers'][] = $id;
            } elseif ($model === \App\Models\Author::class) {
                $wanted['authors'][] = $id;
            }
        }

        $labels = [];

        if ($wanted['teachers']) {
            foreach (\App\Models\Teacher::whereIn('id', $wanted['teachers'])->get() as $teacher) {
                $labels[static::keyFor(\App\Models\Teacher::class, $teacher->id)] = static::teacherLabel($teacher);
            }
        }

        if ($wanted['authors']) {
            foreach (\App\Models\Author::with('authorType')->whereIn('id', $wanted['authors'])->get() as $author) {
                $labels[static::keyFor(\App\Models\Author::class, $author->id)] = static::externalLabel($author);
            }
        }

        return $labels;
    }

    /** "Former" is stated, so an unfamiliar name does not read as a mistake. */
    protected static function teacherLabel(\App\Models\Teacher $teacher): string
    {
        return $teacher->full_name . ($teacher->is_archived ? ' (Former Teacher)' : ' (Teacher)');
    }

    protected static function externalLabel(\App\Models\Author $author): string
    {
        $type = $author->authorType?->name ?? 'External';

        return $author->name . ' (' . $type . ($author->is_active ? '' : ' — Inactive') . ')';
    }

    /**
     * The stored form is "App\Models\Teacher:1066" — the morph type and the id.
     *
     * Written with the class constant rather than a literal so a namespace that
     * moves cannot leave the two halves of this file disagreeing.
     */
    protected static function keyFor(string $model, int|string $id): string
    {
        return $model . ':' . $id;
    }

    /** @return array{0: ?string, 1: ?string} */
    protected static function parseKey(mixed $value): array
    {
        if (! is_string($value) || ! str_contains($value, ':')) {
            return [null, null];
        }

        [$model, $id] = explode(':', $value, 2);

        return [$model, $id];
    }
}

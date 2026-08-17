<?php

namespace App\Filament\Resources\IntegrationMappings\Schemas;

use App\Models\IntegrationMapping;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Http;

class IntegrationMappingForm
{
    /**
     * Reduce a response to the one record a mapping is written against.
     *
     * This has to match what the import actually passes to
     * IntegrationService::transform(), otherwise an administrator maps a path
     * the importer never sees:
     *
     *   - a profile response is normalised by HrApiService, which lifts
     *     `teacher_profile` and flattens `core_info` up beside its sibling
     *     collections;
     *   - a list response contributes its first row.
     *
     * @param mixed $data the decoded response body
     * @return array<string,mixed>
     */
    protected static function sampleRecord(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (isset($data['teacher_profile'])) {
            return \App\Services\HrApiService::normaliseProfile($data) ?? [];
        }

        $records = $data['data'] ?? null;

        if (is_array($records) && $records !== []) {
            $first = $records[0] ?? $records;

            return is_array($first) ? $first : $records;
        }

        return $data;
    }

    /**
     * How many mapped-or-not columns the sections hold, for reporting what a
     * fetch actually added.
     *
     * @param array<int,array<string,mixed>> $groups
     */
    protected static function countColumns(array $groups): int
    {
        return array_sum(array_map(
            fn ($group) => count((array) ($group['rules'] ?? [])),
            $groups,
        ));
    }

    /**
     * The sections a mapping may use, read from the saved sample.
     *
     * A section is the array key in the API response, not a label: the stored
     * rule becomes "<section>.<column>" and is looked up by that exact path.
     * Offering the real keys is what stops a readable name like
     * "General Profile and Settings" being typed in, which produces paths that
     * match nothing and a mapping that silently fills in nothing.
     *
     * @return array<string,string>
     */
    protected static function sectionOptions(?string $sampleJson): array
    {
        $options = ['' => 'Core fields — the columns at the top of a record'];

        $sample = json_decode((string) $sampleJson, true);

        if (! is_array($sample)) {
            return $options;
        }

        foreach (\App\Support\MappingGroups::sectionsIn(self::sampleRecord($sample)) as $section) {
            $options[$section] = $section;
        }

        return $options;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('Used to identify this mapping in code (e.g., "legacy_teacher_search")')
                    ->columnSpanFull(),

                Select::make('api_method')
                    ->label('HTTP Method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                    ])
                    ->default('GET')
                    ->required()
                    ->live(),

                TextInput::make('api_url')
                    ->label('API URL')
                    ->url()
                    ->placeholder('http://localhost:8000/api/teacher/search?q=750000047')
                    ->helperText('Enter the API endpoint to fetch sample data')
                    ->suffixAction(
                        Action::make('fetch')
                            ->label('Fetch Data')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->action(function (Set $set, Get $get) {
                                $apiUrl = $get('api_url');
                                $apiMethod = $get('api_method') ?? 'GET';

                                if (!$apiUrl) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('API URL Required')
                                        ->body('Please enter an API URL first.')
                                        ->warning()
                                        ->send();
                                    return;
                                }

                                // The server does the fetching and shows the body
                                // back, so an unchecked address turns this field
                                // into a window onto the internal network.
                                if ($reason = \App\Helpers\OutboundUrl::rejectionReason($apiUrl)) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('That address cannot be fetched')
                                        ->body($reason)
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                try {
                                    /** @var \App\Services\HrApiService $hrApi */
                                    $hrApi = app(\App\Services\HrApiService::class);

                                    if ($hrApi->ownsUrl($apiUrl)) {
                                        // The HR API needs a bearer token, so an
                                        // unauthenticated fetch here just returned 401
                                        // and there was no way to build a mapping from
                                        // the live endpoint.
                                        $data = $hrApi->fetchUrl($apiUrl);
                                    } else {
                                        $response = $apiMethod === 'POST'
                                            ? Http::timeout(10)->post($apiUrl)
                                            : Http::timeout(10)->get($apiUrl);

                                        if (!$response->successful()) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('API Request Failed')
                                                ->body('Failed to fetch data from API. Status: ' . $response->status())
                                                ->danger()
                                                ->send();
                                            return;
                                        }

                                        $data = $response->json();
                                    }

                                    /*
                                     * Accumulate into the sample rather than
                                     * replace it. This employee may be the first
                                     * with awards, where the sample so far has an
                                     * empty list; fetching a few people is what
                                     * eventually gives every collection a real
                                     * example to read columns from.
                                     */
                                    $merged = \App\Support\SampleMerge::into($get('sample_json'), $data);

                                    $set('sample_json', $merged);

                                    // Detect from the accumulated sample, not just
                                    // this response, so the sections on screen are
                                    // exactly what the sample box now describes.
                                    $sampleData = self::sampleRecord(json_decode($merged, true));

                                    $fields = IntegrationMapping::flattenArray($sampleData);

                                    if (empty($fields)) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('No Fields Found')
                                            ->body('No fields could be extracted from the API response.')
                                            ->warning()
                                            ->send();
                                        return;
                                    }

                                    $existingGroups = (array) ($get('mapping_groups') ?? []);
                                    $before = self::countColumns($existingGroups);

                                    $groups = \App\Support\MappingGroups::mergeDetected(
                                        $existingGroups,
                                        $fields,
                                        \App\Support\MappingGroups::sectionsIn($sampleData),
                                    );

                                    $set('mapping_groups', $groups);

                                    $added = self::countColumns($groups) - $before;

                                    \Filament\Notifications\Notification::make()
                                        ->title($added > 0
                                            ? $added . ' new column(s) added'
                                            : 'Nothing new in this response')
                                        ->body($added > 0
                                            ? 'The sample now covers ' . count($fields) . ' columns across ' . count($groups) . ' section(s). Existing rows were left as they are.'
                                            : 'This employee has no sections the sample was missing. Try another employee ID to pick up awards, certifications or skills.')
                                        ->success()
                                        ->send();

                                } catch (\Throwable $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Error Fetching Data')
                                        ->body('Error: ' . $e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            })
                    )
                    ->columnSpanFull(),

                Placeholder::make('fetch_hint')
                    ->label('')
                    ->content('**Option 1:** Click "Fetch Data" button above, **OR Option 2:** Paste sample JSON below and click "Parse JSON" button')
                    ->columnSpanFull(),

                Textarea::make('sample_json')
                    ->label('Sample JSON Data (Alternative)')
                    ->rows(12)
                    ->placeholder('Paste sample API response here, or press Fetch Data to bring one in...')
                    ->helperText('Filled in by "Fetch Data" and saved with the mapping. Because no single teacher has every section — most have no awards, certifications or skills — fetch a few different employee IDs, paste the missing sections together here, then press "Parse JSON" to map the combined result. Holds a real employee\'s details, so treat it as personal data; it is encrypted at rest.')
                    ->columnSpanFull(),

                Grid::make(1)
                    ->schema([
                        Action::make('parse_json')
                            ->label('Parse JSON')
                            ->icon('heroicon-o-code-bracket')
                            ->color('success')
                            ->action(function (Set $set, Get $get) {
                                $jsonString = $get('sample_json');

                                if (!$jsonString) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('JSON Required')
                                        ->body('Please paste sample JSON data first.')
                                        ->warning()
                                        ->send();
                                    return;
                                }

                                try {
                                    $data = json_decode($jsonString, true);

                                    if (json_last_error() !== JSON_ERROR_NONE) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Invalid JSON')
                                            ->body('The JSON data is not valid: ' . json_last_error_msg())
                                            ->danger()
                                            ->send();
                                        return;
                                    }

                                    $sampleData = self::sampleRecord($data);

                                    // Flatten to get field paths
                                    $fields = IntegrationMapping::flattenArray($sampleData);

                                    if (empty($fields)) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('No Fields Found')
                                            ->body('No fields could be extracted from the JSON.')
                                            ->warning()
                                            ->send();
                                        return;
                                    }

                                    $groups = \App\Support\MappingGroups::mergeDetected(
                                        (array) ($get('mapping_groups') ?? []),
                                        $fields,
                                        \App\Support\MappingGroups::sectionsIn($sampleData),
                                    );

                                    $set('mapping_groups', $groups);

                                    \Filament\Notifications\Notification::make()
                                        ->title('JSON Parsed Successfully')
                                        ->body(count($fields) . ' fields found across ' . count($groups) . ' section(s).')
                                        ->success()
                                        ->send();

                                } catch (\Exception $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Error Parsing JSON')
                                        ->body('Error: ' . $e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ])
                    ->columnSpanFull(),

                /*
                 * One accordion per section of the payload, rather than one flat
                 * list of every column of every collection. A profile response
                 * carries a dozen collections beside the core fields, so the flat
                 * list ran to hundreds of rows with nothing to say which column
                 * belonged to which section.
                 *
                 * Sections that come back empty from the API — awards, skills,
                 * memberships on a teacher who has none — detect no fields at
                 * all, which is why a section can be added by hand and rows added
                 * inside it.
                 */
                Repeater::make('mapping_groups')
                    ->label('Field Mappings')
                    ->helperText('One section per part of the API response. "Core fields" holds the columns at the top of a record; the others are its repeated sections.')
                    ->schema([
                        Select::make('section')
                            ->label('Section')
                            ->options(fn (Get $get) => self::sectionOptions($get('../../sample_json')))
                            ->default('')
                            ->selectablePlaceholder(false)
                            ->live()
                            ->helperText('The array key in the API response — not a label. Pick one detected in the sample, or add a key the sample has not shown yet.')
                            ->createOptionForm([
                                TextInput::make('section')
                                    ->label('Section key')
                                    ->required()
                                    ->helperText('Exactly as the API spells it, e.g. employeeProfessionalMemberships.'),
                            ])
                            ->createOptionUsing(fn (array $data) => $data['section'])
                            ->columnSpanFull(),

                        Repeater::make('rules')
                            ->label('Columns')
                            ->schema([
                                TextInput::make('field')
                                    ->label('API Column')
                                    ->required()
                                    ->placeholder('instituteName')
                                    ->live(onBlur: true)
                                    // Shows the path this row will actually be
                                    // stored and looked up as, so a section or
                                    // column that matches nothing is visible here
                                    // rather than after an import fills in zero
                                    // fields.
                                    ->helperText(function (Get $get): string {
                                        $field = trim((string) $get('field'));

                                        if ($field === '') {
                                            return 'Just the column name inside this section. Nested objects use a dot: degree.name';
                                        }

                                        $section = trim((string) $get('../../section'));

                                        return 'Reads: ' . ($section === '' ? $field : "{$section}.{$field}");
                                    }),

                                Select::make('target_model')
                                    ->label('Target Model')
                                    ->options(IntegrationMapping::getSupportedModels())
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('target_field', null)),

                                Select::make('target_field')
                                    ->label('Target Column')
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->options(function (Get $get) {
                                        $model = $get('target_model');
                                        if (!$model) {
                                            return [];
                                        }

                                        $fields = IntegrationMapping::getModelFillableFields($model);
                                        return array_combine($fields, $fields);
                                    })
                                    // Tick the relation box for the columns that
                                    // really are foreign keys, so the common case
                                    // needs no thought and the exceptions —
                                    // employee_id, scopus_id — stay visibly off.
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                                        'is_relation',
                                        filled($state) && \App\Support\LookupResolver::handles($state),
                                    ))
                                    ->helperText(function (Get $get): ?string {
                                        $field = (string) $get('target_field');

                                        if ($field === '') {
                                            return null;
                                        }

                                        $table = \App\Support\LookupResolver::tableFor($field);

                                        return $table
                                            ? "Text will be looked up in the {$table} table and stored as its ID."
                                            : 'Stored exactly as the API sends it.';
                                    }),

                                Toggle::make('is_identifier')
                                    ->label('Identifier')
                                    ->helperText('Use to find existing records')
                                    ->default(false),

                                /*
                                 * Says out loud that this column holds a
                                 * reference to another table, and is the escape
                                 * hatch where the naming convention gets it
                                 * wrong: employee_id and scopus_id end in _id and
                                 * have no table behind them.
                                 */
                                Toggle::make('is_relation')
                                    ->label('Relation')
                                    ->live()
                                    ->helperText(function (Get $get): string {
                                        $table = \App\Support\LookupResolver::tableFor((string) $get('target_field'));

                                        return $table
                                            ? "Saves the matching {$table} row's ID instead of the text."
                                            : 'No matching table — the text is saved as it arrives.';
                                    })
                                    ->default(false)
                                    ->visible(fn (Get $get): bool => str_ends_with((string) $get('target_field'), '_id')),

                                /*
                                 * Which column the incoming text is compared
                                 * against. "ITM" belongs against departments.code
                                 * and "Male" against genders.name; guessing an
                                 * order works most of the time but is exactly the
                                 * kind of thing that should be stateable.
                                 */
                                Select::make('match_column')
                                    ->label('Match against column')
                                    ->options(fn (Get $get) => \App\Support\LookupResolver::matchColumnOptions(
                                        (string) $get('target_field'),
                                    ))
                                    ->placeholder('Try code, then name, short_name, slug')
                                    ->helperText('Leave empty to try the usual columns in order.')
                                    ->visible(fn (Get $get): bool => (bool) $get('is_relation')
                                        && \App\Support\LookupResolver::tableFor((string) $get('target_field')) !== null),
                            ])
                            ->columns(4)
                            ->columnSpanFull()
                            ->defaultItems(0)
                            ->addActionLabel('Add column')
                            ->itemLabel(function (array $state): ?string {
                                $field = $state['field'] ?? null;

                                if (blank($field)) {
                                    return null;
                                }

                                $target = $state['target_field'] ?? null;

                                if (blank($target)) {
                                    return $field . ' — not mapped';
                                }

                                // Marks the rows that store a reference rather
                                // than the text, so it reads off the collapsed
                                // list without opening every row.
                                return $field . ' → ' . $target
                                    . (! empty($state['is_relation']) ? '  [relation]' : '');
                            })
                            ->collapsible()
                            ->reorderable(false),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->addActionLabel('Add section')
                    ->itemLabel(function (array $state): string {
                        $section = trim((string) ($state['section'] ?? '')) ?: 'Core fields';
                        $rules = (array) ($state['rules'] ?? []);

                        // Unmapped rows are the ones still needing attention, so
                        // the count that matters is how many are done.
                        $mapped = count(array_filter(
                            $rules,
                            fn ($rule) => filled($rule['target_field'] ?? null),
                        ));

                        return $section . '  (' . $mapped . '/' . count($rules) . ' mapped)';
                    })
                    ->collapsible()
                    ->collapsed()
                    ->reorderable(false),
            ]);
    }
}

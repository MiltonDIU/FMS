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

                                try {
                                    if ($apiMethod === 'POST') {
                                        $response = Http::timeout(10)->post($apiUrl);
                                    } else {
                                        $response = Http::timeout(10)->get($apiUrl);
                                    }

                                    if (!$response->successful()) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('API Request Failed')
                                            ->body('Failed to fetch data from API. Status: ' . $response->status())
                                            ->danger()
                                            ->send();
                                        return;
                                    }

                                    $data = $response->json();

                                    // Extract sample data
                                    if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
                                        $sampleData = is_array($data['data'][0]) ? $data['data'][0] : $data['data'];
                                    } else {
                                        $sampleData = $data;
                                    }

                                    // Flatten to get field paths
                                    $fields = IntegrationMapping::flattenArray($sampleData);

                                    if (empty($fields)) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('No Fields Found')
                                            ->body('No fields could be extracted from the API response.')
                                            ->warning()
                                            ->send();
                                        return;
                                    }

                                    // Auto-populate repeater with fields
                                    $mappings = [];
                                    foreach ($fields as $field) {
                                        $mappings[] = [
                                            'source_field' => $field,
                                            'target_model' => null,
                                            'target_field' => null,
                                            'is_identifier' => false,
                                        ];
                                    }

                                    $set('mapping_config', $mappings);

                                    \Filament\Notifications\Notification::make()
                                        ->title('Data Fetched Successfully')
                                        ->body(count($fields) . ' fields found and populated.')
                                        ->success()
                                        ->send();

                                } catch (\Exception $e) {
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
                    ->rows(5)
                    ->placeholder('Paste sample API response here...')
                    ->helperText('If API fetch fails, paste a sample JSON response here')
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

                                    // Extract sample data
                                    if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
                                        $sampleData = is_array($data['data'][0]) ? $data['data'][0] : $data['data'];
                                    } else {
                                        $sampleData = $data;
                                    }

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

                                    // Auto-populate repeater with fields
                                    $mappings = [];
                                    foreach ($fields as $field) {
                                        $mappings[] = [
                                            'source_field' => $field,
                                            'target_model' => null,
                                            'target_field' => null,
                                            'is_identifier' => false,
                                        ];
                                    }

                                    $set('mapping_config', $mappings);

                                    \Filament\Notifications\Notification::make()
                                        ->title('JSON Parsed Successfully')
                                        ->body(count($fields) . ' fields found and populated.')
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

                Repeater::make('mapping_config')
                    ->label('Field Mappings')
                    ->schema([
                        Select::make('source_field')
                            ->label('API Field')
                            ->required()
                            ->searchable()
                            ->options(function (Get $get) {
                                // Get all source fields from the repeater
                                $mappings = $get('../../mapping_config') ?? [];
                                $fields = [];
                                foreach ($mappings as $mapping) {
                                    if (isset($mapping['source_field'])) {
                                        $fields[$mapping['source_field']] = $mapping['source_field'];
                                    }
                                }
                                return $fields;
                            })
                            ->createOptionForm([
                                TextInput::make('custom_field')
                                    ->label('Custom Field Path')
                                    ->required(),
                            ])
                            ->createOptionUsing(function ($data) {
                                return $data['custom_field'];
                            }),

                        Select::make('target_model')
                            ->label('Target Model')
                            ->options([
                                'User' => 'User',
                                'Teacher' => 'Teacher',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('target_field', null)),

                        Select::make('target_field')
                            ->label('Target Column')
                            ->required()
                            ->searchable()
                            ->options(function (Get $get) {
                                $model = $get('target_model');
                                if (!$model) {
                                    return [];
                                }

                                $fields = IntegrationMapping::getModelFillableFields($model);
                                return array_combine($fields, $fields);
                            }),

                        Toggle::make('is_identifier')
                            ->label('Identifier')
                            ->helperText('Use to find existing records')
                            ->default(false),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->addActionLabel('Add Field Mapping')
                    ->reorderable(false),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Teachers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EducationsRelationManager extends RelationManager
{
    protected static string $relationship = 'educations';

    protected static ?string $recordTitleAttribute = 'degree';

    public function form(Schema $form): Schema
    {
        return $form->schema([
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
                ->options(\App\Models\Country::pluck('name', 'id'))
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

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('degreeType.name')->label('Degree')->searchable()->sortable(), // Use relationship
                Tables\Columns\TextColumn::make('major')->label('Major/Field')->searchable(),
                Tables\Columns\TextColumn::make('institution')->label('Institution')->searchable(),
                Tables\Columns\TextColumn::make('passing_year')->label('Year')->sortable(),
                Tables\Columns\TextColumn::make('resultType.type_name')->label('Result Format'),
                // Optionally add specific result column via getState accessors
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

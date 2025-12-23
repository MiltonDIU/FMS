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
                \Filament\Schemas\Components\Section::make('Publication Details')
                    ->schema([
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

                \Filament\Schemas\Components\Section::make('Journal / Conference')
                    ->schema([
                        TextInput::make('journal_name'),
                        TextInput::make('journal_link')->url(),
                        \Filament\Forms\Components\DatePicker::make('publication_date'),
                        TextInput::make('publication_year')->numeric(),
                    ])->columns(2),


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

                \Filament\Schemas\Components\Section::make('Authorship')
                    ->schema([
                        Select::make('first_author_id')
                            ->label('First Author')
                            ->options(\App\Models\Teacher::pluck('last_name', 'id')) // Simplified for now, should be searchable
                            ->searchable()
                            ->preload()
                            ->required()
                            ->afterStateHydrated(fn ($component, $record) => $record ? $component->state($record->teachers()->wherePivot('author_role', 'first')->first()?->id) : null),

                        Select::make('corresponding_author_id')
                            ->label('Corresponding Author')
                             ->options(\App\Models\Teacher::pluck('last_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->afterStateHydrated(fn ($component, $record) => $record ? $component->state($record->teachers()->wherePivot('author_role', 'corresponding')->first()?->id) : null),

                        Select::make('co_author_ids')
                            ->label('Co-Authors')
                             ->options(\App\Models\Teacher::pluck('last_name', 'id'))
                            ->searchable()
                            ->preload()
                            ->multiple()
                            ->afterStateHydrated(fn ($component, $record) => $record ? $component->state($record->teachers()->wherePivot('author_role', 'co_author')->orderByPivot('sort_order')->pluck('teachers.id')->toArray()) : null),
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
                        Toggle::make('is_featured'),
                        Select::make('status')
                            ->options(['draft' => 'Draft', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])
                            ->default('draft')
                            ->required(),
                       TextInput::make('sort_order')->numeric()->default(0),
                    ])->columns(4),
            ]);
    }
}

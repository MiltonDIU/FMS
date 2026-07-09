<?php

namespace App\Filament\Resources\ResearchProjects\Schemas;

use App\Models\Teacher;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ResearchProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Project Details')
                    ->schema([
                        Select::make('teacher_id')
                            ->label('Teacher')
                            ->options(Teacher::where('is_archived', false)->get()->pluck('full_name', 'id'))
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                            
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                            
                        Grid::make(2)
                            ->schema([
                                TextInput::make('project_leader')
                                    ->label('Project Leader')
                                    ->maxLength(255),
                                    
                                Select::make('funding_agency_organization_id')
                                    ->label('Funding Agency')
                                    ->relationship(
                                        'fundingAgencyOrganizationRelation',
                                        'name',
                                        modifyQueryUsing: fn ($query) => $query->where('is_funding_agency', true)->where('is_active', true)->orderBy('name')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $record = \App\Models\Organization::findOrCreateWithAutoApproval($data['name'], null, null, ['is_funding_agency' => true]);
                                        return $record->id;
                                    })
                                    ->required(),
                                    
                                Select::make('role')
                                    ->options([
                                        'PI' => 'Principal Investigator',
                                        'Co-PI' => 'Co-Principal Investigator',
                                        'Researcher' => 'Researcher',
                                        'Supervisor' => 'Supervisor',
                                    ])
                                    ->required(),
                                    
                                Select::make('status')
                                    ->options([
                                        'active' => 'Active',
                                        'completed' => 'Completed',
                                        'submitted' => 'Submitted',
                                        'rejected' => 'Rejected',
                                    ])
                                    ->default('active')
                                    ->required(),
                            ]),
                            
                        Grid::make(3)
                            ->schema([
                                TextInput::make('budget')
                                    ->numeric()
                                    ->prefix('BDT'),
                                    
                                TextInput::make('currency')
                                    ->default('BDT')
                                    ->maxLength(3),
                                    
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0),
                            ]),
                            
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('start_date'),
                                DatePicker::make('end_date'),
                            ]),
                            
                        Textarea::make('description')
                            ->columnSpanFull(),
                            
                        Textarea::make('outcome')
                            ->label('Project Outcome / Findings')
                            ->columnSpanFull(),
                    ])
            ]);
    }
}

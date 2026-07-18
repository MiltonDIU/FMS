<?php

namespace App\Filament\Resources\PublicationIncentives\Schemas;

use App\Models\Publication;
use App\Models\Teacher;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PublicationIncentiveForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                   \Filament\Schemas\Components\Group::make()
                    ->schema([
                Section::make('Publication')
                    ->compact()
                    ->schema([
                        Select::make('publication_id')
                            ->label('Publication')
                            ->relationship(
                                'publication',
                                'title',
                                modifyQueryUsing: fn($query, $record) => $record
                                    ? $query // On edit, show all
                                    : $query->whereDoesntHave('incentive') // On create, exclude those with incentive
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $publication = Publication::find($state);
                                    if ($publication) {
                                        $pivots = \DB::table('publication_authors')
                                            ->where('publication_id', $publication->id)
                                            ->get();

                                        $teacherIds = $pivots->where('authorable_type', \App\Models\Teacher::class)->pluck('authorable_id');
                                        $authorIds = $pivots->where('authorable_type', \App\Models\Author::class)->pluck('authorable_id');

                                        $teachers = \App\Models\Teacher::whereIn('id', $teacherIds)->get()->keyBy('id');
                                        $authors = \App\Models\Author::whereIn('id', $authorIds)->get()->keyBy('id');

                                        $authorsData = $pivots->map(function ($pivot) use ($teachers, $authors) {
                                            $name = 'Unknown';
                                            if ($pivot->authorable_type === \App\Models\Teacher::class) {
                                                $model = $teachers->get($pivot->authorable_id);
                                                $name = $model ? trim("{$model->first_name} {$model->middle_name} {$model->last_name}") : 'Unknown';
                                            } elseif ($pivot->authorable_type === \App\Models\Author::class) {
                                                $model = $authors->get($pivot->authorable_id);
                                                $name = $model ? $model->name : 'Unknown';
                                            }

                                            $rolePriority = match ($pivot->author_role) {
                                                'first' => 1,
                                                'corresponding' => 2,
                                                default => 3,
                                            };

                                            return [
                                                'id' => $pivot->id,
                                                'author_name' => $name,
                                                'author_role' => $pivot->author_role,
                                                'incentive_amount' => $pivot->incentive_amount ?? 0,
                                                'priority' => sprintf('%d-%04d', $rolePriority, $pivot->sort_order),
                                            ];
                                        })->sortBy('priority')->values()->toArray();

                                        $set('author_incentives', $authorsData);
                                    }
                                }
                            })
                            ->disabled(fn($record) => $record !== null), // Disable on edit
                    ]),

                Section::make('Incentive Summary')
                    ->compact()
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('Total Amount (TK)')
                            ->numeric()
                            ->prefix('৳')
                            ->required()
                            ->live()
                            ->helperText('Total amount must equal sum of all author incentives'),

                        Placeholder::make('validation_message')
                            ->label('')
                            ->content(function (Get $get) {
                                $total = (float) ($get('total_amount') ?? 0);
                                $authors = $get('author_incentives') ?? [];
                                $authorsSum = collect($authors)->sum('incentive_amount');

                                if ($total == $authorsSum) {
                                    return '✅ Total matches authors sum: ৳' . number_format($authorsSum, 2);
                                }
                                return '❌ Mismatch! Authors sum: ৳' . number_format($authorsSum, 2) . ' but Total: ৳' . number_format($total, 2);
                            }),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'paid' => 'Paid',
                            ])
                            ->default('pending')
                            ->required(),

                        Textarea::make('remarks')
                            ->label('Remarks')
                            ->rows(2),
                    ])->columns(2),


                    ]),
  \Filament\Schemas\Components\Group::make()
                    ->schema([
                Section::make('Author Incentives')
                    ->compact()
                    ->description('Enter incentive amount for each author. Total amount must equal the sum of all author incentives.')
                    ->schema([
                        Repeater::make('author_incentives')
                            ->label('')
                            ->schema([
                                \Filament\Forms\Components\Hidden::make('id'),
                                TextInput::make('author_name')
                                    ->label('Author')
                                    ->disabled()
                                    ->columnSpan(4)
                                    ->dehydrated(false),
                                Select::make('author_role')
                                    ->label('Role')
                                    ->options([
                                        'first' => '1st Author',
                                        'corresponding' => 'Corresponding Author',
                                        'co_author' => 'Co-Author',
                                    ])
                                    ->disabled()
                                    ->columnSpan(4)
                                    ->dehydrated(true)
                                    ->extraAttributes([
                                        'style' => 'padding: 0 !important;',
                                    ]),
                                TextInput::make('incentive_amount')
                                    ->label('Amount (TK)')
                                    ->numeric()
                                    ->prefix('৳')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $authors = $get('../../author_incentives') ?? [];
                                        $total = collect($authors)->sum('incentive_amount');
                                        $set('../../total_amount', $total);
                                    })
                                    ->columnSpan(4),
                            ])
                            ->columns(12)
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->dehydrated(true),
                    ]),
                     ]),
            ]);

    }
}


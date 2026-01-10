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
                                    $publication = Publication::with('teachers')->find($state);
                                    if ($publication) {
                                        $authors = $publication->teachers
                                            ->sortBy('pivot.sort_order')
                                            ->map(fn($t) => [
                                                'teacher_id' => $t->id,
                                                'author_role' => $t->pivot->author_role,
                                                'incentive_amount' => $t->pivot->incentive_amount ?? 0,
                                            ])->toArray();
                                        $set('author_incentives', $authors);
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
            
                    ->description('প্রতিটা author এর জন্য incentive amount দিন। মোট amount সব authors এর যোগফলের সমান হতে হবে।')
                    ->schema([
                        Repeater::make('author_incentives')
                            ->label('')
                            ->schema([
                                Select::make('teacher_id')
                                    ->label('Author')
                                    ->options(Teacher::all()->mapWithKeys(fn($t) => [$t->id => $t->full_name]))
                                    ->disabled()
                                    ->dehydrated(true),
                                Select::make('author_role')
                                    ->label('Role')
                                    ->options([
                                        'first' => '1st Author',
                                        'corresponding' => 'Corresponding Author',
                                        'co_author' => 'Co-Author',
                                    ])
                                    ->disabled()
                                    ->dehydrated(true),
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
                                    }),
                            ])
                            ->columns(3)
                            ->reorderable(false)
                            ->addable(false)
                            ->deletable(false)
                            ->dehydrated(true),
                    ]),
                     ]),
            ]);
            
    }
}


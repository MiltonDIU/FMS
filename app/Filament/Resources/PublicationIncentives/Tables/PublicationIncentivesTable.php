<?php

namespace App\Filament\Resources\PublicationIncentives\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class PublicationIncentivesTable
{
    public static function configure(Table $table): Table
    {
        $user = auth()->user();
        $adminRole = null;

        if ($user && ! $user->hasRole('super_admin')) {
            $adminRole = $user->administrativeRoles()
                ->wherePivot('is_active', true)
                ->whereNull('administrative_role_user.end_date')
                ->first();
        }

        return $table
            ->defaultSort('created_at', 'desc')

            ->columns([
                TextColumn::make('publication.title')
                    ->label('Publication')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('BDT')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'paid' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('authors_list')
                    ->label('Authors')
                    ->state(function ($record) {
                        if (! $record->publication) {
                            return '—';
                        }

                        $pivots = \DB::table('publication_authors')
                            ->where('publication_id', $record->publication_id)
                            ->get();

                        if ($pivots->isEmpty()) {
                            return '—';
                        }

                        $teacherIds = $pivots->where('authorable_type', \App\Models\Teacher::class)->pluck('authorable_id');
                        $authorIds = $pivots->where('authorable_type', \App\Models\Author::class)->pluck('authorable_id');

                        $teachers = \App\Models\Teacher::whereIn('id', $teacherIds)->get()->keyBy('id');
                        $authors = \App\Models\Author::whereIn('id', $authorIds)->get()->keyBy('id');

                        return $pivots->map(function ($pivot) use ($teachers, $authors) {
                            $name = 'Unknown';
                            $details = '';

                            if ($pivot->authorable_type === \App\Models\Teacher::class) {
                                $model = $teachers->get($pivot->authorable_id);
                                if ($model) {
                                    $name = trim("{$model->first_name} {$model->middle_name} {$model->last_name}");
                                    $details = "ID: {$model->employee_id}";
                                    if ($model->phone) {
                                        $details .= " | PH: {$model->phone}";
                                    }
                                }
                            } elseif ($pivot->authorable_type === \App\Models\Author::class) {
                                $model = $authors->get($pivot->authorable_id);
                                if ($model) {
                                    $name = $model->name;
                                    $details = "Email: {$model->email}";
                                }
                            }

                            $role = $pivot->author_role;
                            $order = $pivot->sort_order;

                            $rolePriority = match ($role) {
                                'first' => 1,
                                'corresponding' => 2,
                                default => 3,
                            };

                            $roleLabel = match ($role) {
                                'first' => 'First Author',
                                'corresponding' => 'Corresponding',
                                'co_author' => 'Co-Author',
                                default => ucfirst($role),
                            };

                            $style = $role === 'first' ? 'font-weight: bold;' : '';

                            $incentiveAmount = (float) ($pivot->incentive_amount ?? 0);
                            $incentiveBadge = $incentiveAmount > 0 
                                ? " <span style='font-weight: 600; color: #059669;'>(৳" . number_format($incentiveAmount, 2) . ")</span>" 
                                : '';

                            return [
                                'priority' => sprintf('%d-%04d', $rolePriority, $order),
                                'html' => "
                                    <div style='margin-bottom: 4px;'>
                                        <span style='{$style}'>{$name}</span>{$incentiveBadge}
                                        <span class='text-gray-500 text-xs'>({$roleLabel})</span>
                                        <div class='text-xs text-gray-400'>{$details}</div>
                                    </div>
                                "
                            ];
                        })->sortBy('priority')->pluck('html')->implode('');
                    })
                    ->html()
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                         return $query->whereHas('publication', function ($pq) use ($search) {
                             $pq->whereHas('teachers', function ($sq) use ($search) {
                                 $sq->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('middle_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('employee_id', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                             })->orWhereHas('externalAuthors', function ($sq) use ($search) {
                                 $sq->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                             });
                         });
                    }),
                TextColumn::make('creator_name')
                    ->label('Created By')
                    ->state(fn($record) => $record->creator?->name ?? 'System Generated')
                    ->sortable(query: fn($query, $direction) => $query->orderBy('created_by', $direction))
                    ->toggleable(),
                TextColumn::make('approver_name')
                    ->label('Approved By')
                    ->state(function ($record) {
                        if ($record->approver) {
                            return $record->approver->name;
                        }
                        return match ($record->status) {
                            'approved', 'paid' => 'System Approved',
                            'pending' => 'Pending Approval',
                            default => '—',
                        };
                    })
                    ->description(fn($record) => $record->approved_at ? $record->approved_at->format('M d, Y h:i A') : null)
                    ->sortable(query: fn($query, $direction) => $query->orderBy('approved_by', $direction))
                    ->toggleable(),
                TextColumn::make('payer_name')
                    ->label('Paid By')
                    ->state(function ($record) {
                        if ($record->payer) {
                            return $record->payer->name;
                        }
                        return match ($record->status) {
                            'paid' => 'System Paid',
                            'approved' => 'Awaiting Payment',
                            'pending' => 'Not Paid Yet',
                            default => '—',
                        };
                    })
                    ->description(fn($record) => $record->paid_at ? $record->paid_at->format('M d, Y h:i A') : null)
                    ->sortable(query: fn($query, $direction) => $query->orderBy('paid_by', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make()
                    ->mutateRecordDataUsing(function (array $data, \App\Models\PublicationIncentive $record): array {
                        $publication = $record->publication;
                        if ($publication) {
                            $pivots = \DB::table('publication_authors')
                                ->where('publication_id', $publication->id)
                                ->get();

                            $teacherIds = $pivots->where('authorable_type', \App\Models\Teacher::class)->pluck('authorable_id');
                            $authorIds = $pivots->where('authorable_type', \App\Models\Author::class)->pluck('authorable_id');

                            $teachers = \App\Models\Teacher::whereIn('id', $teacherIds)->get()->keyBy('id');
                            $authors = \App\Models\Author::whereIn('id', $authorIds)->get()->keyBy('id');

                            $data['author_incentives'] = $pivots->map(function ($pivot) use ($teachers, $authors) {
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
                        }
                        return $data;
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('faculty_department')
                    ->form([
                        \Filament\Forms\Components\Select::make('faculty_id')
                            ->label('Faculty')
                            ->options(function () use ($adminRole) {
                                $query = \App\Models\Faculty::query();
                                if ($adminRole && $adminRole->pivot) {
                                     if ($adminRole->pivot->faculty_id) {
                                         $query->where('id', $adminRole->pivot->faculty_id);
                                     } elseif ($adminRole->pivot->department_id) {
                                         $department = \App\Models\Department::find($adminRole->pivot->department_id);
                                         if ($department) {
                                              $query->where('id', $department->faculty_id);
                                         }
                                     }
                                }
                                return $query->pluck('name', 'id');
                            })
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('department_id', null)),

                        \Filament\Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->options(function (Get $get) use ($adminRole) {
                                $query = \App\Models\Department::query();

                                // User Scoping
                                if ($adminRole && $adminRole->pivot) {
                                    if ($adminRole->pivot->department_id) {
                                        $query->where('id', $adminRole->pivot->department_id);
                                        return $query->pluck('name', 'id');
                                    } elseif ($adminRole->pivot->faculty_id) {
                                        $query->where('faculty_id', $adminRole->pivot->faculty_id);
                                    }
                                }

                                // Dependency Logic
                                $selectedFacultyId = $get('faculty_id');
                                if ($selectedFacultyId) {
                                    $query->where('faculty_id', $selectedFacultyId);
                                }

                                return $query->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function ($query, array $data) {
                        $user = auth()->user();
                        $adminRole = null;

                        if ($user && ! $user->hasRole('super_admin')) {
                            $adminRole = $user->administrativeRoles()
                                ->wherePivot('is_active', true)
                                ->whereNull('administrative_role_user.end_date')
                                ->first();
                        }

                        // Enforce scoped-admin restrictions
                        if ($adminRole && $adminRole->pivot) {
                            if ($adminRole->pivot->department_id) {
                                $data['department_id'] = $adminRole->pivot->department_id;
                            } elseif ($adminRole->pivot->faculty_id) {
                                $data['faculty_id'] = $adminRole->pivot->faculty_id;
                            }
                        }

                        return $query
                            ->when(
                                $data['faculty_id'] ?? null,
                                fn ($query, $id) => $query->whereHas('publication.department', fn ($q) => $q->where('faculty_id', $id))
                            )
                            ->when(
                                $data['department_id'] ?? null,
                                fn ($query, $id) => $query->whereHas('publication', fn ($q) => $q->where('department_id', $id))
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (!empty($data['faculty_id'])) {
                            $faculty = \App\Models\Faculty::find($data['faculty_id']);
                            if ($faculty) {
                                $indicators['faculty_id'] = 'Faculty: ' . $faculty->name;
                            }
                        }

                        if (!empty($data['department_id'])) {
                            $department = \App\Models\Department::find($data['department_id']);
                            if ($department) {
                                $indicators['department_id'] = 'Department: ' . $department->name;
                            }
                        }

                        return $indicators;
                    }),


                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label('Incentive Status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'paid' => 'Paid',
                    ])
                    ->multiple(),

                \Filament\Tables\Filters\Filter::make('publication_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('date_from'),
                        \Filament\Forms\Components\DatePicker::make('date_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn ($query, $date) => $query->whereHas('publication', fn($q) => $q->whereDate('publication_date', '>=', $date)),
                            )
                            ->when(
                                $data['date_until'],
                                fn ($query, $date) => $query->whereHas('publication', fn($q) => $q->whereDate('publication_date', '<=', $date)),
                            );
                    })
            ],layout: FiltersLayout::Modal)
            ->filtersTriggerAction(function ($action) {
                return $action->slideOver();
            })
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

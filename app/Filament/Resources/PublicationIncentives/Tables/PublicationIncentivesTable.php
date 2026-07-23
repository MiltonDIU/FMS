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
                TextColumn::make('authors_count')
                    ->label('Authors')
                    ->state(fn($record) => $record->publication->teachers()->count()),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make()
                    ->mutateRecordDataUsing(function (array $data, \App\Models\PublicationIncentive $record): array {
                        $data['author_incentives'] = $record->publication->teachers
                            ->sortBy('pivot.sort_order')
                            ->map(fn($t) => [
                                'teacher_id' => $t->id,
                                'author_role' => $t->pivot->author_role,
                                'incentive_amount' => $t->pivot->incentive_amount ?? 0,
                            ])->toArray();
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

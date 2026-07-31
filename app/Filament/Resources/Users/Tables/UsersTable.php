<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ReplicateBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(', ')
                    ->sortable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $direction): \Illuminate\Database\Eloquent\Builder {
                        return $query->orderBy(
                            \Spatie\Permission\Models\Role::select('roles.name')
                                ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
                                ->whereColumn('model_has_roles.model_id', 'users.id')
                                ->where('model_has_roles.model_type', \App\Models\User::class)
                                ->limit(1),
                            $direction
                        );
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->sortable()
                    ->dateTime()
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->sortable()
                    // is_active is the first thing canAccessPanel() checks, so
                    // this switch reinstates or locks out an account. Inline
                    // columns skip policies; only disabled() is honoured.
                    ->disabled(fn (User $record): bool => ! auth()->user()?->can('update', $record)),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->filters([
                TrashedFilter::make(),
                \Filament\Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->label('Filter by Role'),
            ],layout: FiltersLayout::Modal)
            ->filtersTriggerAction(function ($action) {
        return $action->slideOver();
    })
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
                ReplicateAction::make()
                    ->form([
                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->unique('users', 'email')
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->maxLength(255),
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            // Same restriction as the edit form: replicating a
                            // user must not become a way to mint a role you do
                            // not hold. The default is intersected too, so
                            // copying a super_admin does not carry the role over.
                            ->options(fn () => \Spatie\Permission\Models\Role::query()
                                ->whereIn('name', auth()->user()?->assignableRoleNames() ?? [])
                                ->pluck('name', 'id'))
                            ->default(fn ($record) => $record->roles
                                ->whereIn('name', auth()->user()?->assignableRoleNames() ?? [])
                                ->pluck('id')
                                ->toArray())
                            ->searchable()
                            ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                if (! auth()->user()?->canAssignRoleIds(\Illuminate\Support\Arr::wrap($value))) {
                                    $fail('You can only assign roles you hold yourself.');
                                }
                            }),
                    ])
                    ->using(function ($record, array $data): \App\Models\User {
                        // Create a replica
                        $replica = $record->replicate();

                        // Set form data
                        $replica->email = $data['email'];
                        $replica->password = bcrypt($data['password']);

                        // Save first
                        $replica->save();

                        // Sync roles
                        if (!empty($data['roles'])) {
                            $replica->roles()->sync($data['roles']);
                        }

                        return $replica;
                    }),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),

                ]),
            ]);
    }
}

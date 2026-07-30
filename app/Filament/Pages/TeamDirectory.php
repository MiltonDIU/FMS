<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use UnitEnum;

class TeamDirectory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static UnitEnum|string|null $navigationGroup = 'User Management';
    protected static ?string $navigationLabel = 'Team Directory';
    protected static ?string $title = 'Team Directory';
    protected static ?string $slug = 'team-directory';
    protected string $view = 'filament.pages.team-directory';
    protected static ?int $navigationSort = 3;

    /**
     * Only super_admin bypasses permission checks automatically.
     * All other roles (including admin) require explicit permission assigned via Roles & Permissions.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Only super_admin automatically bypasses permission checks
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Check assigned permissions for admin and all other roles
        return $user->can('page_TeamDirectory')
            || $user->can('ViewAny:TeamDirectory')
            || $user->can('View:TeamDirectory')
            || $user->can('view_team_directory');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        $query = User::query()->with(['roles', 'teacher']);
        $user = auth()->user();

        // Non-Super Admins strictly see users matching their own role(s)
        if ($user && ! $user->hasRole('super_admin')) {
            $userRoleNames = $user->roles->pluck('name')->toArray();
            if (! empty($userRoleNames)) {
                $query->whereHas('roles', function ($rq) use ($userRoleNames) {
                    $rq->whereIn('name', $userRoleNames);
                });
            }
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-o-envelope'),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->separator(', ')
                    ->color('info')
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Filter Role')
                    ->options(function () {
                        $user = auth()->user();

                        // Only super_admin sees all roles in dropdown automatically
                        if ($user && $user->hasRole('super_admin')) {
                            return Role::all()
                                ->pluck('name', 'name')
                                ->map(fn (string $name): string => ucfirst(str_replace('_', ' ', $name)))
                                ->toArray();
                        }

                        // All other roles see only their own role(s)
                        if ($user) {
                            return $user->roles
                                ->pluck('name', 'name')
                                ->map(fn (string $name): string => ucfirst(str_replace('_', ' ', $name)))
                                ->toArray();
                        }

                        return [];
                    })
                    ->query(function ($query, array $data) {
                        if (! empty($data['value'])) {
                            $query->whereHas('roles', function ($rq) use ($data) {
                                $rq->where('name', $data['value']);
                            });
                        }
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}

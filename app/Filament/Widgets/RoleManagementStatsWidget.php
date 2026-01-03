<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Permission\Models\Role;
use Filament\Tables\Columns\TextColumn;

class RoleManagementStatsWidget extends BaseWidget
{
    protected static ?string $heading = 'Role Management Statistics';

    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Role::query()->withCount(['users', 'permissions'])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Role Name')
                    ->sortable()
                    ->searchable()
                    ->badge(),

                TextColumn::make('users_count')
                    ->label('Assigned Users')
                    ->counts('users') // Optional if withCount is used, usually label is enough if query has it. 
                    // To be safe with Filament's auto logic:
                    ->state(fn (Role $record) => $record->users_count)
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('permissions_count')
                    ->label('Allocated Permissions')
                    ->state(fn (Role $record) => $record->permissions_count)
                    ->sortable()
                    ->alignCenter()
                    ->color('gray'),
            ])
            ->paginated(false);
    }
}

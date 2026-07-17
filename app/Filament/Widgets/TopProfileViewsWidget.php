<?php

namespace App\Filament\Widgets;

use App\Models\Teacher;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopProfileViewsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Profile Views';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Teacher::query()
                    ->where('is_active', true)
                    ->where('is_archived', false)
                    ->orderByDesc('views_count')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Teacher')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('designation.name')
                    ->label('Designation'),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department'),
                Tables\Columns\TextColumn::make('views_count')
                    ->label('Views')
                    ->sortable()
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('last_viewed_at')
                    ->label('Last Viewed')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('views_count', 'desc')
            ->paginated(false);
    }
}

<?php

namespace App\Filament\Resources\Positions;

use App\Filament\Resources\Positions\Pages\ManagePositions;
use App\Models\Position;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;
    
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\Positions\Schemas\PositionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\Positions\Tables\PositionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->addSelect([
            'teachers_count' => DB::table('job_experiences')
                ->selectRaw('COUNT(DISTINCT job_experiences.teacher_id)')
                ->join('teachers', 'job_experiences.teacher_id', '=', 'teachers.id')
                ->whereColumn('job_experiences.position_id', 'positions.id')
                ->whereNull('teachers.deleted_at')
                ->where('teachers.is_archived', false),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePositions::route('/'),
        ];
    }
}

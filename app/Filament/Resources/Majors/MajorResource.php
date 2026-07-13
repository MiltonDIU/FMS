<?php

namespace App\Filament\Resources\Majors;

use App\Filament\Resources\Majors\Pages\ManageMajors;
use App\Models\Major;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class MajorResource extends Resource
{
    protected static ?string $model = Major::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;
    
    protected static UnitEnum|string|null $navigationGroup = 'Academic Lookups';
    
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\Majors\Schemas\MajorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\Majors\Tables\MajorsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->addSelect([
            'teachers_count' => DB::table('educations')
                ->selectRaw('COUNT(DISTINCT educations.teacher_id)')
                ->join('teachers', 'educations.teacher_id', '=', 'teachers.id')
                ->whereColumn('educations.major_id', 'majors.id')
                ->whereNull('teachers.deleted_at')
                ->where('teachers.is_archived', false),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMajors::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\ManageOrganizations;
use App\Models\Organization;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;
    
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\Organizations\Schemas\OrganizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\Organizations\Tables\OrganizationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->addSelect([
            'teachers_count' => DB::table('job_experiences')
                ->selectRaw('COUNT(DISTINCT job_experiences.teacher_id)')
                ->join('teachers', 'job_experiences.teacher_id', '=', 'teachers.id')
                ->whereColumn('job_experiences.organization_id', 'organizations.id')
                ->whereNull('teachers.deleted_at')
                ->where('teachers.is_archived', false),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrganizations::route('/'),
        ];
    }
}

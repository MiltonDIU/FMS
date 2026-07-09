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
            'teachers_count' => DB::table('teachers')
                ->selectRaw('COUNT(DISTINCT teachers.id)')
                ->whereNull('teachers.deleted_at')
                ->where('teachers.is_archived', false)
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from(function ($unionQuery) {
                            $unionQuery->select('teacher_id', 'educational_institution_id as org_id')->from('educations')
                                ->union(DB::table('job_experiences')->select('teacher_id', 'organization_id as org_id'))
                                ->union(DB::table('training_experiences')->select('teacher_id', 'organization_id as org_id'))
                                ->union(DB::table('memberships')->select('teacher_id', 'membership_organization_id as org_id'))
                                ->union(DB::table('awards')->select('teacher_id', 'awarding_body_organization_id as org_id'))
                                ->union(DB::table('certifications')->select('teacher_id', 'issuing_authority_organization_id as org_id'))
                                ->union(DB::table('research_projects')->select('teacher_id', 'funding_agency_organization_id as org_id'));
                        }, 'usages')
                        ->join('organizations as o', 'usages.org_id', '=', 'o.id')
                        ->whereColumn('usages.teacher_id', 'teachers.id')
                        ->where(function ($q) {
                            $q->whereColumn('o.id', 'organizations.id')
                              ->orWhereColumn('o.parent_id', 'organizations.id');
                        });
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrganizations::route('/'),
        ];
    }
}

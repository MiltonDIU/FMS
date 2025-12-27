<?php

namespace App\Filament\Resources\MembershipOrganizations;

use App\Filament\Resources\MembershipOrganizations\Pages\CreateMembershipOrganization;
use App\Filament\Resources\MembershipOrganizations\Pages\EditMembershipOrganization;
use App\Filament\Resources\MembershipOrganizations\Pages\ListMembershipOrganizations;
use App\Filament\Resources\MembershipOrganizations\Schemas\MembershipOrganizationForm;
use App\Filament\Resources\MembershipOrganizations\Tables\MembershipOrganizationsTable;
use App\Models\MembershipOrganization;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use BackedEnum;

class MembershipOrganizationResource extends Resource
{
    protected static ?string $model = MembershipOrganization::class;


    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;







    protected static ?string $recordTitleAttribute = 'name';


    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 11;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Membership Organizations';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Membership Organizations';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Membership Organization';



    public static function form(Schema $schema): Schema
    {
        return MembershipOrganizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipOrganizationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembershipOrganizations::route('/'),
            'create' => CreateMembershipOrganization::route('/create'),
            'edit' => EditMembershipOrganization::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

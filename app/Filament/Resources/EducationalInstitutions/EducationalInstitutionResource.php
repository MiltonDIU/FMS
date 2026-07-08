<?php

namespace App\Filament\Resources\EducationalInstitutions;

use App\Filament\Resources\EducationalInstitutions\Pages\ManageEducationalInstitutions;
use App\Models\EducationalInstitution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EducationalInstitutionResource extends Resource
{
    protected static ?string $model = EducationalInstitution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;
    
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\EducationalInstitutions\Schemas\EducationalInstitutionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\EducationalInstitutions\Tables\EducationalInstitutionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEducationalInstitutions::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\EmploymentStatuses;

use App\Filament\Resources\EmploymentStatuses\Pages;
use App\Filament\Resources\EmploymentStatuses\Schemas\EmploymentStatusForm;
use App\Filament\Resources\EmploymentStatuses\Tables\EmploymentStatusesTable;
use App\Models\EmploymentStatus;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
class EmploymentStatusResource extends Resource
{
    protected static ?string $model = EmploymentStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Faculty Settings';
    protected static ?int $navigationSort = 1;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Employment Statuses';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Employment Statuses';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Employment Status';



    public static function form(Schema $schema): Schema
    {
        return EmploymentStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmploymentStatusesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmploymentStatuses::route('/'),
            'create' => Pages\CreateEmploymentStatus::route('/create'),
            'edit' => Pages\EditEmploymentStatus::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\JobTypes;

use App\Filament\Resources\JobTypes\Pages;
use App\Filament\Resources\JobTypes\Schemas\JobTypeForm;
use App\Filament\Resources\JobTypes\Tables\JobTypesTable;
use App\Models\JobType;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;
class JobTypeResource extends Resource
{
    protected static ?string $model = JobType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Faculty Settings';
    protected static ?int $navigationSort = 2;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Job Types';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Job Types';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Job Type';



    public static function form(Schema $schema): Schema
    {
        return JobTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobTypes::route('/'),
            'create' => Pages\CreateJobType::route('/create'),
            'edit' => Pages\EditJobType::route('/{record}/edit'),
        ];
    }
}

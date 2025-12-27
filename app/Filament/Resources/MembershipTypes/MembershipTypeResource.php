<?php

namespace App\Filament\Resources\MembershipTypes;

use App\Filament\Resources\MembershipTypes\Pages\ManageMembershipTypes;
use App\Filament\Resources\MembershipTypes\Tables\MembershipTypesTable;
use App\Models\MembershipType;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;
class MembershipTypeResource extends Resource
{
    protected static ?string $model = MembershipType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    // Navigation Group - UnitEnum|string|null type
    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 1;
    // Navigation Label (ঐচ্ছিক)
    protected static ?string $navigationLabel = 'Membership Types';

    // Plural Label (ঐচ্ছিক)
    protected static ?string $pluralLabel = 'Membership Types';

    // Model Label (ঐচ্ছিক)
    protected static ?string $modelLabel = 'Membership Type';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->rows(2),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Used for ordering in dropdowns'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Only active types appear in dropdowns'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return MembershipTypesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMembershipTypes::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\DegreeTypes;

use App\Filament\Resources\DegreeTypes\Pages\ManageDegreeTypes;
use App\Models\DegreeType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DegreeTypeResource extends Resource
{
    protected static ?string $model = DegreeType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static \UnitEnum|string|null $navigationGroup = 'Academic Lookups';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('degree_level_id')
                    ->label('Degree Level')
                    ->relationship('level', 'name')
                    ->required(),
                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(20),
                TextInput::make('name')
                    ->label('Degree Name')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('level.name')
                    ->label('Level')
                    ->sortable(),
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->filters([
                 \Filament\Tables\Filters\SelectFilter::make('degree_level_id')
                    ->relationship('level', 'name')
                    ->label('Level'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDegreeTypes::route('/'),
        ];
    }
}

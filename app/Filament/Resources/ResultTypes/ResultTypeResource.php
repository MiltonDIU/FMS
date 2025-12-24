<?php

namespace App\Filament\Resources\ResultTypes;

use App\Filament\Resources\ResultTypes\Pages\ManageResultTypes;
use App\Models\ResultType;
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

class ResultTypeResource extends Resource
{
    protected static ?string $model = ResultType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static \UnitEnum|string|null $navigationGroup = 'Academic Lookups';

    protected static ?string $recordTitleAttribute = 'type_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type_name')
                    ->label('Type Name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                \Filament\Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type_name')
            ->columns([
                TextColumn::make('type_name')
                    ->label('Type Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50),
            ])
            ->filters([
                //
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
            'index' => ManageResultTypes::route('/'),
        ];
    }
}

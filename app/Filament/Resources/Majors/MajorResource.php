<?php

namespace App\Filament\Resources\Majors;

use App\Filament\Resources\Majors\Pages\ManageMajors;
use App\Models\Major;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class MajorResource extends Resource
{
    protected static ?string $model = Major::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;
    
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique('majors', 'name', ignoreRecord: true)
                    ->maxLength(255)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Is Active')
                    ->default(false),
                Select::make('created_by')
                    ->label('Created By Teacher')
                    ->relationship('creator', 'full_name')
                    ->searchable()
                    ->placeholder('System / Bulk Imported'),
                Select::make('approved_by')
                    ->label('Approved By User')
                    ->relationship('approver', 'name')
                    ->searchable()
                    ->placeholder('System / Auto Approved'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('creator.full_name')
                    ->label('Created By')
                    ->placeholder('System')
                    ->sortable(),
                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('System')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => ManageMajors::route('/'),
        ];
    }
}

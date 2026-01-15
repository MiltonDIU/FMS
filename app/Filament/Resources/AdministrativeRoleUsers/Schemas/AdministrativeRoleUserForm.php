<?php

namespace App\Filament\Resources\AdministrativeRoleUsers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AdministrativeRoleUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Select::make('user_id')
                        ->label('User')
                        ->relationship('user', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('administrative_role_id')
                        ->label('Administrative Role')
                        ->relationship('administrativeRole', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('department_id')
                        ->label('Department')
                        ->relationship('department', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    Select::make('faculty_id')
                        ->label('Faculty')
                        ->relationship('faculty', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    DatePicker::make('start_date')
                        ->label('Start Date')
                        ->required()
                        ->default(now()),

                    DatePicker::make('end_date')
                        ->label('End Date')
                        ->nullable(),

                    Toggle::make('is_acting')
                        ->label('Acting Role')
                        ->default(false),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Textarea::make('remarks')
                        ->label('Remarks')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            ]);
    }
}

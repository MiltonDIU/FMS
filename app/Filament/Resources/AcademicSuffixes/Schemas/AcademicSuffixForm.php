<?php

namespace App\Filament\Resources\AcademicSuffixes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AcademicSuffixForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->columnSpanFull()->schema([
                    TextInput::make('name')
                        ->label('Suffix')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('One qualification, written as it should appear after the name — "PhD", not "Ph.D" or "PhD.". Somebody holding two gets both, so add them as separate rows rather than one "PhD, MBA".')
                        ->columnSpanFull(),

                    TextInput::make('sort_order')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Where it sits in the dropdown. The order a teacher writes their own qualifications in is kept on their profile, so this only arranges the list.'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Switching this off keeps it on the profiles that already use it and stops it being offered for new ones.'),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\NamePrefixes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class NamePrefixForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->columnSpanFull()->schema([
                    TextInput::make('name')
                        ->label('Prefix')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Written exactly as it should appear before the name, punctuation and all — "Professor Dr.", not "professor dr". One row holds the whole stack, so "Professor Dr. Engr." is a single entry.')
                        ->columnSpanFull(),

                    TextInput::make('sort_order')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Where it sits in the dropdown. The list is ordered by standing rather than by how many people hold it, so a professor is not scrolling past "Mr." to find their own title.'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Switching this off keeps it on the profiles that already use it and stops it being offered for new ones.'),
                ]),
            ]);
    }
}

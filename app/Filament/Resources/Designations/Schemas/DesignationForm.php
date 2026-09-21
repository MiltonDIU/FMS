<?php

namespace App\Filament\Resources\Designations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DesignationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('erp_id')
                    ->numeric(),
                /*
                 * Unique because a second "Lecturer" row is indistinguishable
                 * from the first to everything that resolves a designation by
                 * name — the HR sync among them, which would then file people
                 * under whichever row the database happened to return. Enforced
                 * here rather than as a database constraint: the table soft
                 * deletes, and a unique index would also refuse to let a
                 * deleted name be created again.
                 */
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('short_name'),
                TextInput::make('rank')
                    ->required()
                    ->numeric()
                    ->default(0),
                /*
                 * Off for the rows that are not academic grades — Adjunct
                 * Faculty, and the unassigned placeholder. Those are kept
                 * because designation_id cannot be null, not because they name
                 * a rank, and a teacher holding one is shown by their job type
                 * and left out of the public designation filter.
                 */
                Toggle::make('is_rank')
                    ->label('Is an academic rank')
                    ->helperText('Turn off for rows that are not a grade the university awards, such as Adjunct Faculty. Those teachers are shown by their job type instead.')
                    ->default(true),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(fn () => (\App\Models\Designation::max('sort_order') ?? 0) + 1),
            ]);
    }
}

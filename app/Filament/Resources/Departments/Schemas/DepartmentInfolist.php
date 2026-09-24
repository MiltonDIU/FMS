<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Models\Department;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * One department at the top of its own page, with the two lists of its
 * teachers underneath as relation managers.
 *
 * The counts split the people the same way the lists below do, because a
 * department is taught by two kinds of teacher: those whose home department
 * it is, and those whose home is elsewhere but who are assigned to teach here.
 * One total hides which is which.
 */
class DepartmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Department')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')
                        ->label('Department Name')
                        ->columnSpan(2),

                    TextEntry::make('faculty.name')
                        ->label('Faculty')
                        ->badge()
                        ->color('info'),

                    TextEntry::make('short_name')
                        ->label('Short')
                        ->badge()
                        ->placeholder('—'),

                    TextEntry::make('code')
                        ->badge()
                        ->color('gray'),

                    IconEntry::make('is_active')
                        ->label('Active')
                        ->boolean(),

                    TextEntry::make('erp_id')
                        ->label('ERP ID')
                        ->placeholder('—'),

                    TextEntry::make('sort_order')
                        ->label('Sort Order'),

                    TextEntry::make('description')
                        ->columnSpanFull()
                        ->placeholder('No description'),
                ]),

            Section::make('Teachers')
                ->columns(3)
                ->schema([
                    TextEntry::make('own_teachers')
                        ->label('Teachers')
                        ->badge()
                        ->color('success')
                        ->state(fn (Department $record): int => $record->teachers()->count())
                        ->helperText('Home department is this one.'),

                    TextEntry::make('assigned_teachers')
                        ->label('Teachers Via Assignment')
                        ->badge()
                        ->color('warning')
                        ->state(fn (Department $record): int => self::assigned($record))
                        ->helperText('Home department is another one; assigned to teach here.'),

                    TextEntry::make('publications_count')
                        ->label('Publications')
                        ->badge()
                        ->color('info')
                        ->state(fn (Department $record): int => $record->publications()->count()),
                ]),
        ]);
    }

    /**
     * Assigned here but belonging somewhere else — the same rows the
     * Teachers Via Assignment list shows, and the "Guest" figure on the index.
     */
    protected static function assigned(Department $record): int
    {
        return $record->teachersViaAssignment()
            ->where(fn ($q) => $q->where('teachers.department_id', '!=', $record->id)
                ->orWhereNull('teachers.department_id'))
            ->count();
    }
}

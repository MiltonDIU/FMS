<?php

namespace App\Filament\Resources\TeacherVersions\Schemas;

use App\Filament\Resources\TeacherVersions\Tables\TeacherVersionsTable;
use App\Models\TeacherVersion;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TeacherVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Summary')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('teacher_name')
                            ->label('Teacher')
                            ->state(fn (TeacherVersion $record): ?string => $record->teacher?->display_name),
                        TextEntry::make('version_number')
                            ->label('Version'),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Str::headline($state))
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'partially_approved' => 'info',
                                'approved', 'completed' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('submittedBy.name')
                            ->label('Submitted by')
                            ->placeholder('System'),
                        TextEntry::make('submitted_at')
                            ->label('Submitted at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('sections')
                            ->label('Sections changed')
                            ->state(fn (TeacherVersion $record): array => collect(TeacherVersionsTable::sectionsOf($record))
                                ->map(fn (string $section): string => Str::headline($section))
                                ->all())
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('reviewedBy.name')
                            ->label('Reviewed by')
                            ->placeholder('Not reviewed yet'),
                        TextEntry::make('reviewed_at')
                            ->label('Reviewed at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('review_remarks')
                            ->label('Remarks')
                            ->placeholder('—'),
                        TextEntry::make('restore_point')
                            ->label('Restore point')
                            ->state(fn (TeacherVersion $record): string => $record->isRestorable()
                                ? 'Yes — the profile can be rolled back to this version'
                                : 'No — this version records only the sections it changed')
                            ->badge()
                            ->color(fn (TeacherVersion $record): string => $record->isRestorable() ? 'info' : 'gray'),
                        TextEntry::make('section_remarks_list')
                            ->label('Remarks by section')
                            ->state(fn (TeacherVersion $record): array => collect($record->section_remarks ?? [])
                                ->map(fn ($remark, $section): string => Str::headline($section) . ': ' . $remark)
                                ->values()
                                ->all())
                            ->listWithLineBreaks()
                            ->visible(fn (TeacherVersion $record): bool => ! empty($record->section_remarks))
                            ->columnSpanFull(),
                    ]),

                Section::make('Changes')
                    ->description('Each section as it was before this change, beside the change itself.')
                    ->columnSpanFull()
                    ->visible(fn (TeacherVersion $record): bool => TeacherVersionsTable::sectionsOf($record) !== [])
                    ->schema(fn (TeacherVersion $record): array => TeacherVersionsTable::getComparisonFormSchema(
                        $record,
                        TeacherVersionsTable::sectionsOf($record),
                        false,
                    )),

                // For a restore point: the whole profile it holds, beside the
                // profile now — what a rollback to it would change.
                Section::make('Full profile at this version')
                    ->description('Everything this version holds, beside the profile as it is now. A rollback restores all of it except Publications, which the research team manages. Sections that match are folded.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (TeacherVersion $record): bool => $record->isRestorable())
                    ->schema(fn (TeacherVersion $record): array => TeacherVersionsTable::getFullProfileSchema($record)),
            ]);
    }
}

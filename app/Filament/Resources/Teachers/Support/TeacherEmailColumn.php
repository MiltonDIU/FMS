<?php

namespace App\Filament\Resources\Teachers\Support;

use App\Models\Teacher;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A teacher's two email addresses in one optional column.
 *
 * The account address (users.email) is the one the system mails and signs in
 * with, so it comes first; teachers.secondary_email sits underneath. Either
 * may be missing — plenty of imported teachers have no secondary address and a
 * few have no account — and the column then shows what there is, or a dash.
 *
 * Hidden until switched on from the column menu, since most visits to these
 * lists are not about email. Shared by the teachers list and the department
 * teachers list; $relation is how a row reaches its teacher ('' when the row
 * is the teacher, 'teacher' for a department assignment).
 */
class TeacherEmailColumn
{
    public static function make(string $relation = ''): TextColumn
    {
        $teacherOf = fn (Model $record): ?Teacher => $relation === '' ? $record : $record->{$relation};

        $addresses = function (Model $record) use ($teacherOf): array {
            $teacher = $teacherOf($record);

            $primary = trim((string) $teacher?->user?->email);
            $secondary = trim((string) $teacher?->secondary_email);

            // The same address in both places is shown once.
            if ($secondary !== '' && strcasecmp($secondary, $primary) === 0) {
                $secondary = '';
            }

            return [$primary, $secondary];
        };

        return TextColumn::make('email_addresses')
            ->label('Email')
            ->state(function (Model $record) use ($addresses): ?string {
                [$primary, $secondary] = $addresses($record);

                return $primary !== '' ? $primary : ($secondary !== '' ? $secondary : null);
            })
            ->description(function (Model $record) use ($addresses): ?string {
                [$primary, $secondary] = $addresses($record);

                return $primary !== '' && $secondary !== '' ? 'Secondary: ' . $secondary : null;
            })
            ->placeholder('—')
            ->copyable()
            ->searchable(query: function (Builder $query, string $search) use ($relation): Builder {
                $match = fn (Builder $teacher): Builder => $teacher
                    ->where('teachers.secondary_email', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', "%{$search}%"));

                return $relation === ''
                    ? $query->where($match)
                    : $query->whereHas($relation, $match);
            })
            ->toggleable(isToggledHiddenByDefault: true);
    }
}

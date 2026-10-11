<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\Resources\Publications\Schemas\PublicationForm;
use App\Models\Publication;
use App\Models\Teacher;
use App\Models\User;
use App\Notifications\PublicationAuthorshipAnswered;
use App\Notifications\PublicationAuthorshipRequested;
use Illuminate\Support\Facades\DB;

/**
 * Writes a publication's author list, and keeps track of who agreed to be on it.
 *
 * Every form that names authors comes through here: the publications repeater
 * on the teacher form (saved directly or when TeacherVersionService applies an
 * approved version) and the publication create and edit pages. There used to
 * be three copies of this, and a shorter fourth in the service that treated
 * "App\Models\Teacher:1704" as a plain id, dropped external authors, and synced
 * away every author's affiliation and incentive amount.
 *
 * Consent. A teacher naming another teacher puts them on the paper as
 * `pending`: it does not reach that colleague's profile, counts or incentives
 * until they confirm it from their dashboard. Otherwise anybody could attach a
 * colleague's name to a paper they never wrote. Nobody else needs asking — the
 * teacher themselves, an external author (who has no account to answer from),
 * and anything written by an administrator or the research team are confirmed
 * as written. A refusal is kept as `declined`, through every later save, so
 * the same person cannot simply be named again.
 */
final class PublicationAuthorship
{
    public const CONFIRMED = 'confirmed';

    public const PENDING = 'pending';

    public const DECLINED = 'declined';

    /** Roles whose naming of an author is taken as it stands. */
    public const TRUSTED_ROLES = ['super_admin', 'admin', 'research_team'];

    /**
     * The publications repeater on a teacher's profile.
     *
     * @param  array<string, mixed>  $item  first_author_id, corresponding_author_id
     *                                     and co_author_ids, each a "Model:id" key
     * @param  Teacher  $teacher  whose profile this row belongs to; they stay an
     *                            author even when the row does not name them
     * @param  User|null  $actor  who made the change — the submitter when a version
     *                           is approved, not the approver
     */
    public static function write(Publication $publication, array $item, Teacher $teacher, ?User $actor = null): void
    {
        self::sync($publication, $item, $teacher, $actor);
    }

    /**
     * Rebuild the author list from a form, keeping what each author already had.
     *
     * @param  array<string, mixed>  $item
     * @param  Teacher|null  $owner  a teacher who stays on the paper whatever the
     *                               form says (the profile it was edited from)
     */
    public static function sync(Publication $publication, array $item, ?Teacher $owner = null, ?User $actor = null): void
    {
        $actor ??= auth()->user();
        $trusted = self::trusts($actor);
        $self = array_filter([$owner?->id, $actor?->teacher?->id]);

        $carried = DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->get();

        $byRole = $carried->keyBy(fn ($row) => $row->authorable_type . ':' . $row->authorable_id . ':' . $row->author_role);

        DB::table('publication_authors')->where('publication_id', $publication->id)->delete();

        // What an author had on this paper before — affiliation, incentive,
        // whether they agreed to it — survives being written again, under the
        // same role if it can.
        $previous = function (string $model, $id, string $role) use ($byRole, $carried) {
            return $byRole->get("{$model}:{$id}:{$role}")
                ?? $carried->first(fn ($row) => $row->authorable_type === $model
                    && (string) $row->authorable_id === (string) $id);
        };

        $inserted = [];
        $newlyPending = [];

        $insertAuthor = function (string $model, $id, string $role, int $sortOrder) use ($publication, $previous, $trusted, $self, $actor, &$inserted, &$newlyPending) {
            $was = $previous($model, $id, $role);

            $status = $was->confirmation_status
                ?? (self::needsConsent($model, $id, $trusted, $self) ? self::PENDING : self::CONFIRMED);

            DB::table('publication_authors')->insert([
                'publication_id' => $publication->id,
                'authorable_type' => $model,
                'authorable_id' => $id,
                'author_role' => $role,
                'confirmation_status' => $status,
                'added_by' => $was ? $was->added_by : $actor?->id,
                'confirmed_at' => $was ? $was->confirmed_at : ($status === self::CONFIRMED ? now() : null),
                'sort_order' => $sortOrder,
                'affiliation' => $was->affiliation ?? null,
                'used_our_affiliation' => $was->used_our_affiliation ?? null,
                'incentive_amount' => $was->incentive_amount ?? 0.00,
                'created_at' => $was->created_at ?? now(),
                'updated_at' => now(),
            ]);

            $inserted["{$model}:{$id}"] = true;

            if (! $was && $status === self::PENDING) {
                $newlyPending[(int) $id] = true;
            }
        };

        foreach (['first_author_id' => 'first', 'corresponding_author_id' => 'corresponding'] as $field => $role) {
            [$model, $id] = PublicationForm::parseKey($item[$field] ?? null);
            if ($model && $id) {
                $insertAuthor($model, $id, $role, 0);
            }
        }

        foreach (is_array($item['co_author_ids'] ?? null) ? $item['co_author_ids'] : [] as $index => $coAuthorKey) {
            [$model, $id] = PublicationForm::parseKey($coAuthorKey);
            // Once per person: a co-author listed twice is one author, and two
            // rows for them leave the form unable to save the paper again.
            if ($model && $id && ! isset($inserted["{$model}:{$id}"])) {
                $insertAuthor($model, $id, 'co_author', $index + 1);
            }
        }

        if ($owner && ! isset($inserted[Teacher::class . ":{$owner->id}"])) {
            $insertAuthor(Teacher::class, $owner->id, 'co_author', 99);
        }

        // A refusal outlives the form. The forms do not show declined authors,
        // so a save leaves them out; put them back, unchanged, so that being
        // left off a form does not wipe the "no" and let them be named again.
        foreach ($carried->where('confirmation_status', self::DECLINED) as $row) {
            if (! isset($inserted[$row->authorable_type . ':' . $row->authorable_id])) {
                DB::table('publication_authors')->insert(array_diff_key((array) $row, ['id' => true]));
            }
        }

        if ($newlyPending !== []) {
            self::askForConsent($publication, array_keys($newlyPending), $actor);
        }
    }

    /** Whether this author must agree before the paper is credited to them. */
    private static function needsConsent(string $model, $id, bool $trusted, array $self): bool
    {
        return $model === Teacher::class
            && ! $trusted
            && ! in_array((int) $id, array_map('intval', $self), true);
    }

    public static function trusts(?User $actor): bool
    {
        return $actor !== null && $actor->hasRole(self::TRUSTED_ROLES);
    }

    /**
     * Confirm or decline being an author, for every role the teacher holds on it.
     *
     * @return int how many authorship rows changed
     */
    public static function answer(Publication $publication, Teacher $teacher, bool $accept): int
    {
        $rows = DB::table('publication_authors')
            ->where('publication_id', $publication->id)
            ->where('authorable_type', Teacher::class)
            ->where('authorable_id', $teacher->id)
            ->where('confirmation_status', self::PENDING);

        $askers = (clone $rows)->whereNotNull('added_by')->pluck('added_by')->unique();

        $changed = $rows->update([
            'confirmation_status' => $accept ? self::CONFIRMED : self::DECLINED,
            'confirmed_at' => $accept ? now() : null,
            'updated_at' => now(),
        ]);

        if ($changed > 0) {
            User::whereIn('id', $askers)->get()
                ->each(fn (User $asker) => $asker->notify(new PublicationAuthorshipAnswered($publication, $teacher, $accept)));
        }

        return $changed;
    }

    /**
     * Tell each newly named teacher, in the panel, that a paper is waiting for them.
     *
     * @param  array<int, int>  $teacherIds
     */
    private static function askForConsent(Publication $publication, array $teacherIds, ?User $actor): void
    {
        Teacher::with('user')->whereIn('id', $teacherIds)->get()
            ->each(fn (Teacher $teacher) => $teacher->user?->notify(new PublicationAuthorshipRequested($publication, $actor)));
    }
}

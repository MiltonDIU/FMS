<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Activity;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Records who gave or took away a role or a permission.
 *
 * Neither is a model change the activity log can see: both are rows in a pivot
 * table, written by attach, detach and sync, which fire no model events. And
 * Spatie's own events would say the wrong thing — syncPermissions() revokes
 * everything and grants it back, so editing one checkbox on a role would read
 * as every permission removed and re-added.
 *
 * So this compares the names before and after the change and records only the
 * difference, as one entry under the "access" log. Calls nest — syncRoles()
 * goes through removeRole() — and only the outermost one writes.
 */
final class AccessAudit
{
    public const LOG_NAME = 'access';

    private static int $depth = 0;

    /**
     * Run a change to $model's roles or permissions and record what it did.
     *
     * @template T
     *
     * @param  'roles'|'permissions'  $relation
     * @param  Closure(): T  $change
     * @return T
     */
    public static function watch(Model $model, string $relation, Closure $change): mixed
    {
        $before = self::$depth === 0 && Activity::recordsChangesHere() ? self::names($model, $relation) : null;

        self::$depth++;

        try {
            $result = $change();
        } finally {
            self::$depth--;
        }

        if ($before !== null) {
            self::record($model, $relation, $before);
        }

        return $result;
    }

    /** @return array<int, string> */
    public static function names(Model $model, string $relation): array
    {
        if (! $model->exists) {
            return [];
        }

        $query = $model->$relation();

        return $query->pluck($query->getRelated()->qualifyColumn('name'))->sort()->values()->all();
    }

    /**
     * Compare with what $model had before and record the difference.
     *
     * @param  array<int, string>  $before
     */
    public static function record(Model $model, string $relation, array $before): void
    {
        if (! Activity::recordsChangesHere()) {
            return;
        }

        $model->unsetRelation($relation);
        $after = self::names($model, $relation);

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added === [] && $removed === []) {
            return;
        }

        activity(self::LOG_NAME)
            ->performedOn($model)
            ->withProperties(array_filter(['added' => $added, 'removed' => $removed]))
            ->event($relation . '_changed')
            ->log($relation . ' changed · ' . ($model->getAttribute('name') ?? $model->getAttribute('email') ?? '#' . $model->getKey()));
    }
}

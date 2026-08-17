<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Turns the text an external system sends into the local row's id.
 *
 * The HR API sends `"gender": "Male"`, `"department_code": "ITM"`,
 * `"employment_status": "Active"`. The teachers table stores gender_id,
 * department_id, employment_status_id. Without this every such rule mapped a
 * word into an integer column and quietly stored nothing.
 *
 * Which table to search is inferred from the target column, so a mapping row
 * needs no extra configuration: `gender_id` looks in genders, `blood_group_id`
 * in blood_groups, `employment_status_id` in employment_statuses. A rule may
 * still name `lookup_model` explicitly when the column does not follow the
 * convention.
 */
class LookupResolver
{
    /**
     * Columns searched, in order of how much we trust them.
     *
     * A code is exact by nature — "ITM" is one department — so it is tried
     * before a name that several rows might resemble.
     */
    protected const MATCH_COLUMNS = ['code', 'erp_id', 'nationality', 'name', 'short_name', 'slug', 'title'];

    /**
     * Columns that look like foreign keys but must never be guessed at.
     *
     * user_id in particular: matching an account by a name off an external
     * system is how a profile gets attached to the wrong person.
     */
    protected const NEVER_INFER = ['user_id', 'teacher_id', 'created_by', 'updated_by'];

    /** @var array<string,int|null> resolved once per run */
    protected static array $memo = [];

    /**
     * Whether this target column wants an id we could look up.
     *
     * Ending in _id is not enough. employee_id and scopus_id end that way and
     * are plain values with no table behind them — treating them as foreign
     * keys cast "710000361" to an integer and would have erased an employee
     * number like "EMP-0071" outright. So the column only qualifies when a
     * model actually exists to search.
     */
    public static function handles(string $targetField): bool
    {
        if (! str_ends_with($targetField, '_id') || in_array($targetField, self::NEVER_INFER, true)) {
            return false;
        }

        return self::modelFor($targetField, null) !== null;
    }

    /**
     * The table a column would be looked up in, for the mapping screen to say
     * so out loud. Null when the value is stored as it arrives.
     */
    public static function tableFor(string $targetField, ?string $modelName = null): ?string
    {
        if (in_array($targetField, self::NEVER_INFER, true)) {
            return null;
        }

        $class = self::modelFor($targetField, $modelName);

        return $class ? (new $class())->getTable() : null;
    }

    /**
     * The columns of the table a value would be searched in, for the mapping
     * screen to offer as a choice.
     *
     * @return array<string,string>
     */
    public static function matchColumnOptions(string $targetField, ?string $modelName = null): array
    {
        $table = self::tableFor($targetField, $modelName);

        if ($table === null) {
            return [];
        }

        $skip = ['id', 'created_at', 'updated_at', 'deleted_at'];

        $columns = array_values(array_diff(Schema::getColumnListing($table), $skip));

        return array_combine($columns, $columns);
    }

    /**
     * Resolve a value for a foreign-key column.
     *
     * Returns the original value untouched when it is already an id, and null
     * when nothing matched — null meaning "leave this column alone" rather than
     * "store a wrong id".
     *
     * @param string|null $modelName explicit override from the mapping row
     * @param string|null $matchColumn the single column to search, when the
     *                                 mapping row names one
     */
    public static function resolve(
        mixed $value,
        string $targetField,
        ?string $modelName = null,
        ?string $matchColumn = null,
    ): mixed {
        // Already an id, or nothing to work with.
        if ($value === null || $value === '' || is_array($value) || is_bool($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $class = self::modelFor($targetField, $modelName);

        if ($class === null) {
            return null;
        }

        $needle = trim((string) $value);
        $key = $class . '|' . (string) $matchColumn . '|' . mb_strtolower($needle);

        return self::$memo[$key] ??= self::search($class, $needle, $matchColumn);
    }

    /**
     * The Eloquent model behind a foreign-key column.
     *
     * @return class-string|null
     */
    protected static function modelFor(string $targetField, ?string $modelName): ?string
    {
        $candidate = filled($modelName)
            ? Str::studly($modelName)
            : Str::studly(Str::beforeLast($targetField, '_id'));

        $class = "App\\Models\\{$candidate}";

        // The name is built from operator-editable data, so it has to resolve to
        // a real Eloquent model before anything is instantiated.
        return class_exists($class) && is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)
            ? $class
            : null;
    }

    /**
     * @param class-string $class
     */
    protected static function search(string $class, string $needle, ?string $matchColumn = null): ?int
    {
        /** @var \Illuminate\Database\Eloquent\Model $probe */
        $probe = new $class();
        $table = $probe->getTable();

        /*
         * A mapping row that names a column is obeyed: "match department_code
         * against departments.code" is knowledge the guesswork below cannot
         * have. Anything else falls back to trying the usual naming columns in
         * order of how exact they tend to be.
         */
        $columns = filled($matchColumn)
            ? [$matchColumn]
            : self::MATCH_COLUMNS;

        $columns = array_values(array_filter(
            $columns,
            fn ($column) => Schema::hasColumn($table, $column),
        ));

        if ($columns === []) {
            return null;
        }

        // Exact, column by column, most trusted first.
        foreach ($columns as $column) {
            $id = $class::query()->where($column, $needle)->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        // Then a contains match, for "Department of Information Technology &
        // Management (ITM)" against a row stored as the plainer name.
        foreach ($columns as $column) {
            $id = $class::query()->where($column, 'LIKE', '%' . $needle . '%')->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        // Last, the reverse: the stored value as a prefix of what arrived.
        // "Bangladeshi" finds the country stored as "Bangladesh", which is the
        // shape a nationality field always has.
        foreach ($columns as $column) {
            $id = $class::query()
                ->whereRaw("? LIKE CONCAT({$column}, '%')", [$needle])
                ->whereRaw("CHAR_LENGTH({$column}) >= 4")
                ->value('id');

            if ($id) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Drop the per-run cache. Used by tests and long-running imports.
     */
    public static function flush(): void
    {
        self::$memo = [];
    }
}

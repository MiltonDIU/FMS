<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * majors, positions and organizations: created_by becomes a users id, the
 * way publications.created_by already is.
 *
 * It held a teachers id, so a name added by anyone without a teacher profile
 * — an admin, the research team — recorded nobody. Each stored teacher id is
 * replaced with that teacher's user account; one with no account, or an
 * account that no longer exists, becomes null rather than pointing at the
 * wrong person. At the time of writing that was 13 rows (3 majors, 9
 * organizations, 1 position), every one of them with an account.
 *
 * Written for a database it has not seen. The foreign key is found by asking
 * MySQL rather than assumed by name, a table or column that is missing is
 * skipped, and running it twice does no harm: once the key points at users,
 * the table is left alone.
 */
return new class extends Migration
{
    protected const TABLES = ['majors', 'positions', 'organizations'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'created_by')) {
                continue;
            }

            if ($this->referencedTable($table) === 'users') {
                continue;
            }

            $this->dropForeignKey($table);

            DB::statement("UPDATE `{$table}` x LEFT JOIN `teachers` t ON t.id = x.created_by SET x.created_by = t.user_id WHERE x.created_by IS NOT NULL");
            $this->clearMissing($table, 'users');

            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'created_by') || $this->referencedTable($table) === 'teachers') {
                continue;
            }

            $this->dropForeignKey($table);

            // A user without a teacher profile — an admin — has no teachers id
            // to go back to, and becomes null, as it was before.
            DB::statement("UPDATE `{$table}` x LEFT JOIN `teachers` t ON t.user_id = x.created_by SET x.created_by = t.id WHERE x.created_by IS NOT NULL");
            $this->clearMissing($table, 'teachers');

            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `teachers` (`id`) ON DELETE SET NULL");
        }
    }

    /** The table created_by currently points at, or null when it has no key. */
    protected function referencedTable(string $table): ?string
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'created_by')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('REFERENCED_TABLE_NAME');
    }

    protected function dropForeignKey(string $table): void
    {
        $names = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'created_by')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($names as $name) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
        }
    }

    /** No value may point at a row that is not there, or the new key refuses. */
    protected function clearMissing(string $table, string $target): void
    {
        DB::statement("UPDATE `{$table}` x LEFT JOIN `{$target}` r ON r.id = x.created_by SET x.created_by = NULL WHERE x.created_by IS NOT NULL AND r.id IS NULL");
    }
};

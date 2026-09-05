<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the teachers the research directory knows about.
 *
 * The biography, the expertise areas and the scholarly profile links that
 * import:researcher-profiles brings in all come from one JSON file kept by the
 * Directorate of Research, and until now nothing on the teacher said so. That
 * matters because those profiles are the ones a second site will read back
 * through an API: without a flag, the only way to answer "is this teacher in
 * the research directory?" would be to re-read the file.
 *
 * A column rather than a lookup because it is one bit, and a writable one
 * rather than a record of what the import did: the import turns it on for
 * everyone it matches, and the teachers table screen can turn it on or off for
 * anyone else. Somebody who should be in the directory but is missing from the
 * file is then one click away, and somebody who should not be there can be
 * taken out without editing the file.
 *
 * Indexed because the API filters on it and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->boolean('is_researcher')
                ->default(false)
                ->after('is_public')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex(['is_researcher']);
            $table->dropColumn('is_researcher');
        });
    }
};

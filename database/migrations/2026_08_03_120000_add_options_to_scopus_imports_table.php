<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The matching rules a run was told to use.
 *
 * Kept with the run rather than read from configuration, so a workbook produced
 * last month can still say what produced it — and two runs of the same file can
 * be compared knowing which switch actually differed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scopus_imports', function (Blueprint $table) {
            $table->json('options')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('scopus_imports', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};

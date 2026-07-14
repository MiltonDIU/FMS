<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title')->index();
        });

        // Backfill slugs from existing titles (deduplicated per collision).
        $publications = DB::table('publications')->select('id', 'title', 'slug')->get();

        foreach ($publications as $pub) {
            $base = $pub->slug ?: Str::slug((string) $pub->title);

            if ($base === '') {
                $base = 'publication-' . $pub->id;
            }

            $slug = $base;
            $counter = 1;

            while (DB::table('publications')
                ->where('slug', $slug)
                ->where('id', '!=', $pub->id)
                ->exists()
            ) {
                $slug = $base . '-' . ++$counter;
            }

            DB::table('publications')->where('id', $pub->id)->update(['slug' => $slug]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
};

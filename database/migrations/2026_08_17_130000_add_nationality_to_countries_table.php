<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The demonym, so a nationality can be matched to a country directly.
 *
 * The HR API reports nationality — "Bangladeshi", "Japanese" — where the
 * countries table only held the country's own name. Matching relied on the
 * stored name being a prefix of the incoming word, which works for Bangladesh
 * and Japan but not for China ("Chinese"), the United States ("American") or
 * the United Kingdom ("British").
 *
 * Nullable: rows without a demonym fall back to the previous matching, so
 * nothing that resolved before stops resolving.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('nationality')->nullable()->after('name');
            $table->index('nationality');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropIndex(['nationality']);
            $table->dropColumn('nationality');
        });
    }
};

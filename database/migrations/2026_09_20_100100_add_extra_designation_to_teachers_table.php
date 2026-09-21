<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The standing title a teacher carries beside their rank.
 *
 * "Professor & Advisor", "Associate Professor & Director, M.Sc in CSE" — the
 * rank is in designation_id and this column holds the rest. It is display text
 * on purpose: there is no fixed list of programme directorships to look up, and
 * an administrative_roles assignment would move the holder into the
 * Administration group on the department page, which is not what these titles
 * mean.
 *
 * Nullable, and null for almost everybody: only the people whose old record
 * carried a compound title get one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('extra_designation')->nullable()->after('designation_id');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('extra_designation');
        });
    }
};

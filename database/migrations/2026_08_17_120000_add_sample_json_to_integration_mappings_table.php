<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the sample response a mapping was built from.
 *
 * No single employee exercises the whole payload — most have no awards, no
 * certifications, an empty skills list — so a mapping built from one profile is
 * always missing sections. Holding the sample lets an administrator collect
 * several people's responses into one complete document over more than one
 * sitting, and re-parse it later without calling the live API again.
 *
 * longText rather than json, and encrypted at the model: a real response
 * carries the employee's date of birth, home address, phone numbers and last
 * drawn salary, and this column will sit in every database dump taken from here
 * on. Encrypting it means a copy of the dump alone does not hand that over.
 * Ciphertext is not valid JSON, which is why the column cannot be a json type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_mappings', function (Blueprint $table) {
            $table->longText('sample_json')->nullable()->after('mapping_config');
        });
    }

    public function down(): void
    {
        Schema::table('integration_mappings', function (Blueprint $table) {
            $table->dropColumn('sample_json');
        });
    }
};

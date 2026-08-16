<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publication_authors', function (Blueprint $table) {
            $table->text('affiliation')->nullable()->after('author_role');
        });
    }

    public function down(): void
    {
        Schema::table('publication_authors', function (Blueprint $table) {
            $table->dropColumn('affiliation');
        });
    }
};

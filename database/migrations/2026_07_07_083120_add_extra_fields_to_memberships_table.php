<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add extra optional fields to memberships table.
     *
     * New columns:
     *
     *  record_type  — Distinguishes between a formal "membership" (e.g. Life Member, IEEE)
     *                 and a broader "affiliation" (e.g. Editorial Board Member, Conference Role,
     *                 Administrative Role). Allows the same table to serve both purposes
     *                 while keeping them filterable in the UI.
     *
     *  position     — The specific role or position within the organization
     *                 (e.g. "Vice President", "Editorial Board Member", "Country Representative").
     *                 Useful for affiliations where the type alone is not descriptive enough.
     *
     *  scope        — Geographic / reach scope of the organization:
     *                 local | national | international
     *                 Helps filter/display (e.g. show only international memberships on a public CV).
     *
     *  url          — Optional verification or profile link for the membership
     *                 (e.g. society profile page, digital member card URL).
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {

            // record_type: membership vs affiliation — placed after membership_type_id
            $table->enum('record_type', ['membership', 'affiliation'])
                  ->default('membership')
                  ->after('membership_type_id')
                  ->comment('membership = formal society/body membership; affiliation = board/role/conference/admin position');

            // position: specific role title within the org
            $table->string('position', 255)
                  ->nullable()
                  ->after('record_type')
                  ->comment('e.g. Vice President, Editorial Board Member, Country Representative');

            // scope: local / national / international
            $table->enum('scope', ['local', 'national', 'international'])
                  ->nullable()
                  ->after('position')
                  ->comment('Geographic scope of the organization');

            // url: verification / profile link
            $table->string('url', 500)
                  ->nullable()
                  ->after('scope')
                  ->comment('Optional verification URL or member profile link');

            // Index for common filter: record_type
            $table->index('record_type');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex(['record_type']);
            $table->dropColumn(['record_type', 'position', 'scope', 'url']);
        });
    }
};

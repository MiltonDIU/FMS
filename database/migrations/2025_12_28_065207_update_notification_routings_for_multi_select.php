<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_routings', function (Blueprint $table) {
            // Change trigger_section to JSON array for multiple sections
            $table->json('trigger_sections')->nullable()->after('trigger_type');
            
            // Change recipient_identifier to JSON array for multiple recipients
            $table->json('recipient_identifiers')->nullable()->after('recipient_type');
            
            // Drop old single-value columns
            $table->dropColumn(['trigger_section', 'recipient_identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_routings', function (Blueprint $table) {
            // Restore old columns
            $table->string('trigger_section')->nullable()->after('trigger_type');
            $table->string('recipient_identifier')->nullable()->after('recipient_type');
            
            // Drop new columns
            $table->dropColumn(['trigger_sections', 'recipient_identifiers']);
        });
    }
};

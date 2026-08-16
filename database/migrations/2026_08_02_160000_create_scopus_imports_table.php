<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per Scopus file somebody uploaded for review.
 *
 * The work is done in a queued job over a 4.7 MB export, so the request that
 * started it is long gone by the time there is anything to show. This table is
 * what the admin page reads: which files have been through, how they turned
 * out, and where the workbook to download is.
 *
 * It also means a run that fails leaves a record saying so, rather than a
 * notification that never arrives and no way to find out why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scopus_imports', function (Blueprint $table) {
            $table->id();

            $table->string('original_filename');
            $table->string('source_path');

            // uploaded -> processing -> ready | failed
            $table->string('status')->default('uploaded')->index();
            $table->text('failure_reason')->nullable();

            $table->string('result_path')->nullable();

            /*
             * The report the first step produces, kept whole rather than split
             * into columns: it is written once, read by a human, and the shape
             * of what is worth counting will change as the review does.
             */
            $table->json('summary')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scopus_imports');
    }
};

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
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();

            // ERP Aligned Fields
            $table->enum('type', ['journal', 'conference', 'book', 'book_chapter', 'thesis', 'other'])->default('journal');
            $table->string('title');
            $table->text('authors');
            $table->string('journal_name')->nullable();
            $table->string('publisher')->nullable();
            $table->string('indexed_by')->nullable();
            $table->string('doi')->nullable();
            $table->string('url')->nullable();
            $table->string('volume')->nullable();
            $table->string('issue')->nullable();
            $table->string('pages')->nullable();
            $table->year('publication_year')->nullable();
            $table->string('country')->nullable();
            $table->text('keywords')->nullable();
            $table->text('abstract')->nullable();
            $table->boolean('is_international')->default(false);

            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['teacher_id', 'type']);
            $table->index('publication_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};

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
            
            // Lookups
            $table->foreignId('publication_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('publication_linkage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('publication_quartile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grant_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('research_collaboration_id')->nullable()->constrained()->nullOnDelete();

            // Core Info
            $table->string('title');
            $table->text('abstract')->nullable();
            $table->text('keywords')->nullable();
            $table->text('research_area')->nullable();
            
            // Journal/Conference Info
            $table->string('journal_name')->nullable();
            $table->string('journal_link')->nullable();
            $table->date('publication_date')->nullable();
            $table->year('publication_year')->nullable();
            
            // Metrics
            $table->string('h_index')->nullable();
            $table->decimal('citescore', 8, 2)->nullable();
            $table->decimal('impact_factor', 8, 2)->nullable();
            
            // Flags/Status
            $table->boolean('student_involvement')->default(false);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('publication_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            
            // Polymorphic relation to handle Teacher, Student, Admin, External, etc.
            $table->morphs('authorable'); // Creates authorable_id and authorable_type
            
            $table->enum('author_role', ['first', 'corresponding', 'co_author'])->default('co_author');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_authors');
        Schema::dropIfExists('publications');
    }
};

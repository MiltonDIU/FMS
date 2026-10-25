<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback from the people using the system, and the conversation about it.
 *
 * A teacher reports a problem or asks for something; the team answers, moves
 * it along, and finally says it is done. The teacher then confirms it works or
 * says it does not, which reopens it. Without the confirmation the team would
 * be closing things nobody had checked.
 *
 * The submitter's name is copied onto the rows, as the email batches do, so the
 * record still reads correctly after an account is renamed or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('submitter_name');

            // bug, feature, improvement, question, other
            $table->string('type');
            $table->string('subject');
            $table->text('message');

            // Where it happened, when the teacher came from a page in the panel.
            $table->string('page_url', 1000)->nullable();
            $table->json('attachments')->nullable();

            // new, in_review, planned, in_progress, resolved, closed, declined
            $table->string('status')->default('new');
            $table->string('priority')->default('normal');

            // Whose turn it is: 'staff' or 'submitter'. Drives both menu badges.
            $table->string('waiting_on')->nullable();

            $table->unsignedInteger('reopened_count')->default(0);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'waiting_on']);
            $table->index(['user_id', 'last_activity_at']);
        });

        Schema::create('feedback_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');

            // Written by the team rather than the submitter.
            $table->boolean('is_staff')->default(false);

            // A note among the team. Never shown to the submitter.
            $table->boolean('is_internal')->default(false);

            $table->text('body')->nullable();

            // Set when the message moved the status, so the thread shows when
            // and by whom each step was taken.
            $table->string('status_from')->nullable();
            $table->string('status_to')->nullable();

            $table->json('attachments')->nullable();

            $table->timestamps();

            $table->index(['feedback_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_messages');
        Schema::dropIfExists('feedback');
    }
};

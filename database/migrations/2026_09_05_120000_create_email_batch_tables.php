<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record of every email this system sends to teachers.
 *
 * Until now a send left nothing behind but a line in laravel.log and a count in
 * a notification that disappeared on the next page load. Nobody could answer the
 * questions that actually get asked afterwards: who was this sent to, who never
 * received it, who has read it, and who is still to be chased. These two tables
 * exist to answer exactly those.
 *
 * A batch is one press of a send button — or one run of teachers:send-activation
 * — and a recipient row is one person in it. The recipient row carries a name and
 * an address of its own rather than only pointing at the teacher, because the
 * record has to stay readable after a teacher is renamed, given a new address, or
 * removed entirely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_batches', function (Blueprint $table) {
            $table->id();

            // What went out. Stored on the batch rather than read back from the
            // template, because the send dialog lets an administrator edit the
            // wording before sending, and the template can be edited afterwards.
            $table->string('subject');
            $table->text('body');

            $table->foreignId('email_template_id')->nullable()
                ->constrained('email_templates')->nullOnDelete();
            $table->string('template_name')->nullable();

            $table->foreignId('sent_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // Which of the four ways it was sent: one teacher's row, a selection
            // of rows, a faculty/department filter, or the console command.
            $table->string('source')->default('selected');

            // The faculty, department, status and job type chosen in the filtered
            // dialog, so that "who was this sent to" has an answer even when the
            // list of teachers has since changed.
            $table->json('filters')->nullable();

            $table->boolean('uses_activation_link')->default(false);
            $table->unsignedSmallInteger('link_validity_days')->nullable();

            // Everyone the batch addressed, skipped recipients included.
            $table->unsignedInteger('total_recipients')->default(0);

            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('email_batch_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_batch_id')->constrained()->cascadeOnDelete();

            // Kept when the teacher row goes: the batch still has to add up.
            $table->foreignId('teacher_id')->nullable()
                ->constrained('teachers')->nullOnDelete();

            $table->string('teacher_name');
            $table->string('email')->nullable();

            // queued  — dispatched, not yet handed to the mail server
            // sent    — accepted by the mail server
            // failed  — the mail server refused it, or the job threw
            // skipped — never dispatched, with the reason alongside
            $table->string('status')->default('queued');
            $table->string('skip_reason')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // First open and first click; the counts carry the rest.
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);

            // Identifies this one message to the tracking pixel and the link
            // redirect. Random rather than the row id, so that reading somebody
            // else's mail is not a matter of counting upwards.
            $table->string('track_token', 64)->unique();

            $table->timestamps();

            $table->index(['email_batch_id', 'status']);
            $table->index(['teacher_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_batch_recipients');
        Schema::dropIfExists('email_batches');
    }
};

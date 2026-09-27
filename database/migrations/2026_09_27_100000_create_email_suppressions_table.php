<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses the system must stop emailing.
 *
 * Activation emails were sent again and again to addresses that bounce every
 * time — some mistyped, some invented to fill a required field in the old
 * system. Each resend is another bounce against the sending account, and enough
 * of them get a sender treated as a spammer. This is the list of addresses that
 * are known not to work, so every send path can skip them and say why.
 *
 * Bounces arrive as replies in the sender's mailbox, which nothing here reads,
 * so an address gets on the list by an administrator putting it there — from a
 * recipient row in a delivery report, or typed in directly.
 *
 * Kept per address rather than as a flag on the teacher: the address is what
 * bounces, one teacher can have two (their own and their account's), and the
 * entry has to outlive the teacher row if that is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();

            // Stored lower-cased and trimmed, so one address is one row.
            $table->string('email')->unique();

            // bounced | invalid | complaint | other
            $table->string('reason')->default('bounced');
            $table->text('note')->nullable();

            // Who it belongs to, found by address when the entry is made.
            $table->foreignId('teacher_id')->nullable()
                ->constrained('teachers')->nullOnDelete();

            // The delivery report row it was marked from, when it was.
            $table->foreignId('email_batch_recipient_id')->nullable()
                ->constrained('email_batch_recipients')->nullOnDelete();

            $table->foreignId('added_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};

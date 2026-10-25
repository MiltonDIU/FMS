<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last step of an activation email: the teacher chose a password.
 *
 * The delivery report stopped at "clicked", which only says the link was used.
 * Somebody who clicked and then closed the tab is still locked out, and the
 * people chasing activations could not tell them apart from those who finished.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_batch_recipients', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('clicked_at');
        });

        /*
         * Teachers who finished before this column existed. The credit goes where
         * TeacherActivationService would have put it: the most recent activation
         * email that reached them before they set the password, since each send
         * retires the link in the one before.
         */
        $finished = DB::table('teachers')
            ->join('users', 'users.id', '=', 'teachers.user_id')
            ->whereNotNull('users.password_set_at')
            ->select('teachers.id as teacher_id', 'users.password_set_at')
            ->get();

        foreach ($finished as $row) {
            $recipientId = DB::table('email_batch_recipients')
                ->join('email_batches', 'email_batches.id', '=', 'email_batch_recipients.email_batch_id')
                ->where('email_batches.uses_activation_link', true)
                ->where('email_batch_recipients.teacher_id', $row->teacher_id)
                ->where('email_batch_recipients.status', 'sent')
                ->where('email_batch_recipients.sent_at', '<=', $row->password_set_at)
                ->orderByDesc('email_batch_recipients.id')
                ->value('email_batch_recipients.id');

            if ($recipientId !== null) {
                DB::table('email_batch_recipients')
                    ->where('id', $recipientId)
                    ->update(['password_set_at' => $row->password_set_at]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('email_batch_recipients', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};

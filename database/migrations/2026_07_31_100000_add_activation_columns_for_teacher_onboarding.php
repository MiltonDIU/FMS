<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns for onboarding migrated teachers.
 *
 * Every teacher account came across with an unusable password, so the only way
 * in is an emailed activation link. A link that logs someone in is as powerful
 * as a password, so the token needs the two things it currently lacks: an expiry
 * and a record of having been used. Without those it would sit in an inbox
 * forever, reusable by anyone the mail is forwarded to.
 *
 * password_set_at lives on users because that is where the password is, and it
 * is what tells the flow whether the account still needs to choose one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->timestamp('verification_token_expires_at')
                ->nullable()
                ->after('verification_token');

            // Set the moment a token is redeemed, which is what makes it
            // single-use: a second click finds it already spent.
            $table->timestamp('verification_token_used_at')
                ->nullable()
                ->after('verification_token_expires_at');

            $table->timestamp('activation_email_sent_at')
                ->nullable()
                ->after('verification_token_used_at');

            // Redeeming a token means looking it up, so it needs an index.
            $table->index('verification_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')
                ->nullable()
                ->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex(['verification_token']);
            $table->dropColumn([
                'verification_token_expires_at',
                'verification_token_used_at',
                'activation_email_sent_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};

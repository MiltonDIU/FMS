<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create organizations table
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('teachers')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 2. Create positions table
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('teachers')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Add foreign keys to job_experiences table
        Schema::table('job_experiences', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('organization');
            $table->unsignedBigInteger('position_id')->nullable()->after('position');

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
        });

        // 4. Data migration: Migrate existing string-based data to new tables
        $experiences = DB::table('job_experiences')->select('id', 'organization', 'position')->get();

        foreach ($experiences as $exp) {
            $orgId = null;
            $posId = null;

            // Resolve Organization
            $orgName = trim($exp->organization ?? '');
            if ($orgName !== '') {
                $dbOrg = DB::table('organizations')->where('name', $orgName)->first();
                if ($dbOrg) {
                    $orgId = $dbOrg->id;
                } else {
                    $orgId = DB::table('organizations')->insertGetId([
                        'name'       => $orgName,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Resolve Position
            $posName = trim($exp->position ?? '');
            if ($posName !== '') {
                $dbPos = DB::table('positions')->where('name', $posName)->first();
                if ($dbPos) {
                    $posId = $dbPos->id;
                } else {
                    $posId = DB::table('positions')->insertGetId([
                        'name'       => $posName,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Link IDs back to job_experiences record
            if ($orgId || $posId) {
                $update = [];
                if ($orgId) $update['organization_id'] = $orgId;
                if ($posId) $update['position_id'] = $posId;

                DB::table('job_experiences')->where('id', $exp->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        Schema::table('job_experiences', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['position_id']);
            $table->dropColumn(['organization_id', 'position_id']);
        });

        Schema::dropIfExists('organizations');
        Schema::dropIfExists('positions');
    }
};

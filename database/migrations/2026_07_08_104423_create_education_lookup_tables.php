<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create educational_institutions table
        Schema::create('educational_institutions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('teachers')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 2. Create majors table
        Schema::create('majors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('teachers')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Add foreign keys to educations table
        Schema::table('educations', function (Blueprint $table) {
            $table->unsignedBigInteger('educational_institution_id')->nullable()->after('result_type_id');
            $table->unsignedBigInteger('major_id')->nullable()->after('duration');

            $table->foreign('educational_institution_id')->references('id')->on('educational_institutions')->onDelete('set null');
            $table->foreign('major_id')->references('id')->on('majors')->onDelete('set null');
        });

        // 4. Data migration: Migrate existing string-based data to new tables
        $educations = DB::table('educations')->select('id', 'institution', 'major')->get();

        foreach ($educations as $edu) {
            $institutionId = null;
            $majorId = null;

            // Resolve Institution
            $institutionName = trim($edu->institution ?? '');
            if ($institutionName !== '') {
                $dbInst = DB::table('educational_institutions')->where('name', $institutionName)->first();
                if ($dbInst) {
                    $institutionId = $dbInst->id;
                } else {
                    $institutionId = DB::table('educational_institutions')->insertGetId([
                        'name'       => $institutionName,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Resolve Major
            $majorName = trim($edu->major ?? '');
            if ($majorName !== '') {
                $dbMajor = DB::table('majors')->where('name', $majorName)->first();
                if ($dbMajor) {
                    $majorId = $dbMajor->id;
                } else {
                    $majorId = DB::table('majors')->insertGetId([
                        'name'       => $majorName,
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Link IDs back to education record
            if ($institutionId || $majorId) {
                $update = [];
                if ($institutionId) $update['educational_institution_id'] = $institutionId;
                if ($majorId) $update['major_id'] = $majorId;

                DB::table('educations')->where('id', $edu->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::table('educations', function (Blueprint $table) {
                $table->dropForeign(['educational_institution_id']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('educations', function (Blueprint $table) {
                $table->dropForeign(['major_id']);
            });
        } catch (\Exception $e) {}

        Schema::table('educations', function (Blueprint $table) {
            if (Schema::hasColumn('educations', 'educational_institution_id')) {
                $table->dropColumn('educational_institution_id');
            }
            if (Schema::hasColumn('educations', 'major_id')) {
                $table->dropColumn('major_id');
            }
        });

        Schema::dropIfExists('educational_institutions');
        Schema::dropIfExists('majors');
    }
};

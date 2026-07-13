<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename old tables to temporary names to avoid conflict
        if (Schema::hasTable('organizations')) {
            try {
                Schema::table('organizations', function (Blueprint $table) {
                    $table->dropForeign(['created_by']);
                });
            } catch (\Exception $e) {}
            try {
                Schema::table('organizations', function (Blueprint $table) {
                    $table->dropForeign(['approved_by']);
                });
            } catch (\Exception $e) {}
            Schema::dropIfExists('old_organizations');
            Schema::rename('organizations', 'old_organizations');
        }
        if (Schema::hasTable('educational_institutions')) {
            try {
                Schema::table('educational_institutions', function (Blueprint $table) {
                    $table->dropForeign(['created_by']);
                });
            } catch (\Exception $e) {}
            try {
                Schema::table('educational_institutions', function (Blueprint $table) {
                    $table->dropForeign(['approved_by']);
                });
            } catch (\Exception $e) {}
            Schema::dropIfExists('old_educational_institutions');
            Schema::rename('educational_institutions', 'old_educational_institutions');
        }
        if (Schema::hasTable('membership_organizations')) {
            try {
                Schema::table('membership_organizations', function (Blueprint $table) {
                    $table->dropForeign(['created_by']);
                });
            } catch (\Exception $e) {}
            try {
                Schema::table('membership_organizations', function (Blueprint $table) {
                    $table->dropForeign(['activated_by']);
                });
            } catch (\Exception $e) {}
            Schema::dropIfExists('old_membership_organizations');
            Schema::rename('membership_organizations', 'old_membership_organizations');
        }

        // Drop foreign key constraints on target tables before altering columns
        try {
            Schema::table('educations', function (Blueprint $table) {
                $table->dropForeign(['educational_institution_id']);
            });
        } catch (\Exception $e) {}
        try {
            Schema::table('job_experiences', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });
        } catch (\Exception $e) {}
        try {
            Schema::table('memberships', function (Blueprint $table) {
                $table->dropForeign(['membership_organization_id']);
            });
        } catch (\Exception $e) {}

        // 1. Create the unified organizations table
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->boolean('is_educational_institution')->default(false);
            $table->boolean('is_employer')->default(false);
            $table->boolean('is_training_center')->default(false);
            $table->boolean('is_professional_body')->default(false);
            $table->boolean('is_awarding_body')->default(false);
            $table->boolean('is_certifying_authority')->default(false);
            $table->boolean('is_funding_agency')->default(false);
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('teachers')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['name', 'country_id']);
        });

        // Let's add new fk columns to other tables
        Schema::table('training_experiences', function (Blueprint $table) {
            if (!Schema::hasColumn('training_experiences', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('organization');
            }
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
        });
        Schema::table('awards', function (Blueprint $table) {
            if (!Schema::hasColumn('awards', 'awarding_body_organization_id')) {
                $table->unsignedBigInteger('awarding_body_organization_id')->nullable()->after('awarding_body');
            }
            $table->foreign('awarding_body_organization_id')->references('id')->on('organizations')->onDelete('set null');
        });
        Schema::table('certifications', function (Blueprint $table) {
            if (!Schema::hasColumn('certifications', 'issuing_authority_organization_id')) {
                $table->unsignedBigInteger('issuing_authority_organization_id')->nullable()->after('issuing_authority');
            }
            $table->foreign('issuing_authority_organization_id')->references('id')->on('organizations')->onDelete('set null');
        });
        Schema::table('research_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('research_projects', 'funding_agency_organization_id')) {
                $table->unsignedBigInteger('funding_agency_organization_id')->nullable()->after('funding_agency');
            }
            $table->foreign('funding_agency_organization_id')->references('id')->on('organizations')->onDelete('set null');
        });

        // Let's migrate and rebuild IDs maps
        // --- 1. Educational Institutions ---
        $eduInstIdMap = []; // old_id => new_id
        if (Schema::hasTable('old_educational_institutions')) {
            $records = DB::table('old_educational_institutions')->get();
            foreach ($records as $r) {
                // Find country_id from educations
                $countryId = DB::table('educations')
                    ->where('educational_institution_id', $r->id)
                    ->whereNotNull('country_id')
                    ->value('country_id') ?? 18; // Bangladesh default

                // Check if name + country_id exists
                $exists = DB::table('organizations')->where('name', $r->name)->where('country_id', $countryId)->first();
                if ($exists) {
                    DB::table('organizations')->where('id', $exists->id)->update([
                        'is_educational_institution' => true,
                        'is_active' => $exists->is_active || $r->is_active,
                    ]);
                    $eduInstIdMap[$r->id] = $exists->id;
                } else {
                    $newId = DB::table('organizations')->insertGetId([
                        'name' => $r->name,
                        'country_id' => $countryId,
                        'is_educational_institution' => true,
                        'is_active' => $r->is_active,
                        'created_by' => $r->created_by,
                        'approved_by' => $r->approved_by,
                        'created_at' => $r->created_at ?? now(),
                        'updated_at' => $r->updated_at ?? now(),
                    ]);
                    $eduInstIdMap[$r->id] = $newId;
                }
            }
        }

        // --- 2. Employers (old_organizations) ---
        $employerIdMap = []; // old_id => new_id
        if (Schema::hasTable('old_organizations')) {
            $records = DB::table('old_organizations')->get();
            foreach ($records as $r) {
                $countryId = DB::table('job_experiences')
                    ->where('organization_id', $r->id)
                    ->whereNotNull('country_id')
                    ->value('country_id') ?? 18; // Bangladesh default

                $exists = DB::table('organizations')->where('name', $r->name)->where('country_id', $countryId)->first();
                if ($exists) {
                    DB::table('organizations')->where('id', $exists->id)->update([
                        'is_employer' => true,
                        'is_active' => $exists->is_active || $r->is_active,
                    ]);
                    $employerIdMap[$r->id] = $exists->id;
                } else {
                    $newId = DB::table('organizations')->insertGetId([
                        'name' => $r->name,
                        'country_id' => $countryId,
                        'is_employer' => true,
                        'is_active' => $r->is_active,
                        'created_by' => $r->created_by,
                        'approved_by' => $r->approved_by,
                        'created_at' => $r->created_at ?? now(),
                        'updated_at' => $r->updated_at ?? now(),
                    ]);
                    $employerIdMap[$r->id] = $newId;
                }
            }
        }

        // --- 3. Memberships (old_membership_organizations) ---
        $membershipIdMap = [];
        if (Schema::hasTable('old_membership_organizations')) {
            $records = DB::table('old_membership_organizations')->get();
            foreach ($records as $r) {
                $countryId = 18; // default to Bangladesh/18

                $exists = DB::table('organizations')->where('name', $r->name)->where('country_id', $countryId)->first();
                if ($exists) {
                    DB::table('organizations')->where('id', $exists->id)->update([
                        'is_professional_body' => true,
                        'is_active' => $exists->is_active || $r->is_active,
                    ]);
                    $membershipIdMap[$r->id] = $exists->id;
                } else {
                    $newId = DB::table('organizations')->insertGetId([
                        'name' => $r->name,
                        'country_id' => $countryId,
                        'is_professional_body' => true,
                        'is_active' => $r->is_active,
                        'created_by' => $r->created_by,
                        'approved_by' => $r->activated_by ?? null,
                        'created_at' => $r->created_at ?? now(),
                        'updated_at' => $r->updated_at ?? now(),
                    ]);
                    $membershipIdMap[$r->id] = $newId;
                }
            }
        }

        // --- 4. Training Experiences (raw text to lookup) ---
        $trainings = DB::table('training_experiences')->get();
        foreach ($trainings as $t) {
            $name = trim($t->organization ?? '');
            if ($name === '') continue;

            $countryId = $t->country_id ?? 18;

            $exists = DB::table('organizations')->where('name', $name)->where('country_id', $countryId)->first();
            if ($exists) {
                DB::table('organizations')->where('id', $exists->id)->update([
                    'is_training_center' => true,
                ]);
                DB::table('training_experiences')->where('id', $t->id)->update(['organization_id' => $exists->id]);
            } else {
                $newId = DB::table('organizations')->insertGetId([
                    'name' => $name,
                    'country_id' => $countryId,
                    'is_training_center' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('training_experiences')->where('id', $t->id)->update(['organization_id' => $newId]);
            }
        }

        // --- 5. Awards (raw text to lookup) ---
        $awards = DB::table('awards')->get();
        foreach ($awards as $a) {
            $name = trim($a->awarding_body ?? '');
            if ($name === '') continue;

            $countryId = 18; // default

            $exists = DB::table('organizations')->where('name', $name)->where('country_id', $countryId)->first();
            if ($exists) {
                DB::table('organizations')->where('id', $exists->id)->update([
                    'is_awarding_body' => true,
                ]);
                DB::table('awards')->where('id', $a->id)->update(['awarding_body_organization_id' => $exists->id]);
            } else {
                $newId = DB::table('organizations')->insertGetId([
                    'name' => $name,
                    'country_id' => $countryId,
                    'is_awarding_body' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('awards')->where('id', $a->id)->update(['awarding_body_organization_id' => $newId]);
            }
        }

        // --- 6. Certifications (raw text to lookup) ---
        $certs = DB::table('certifications')->get();
        foreach ($certs as $c) {
            $name = trim($c->issuing_authority ?? '');
            if ($name === '') continue;

            $countryId = 18; // default

            $exists = DB::table('organizations')->where('name', $name)->where('country_id', $countryId)->first();
            if ($exists) {
                DB::table('organizations')->where('id', $exists->id)->update([
                    'is_certifying_authority' => true,
                ]);
                DB::table('certifications')->where('id', $c->id)->update(['issuing_authority_organization_id' => $exists->id]);
            } else {
                $newId = DB::table('organizations')->insertGetId([
                    'name' => $name,
                    'country_id' => $countryId,
                    'is_certifying_authority' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('certifications')->where('id', $c->id)->update(['issuing_authority_organization_id' => $newId]);
            }
        }

        // --- 7. Research Projects (raw text to lookup) ---
        $projects = DB::table('research_projects')->get();
        foreach ($projects as $p) {
            $name = trim($p->funding_agency ?? '');
            if ($name === '') continue;

            $countryId = 18; // default

            $exists = DB::table('organizations')->where('name', $name)->where('country_id', $countryId)->first();
            if ($exists) {
                DB::table('organizations')->where('id', $exists->id)->update([
                    'is_funding_agency' => true,
                ]);
                DB::table('research_projects')->where('id', $p->id)->update(['funding_agency_organization_id' => $exists->id]);
            } else {
                $newId = DB::table('organizations')->insertGetId([
                    'name' => $name,
                    'country_id' => $countryId,
                    'is_funding_agency' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('research_projects')->where('id', $p->id)->update(['funding_agency_organization_id' => $newId]);
            }
        }

        // Now align existing foreign keys using the ID maps
        // --- Educations ---
        foreach ($eduInstIdMap as $oldId => $newId) {
            DB::table('educations')->where('educational_institution_id', $oldId)->update(['educational_institution_id' => $newId]);
        }
        // --- Job Experiences ---
        foreach ($employerIdMap as $oldId => $newId) {
            DB::table('job_experiences')->where('organization_id', $oldId)->update(['organization_id' => $newId]);
        }

        // Add back foreign key constraints pointing to unified 'organizations' table
        Schema::table('educations', function (Blueprint $table) {
            $table->foreign('educational_institution_id')->references('id')->on('organizations')->onDelete('set null');
        });
        Schema::table('job_experiences', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
        });
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreign('membership_organization_id')->references('id')->on('organizations')->onDelete('set null');
        });

        // Drop temporary old tables
        Schema::dropIfExists('old_educational_institutions');
        Schema::dropIfExists('old_organizations');
        Schema::dropIfExists('old_membership_organizations');
    }

    public function down(): void
    {
        // 1. Drop foreign keys and columns we added to other tables
        try {
            Schema::table('training_experiences', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });
        } catch (\Exception $e) {}

        Schema::table('training_experiences', function (Blueprint $table) {
            if (Schema::hasColumn('training_experiences', 'organization_id')) {
                $table->dropColumn('organization_id');
            }
        });

        try {
            Schema::table('awards', function (Blueprint $table) {
                $table->dropForeign(['awarding_body_organization_id']);
            });
        } catch (\Exception $e) {}

        Schema::table('awards', function (Blueprint $table) {
            if (Schema::hasColumn('awards', 'awarding_body_organization_id')) {
                $table->dropColumn('awarding_body_organization_id');
            }
        });

        try {
            Schema::table('certifications', function (Blueprint $table) {
                $table->dropForeign(['issuing_authority_organization_id']);
            });
        } catch (\Exception $e) {}

        Schema::table('certifications', function (Blueprint $table) {
            if (Schema::hasColumn('certifications', 'issuing_authority_organization_id')) {
                $table->dropColumn('issuing_authority_organization_id');
            }
        });

        try {
            Schema::table('research_projects', function (Blueprint $table) {
                $table->dropForeign(['funding_agency_organization_id']);
            });
        } catch (\Exception $e) {}

        Schema::table('research_projects', function (Blueprint $table) {
            if (Schema::hasColumn('research_projects', 'funding_agency_organization_id')) {
                $table->dropColumn('funding_agency_organization_id');
            }
        });

        // Drop foreign keys on educations, job_experiences, memberships pointing to unified organizations
        try {
            Schema::table('educations', function (Blueprint $table) {
                $table->dropForeign(['educational_institution_id']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('job_experiences', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('memberships', function (Blueprint $table) {
                $table->dropForeign(['membership_organization_id']);
            });
        } catch (\Exception $e) {}

        Schema::dropIfExists('organizations');

        // Recreate legacy tables to restore clean state for rollback
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('teachers')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('educational_institutions')) {
            Schema::create('educational_institutions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamps();

                $table->foreign('created_by')->references('id')->on('teachers')->onDelete('set null');
                $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('membership_organizations')) {
            Schema::create('membership_organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('teachers')->nullOnDelete();
                $table->timestamp('activated_at')->nullable();
                $table->foreignId('activated_by')->nullable()->constrained('teachers')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Drop temporary tables if they somehow still exist
        Schema::dropIfExists('old_organizations');
        Schema::dropIfExists('old_educational_institutions');
        Schema::dropIfExists('old_membership_organizations');

        // Clean up orphaned foreign key values before re-adding constraints to avoid integrity violations
        if (Schema::hasTable('educational_institutions')) {
            DB::table('educations')
                ->whereNotNull('educational_institution_id')
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('educational_institutions')
                        ->whereColumn('educational_institutions.id', 'educations.educational_institution_id');
                })
                ->update(['educational_institution_id' => null]);
        }

        if (Schema::hasTable('organizations')) {
            DB::table('job_experiences')
                ->whereNotNull('organization_id')
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('organizations')
                        ->whereColumn('organizations.id', 'job_experiences.organization_id');
                })
                ->update(['organization_id' => null]);
        }

        if (Schema::hasTable('membership_organizations')) {
            DB::table('memberships')
                ->whereNotNull('membership_organization_id')
                ->whereNotExists(function ($query) {
                    $query->select('id')
                        ->from('membership_organizations')
                        ->whereColumn('membership_organizations.id', 'memberships.membership_organization_id');
                })
                ->update(['membership_organization_id' => null]);
        }

        // Re-add legacy foreign keys
        Schema::table('educations', function (Blueprint $table) {
            if (Schema::hasTable('educational_institutions')) {
                $table->foreign('educational_institution_id')->references('id')->on('educational_institutions')->onDelete('set null');
            }
        });
        Schema::table('job_experiences', function (Blueprint $table) {
            if (Schema::hasTable('organizations')) {
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
            }
        });
        Schema::table('memberships', function (Blueprint $table) {
            if (Schema::hasTable('membership_organizations')) {
                $table->foreign('membership_organization_id')->references('id')->on('membership_organizations')->onDelete('set null');
            }
        });
    }
};

<?php

namespace Tests\Feature;

use App\Helpers\Branding;
use App\Models\JobExperience;
use App\Models\Teacher;
use Tests\TestCase;

/**
 * The CV template addressed several columns that do not exist — `designation`
 * on job_experiences, `year` on publications, `organization` on awards, `name`
 * on certifications. Blade resolves a missing attribute to null without
 * complaint, so every one of them failed silently: the PDF printed the literal
 * fallback "Role" for every post, and dropped publication years entirely.
 *
 * These assert the real column names still reach the page, which is the part a
 * rename would break again.
 */
class CvContentTest extends TestCase
{
    protected function renderCvFor(Teacher $teacher): string
    {
        $teacher = Teacher::with([
            'designation', 'department.faculty',
            'educations.degreeLevel', 'educations.degreeType', 'educations.resultType',
            'educations.educationalInstitution',
            'publications', 'trainingExperiences', 'skills',
            'certifications.issuingAuthorityOrganizationRelation',
            'teachingAreas', 'memberships.membershipType', 'memberships.membershipOrganization',
            'awards.awardingBodyOrganizationRelation',
            'jobExperiences.positionRelation', 'jobExperiences.organizationRelation',
            'socialLinks.platform', 'user',
        ])->findOrFail($teacher->id);

        return view('frontend.cv', ['teacher' => $teacher, 'brand' => Branding::all()])->render();
    }

    protected function teacherWithExperience(): Teacher
    {
        $id = JobExperience::whereNotNull('position')->value('teacher_id');

        if (! $id || ! ($teacher = Teacher::find($id))) {
            $this->markTestSkipped('No teacher with a recorded job position.');
        }

        return $teacher;
    }

    public function test_a_job_position_reaches_the_page(): void
    {
        $teacher = $this->teacherWithExperience();
        $position = $teacher->jobExperiences->firstWhere('position', '!=', null)->position;

        $html = $this->renderCvFor($teacher);

        $this->assertStringContainsString(e($position), $html);
        $this->assertStringNotContainsString('Position not recorded', $html);
    }

    public function test_a_finished_post_shows_its_end_date_and_is_not_called_current(): void
    {
        $experience = JobExperience::where('is_current', false)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->first();

        if (! $experience || ! ($teacher = Teacher::find($experience->teacher_id))) {
            $this->markTestSkipped('No finished job experience with both dates.');
        }

        $html = $this->renderCvFor($teacher);

        $this->assertStringContainsString(
            $experience->start_date->format('M Y') . ' – ' . $experience->end_date->format('M Y'),
            $html,
        );
    }

    public function test_a_post_still_held_is_marked_current(): void
    {
        $experience = JobExperience::where('is_current', true)->first();

        if (! $experience || ! ($teacher = Teacher::find($experience->teacher_id))) {
            $this->markTestSkipped('No current job experience recorded.');
        }

        $html = $this->renderCvFor($teacher);

        $this->assertStringContainsString('Present', $html);
        $this->assertStringContainsString('class="current"', $html);
    }

    public function test_a_publication_year_reaches_the_page(): void
    {
        $teacher = Teacher::whereHas('publications', fn ($q) => $q->whereNotNull('publication_year'))->first();

        if (! $teacher) {
            $this->markTestSkipped('No teacher with a dated publication.');
        }

        $year = $teacher->publications->firstWhere('publication_year', '!=', null)->publication_year;

        $this->assertStringContainsString((string) $year, $this->renderCvFor($teacher));
    }

    public function test_contact_details_use_words_rather_than_glyphs(): void
    {
        // The embedded theme font has no envelope or telephone glyph, so dompdf
        // printed "?" beside the email and phone.
        $html = $this->renderCvFor($this->teacherWithExperience());

        $this->assertStringNotContainsString('&#9993;', $html);
        $this->assertStringNotContainsString('&#9742;', $html);
        $this->assertStringContainsString('Email:', $html);
    }
}

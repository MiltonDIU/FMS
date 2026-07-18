<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Department;
use App\Models\Setting;
use App\Models\Teacher;
use App\Helpers\ProfileDownload;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class TeacherController extends Controller
{
    public function show(string $faculty_short_name, string $department_code, string $teacher_webpage): View
    {
        $activeTheme = Setting::get('active_theme', 'theme_default');

        // Find faculty
        $faculty = Faculty::where('is_active', true)
            ->where(function ($q) use ($faculty_short_name) {
                $q->where('short_name', $faculty_short_name)
                  ->orWhere('id', $faculty_short_name);
            })
            ->firstOrFail();

        // Find department under that faculty
        $department = Department::where('faculty_id', $faculty->id)
            ->where('is_active', true)
            ->where(function ($q) use ($department_code) {
                $q->where('code', $department_code)
                  ->orWhere('id', $department_code);
            })
            ->firstOrFail();

        // Load teacher by webpage or employee_id or database id under the department
        // (matches either the teacher's home department or a department_teacher assignment)
        $teacher = Teacher::where(function ($query) use ($department) {
                $query->where('department_id', $department->id)
                    ->orWhereHas('departments', function ($q) use ($department) {
                        $q->whereNull('department_teacher.deleted_at')
                            ->where('department_teacher.department_id', $department->id);
                    });
            })
            ->where(function ($query) use ($teacher_webpage) {
                $query->where('webpage', $teacher_webpage)
                    ->orWhere('employee_id', $teacher_webpage)
                    ->orWhere('id', $teacher_webpage);
            })
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'designation',
                'department',
                'educations.degreeLevel',
                'educations.degreeType',
                'educations.resultType',
                'publications',
                'trainingExperiences',
                'certifications',
                'skills',
                'teachingAreas',
                'memberships.membershipType',
                'memberships.membershipOrganization',
                'awards',
                'jobExperiences',
                'socialLinks.platform',
                'administrativeRoles'
            ])
            ->firstOrFail();

        // Track profile views (once per session hour to avoid refresh inflation).
        $viewKey = "viewed_teacher_{$teacher->id}";
        if (! session()->has($viewKey)) {
            $teacher->timestamps = false;
            $teacher->increment('views_count');
            $teacher->updateQuietly(['last_viewed_at' => now()]);
            $teacher->timestamps = true;
            session()->put($viewKey, true);
        }

        // Build SEO / social sharing metadata.
        $fullName = trim("{$teacher->first_name} {$teacher->middle_name} {$teacher->last_name}");
        $titleSuffix = \App\Helpers\Branding::get('meta_title_suffix');
        $metaTitle = "{$fullName} — " . ($teacher->designation?->name ?? 'Faculty Member')
            . " | {$department->name}{$titleSuffix}";

        $rawDesc = $teacher->bio ?: $teacher->research_interest ?: \App\Helpers\Branding::get('meta_description');
        $metaDescription = \Illuminate\Support\Str::limit(
            trim(preg_replace('/\s+/', ' ', strip_tags($rawDesc))),
            160
        );

        $photoUrl = $teacher->photo
            ? (str_starts_with($teacher->photo, 'http') ? $teacher->photo : asset("storage/{$teacher->photo}"))
            : \App\Helpers\Branding::logoUrl();
        $profileUrl = request()->url();

        return view("frontend.themes.{$activeTheme}.profile", compact(
            'activeTheme', 'faculty', 'department', 'teacher',
            'metaTitle', 'metaDescription', 'photoUrl', 'profileUrl'
        ));
    }

    /**
     * Download the teacher's contact as a vCard (.vcf) — no dependencies.
     */
    public function vcard(string $faculty_short_name, string $department_code, string $teacher_webpage): Response
    {
        abort_unless(ProfileDownload::vcardEnabled(), 404);

        $teacher = $this->resolveTeacher($faculty_short_name, $department_code, $teacher_webpage);
        $faculty = $teacher->department?->faculty;
        $department = $teacher->department;

        $fullName = $teacher->full_name;
        $email = $teacher->user?->email ?? $teacher->secondary_email;
        $phone = $teacher->phone ?? $teacher->personal_phone;
        $org = trim(($faculty?->name ?? '') . ' / ' . ($department?->name ?? ''), ' /');
        $title = $teacher->designation?->name;

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:' . $fullName,
            'N:' . ($teacher->last_name ?? '') . ';' . ($teacher->first_name ?? '') . ';' . ($teacher->middle_name ?? '') . ';;',
        ];
        if ($title) {
            $lines[] = 'TITLE:' . $title;
        }
        if ($email) {
            $lines[] = 'EMAIL;TYPE=INTERNET:' . $email;
        }
        if ($phone) {
            $lines[] = 'TEL;TYPE=CELL:' . $phone;
        }
        if ($org) {
            $lines[] = 'ORG:' . $org;
        }
        if ($department?->name) {
            $lines[] = 'CATEGORIES:' . $department->name;
        }
        if ($teacher->present_address) {
            $lines[] = 'ADR;TYPE=HOME:;;' . str_replace(["\r", "\n"], ', ', $teacher->present_address);
        }
        $profileUrl = route('teacher.show', [
            'faculty_short_name' => $faculty?->short_name ?? $faculty_short_name,
            'department_code' => $department?->code ?? $department_code,
            'teacher_webpage' => $teacher->webpage ?? $teacher_webpage,
        ]);
        $lines[] = 'URL:' . $profileUrl;
        if ($teacher->photo) {
            $photo = str_starts_with($teacher->photo, 'http')
                ? $teacher->photo
                : asset("storage/{$teacher->photo}");
            $lines[] = 'PHOTO;VALUE=URI:' . $photo;
        }
        $lines[] = 'END:VCARD';

        $filename = Str::slug($fullName) . '.vcf';

        return ResponseFacade::make(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generate and download a professional CV/Resume PDF from the teacher's profile.
     */
    public function cv(string $faculty_short_name, string $department_code, string $teacher_webpage)
    {
        abort_unless(ProfileDownload::cvEnabled(), 404);

        $teacher = $this->resolveTeacher($faculty_short_name, $department_code, $teacher_webpage, [
            'designation', 'department', 'department.faculty',
            'educations.degreeLevel', 'educations.degreeType', 'educations.resultType',
            'publications', 'trainingExperiences', 'certifications', 'skills',
            'teachingAreas', 'memberships.membershipType', 'memberships.membershipOrganization', 'awards',
            'jobExperiences', 'socialLinks.platform', 'user',
        ]);

        $brand = \App\Helpers\Branding::all();
        $pdf = Pdf::loadView('frontend.cv', compact('teacher', 'brand'))
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true);

        $filename = Str::slug($teacher->full_name) . '-cv.pdf';

        return $pdf->download($filename);
    }

    /**
     * Resolve a teacher (with optional eager loads) for the vcard/cv routes.
     */
    protected function resolveTeacher(string $faculty_short_name, string $department_code, string $teacher_webpage, array $with = []): Teacher
    {
        $faculty = Faculty::where('is_active', true)
            ->where(fn ($q) => $q->where('short_name', $faculty_short_name)->orWhere('id', $faculty_short_name))
            ->firstOrFail();

        $department = Department::where('faculty_id', $faculty->id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('code', $department_code)->orWhere('id', $department_code))
            ->firstOrFail();

        return Teacher::where(fn ($query) => $query
                ->where('department_id', $department->id)
                ->orWhereHas('departments', fn ($q) => $q
                    ->whereNull('department_teacher.deleted_at')
                    ->where('department_teacher.department_id', $department->id)))
            ->where(fn ($query) => $query
                ->where('webpage', $teacher_webpage)
                ->orWhere('employee_id', $teacher_webpage)
                ->orWhere('id', $teacher_webpage))
            ->where('is_active', true)
            ->where('is_archived', false)
            ->when($with, fn ($q) => $q->with($with))
            ->firstOrFail();
    }
}

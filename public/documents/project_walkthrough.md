# FMS

> **Historical snapshot — written 2025-12-20, annotated 2026-08-30.**
>
> These are development notes from the first weeks of the project, kept for the
> record. They describe the schema and the plan as they stood then, not the
> system as it is now. For the current description see
> *BRD - Faculty Management System.md* and
> *SRS - Faculty Management System.md* (both revision 2.1).
>
> What has changed since: **18 tables became 72**, **18 models became 53**, the
> panel runs on **Filament 5 / Laravel 13** rather than Filament v4, and the
> system now carries 2,000 teachers and 17,510 publications in production.

---

### Teacher Profile Interface
The Teacher Profile is now fully implemented with a comprehensive Tabbed Interface and integrated Relation Managers.

#### Edit Form with Tabs
The form is organized into logical sections using tabs: Basic Information, Contact & Address, Personal Details, Academic & Social, and Settings.

*(Screenshot removed: the original link pointed at an absolute path on a
developer machine and could never resolve for a reader of this public folder.)*

#### Relation Managers
At the bottom of the profile, 8 specialized subsystems are available for managing related records such as Educations, Publications, Job Experiences, etc.

*(Screenshot removed: the original link pointed at an absolute path on a
developer machine and could never resolve for a reader of this public folder.)*

### Changes Implemented
- **TeacherResource**: Configured with `TeacherForm` schema and registered 8 relation managers.
- **TeacherForm**: Created a Schema-based form layout using `Tabs`, `Grid`, and `Section` from `Filament\Schemas` namespace.
- **Relation Managers**: Created and registered 8 relation managers (Educations, Publications, JobExperiences, Skills, Awards, SocialLinks, Versions, TeachingAreas) using the `Actions` and `Tables` namespaces.
- **Filament Compatibility**: Resolved namespace conflicts by correctly mixing `Filament\Forms` (for inputs) and `Filament\Schemas` (for layouts). *(Written against Filament v4; the panel now runs on Filament 5.)*

 - Walkthrough

## Summary

Successfully implemented the Faculty Management System (FMS) database schema with **18 tables** and **18 Eloquent models** with full relationships.

> **As it stands on 2026-08-30:** 72 tables and 53 Eloquent models. The 18 below
> were the first cut.

---

## Changes Made

### Migrations Created (18)

| # | Migration | Table |
|---|-----------|-------|
| 1 | `2025_12_20_000001_create_faculties_table` | faculties |
| 2 | `2025_12_20_000002_create_departments_table` | departments |
| 3 | `2025_12_20_000003_create_designations_table` | designations |
| 4 | `2025_12_20_000004_create_administrative_roles_table` | administrative_roles |
| 5 | `2025_12_20_000005_create_teachers_table` | teachers |
| 6 | `2025_12_20_000006_create_educations_table` | educations |
| 7 | `2025_12_20_000007_create_publications_table` | publications |
| 8 | `2025_12_20_000008_create_research_projects_table` | research_projects |
| 9 | `2025_12_20_000009_create_training_experiences_table` | training_experiences |
| 10 | `2025_12_20_000010_create_certifications_table` | certifications |
| 11 | `2025_12_20_000011_create_skills_table` | skills |
| 12 | `2025_12_20_000012_create_teaching_areas_table` | teaching_areas |
| 13 | `2025_12_20_000013_create_memberships_table` | memberships |
| 14 | `2025_12_20_000014_create_awards_table` | awards |
| 15 | `2025_12_20_000015_create_job_experiences_table` | job_experiences |
| 16 | `2025_12_20_000016_create_social_links_table` | social_links |
| 17 | `2025_12_20_000017_create_teacher_administrative_roles_table` | teacher_administrative_roles *(now `administrative_role_user`)* |
| 18 | `2025_12_20_000018_create_teacher_versions_table` | teacher_versions |

---

### Models Created (18)

| Model | Location |
|-------|----------|
| [Faculty](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Faculty.php) | app/Models/Faculty.php |
| [Department](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Department.php) | app/Models/Department.php |
| [Designation](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Designation.php) | app/Models/Designation.php |
| [AdministrativeRole](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/AdministrativeRole.php) | app/Models/AdministrativeRole.php |
| [Teacher](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Teacher.php) | app/Models/Teacher.php |
| [Education](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Education.php) | app/Models/Education.php |
| [Publication](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Publication.php) | app/Models/Publication.php |
| [ResearchProject](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/ResearchProject.php) | app/Models/ResearchProject.php |
| [TrainingExperience](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/TrainingExperience.php) | app/Models/TrainingExperience.php |
| [Certification](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Certification.php) | app/Models/Certification.php |
| [Skill](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Skill.php) | app/Models/Skill.php |
| [TeachingArea](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/TeachingArea.php) | app/Models/TeachingArea.php |
| [Membership](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Membership.php) | app/Models/Membership.php |
| [Award](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/Award.php) | app/Models/Award.php |
| [JobExperience](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/JobExperience.php) | app/Models/JobExperience.php |
| [SocialLink](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/SocialLink.php) | app/Models/SocialLink.php |
| [TeacherAdministrativeRole](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/TeacherAdministrativeRole.php) | app/Models/TeacherAdministrativeRole.php |
| [TeacherVersion](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/TeacherVersion.php) | app/Models/TeacherVersion.php |

---

### User Model Updated

[User.php](file:///wsl.localhost/Ubuntu/home/milton/Project/FMS/app/Models/User.php) - Added:
- `teacher()` relationship
- `isTeacher()` helper method

---

## Verification

✅ All 18 migrations ran successfully
✅ All tables created in database

---

## Next Steps *(as written in December 2025 — all of these were completed)*

1. Install activity log packages:
   ```bash
   composer require spatie/laravel-activitylog
   composer require rmsramos/activitylog
   ```
2. Create Filament resources for each model
3. Configure Filament Shield permissions

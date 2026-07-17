# Feature #2 — vCard Download + Auto CV/PDF Generation (2.3 part A)

Stack: Laravel 13, Filament 5, Livewire 4, Tailwind v4. Follows Feature #1.

## Scope
1. "Save Contact" button on teacher profile → downloads `.vcf` (vCard 3.0).
2. "Download CV" button on teacher profile → generates a professional PDF resume from profile data.

## PDF library decision
- Use **`barryvdh/laravel-dompdf`** (pure PHP, no external Chrome/binary). Best fit for the existing stack; installs cleanly, renders Blade → PDF.
- (Alternative `spatie/browsershot` needs headless Chrome — heavier, skipped.)

## Implementation

### A. vCard route + controller (no dependency)
- New `GET /faculty/{faculty}/department/{dept}/teacher/{webpage}/vcard` route → `TeacherController::vcard()`.
- Build vCard 3.0 string: `BEGIN:VCARD`, `VERSION:3.0`, `FN` (full name), `EMAIL`, `TEL` (phone/personal_phone), `ORG` (faculty/department), `TITLE` (designation), `PHOTO` (URL), `URL` (profile), `ADR` (present_address).
- Return `Response` with `Content-Type: text/vcard` (or `text/x-vcard`) + `Content-Disposition: attachment; filename="name.vcf"`.

### B. CV/PDF route + controller
- `composer require barryvdh/laravel-dompdf`.
- New `GET .../cv` route → `TeacherController::cv()` (or a small `TeacherCvController`).
- Eager-load same relations as `show()` (designation, department, educations, publicats, certifications, skills, teachingAreas, memberships, awards, jobExperiences, socialLinks).
- Render `resources/views/frontend/cv.blade.php` (clean print-friendly Blade, brand-neutral) via `Pdf::loadView('...cv', $data)->download("name-cv.pdf")`.
- CV layout sections: Header (name, photo, designation, contact), Summary (bio), Research Interests, Education, Experience, Publications, Skills, Memberships, Awards, Social links.

### C. Buttons on profile (all 3 themes)
- In the "Quick contact card" (modern: profile.blade.php:103) and equivalent diu/default contact cards, add two buttons below contact rows:
  - "Save Contact" → `route('teacher.vcard', [...])` (anchor, download).
  - "Download CV" → `route('teacher.cv', [...])`.
- Keep theme styling (diu-primary buttons / glass). Use existing route params (`$faculty->short_name`, `$department->code`, `$teacher->webpage`).

### D. Routes (web.php)
- Add `teacher.vcard` and `teacher.cv` named routes mirroring the existing `teacher.show` route pattern & middleware.

## Files
- MODIFY `routes/web.php` — add 2 routes
- MODIFY `app/Http/Controllers/Frontend/TeacherController.php` — add `vcard()` + `cv()`
- MODIFY `composer.json` / run `composer require barryvdh/laravel-dompdf`
- CREATE `resources/views/frontend/cv.blade.php` (shared CV template)
- MODIFY 3 profile blades (`theme_modern`, `theme_diu`, `theme_default`) — add Save Contact + Download CV buttons
- (Optional) config publish for dompdf

## Verification
- Visit profile → click "Save Contact" → `.vcf` downloads, imports into phone contacts.
- Click "Download CV" → PDF renders with all sections, branded header.
- `php -l` on controller; `npm run build` NOT needed (Blade only, but CV blade is plain HTML/CSS).
- Clear view cache.

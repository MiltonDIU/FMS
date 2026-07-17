# Feature #1 — SEO (Dynamic Meta/OpenGraph + Schema.org Person) & Profile Views Analytics

Stack: Laravel 13, Filament 5, Livewire 4, Tailwind v4. Excludes 2.4.

## Scope
1. Schema.org `Person` JSON-LD on teacher profile pages (Google Knowledge Graph).
2. Dynamic OpenGraph + Twitter meta tags on teacher profile pages.
3. Profile Views tracking (counter + last-viewed) with an admin dashboard widget.

## Implementation

### A. Profile Views model + migration
- New `ProfileView` (or simpler: add `views_count` + `last_viewed_at` to `teachers` table via migration).
  - Chosen: migration `add_views_tracking_to_teachers_table` adding `views_count` (unsigned int, default 0) + `last_viewed_at` (nullable timestamp).
  - Rationale: single-table counter avoids extra joins; sufficient for "which profiles get most views".

### B. TeacherController (show)
- After loading `$teacher`, increment view count (guarded against bots / own session via a short session key to avoid double-count on refresh).
  - `session()->has("viewed_teacher_{$teacher->id}")` check; if not, `increment('views_count')`, set `last_viewed_at = now()`, set session flag (TTL 1h).
- Compute meta for the view:
  - `$title` = "{$teacher->full_name} — {$designation} | {$department->name}".
  - `$description` = bio/research_interest excerpt (strip tags, limit ~160 chars).
  - `$ogImage` = teacher photo URL (from media or `photo` field) fallback to `Branding::logoUrl()`.
  - `$profileUrl` = current URL (`request()->url()`).
  - Pass `metaTitle`, `metaDescription`, `ogImage`, `profileUrl`, `teacher` to a new `@include('frontend.themes.'.$activeTheme.'.partials.seo')`.

### C. SEO partial (per-theme include)
- New `resources/views/frontend/themes/{theme}/partials/seo.blade.php` (3 themes: modern, diu, default) OR a single shared partial at `resources/views/frontend/partials/seo.blade.php` included from each theme's `head.blade.php`/profile layout.
  - Outputs: `<meta property="og:title">`, `og:description`, `og:image`, `og:url`, `og:type=profile`, `twitter:card/title/description/image`, and `<script type="application/ld+json">{Person schema}</script>`.
  - `Person` schema fields: `@context`, `@type:Person`, `name`, `jobTitle`, `affiliation` (EducationalOrganization: name=university, department), `url`, `image`, `email` (secondary_email), `telephone`, `sameAs` (socialLinks), `description`.
- Wire into `head.blade.php` via `@yield('seo')` OR directly include in profile blade. Simplest: include SEO partial at top of profile blade using controller-passed vars, falling back to brand defaults when vars absent.

### D. Meta defaults (Branding already has meta_title_suffix / meta_description)
- Extend `Branding::all()` usage in `head.blade.php` (already yields `meta_description` / `title`). Keep as global fallback; profile page overrides via section yield.

### E. Admin widget — Profile Views
- New `app/Filament/Widgets/TopProfileViewsWidget.php` (StatsOverviewWidget or Table widget).
  - Shows top 10 teachers by `views_count` with name, department, views, last viewed.
  - Registered in `AdminPanelProvider` widgets or via `discoverWidgets` (auto-discovered) — just ensure class exists in `app/Filament/Widgets`.
- Optionally add a small `Stat` "Total Profile Views" to existing `TeacherStatsOverview`.

## Files to create/modify
- CREATE `database/migrations/2026_07_18_000000_add_views_tracking_to_teachers_table.php`
- MODIFY `app/Http/Controllers/Frontend/TeacherController.php` (view increment + meta vars)
- CREATE `resources/views/frontend/partials/seo.blade.php` (shared SEO + JSON-LD partial)
- MODIFY each theme `profile.blade.php` (include seo partial) — modern/diu/default
- CREATE `app/Filament/Widgets/TopProfileViewsWidget.php`
- MODIFY `app/Filament/Widgets/TeacherStatsOverview.php` (add total views stat) — optional

## Verification
- `php artisan migrate` (or `migrate --force` in prod).
- Visit a teacher profile → view page source: confirm og: tags + JSON-LD `Person` present.
- Refresh once → count increments only once per session.
- Admin panel → new widget lists top profiles by views.
- `npm run build` not required (Blade/PHP only); no CSS change.

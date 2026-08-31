# FMS Database Schema Design

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

## Phase 1-4: ✅ Complete

## Phase 5: Teacher Profile Management ✅
- [x] Setup Spatie Media Library
- [x] Update Teacher model with HasMedia
- [x] Create TeacherResource with tabs
- [x] Add Relation Managers (8 total)
- [x] Teacher "My Profile" page
- [x] Version Control UI
- [x] Approval Workflow

## Phases delivered since (see the SRS for detail)

- [x] Phase 6 — Publications, authors and publication incentives
- [x] Phase 7 — Public directory, themes, SEO, CV and share cards
- [x] Phase 8 — Legacy data migration from the seven free-text columns
- [x] Phase 9 — DIU HR/ERP API client for teacher auto-fill *(awaiting credentials)*
- [x] Phase 10 — Scopus export review, affiliation matching and author merge







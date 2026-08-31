# Software Requirements Specification (SRS)

## Faculty Management System (FMS) — Daffodil International University

| | |
|---|---|
| **Document** | Software Requirements Specification |
| **System** | Faculty Management System (FMS) |
| **Version** | 2.1 |
| **Date** | 2026-08-30 |
| **Status** | Revised against the delivered system |
| **Supersedes** | *Software Requirements Specification - SRS (Final)* |
| **Companion** | *BRD - Faculty Management System.md* |

---

## 1. Introduction

### 1.1 Purpose

This document specifies what the Faculty Management System does, how it behaves,
and what it requires of its environment. It is written for developers
maintaining the system, testers verifying it, and reviewers assessing it.

Unlike the original SRS, which described an intended system, this revision was
written against the built one. Each requirement carries a status:

| | |
|---|---|
| **D** | Delivered — implemented and verified |
| **B** | Built — implemented but not yet exercised in operation |
| **N** | Not built |

### 1.2 Scope

FMS is a web application with two faces:

- an **administrative panel** behind authentication, where faculty data is
  maintained, reviewed and reported on;
- a **public directory**, open to anyone, generated from approved data.

It holds the authoritative record of academic staff, their qualifications,
appointments and scholarly output, and publishes the public-facing part of it.

### 1.3 Definitions

| Term | Meaning |
|---|---|
| Teacher | An academic staff member holding a profile |
| Designation | Academic rank — Professor, Associate Professor, Lecturer |
| Administrative role | An office held alongside a designation — Dean, Head |
| Version | A snapshot of submitted profile changes awaiting or carrying a decision |
| Section | A named group of profile fields that approval is configured against |
| Employment status | Whether a teacher is active, on leave, retired, and so on |
| Theme | A removable set of templates and styles rendering the public site |
| Shield | The Filament plugin generating per-model permissions |

### 1.4 References

- *BRD - Faculty Management System.md* — business requirements and rationale
- *RBAC Matrix & Workflow Diagrams (Final)* — original governance design
- *Legacy → New System Mapping (Final)* — migration field mapping

---

## 2. Overall description

### 2.1 Architecture as built

| Layer | Technology |
|---|---|
| Runtime | PHP 8.3+ (running on 8.4) |
| Framework | Laravel 13 |
| Admin panel | Filament 5 |
| Interactivity | Livewire 4 with Alpine.js |
| Authorisation | Spatie Laravel Permission with Filament Shield 4 |
| Styling | Tailwind CSS 4, built with Vite 7 |
| Database | MySQL |
| PDF | dompdf 3.1 |
| Files | Spatie MediaLibrary |
| Images | GD (share cards) |

It is a server-rendered monolith. There is no separate frontend application; an
earlier Next.js attempt was removed and its API layer with it.

### 2.2 Actors

| Actor | Count | Authenticated | Description |
|---|---|---|---|
| Super Admin | 1 | Yes | Unrestricted; configuration and override |
| Admin | 1 | Yes | Operational management of users and data |
| Registrar | 1 | Yes | Verification and final approval |
| Research team | 6 | Yes | Central publication management |
| Dean / Associate Dean | 10 | Yes | Faculty-level visibility |
| Head / Associate Head | 30 | Yes | Department-level visibility |
| Teacher | 2,001 | Yes | Own profile only (1,128 profiles published) |
| Public visitor | — | No | Read-only directory |

A user may hold several roles at once.

### 2.3 Operating environment

Modern browsers (Chrome, Firefox, Edge, Safari), desktop and mobile. Dark and
light appearance both supported. Server requires PHP with GD, a MySQL database,
a queue worker for mail, and outbound HTTPS for the ERP and contacts endpoints.

### 2.4 Design constraints

- **DC-1** Every data-changing action is authorised by a policy. Filament runs
  with strict authorisation: a model without a policy is denied, not allowed.
- **DC-2** A theme is a self-contained folder. Nothing outside it may depend on
  it, and its removal must not take the site down.
- **DC-3** Anything the server fetches from a stored URL is checked against
  internal address ranges first.
- **DC-4** Data written by the legacy import is not trusted as input.

---

## 3. External interfaces

### 3.1 Public web interface

| Route | Purpose |
|---|---|
| `/` | Directory home; faculty and department index |
| `/{faculty}` | One faculty with its departments and members |
| `/{faculty}/{department}` | Department member list with search and filters |
| `/{faculty}/{department}/contact` | Department office contacts, in the same layout |
| `/{faculty}/{department}/{teacher}` | Teacher profile |
| `/{faculty}/{department}/{teacher}/publication/{slug}` | A single publication |
| `/{faculty}/{department}/{teacher}/cv` | CV as PDF |
| `/{faculty}/{department}/{teacher}/vcard` | Contact as vCard |
| `/{faculty}/{department}/{teacher}/share-image.png` | Social share card |

### 3.2 Administrative interface

Filament panel at `/admin`: **40 resources**, **8 custom pages** (Dashboard,
Teacher Dashboard, My Profile, Team Directory, Import Teachers, Scopus Review,
Institution Identity, System Settings), **26 dashboard widgets**.

### 3.3 Outbound integrations

| Interface | Direction | Purpose | State |
|---|---|---|---|
| DIU HR/ERP employee API | Outbound HTTPS | Three endpoints used when a teacher is created: an OAuth (Keycloak) token, employee search, and employee profile by employee ID | Client built and exercised against the live directory; production credentials not entered |
| University contacts API | Outbound HTTP | Department Dean/Head office contacts | In use, cached 6 hours |
| SMTP | Outbound | Activation and templated bulk email | Configured; not exercised at volume |
| Legacy image host | Outbound | Teacher photographs | Retired — 1,861 images copied into local storage and served from there |
| Scopus export | File import, no network | Publication records reviewed and merged from a Scopus CSV or workbook export | In use — 1 import processed |

### 3.4 Interface constraints

- **IR-1** Outbound fetches to an operator- or import-supplied URL are refused
  if the host resolves into a private, loopback or link-local range, in IPv4 or
  IPv6 form. *(Status: D)*
- **IR-2** A failing external service degrades the page rather than breaking it;
  failures are cached briefly so an outage costs one slow request per interval,
  not one per visitor. *(Status: D)*

---

## 4. Functional requirements

### 4.1 Authentication and access

| ID | Requirement | Status |
|---|---|---|
| FR-001 | The system shall authenticate users by email and password | D |
| FR-002 | Failed sign-in attempts shall be rate limited | D — 5 attempts |
| FR-003 | Permissions shall be defined per model and per action | D — 536 permissions |
| FR-004 | A user may hold multiple roles; effective permission is the union | D |
| FR-005 | Every model reachable through the panel shall have a policy; unmatched actions shall be denied | D — 42 policies, strict mode on |
| FR-006 | A teacher shall reach only their own profile and their own dashboard | D |
| FR-007 | A migrated teacher shall gain first access through a one-time emailed link | B |
| FR-008 | An activation link shall expire after a configurable number of days (default 7) | B |
| FR-009 | Activation shall exclude teachers who already have a usable password | B |
| FR-010 | A teacher signing in by activation link shall be required to set a password before proceeding | B |

### 4.2 Faculty structure

| ID | Requirement | Status |
|---|---|---|
| FR-020 | The system shall hold Faculty → Department → Teacher | D — 6 / 31 / 2,000 records, 1,128 published |
| FR-021 | A teacher shall have one home department and may be assigned to others | D |
| FR-022 | Academic designations shall be maintained with a display order | D — 7 |
| FR-023 | Administrative roles shall be assignable per faculty or per department | D |
| FR-024 | Administrative role shall be independent of academic designation | D |
| FR-025 | Employment status shall determine whether a teacher appears publicly | D — 9 statuses |
| FR-026 | Reference data (countries, religions, blood groups, degree types and levels, result types, job types, membership types, organisations, positions) shall be maintained through the panel | D |

### 4.3 The teacher profile

| ID | Requirement | Status |
|---|---|---|
| FR-030 | A profile shall carry identity, contact, biography, research interests and a photograph | D |
| FR-031 | Educational qualifications shall be individual records with degree, institution, year and result | D — 1,831 |
| FR-032 | Employment history shall be individual records with position, organisation, period and a current flag | D — 1,952 |
| FR-033 | Training shall be individual records with organisation, year, duration and category | D — 5,032 |
| FR-034 | Awards shall be individual records with awarding body and year | D — 2,486 |
| FR-035 | Memberships shall be individual records with organisation, role, scope and period | D — 905 |
| FR-036 | Teaching areas shall be individual records | D — 4,584 |
| FR-037 | Skills, social links and documents shall be maintained per teacher | D |
| FR-038 | Research projects shall be recordable with funding, role and period | B — no records entered |
| FR-039 | The system shall score profile completeness and name what is missing | D |
| FR-040 | Profile views shall be counted, at most once per session per hour | D |
| FR-041 | Research interests shall be individual records rather than one comma-separated field | D — 854 |
| FR-042 | Teacher photographs shall be held and served by the system's own storage rather than fetched from the legacy host, including in CVs and share cards | D — 1,861 images |

### 4.4 Approval and versioning

| ID | Requirement | Status |
|---|---|---|
| FR-050 | The profile shall be divided into sections that approval is configured against | D — 15 sections |
| FR-051 | Each section shall be independently marked as requiring approval or not | D |
| FR-052 | A change to a section requiring approval shall create a pending version rather than publishing | D |
| FR-053 | A change to a section not requiring approval shall apply immediately | D |
| FR-054 | A reviewer shall approve or reject the whole version | D |
| FR-055 | A reviewer shall approve or reject an individual section of a version | D |
| FR-056 | A rejection shall require written remarks, returned to the teacher | D |
| FR-057 | Each version shall record its author, decision maker and timestamps | D |
| FR-058 | An approved version's data shall be applied to the live profile on approval | D |
| FR-059 | A previously approved version shall be restorable | B |
| FR-060 | Approval authority shall be checked per section against the reviewer's permissions | D |
| FR-061 | Rejected and superseded versions shall be retained | D |

> **Operational note.** The machinery is complete, but only 2 versions exist in
> the live database. It has not met 2,000 teachers yet. See BRD risk RK-1.

### 4.5 Publications

| ID | Requirement | Status |
|---|---|---|
| FR-070 | Publications shall be held centrally and attributed to teacher authors | D — 17,510 |
| FR-071 | A publication shall record title, abstract, venue, year, type and keywords | D |
| FR-072 | Author order and role shall be recorded per author | D |
| FR-073 | Contributors who are not DIU teachers shall be recorded without a public profile | D — 1,600 authors |
| FR-074 | Quartile, impact factor, CiteScore and h-index shall be recordable | D |
| FR-075 | Funding type, linkage and collaboration shall be classifiable | D |
| FR-076 | A date-range filter shall include records for which only the year is known | D |
| FR-077 | An incentive shall be recordable against a publication, carrying amount, status, approver and payer | D — 1,759 records |
| FR-080 | Incentive status shall move through approved and paid, each with its own timestamp and actor | D |
| FR-081 | Changes to an incentive shall be logged | D |
| FR-082 | Incentives shall be filterable by author, faculty, department, status and publication date | D |
| FR-083 | Paid, approved-unpaid and pending incentive totals shall be shown as running figures | D |
| FR-084 | A publication shall appear on its authors' public profiles without separate entry | D |
| FR-078 | Publications shall be exportable to spreadsheet with the applied filters | D |
| FR-079 | Each publication shall have its own public page with APA, IEEE and BibTeX citations | D |
| FR-085 | Each author on a publication shall be marked as first author, corresponding author or co-author | D — 293 corresponding-author attributions |
| FR-086 | The affiliation an author published under shall be recorded, together with whether it was the university's own | D — column populated by review; no rows flagged as ours yet |
| FR-087 | The publication list shall show whether a record arrived from an external source or was created in FMS | D |
| FR-088 | Publications on a profile shall be grouped by year | D |

### 4.6 The public directory

| ID | Requirement | Status |
|---|---|---|
| FR-090 | The public site shall render only active, non-archived teachers | D |
| FR-091 | A visitor shall search within a department by name, email or employee ID | D |
| FR-092 | Results shall be filterable by designation and administrative role | D |
| FR-093 | Administrative office holders shall be listed ahead of other members | D |
| FR-094 | A profile shall present its sections as tabs, deep-linkable by `?tab=` | D |
| FR-095 | A profile shall state a non-active employment status | D — 250 teachers affected |
| FR-096 | Department office contacts shall render inside the department layout, keeping its navigation | D |
| FR-097 | A CV shall be downloadable as PDF with configurable sections and watermark | D |
| FR-098 | Contact details shall be downloadable as vCard | D |
| FR-099 | Navigation shall not reload the page | D — `wire:navigate` |
| FR-100 | An unknown faculty, department or teacher shall return 404 | D |

### 4.7 Presentation and theming

| ID | Requirement | Status |
|---|---|---|
| FR-110 | The public site shall be rendered by a selectable theme | D — 4 installed: DIU, Modern, Ledger, Aurora |
| FR-111 | A theme shall be a self-contained folder, addable by dropping it in | D |
| FR-112 | A theme shall be listed as available only if it ships every view the site needs | D — 14 required views |
| FR-113 | If the selected theme is removed, the site shall fall back to an installed one | D |
| FR-114 | If no theme is installed, public routes shall return 503 with an explanation, and the panel shall stay reachable | D |
| FR-115 | A theme shall describe itself through a `theme.json` and may ship a screenshot | D |
| FR-116 | An administrator shall preview a theme on the live site without switching it for visitors | D |
| FR-117 | Branding, colour palette and typography shall be configurable from settings | D — 160 settings |
| FR-118 | The site shall support light and dark appearance | D |

### 4.8 Search-engine visibility and sharing

| ID | Requirement | Status |
|---|---|---|
| FR-130 | Every public page shall carry a canonical URL | D |
| FR-131 | Directory, faculty, department, profile and publication pages shall carry OpenGraph and Twitter tags | D |
| FR-132 | Pages shall carry Schema.org data — CollectionPage, Person, ScholarlyArticle — with breadcrumbs | D |
| FR-133 | A teacher profile shall present a generated 1200×630 share card carrying photograph, name, designation and department | D |
| FR-134 | The Person schema shall suggest up to five publications that carry an abstract | D |
| FR-135 | Contact details shall not appear in structured data or share images | D |
| FR-136 | Structured data shall be escaped so page content cannot break out of its script element | D |
| FR-137 | A sitemap shall be generatable on demand with a configurable validity date | D |

### 4.9 Reporting

| ID | Requirement | Status |
|---|---|---|
| FR-150 | Administrators shall see system-wide counts and trends | D |
| FR-151 | Publication statistics shall break down by year, type, quartile, funding, linkage, collaboration and source | D — 12 widgets |
| FR-152 | Teachers shall see their own profile, publication and research statistics | D — 9 widgets |
| FR-153 | Most-viewed profiles shall be listed | D |
| FR-154 | Queue and system health shall be visible to administrators | D |
| FR-155 | Teacher and publication data shall be exportable to spreadsheet | D |

### 4.10 Administration and operations

| ID | Requirement | Status |
|---|---|---|
| FR-170 | An administrator shall create a teacher and its user account together | D |
| FR-171 | A teacher record shall be creatable from an ERP lookup by employee ID | B — client built (token, search, profile-by-id); credentials not entered |
| FR-172 | Field mapping between ERP and FMS shall be configurable | D |
| FR-173 | Teachers shall be importable in bulk from a file | D |
| FR-174 | Duplicate teacher records shall be detectable | D — comparison assisted by Vertex AI (Gemini 2.5 Flash) |
| FR-180 | Re-importing a teacher the system already holds shall merge into the existing record rather than overwrite it | D |
| FR-175 | Email templates shall be editable in the panel | D — 4 templates |
| FR-176 | Bulk email shall be sendable to a selected set of teachers using a template | B |
| FR-177 | Mail transport shall be configurable in the panel | D |
| FR-178 | Long-running work shall run on a queue | D |
| FR-179 | An administrator shall assign roles within the limits of their own authority | D |

### 4.11 Scopus review and merge

Requested by the research team. A Scopus export is a file, not a feed: this
subsystem makes no network call and there is no Scopus API in the system.

| ID | Requirement | Status |
|---|---|---|
| FR-190 | A Scopus export shall be uploadable as CSV or workbook and read without a network call | D — 1 import processed |
| FR-191 | The institution's own affiliation shall be recognised by configurable patterns, with explicit exclusions for similarly named institutions | D — maintained on the Institution Identity page |
| FR-192 | Each incoming record shall be resolved against the existing publication record and reported as new, already held, or ambiguous | D |
| FR-193 | An incoming author shall be matched to a teacher where possible and recorded as an external author where not | D |
| FR-194 | The corresponding author shall be identified from the export | D — 293 attributions |
| FR-195 | A reviewer shall accept or reject each record in the browser, and accepted decisions shall be applied on a queue | D |
| FR-196 | The uploaded file, the matching options used and the decisions taken shall be retained so a review is reproducible | D |
| FR-197 | An external author later identified as a DIU teacher shall be mergeable into that teacher | D |

---

## 5. Data requirements

### 5.1 Principal entities

Users · Roles · Permissions · Teachers · Faculties · Departments · Designations ·
Administrative roles · Employment statuses · Job types · Teacher versions ·
Approval settings · Educations · Job experiences · Training experiences ·
Teaching areas · Awards · Memberships · Skills · Social links · Research
projects · Publications · Publication authors · Authors · Publication types ·
Quartiles · Linkages · Grant types · Research collaborations · Publication
incentives · Incentive logs · Organizations · Positions · Majors · Degree types ·
Degree levels · Result types · Countries · Religions · Blood groups · Genders ·
Membership types · Email templates · Integration mappings · Notification
routings · Research interests · Scopus imports · Scopus author IDs · Settings.

### 5.2 Volumes

Read from the production database on 2026-08-30.

| Entity | Records |
|---|---|
| Publication authors | 25,485 |
| Publications | 17,510 |
| Training experiences | 5,032 |
| Teaching areas | 4,584 |
| Organizations | 4,422 |
| Awards | 2,486 |
| Users | 2,016 |
| Teachers | 2,000 (1,128 approved, 872 archived) |
| Activity log entries | 2,017 |
| Job experiences | 1,952 |
| Photographs held as media | 1,861 |
| Educations | 1,831 |
| Publication incentives | 1,759 |
| Authors (non-teacher) | 1,600 |
| Memberships | 905 |
| Research interests | 854 |
| Permissions | 536 |
| Settings | 160 |
| Departments | 31 |
| Approval sections | 15 |
| Administrative roles | 11 |
| Roles | 9 |
| Faculties | 6 |
| Scopus imports | 1 |
| Teacher versions | 2 |

### 5.3 Provenance: what the legacy schema looked like

The normalised entities above did not exist before. The legacy `teacher` table
held **one free-text column per whole category**, and every entry a teacher had
in that category lived inside it as prose:

| Legacy column | Became | Records produced |
|---|---|---|
| `academicQualification` | `educations` | 1,831 |
| `publication` | `publications` + `publication_authors` | 17,510 |
| `previousEmployment` | `job_experiences` | 1,952 |
| `trainingExperience` | `training_experiences` | 5,032 |
| `awardScholarship` | `awards` | 2,486 |
| `membership` | `memberships` | 905 |
| `teachingArea` | `teaching_areas` | 4,584 |
| | **Total** | **34,300** |

Seven columns per teacher became 34,300 individually addressable records —
roughly 17 per teacher. This is what makes every counting, filtering and
reporting requirement in §4.9 possible; none of it could be asked of the
legacy shape.

Access was equally coarse: authentication was **per department**, one shared
account, so the legacy data carries no attribution to individuals. Records
migrated from it therefore begin their audit history at import.

**Migration mechanism.** Splitting the prose was done by machine parsing with
review (`ExportOldTeachers*Command`, reading the `old_db` connection), not by
retyping. Failed parses were logged rather than silently dropped. The commands
remain in the codebase as a record of how each field was interpreted, and are
not part of the running application.

### 5.4 Data quality, as inherited

| Field | State | Consequence |
|---|---|---|
| `publication_date` | 1,465 of 17,510 | Date-range reporting falls back to year (FR-076) |
| `publication_year` | 17,003 of 17,510 (97%) | The practical basis for reporting |
| Publication abstract | 7,053 of 17,510 (40%) | Only abstracted papers are suggested (FR-134) |
| Teacher photograph | 1,861 images now held locally; 80 of 1,128 published teachers have none | Initials block shown instead; the migrated files are the legacy 90–120px thumbnails |
| Employment status | 219 on study leave, 31 on leave | Surfaced publicly (FR-095) |

### 5.5 Retention

Soft deletes throughout: records are withdrawn from view, not removed.
Superseded and rejected versions are retained. A retention period is an open
business decision (BRD DEC-5).

---

## 6. Non-functional requirements

### 6.1 Performance

| ID | Requirement | Measured |
|---|---|---|
| NFR-01 | A department page shall render in under 250 ms for the largest department | 154–203 ms for 318 teachers, 40 queries |
| NFR-02 | A profile page shall render in under 250 ms | ~140 ms |
| NFR-03 | A publication page shall render in under 100 ms | ~41 ms |
| NFR-04 | A CV PDF shall generate in under 2 s | ~0.5 s |
| NFR-05 | A share card shall be generated once and cached | 490 ms first, 0.3 ms cached |
| NFR-06 | External lookups shall be cached rather than repeated per request | Contacts cached 6 h, failures 5 min |
| NFR-07 | List pages shall not issue per-row queries | Verified: no N+1 on directory pages |

### 6.2 Security

| ID | Requirement | Status |
|---|---|---|
| NFR-10 | Authorisation shall be enforced server-side on every action, not by hiding controls | D |
| NFR-11 | A model without a policy shall be denied | D — strict mode |
| NFR-12 | Sign-in shall be rate limited | D |
| NFR-13 | Output shall be escaped by default; unescaped output shall be developer-authored only | D |
| NFR-14 | Structured data shall be escaped against script-element breakout | D |
| NFR-15 | Server-side fetches of stored URLs shall be refused for internal addresses (IPv4 and IPv6) | D |
| NFR-16 | Dynamic SQL shall carry no unbound user input | D — verified integer casts |
| NFR-17 | Dependencies shall carry no known advisories | D — `composer audit` clean |
| NFR-18 | Personal contact details shall be excluded from machine-readable output | D |
| NFR-19 | In production: debug off, secure session cookies, environment set to production | **N** — outstanding for deployment |

### 6.3 Reliability

| ID | Requirement | Status |
|---|---|---|
| NFR-30 | Removing the active theme shall not take the public site down | D |
| NFR-31 | Removing every theme shall produce an explanatory 503, with the panel still reachable | D |
| NFR-32 | An unavailable external service shall not break a page | D |
| NFR-33 | Queued mail shall survive a failed send and be retryable | D |

### 6.4 Maintainability

| ID | Requirement | Status |
|---|---|---|
| NFR-40 | An automated test suite shall cover the behaviour that has previously broken | **N** — the suite reported in revision 2.0 (84 tests, 764 assertions) is not in the repository: `tests/` is absent from both the working tree and version control. PHPUnit is still configured and points at `tests/Unit` and `tests/Feature`. See OI-7. |
| NFR-41 | Themes shall be verifiable as independent of one another | B — the rule holds by construction (a theme resolves only its own views, with a documented fallback), but the test that enforced it went with the suite |
| NFR-42 | Shared logic shall not be duplicated per theme | D |
| NFR-43 | Configuration shall be changeable without deployment | D — 160 settings |

### 6.5 Usability and accessibility

| ID | Requirement | Status |
|---|---|---|
| NFR-50 | The public site shall be usable on a phone | D |
| NFR-51 | Light and dark appearance shall both be legible | D — contrast verified |
| NFR-52 | Long lists shall remain navigable — sticky tabs, sticky filters | D |
| NFR-53 | Bengali and English shall render together | D — font fallback configured |
| NFR-54 | On a phone, the directory search and filter bar shall collapse rather than consume the screen | D |

---

## 7. Traceability: business requirement → specification

| BRD | SRS |
|---|---|
| BR-01, BR-03 | FR-030…FR-037, FR-170 |
| BR-02 | FR-031…FR-036, FR-070 |
| BR-04 | FR-039 |
| BR-05 | FR-097 |
| BR-10…BR-14 | FR-050…FR-058, FR-061 |
| BR-15 | FR-059 |
| BR-16 | FR-060 |
| BR-20…BR-24 | FR-003…FR-006, FR-023, FR-179 |
| BR-30…BR-33 | FR-090…FR-096, FR-079 |
| BR-34 | FR-130…FR-137 |
| BR-35 | FR-110…FR-118 |
| BR-36 | FR-135, NFR-18 |
| BR-40…BR-42 | FR-150…FR-155 |
| BR-43 | FR-076 |
| BR-50…BR-52 | FR-007…FR-010 |
| BR-53 | FR-171, FR-172, FR-180 |
| BR-54, BR-56 | FR-070…FR-073 |
| BR-55 | FR-175, FR-176 |
| BR-57 | FR-084 |
| BR-58 | FR-077, FR-080, FR-081 |
| BR-59 | FR-082, FR-083 |
| BR-60 | FR-190, FR-192, FR-195, FR-196 |
| BR-61 | FR-191 |
| BR-62 | FR-193, FR-197 |

---

## 8. Not built

| Item | Reason |
|---|---|
| Multi-office approval routing | Single-authority approval meets present policy; the section model would carry it |
| Research project records | No business process defined; the module exists and is empty |
| Public search across all faculties | Search is per department today |
| Notification escalation on stalled approvals | Awaiting operational experience |
| Theme screenshots | Mechanism built; images not yet produced |
| Production hardening | Deployment-time work — NFR-19 |

---

## 9. Open items

| ID | Item |
|---|---|
| OI-1 | Approval workflow unproven at scale — 2 versions to date |
| OI-2 | Activation email never sent at volume |
| OI-3 | ERP credentials not issued. The base and token URLs are configured; client ID, username and password are empty, so the client reports itself unconfigured |
| OI-4 | **Closed.** Photographs migrated into local storage (1,861 images); what was copied remains at the legacy thumbnail resolution |
| OI-5 | Code style has never been normalised — no functional effect |
| OI-6 | The system has been verified by rendering, not by human visual review of every page |
| OI-7 | The automated test suite is absent from the repository (NFR-40); PHPUnit configuration still expects it |
| OI-8 | `active_theme` is set to `theme_diu_vanguard`, which ships no `layouts/app.blade.php` and is therefore not an installed theme. The site is serving the fallback (FR-113 working as specified), but the setting should be pointed at a real theme |
| OI-9 | Scopus review has one import behind it; `used_our_affiliation` and `scopus_author_ids` carry no rows yet |

---

## 10. Verification

| Method | Coverage |
|---|---|
| Automated tests | **Not currently available** — the suite reported in revision 2.0 is not in the repository (OI-7) |
| Rendered-output checks | Every public route rendered under every installed theme, including with the active theme deleted |
| Measured performance | Timings and query counts recorded per page (§6.1) |
| Dependency audit | `composer audit` clean |
| Static checks | PHP syntax across 497 application files; all 152 Blade templates compile |
| Live data check | Every record count in §5.2 read directly from the production database on 2026-08-30 |

---

*This specification describes the system as delivered on 2026-08-30. Figures
were measured from the running system rather than estimated.*

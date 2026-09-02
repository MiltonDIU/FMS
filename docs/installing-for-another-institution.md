# Installing FMS for Another Institution

What has to change when this system is stood up somewhere that is not Daffodil
International University.

This is a survey, not a task list to work through today. It exists so that the
question "what is still hard-wired to DIU?" has a written answer instead of being
re-discovered by grep every time it comes up. Nothing here is broken for the
current install — every item behaves correctly at DIU. They are the places where
a second institution would need attention.

Every file reference was checked against the code, not assumed.

---

## The three categories

Reading this document is much easier once these are kept apart.

**A. Already portable.** Resolves from the `settings` table or from lookup rows,
with the Daffodil value as a fallback. A new institution edits a field. No code
change, no deployment.

**B. Migration scaffolding.** Code that exists only to carry data across from the
old faculty site. For another institution these are not settings to fill in —
they are either deleted or pointed at that institution's own legacy source. They
will also stop mattering here once the migration is finished.

**C. Hard-wired.** A value that lives only in code. This is the real list.

---

## A. Already portable

Nothing to do for these. Listed so nobody spends time on them.

| Area | Where it is configured |
| --- | --- |
| Institution name, match patterns, email domains, student-address rule | `App\Helpers\Institution` + the **Institution Identity** page |
| Site name, logo, addresses, footer, watermark, meta text | `App\Helpers\Branding` + **Settings** |
| HR API base URL, token URL, employee search path, employee profile path | **Settings → Teacher API Integration** |
| ERP field mapping (93 rules, including name-to-id lookups) | `integration_mappings` table |
| Colours, fonts, frontend themes | Settings |
| Faculties, departments, designations, employment statuses, religions, blood groups, countries, degree levels | Database, seeded |
| Email templates and notification routing | Database |
| Timezone, locale, app URL | `.env` |

`Institution` and `Branding` are the pattern the rest of this document points at:
a `DEFAULTS` constant holding the Daffodil value, and a `get()` that prefers the
setting. Anything in category C should end up shaped like that.

---

## B. Migration scaffolding

These exist to move data off `faculty.daffodilvarsity.edu.bd` and the old
database. They are worth listing because a reader will otherwise mistake them for
category C and try to make them configurable — which would be effort spent on
code that is meant to be deleted.

| What | Where | What happens to it |
| --- | --- | --- |
| Legacy photograph base URL | `App\Models\Teacher::PHOTO_BASE_URL` | Delete with the fetch, once every photograph is local. Until then it is the address the pictures are being pulled from. |
| `teachers:download-photos` | `App\Console\Commands\DownloadTeacherPhotosCommand` | Same. Its `--reorganise` pass is separately useful and should outlive it, or move. |
| Old-database sync | `App\Services\SingleTeacherSyncService`, the **Sync Old Data** row action | Delete once nothing is left to sync. |
| `export:old-teachers-*` commands | `app/Console/Commands/ExportOldTeachers*.php` | One-time conversions, already run. They also call AI providers directly (Anthropic, Groq), which is a separate concern from the institution's own APIs. |
| `update:teacher-details-from-json` | `App\Console\Commands\UpdateTeacherExtraDetailsFromJsonCommand` | Reads a file from an operator's desktop. One-time. |

**When the migration is signed off, this whole section should be deleted from the
codebase rather than made configurable.** That is the cheapest way to shrink the
portability problem.

---

## C. Hard-wired — the actual list

Ordered by how badly it fails if missed.

### C1. A country's primary key, written as a literal

`app/Models/Organization.php:292-293`

```php
// Auto-detect country if null or Bangladesh (18)
if ($countryId === null || $countryId == 18) {
```

The worst item on this list, because it fails silently. On another install row 18
is some other country, so organisations get filed under it and nothing reports a
problem. Every other item on this list produces a visible error or an obviously
wrong screen; this one produces plausible, wrong data.

**Fix:** resolve by slug. `app/Helpers/FormPayloadResolver.php:189` already does
exactly this and can be copied:

```php
Country::where('slug', 'bangladesh')->first()?->id
```

Better still, take the home country from a setting so the slug is not hard-wired
either.

### C2. Email domain, used to invent addresses

`app/Services/IntegrationService.php:187` — `$employee_id . '@daffodilvarsity.edu.bd'`
`app/Observers/TeacherObserver.php:63` — `... . '@diu.edu.bd'`

Both are fallbacks for when an imported record has no email. At another
institution they mint accounts at a domain that institution does not own.

**Fix:** the setting already exists — `Institution::get('email_domains')` returns
the list, first entry is the primary. These two call sites simply do not read it.
Small change, no new setting.

### C3. Currency, hard-wired in seven places

```
app/Filament/Resources/IncentiveLogs/Tables/IncentiveLogsTable.php:42          ->money('BDT')
app/Filament/Resources/PublicationIncentives/Tables/PublicationIncentivesTable.php:40  ->money('BDT')
app/Filament/Resources/Publications/Tables/PublicationsTable.php:140           ->money('BDT')
app/Filament/Resources/ResearchProjects/Tables/ResearchProjectsTable.php:46    ->money('BDT')
app/Filament/Resources/ResearchProjects/Schemas/ResearchProjectForm.php:85     ->prefix('BDT')
app/Filament/Resources/ResearchProjects/Schemas/ResearchProjectForm.php:88     ->default('BDT')
app/Filament/Resources/Authors/RelationManagers/PublicationsRelationManager.php:60  ->money('BDT')
```

Note `research_projects` already has a `currency` column, so the data model
allows more than one currency while the screens assume one.

**Fix:** an `institution_currency` setting and a one-line helper the seven call
sites read. Cheap and mechanical.

### C4. Department contacts service

`app/Services/DepartmentContacts.php:49` — `PHOTO_BASE_URL` constant
`config/services.php:41` — `diu_contacts_api`, env-overridable but DIU by default

The API endpoint can be moved with an env var; the photograph base cannot.

**Fix:** move both into Settings beside the HR API fields, following the pattern
used for the HR API paths.

### C5. Scopus name and affiliation heuristics

`app/Services/Scopus/RecordResolver.php` — stop-words that carry no weight in a
Bangladeshi name
`app/Services/Scopus/CorrespondingAuthors.php` — where a Bangladeshi surname
begins

Unlike everything above, these are not strings standing in for a setting. They
are assumptions about how names are written, tuned against a real export.

**Fix:** the stop-word list can become a setting. The rest cannot be made
universal cheaply, and pretending otherwise would be worse than documenting it.
The honest position is: **matching accuracy is tuned for Bangladeshi names and
will degrade elsewhere**, and a new institution should expect to re-tune it.

The affiliation side is already portable — `Institution` handles patterns,
sister-institution names and email domains.

### C6. A fresh install starts as Daffodil

`Institution::DEFAULTS`, `Branding::DEFAULTS` and `SettingsSeeder` carry Daffodil
names and DIU URLs (`https://api.diu.edu.bd`, the auth0 token endpoint).

Not a blocker — every one is overridable — but a new institution sees Daffodil
branding until somebody works through the settings tabs, and nothing tells them
which tabs those are.

**Fix:** an install command (`php artisan fms:install`) that asks for the name,
short name, email domain, country, currency and API URLs, and writes them to
settings. It turns "find the settings" into "answer six questions", and makes the
Daffodil defaults harmless because they are overwritten on day one.

---

## Suggested order

1. **C1** on its own. It is small, and it is the only one that fails quietly.
2. **C2 and C3** together — both are mechanical, and both reuse settings or
   patterns that already exist.
3. **C4**, alongside whatever else touches the integration settings tab.
4. **B** — delete the migration scaffolding once the migration is signed off.
   This removes more DIU-specific code than any of the fixes above.
5. **C6**, the install command, once C1–C4 mean there is a coherent set of
   questions to ask.
6. **C5** last, or never, with the limitation written down.

## Keeping this honest

Anything added to the codebase that names Daffodil, DIU, Bangladesh, BDT or a
`daffodilvarsity.edu.bd` address belongs in this document, or better, in a
setting. A quick check:

```bash
grep -rniE 'daffodil|diu\.edu|BDT|Bangladesh' app/ config/ --include='*.php'
```

Hits inside `app/Console/Commands/ExportOldTeachers*` and the other category B
files are expected. Hits anywhere else are worth a second look.

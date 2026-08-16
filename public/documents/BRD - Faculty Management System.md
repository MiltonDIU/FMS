# Business Requirements Document (BRD)

## Faculty Management System (FMS) — Daffodil International University

| | |
|---|---|
| **Document** | Business Requirements Document |
| **System** | Faculty Management System (FMS) |
| **Organisation** | Daffodil International University |
| **Version** | 2.0 |
| **Date** | 2026-08-01 |
| **Status** | Rewritten against the delivered system |
| **Supersedes** | *Project Objectives & Scope Document (Final)* |
| **Companion** | *SRS - Faculty Management System.md* |

---

## 0. About this revision

The first BRD was written before development began and described an intended
system. This revision was written after Phase-1 was built and populated with
live data, and it separates three different things that the earlier documents
did not:

- **Delivered** — built, running, and verified against production data.
- **Built, not yet in use** — the feature exists and works, but the business has
  not started using it, so there is no operational evidence yet.
- **Not built** — deferred, descoped, or awaiting a dependency.

Every quantity in this document was measured from the working system on the date
above, not estimated. Where a requirement is marked delivered, it is because it
was exercised against the real data set of 2,000 teacher records.

---

## 1. Executive summary

DIU holds records for **2,000 academic staff** — **1,128 of them currently
published** — across **6 faculties** and **31 departments**, together with
**17,510 publication records**.

Until this project, none of that was data in any useful sense. Teachers sent
PDF or DOCX files to the Registrar Office, which acted as the university's admin
officer for faculty information; a department user signed in with a **login
shared by the whole department** and typed the contents into seven free-text
fields — one for every degree, one for every publication, one
for every previous post, and so on. Teachers had no accounts. Nothing inside
those fields could be counted. No change could be traced to a person or undone.

FMS replaces that with a governed, self-service platform. Teachers maintain
their own profiles; every change is captured as a version and published only
after approval; academic data is stored in structured, countable form; and the
public directory is generated from the approved data rather than maintained
separately.

Phase-1 is complete and carries the university's live faculty data. The
substantial parts of Phases 2 and 3 — ERP-assisted onboarding and centralised
publication management — were built alongside it rather than after it.

---

## 2. Background: the problem being solved

### 2.1 How it worked before

| Aspect | Legacy practice |
|---|---|
| Accounts | **One shared login per department.** There were no individual teacher accounts. |
| Data collection | Teachers sent **PDF or DOCX files** to the Registrar Office, which acted as the university's admin officer for faculty data |
| Data entry | A department user signed in with the shared account and typed the contents in |
| Storage | **One free-text column per whole category** — see 2.2 |
| Update path | Teacher → PDF/DOCX → Registrar Office → department account → legacy system, by hand |
| Approval | Informal; a document read by eye before typing |
| History | None; an overwrite lost the previous value |
| Reporting | Manual counting from prose |
| Public site | A single, seldom-updated profile page per teacher |

### 2.2 The shape of the legacy data

This is the heart of the problem, and it is worth stating precisely. The legacy
`teacher` table held **one text column for each entire category** of a teacher's
career. Everything a teacher had ever done in that category — every degree,
every paper, every post — went into that single field as prose:

| Legacy column | Held |
|---|---|
| `academicQualification` | Every degree, in one field |
| `publication` | Every publication, in one field |
| `previousEmployment` | Every previous post, in one field |
| `trainingExperience` | Every training, in one field |
| `awardScholarship` | Every award and scholarship, in one field |
| `membership` | Every professional membership, in one field |
| `teachingArea` | Every teaching area, in one field |

Seven text fields per teacher. Nothing inside them could be counted, sorted,
filtered, dated or linked to anything else — a publication was a sentence in a
paragraph, not a record.

Converting them was itself a project: the free text had to be read and split
into individual records, which was done with machine parsing and review rather
than by retyping. Those seven fields became **34,300 individual records** across
seven tables — an average of about 17 real records hidden inside each teacher's
seven paragraphs.

### 2.3 What that cost the university

- **BP-1 — Teachers had no way in.** There was no such thing as a teacher
  account. Every correction, however small, travelled through the Registrar
  Office and a department user.
- **BP-2 — The Registrar Office was a data-entry bottleneck**, not a reviewing
  authority. It received the paperwork and it was where the typing happened.
- **BP-3 — Nothing could be counted.** With seven prose fields per teacher,
  accreditation and ranking submissions were reconstructed by reading.
- **BP-4 — No accountability, by design.** A department shared one login, so a
  change could be traced to a department at best, never to a person — and there
  was no history to trace it in.
- **BP-5 — The public directory decayed.** It was maintained separately from the
  authoritative record, so the two diverged.
- **BP-6 — Faculty and department leadership had no visibility** of their own
  units without asking someone to count.
- **BP-7 — Publications and teachers were not connected.** The system the
  research team used held publications with no relationship to the teacher
  records, so answering *how much incentive has this teacher received* meant
  working it out by hand, paper by paper. There was no way to see, per teacher
  or per department, what had been paid.

---

## 3. Business objectives and how success is measured

| ID | Objective | Success measure | Status |
|---|---|---|---|
| BO-1 | Move profile maintenance to the people who own the data | Teachers can edit every profile section themselves | **Delivered** |
| BO-2 | Make the Registrar Office an approving authority rather than a typing pool | No profile change reaches the public site without a review decision | **Delivered** |
| BO-3 | Make academic data countable | Qualifications, publications, experience, awards, memberships, training and teaching areas each held in their own structured records | **Delivered** — 7 text columns per teacher parsed into 34,300 records |
| BO-4 | Make every change reversible and attributable | Each submission stored as a version; prior versions restorable | **Delivered**, awaiting operational use |
| BO-5 | Publish one authoritative directory | The public site renders from the approved record, with no separate maintenance | **Delivered** |
| BO-6 | Give leadership figures without asking for them | Faculty-, department- and teacher-level dashboards | **Delivered** — 25 dashboard widgets |
| BO-7 | Cut duplicate entry between systems | Faculty records fetched from ERP by employee ID; publications entered once centrally | **Built** — awaiting ERP go-live |
| BO-8 | Support accreditation and ranking submissions | Publication data queryable by year, quartile, type, funding and department | **Delivered** |
| BO-9 | Give the research team ownership of the publication record, so what is published under the university's name is visible and accountable | Publications held centrally, attributed to authors, and shown on their profiles without separate entry | **Delivered** — 17,510 publications |
| BO-10 | Make publication incentives traceable to the teacher who received them | Per-teacher and per-department incentive totals obtainable by filtering | **Delivered** — 1,759 incentive records |

---

## 4. Stakeholders

| Stakeholder | Interest | Role in the project |
|---|---|---|
| University management | Accurate institutional data; public standing | Approves scope and funding |
| Registrar Office | Correctness of the academic record | Final approval authority; primary reviewer |
| Faculty deans and department heads | Visibility of their own unit | Consumers of unit dashboards |
| Teachers (2,000 records, 1,128 published) | Control of their own professional record | Primary users |
| Research team | A publication record they own and can account for; incentive payments traceable to a teacher | Requested and operates the central publication and incentive modules |
| IT and development | Maintainability and security | Builds and operates |
| Public — students, applicants, peers, media | Finding and reading faculty profiles | Read-only audience |

---

## 5. Scope

### 5.1 In scope and delivered

| Area | Includes |
|---|---|
| Faculty structure | Faculty → Department → Teacher hierarchy; academic designations |
| Teacher profile | Personal and contact details, biography, research interests, photograph |
| Structured academic record | Education, publications, experience, training, awards, memberships, skills, teaching areas, social links, documents |
| Approval and versioning | Per-section approval configuration, submission versions, approve/reject with remarks, restore |
| Access control | Role-based permissions enforced on every action |
| Administrative roles | Dean, associate dean, head, associate head, held alongside academic designation |
| Public directory | Faculty, department, profile, publication and contact pages |
| Profile outputs | CV as PDF, contact as vCard, social share card |
| Dashboards | Role-specific dashboards for administrators, research team and teachers |
| Onboarding | One-time activation links so migrated teachers can set their own password |
| Presentation | Four selectable public themes; branding, colours and fonts configurable without a developer |

### 5.2 Built and awaiting a business dependency

| Area | Waiting on |
|---|---|
| ERP-assisted teacher creation | ERP endpoint availability and credentials |
| Research project records | Business process definition; no records entered yet |
| Teacher activation email | Rollout decision; never sent at volume |

### 5.3 Explicitly out of scope

Payroll and finance · attendance and leave · student academic records ·
performance appraisal · course and timetable management · admissions.

---

## 6. Business requirements

Priority: **M** must-have · **S** should-have · **C** could-have.
Status: **D** delivered · **B** built, not in operational use · **N** not built.

### 6.1 Profile ownership and maintenance

| ID | Requirement | Pri | Status |
|---|---|---|---|
| BR-01 | A teacher shall hold an individual account and maintain their own profile without going through an office | M | D |
| BR-02 | Every academic record type shall be captured as structured, individually countable entries | M | D |
| BR-03 | An administrator shall be able to maintain a profile on a teacher's behalf | M | D |
| BR-04 | The system shall show a teacher how complete their profile is and what is missing | S | D |
| BR-05 | A teacher shall be able to take their profile away as a formatted CV | S | D |

### 6.2 Governance, approval and audit

| ID | Requirement | Pri | Status |
|---|---|---|---|
| BR-10 | No change to a public profile shall become visible without an approval decision | M | D |
| BR-11 | The university shall decide, per section of the profile, whether that section needs approval | M | D |
| BR-12 | A reviewer shall approve or reject a submission section by section, not only as a whole | S | D |
| BR-13 | A rejection shall carry a written reason back to the teacher | M | D |
| BR-14 | Every submission shall be retained as a version attributable to a person and a time | M | D |
| BR-15 | An earlier approved version shall be restorable | M | B |
| BR-16 | The Registrar Office shall hold final approval authority; Super Admin may override | M | D |

### 6.3 Access control

| ID | Requirement | Pri | Status |
|---|---|---|---|
| BR-20 | Access shall be governed by role, enforced on every action and not only hidden in the interface | M | D |
| BR-21 | A person shall be able to hold several roles at once | M | D |
| BR-22 | Administrative office shall be recorded separately from academic designation | M | D |
| BR-23 | Leadership shall see their own unit's data; system-wide visibility shall be limited to central roles | M | D |
| BR-24 | An unassigned or unauthorised action shall be refused rather than silently allowed | M | D |

### 6.4 The public directory

| ID | Requirement | Pri | Status |
|---|---|---|---|
| BR-30 | The public site shall be generated from the approved record | M | D |
| BR-31 | A visitor shall find a teacher by faculty, by department, or by searching | M | D |
| BR-32 | A profile shall state when a teacher is not currently at their post (leave, study leave, deputation) | M | D |
| BR-33 | Publications shall be readable as individual, citable pages | S | D |
| BR-34 | Profiles and publications shall be discoverable by search engines and preview correctly when shared | S | D |
| BR-35 | The university shall change the site's look, branding and fonts without a developer | S | D |
| BR-36 | Personal contact details shall not be broadcast to crawlers or in share previews | M | D |

### 6.5 Institutional reporting

| ID | Requirement | Pri | Status |
|---|---|---|---|
| BR-40 | Faculty-, department- and teacher-level statistics shall be available on screen | M | D |
| BR-41 | Publication data shall be filterable by year, type, quartile, funding and unit | M | D |
| BR-42 | Publication and teacher data shall be exportable for offline submission | S | D |
| BR-43 | A date-range report shall include records for which only the year of publication is known | M | D |

### 6.6 Onboarding and integration

| ID | Requirement | Pri | Status |
|---|---|---|---|
| BR-50 | Migrated teachers shall gain first access by a one-time emailed link and set their own password | M | B |
| BR-51 | Activation shall skip teachers who have already set a password | M | B |
| BR-52 | The validity period of an activation link shall be configurable | S | B |
| BR-53 | A teacher record shall be creatable from an ERP lookup by employee ID | S | B |
| BR-55 | Bulk email to teachers shall use editable templates | S | B |

### 6.7 Publications and research incentives

Requested by the research team, who own this data.

| ID | Requirement | Pri | Status |
|---|---|---|---|
| BR-54 | Publications shall be entered once, centrally, and attach to the right teachers | M | D |
| BR-56 | The research team shall hold and maintain the publication record, so that what is published under the university's name is visible and accountable | M | D |
| BR-57 | A publication shall appear on its authors' public profiles without separate entry | M | D |
| BR-58 | The incentive paid for a publication shall be recorded against it, with who approved and who paid | M | D |
| BR-59 | The total incentive received by a teacher shall be obtainable by filtering, not by manual calculation | M | D |

---

## 7. Business rules

| ID | Rule |
|---|---|
| BRule-01 | Only one version of a profile is publicly live at any time. |
| BRule-02 | A rejected version is retained, never deleted. |
| BRule-03 | Academic designation and administrative office are independent; gaining or losing an office does not change designation. |
| BRule-04 | A teacher whose employment status is not "Active" but who remains on the establishment stays visible, with the status shown. |
| BRule-05 | A teacher whose status ends the appointment (retired, resigned, terminated) leaves the public directory but is retained in the record. |
| BRule-06 | Publications are attributed to teachers as authors; contributors who are not DIU teachers are recorded without a public profile. |
| BRule-07 | Personal contact details appear on the profile page for a reader who navigates to it, and are excluded from machine-readable metadata and share images. |
| BRule-08 | Where only the year of a publication is known, the year governs date-range reporting. |

---

## 8. Benefits realised

| Benefit | Evidence |
|---|---|
| Administrative effort removed | 2,000 profiles maintainable by their owners; previously every change passed through the Registrar Office and a shared department account |
| The record became countable | Seven free-text columns per teacher became **34,300 individual records**: 17,510 publications, 5,032 training records, 4,584 teaching areas, 2,486 awards, 1,952 employment records, 1,831 qualifications, 905 memberships |
| Accountability | Every submission attributable to a named person and reversible; the legacy shared department logins could attribute a change to a department at best |
| One directory, not two | The public site and the authoritative record are the same data |
| Institutional visibility | 25 dashboard widgets across administrative, research and teacher views |
| Incentive spending became answerable | 1,759 incentive records totalling BDT 23.5M, filterable by teacher, department, faculty, status and date — previously a manual calculation with no link between a publication and its author |
| Presentation under university control | Four themes, branding, colour palette and typography configurable from settings |

---

## 9. Assumptions

- Every teacher has a working institutional email address, since first access
  depends on it.
- The Registrar Office has the staff to review submissions at the rate teachers
  produce them.
- Legacy data is accepted as the starting point; corrections are made through
  the new system rather than by re-importing.
- Where the legacy record holds only a year of publication, that is treated as
  complete rather than as missing data.

## 10. Dependencies

| Dependency | Needed for | State |
|---|---|---|
| ERP employee endpoint | BR-53 | Not yet available |
| Institutional mail relay | BR-50, BR-55 | Configurable; not yet exercised at volume |
| University contacts API | Department office contacts | In use |
| Photograph hosting | Profile and share images | Currently the legacy host; migration planned |

## 11. Constraints

- Publication dates: the source export carries a full date for 1,465 of 12,423
  rows. The year is present for 97% of records and is what reporting relies on.
- Photographs: the legacy host serves 90–120px thumbnails, which limits image
  quality until the images are moved into local storage.
- The system is browser-based and requires connectivity; there is no offline mode.

---

## 12. Risks

| ID | Risk | Impact | Likelihood | Response |
|---|---|---|---|---|
| RK-1 | Approval workflow is unexercised — 4 versions recorded to date | Bottleneck or confusion discovered only at go-live | High | Pilot with one department before university-wide rollout |
| RK-2 | Activation emails have never been sent at volume | Delivery failures, spam classification, support load | Medium | Send in controlled batches; monitor bounces |
| RK-3 | ERP endpoint may not arrive | BR-53 remains unrealised | Medium | Phase-1 does not depend on it; manual creation works |
| RK-4 | 2,000 teachers arriving at once will produce a review queue | Reviewer overload | Medium | Stage onboarding by faculty |
| RK-5 | Photograph quality limits presentation | Public perception | Low | Local storage migration planned; no code change needed |

---

## 13. Decisions required from the business

| ID | Decision | Needed by |
|---|---|---|
| DEC-1 | Which profile sections genuinely require approval — all 15 are currently configured to | Before rollout |
| DEC-2 | Rollout sequence and pace for activation emails | Before rollout |
| DEC-3 | Whether research projects will be used, and who owns them | Before Phase-3 sign-off |
| DEC-4 | Whether incentive amounts should be visible to the teacher who earned them, or to the research team only | Before teacher rollout |
| DEC-5 | Retention period for rejected and superseded versions | Before rollout |

---

## 14. Acceptance

Phase-1 is accepted when, for a pilot department: teachers activate and edit
their own profiles; submissions reach the Registrar Office and are approved or
rejected with remarks; approved changes appear publicly and rejected ones do
not; a prior version is successfully restored; and unit dashboards reconcile
with the underlying records.

---

*Prepared as a description of the system as delivered. Figures were measured
from the running system on 2026-08-01. Requirement-level implementation detail
is in the companion SRS.*

# Faculty Management System — Mobile API (v1)

For the departments building directory apps against the FMS.

**Base URL:** `https://<host>/api/v1`
**Format:** JSON in, JSON out. Send `Accept: application/json` on every request — without it a validation failure comes back as an HTML redirect instead of a 422.
**Auth:** bearer token (Laravel Sanctum).

---

## 1. Why there is a login at all

Everything this API returns is already public on the website. The token is not
secrecy — it is knowing *which* app is asking, so a misbehaving client can be cut
off without taking the website down with it, and so a teacher who is still using
the password that was emailed to them can be told to replace it before they go
any further.

Only three endpoints work without a token: `login`, `password/forgot`,
`password/reset`.

---

## 2. Signing in

### `POST /auth/login`

```json
{ "email": "someone@diu.edu.bd", "password": "…", "device": "Pixel 8" }
```

`device` is optional and names the token, so signing out of a phone leaves a
tablet signed in.

**200**

```json
{
  "token": "17|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "must_change_password": true,
  "user": {
    "id": 42, "name": "…", "email": "…",
    "teacher": {
      "id": 88, "full_name": "…", "designation": "…", "department": "…",
      "profile_path": "FSIT/CSE/anwar-hossain"
    }
  }
}
```

`profile_path` is where to send them for their own public page — append it to the
base URL.

**422** — wrong password, or no such account. **Deliberately the same answer for
both.** Telling them apart would turn this endpoint into a way of asking which of
two thousand university addresses are real.

Rate limited to **6 requests a minute** per IP.

### The forced first password change

Every account came over from the migration with a generated password that was
emailed out. Until the person replaces it, the account is only as private as that
email.

So while `must_change_password` is `true`, **every endpoint except three returns
403**:

```json
{ "message": "Choose your own password before continuing.", "must_change_password": true }
```

The three that stay open are `GET /auth/me`, `POST /auth/password/change`, and
`POST /auth/logout` — find out you are in this state, leave it, or sign out.

This is enforced on the server, not by the app's screen. A modified client, or
anyone with the token and a curl command, gets the same 403.

An account that already chose its own password never sees any of this.

### `POST /auth/password/change`

```json
{ "current_password": "…", "password": "…", "password_confirmation": "…" }
```

Minimum 8 characters, must differ from the current one. On success every **other**
token is revoked — a password is changed either because it was the emailed one or
because somebody else may have it, and in both cases the other sessions should
end. The token making the request survives.

### `GET /auth/me`

Call this on every cold start. A token kept on the device may have been revoked,
and this is the only way to find out before a real request fails somewhere less
convenient. A revoked token gets a 401.

### `POST /auth/logout`

Revokes the token this request arrived with, and no others.

### `POST /auth/password/forgot` · `POST /auth/password/reset`

Standard reset by emailed token. `forgot` does not say whether the address is
known, for the same reason `login` does not. A completed reset revokes **all**
tokens — a reset is a recovery, so whatever was already signed in was not them.

---

## 3. The directory

All of these need a valid token. **120 requests a minute** per token.

| Method | Path | What it answers |
| --- | --- | --- |
| GET | `/faculties` | Every active faculty, with department and teacher counts |
| GET | `/faculties/{faculty}` | One faculty |
| GET | `/faculties/{faculty}/departments` | Its departments |
| GET | `/faculties/{faculty}/departments/{department}` | One department |
| GET | `/faculties/{faculty}/departments/{department}/teachers` | Its teachers, paged |
| GET | `/departments` | Every department in the university, flat |
| GET | `/teachers` | Search across every faculty, paged |
| GET | `/lookups` | The fixed lists a filter screen is built from |

`{faculty}` is the faculty's `short_name` (e.g. `FSIT`); `{department}` is the
department's `code` (e.g. `CSE`). Both also accept the numeric id. Every resource
carries a `slug` field spelled exactly as the routes expect — build paths from
that rather than assembling them by hand.

`/departments` exists because the website has no equivalent: there you reach a
department through its faculty. An app filling a filter needs the flat list
without making one request per faculty.

### Filters on `/teachers` and `/…/teachers`

`?q=` name, employee id or email · `?designation=` id · `?faculty=` id or short
name · `?department=` id or code · `?per_page=` (default 20, max 100).

### `GET /lookups`

Designations, employment statuses, publication types and quartiles in one
response, each as `{id, name}` — the id is what the filter parameters take, so it
travels with the name. Cached for a day; these change a few times a year.

---

## 4. One teacher

The path mirrors the public website exactly, so an app handed a shared link can
ask for the same record.

| Method | Path | |
| --- | --- | --- |
| GET | `/{faculty}/{department}/{teacher}` | The full profile |
| GET | `/{faculty}/{department}/{teacher}/publications` | Their papers, paged |
| GET | `/{faculty}/{department}/{teacher}/publications/{publication}` | One paper |
| GET | `/{faculty}/{department}/{teacher}/cv` | CV as a PDF download |
| GET | `/{faculty}/{department}/{teacher}/vcard` | Contact card (`.vcf`) |

`{teacher}` is the `webpage` slug.

The CV and the vCard are the same files the website builds — the same controller
serves both, so the version an app downloads is the version the profile page
offers. Both can be switched off in the site settings, in which case they return
404.

### `?include=` — asking for less

By default the profile carries every section. Pass a comma-separated list to load
only what a screen needs:

```
GET /FSIT/CSE/anwar-hossain?include=education,experience
```

Available: `education`, `experience`, `training`, `awards`, `memberships`,
`teaching_areas`, `skills`, `research`, `social_links`.

Name, designation, department, photo, contact and employment status are always
present — without them the record does not identify anybody.

### `employment_status`

`null` when the teacher is at their desk. Otherwise an object with a `label` and
a `tone`, and the app should show it. Roughly a fifth of the visible teachers are
on study or ordinary leave, and a directory that lists them silently alongside
everyone else is asserting something false.

### Publications are a separate request

They are the only part of a profile with no ceiling — one teacher here has
sixty-two. Folding them in would make the size of a profile depend on how prolific
the person is. `publications_count` is on the profile so a tab can show a number
without fetching the list.

---

## 5. Publications

### `GET /publications`

`?q=` title, journal, keywords or research area · `?year=` · `?from=` `&to=` ·
`?type=` id · `?faculty=` id · `?department=` id · `?per_page=`.

**On dates.** An exact publication date exists for 1,465 of 17,510 records — the
year is all that was ever recorded for the rest, and that is true of the source
export too, not an import that went wrong. A `from`/`to` range therefore matches
the exact date where there is one and the *year* where there is not. A filter
that read the date alone would answer with a twelfth of the library and give no
sign that it had. Sort and group by `publication_year`, not `publication_date`.

### `GET /{faculty}/{department}/{teacher}/publications/{publication}`

`{publication}` is the paper's slug, or its id.

Only this endpoint carries `abstract` — a list of a hundred of them is a payload
nobody asked for. It also carries:

- `citations` — `apa`, `ieee` and `bibtex`, ready to copy
- `contributors` — everyone credited, in the recorded order, each with `name`,
  `role`, `is_faculty` and (for faculty) a `slug` to move to their profile

A paper with several faculty authors is reachable at one URL per author, all
serving the same record.

What a teacher was paid for a paper is on the same table and is **never**
returned. That is internal.

---

## 6. What is not returned, at any depth

1,128 of the 2,000 teacher records are published. The other 872 are archived or
inactive — several are people who have left the university — and the API answers
**404** for them, not 403: a record that was taken down should read as never
having been there rather than confirming it exists to whoever asks.

A teacher also cannot be reached under a department they do not belong to, even
with a correct slug.

Home address, date of birth, national id and the verification token are not in
any response. Office contact details are, because the public profile page already
shows them to any visitor.

---

## 7. Errors

| Status | Meaning |
| --- | --- |
| 401 | No token, or it has been revoked. Sign in again. |
| 403 | `must_change_password` — send them to the change-password screen. |
| 404 | No such record, or it is not published. |
| 422 | Validation failed; `errors` is keyed by field. |
| 429 | Rate limited. `Retry-After` says for how long. |

---

## 8. Paging

Every list endpoint returns Laravel's standard pagination envelope:

```json
{
  "data": [ … ],
  "links": { "first": "…", "last": "…", "prev": null, "next": "…" },
  "meta": { "current_page": 1, "last_page": 57, "per_page": 20, "total": 1128 }
}
```

`per_page` is capped at 100 whatever is asked for.

---

## 9. Versioning

The prefix is `/v1` from the first day. An app that has shipped cannot be asked
to update in step with the server, so v1 keeps answering the way it does today
even once a v2 exists. New fields may be **added** to v1 responses; existing ones
will not change meaning or disappear.

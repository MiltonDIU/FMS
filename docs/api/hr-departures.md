# Reporting a departure — HR API (v1)

For HR / ERP developers. When HR records that a teacher has left, one request
tells the Faculty Management System. The public profile comes down, the teacher
can no longer sign in, and their current period of service closes with the
leaving date and the reason.

This endpoint is separate from the mobile directory API in [README.md](README.md).
It uses a different token. A token from the mobile app's sign-in is refused here.

**Base URL:** `https://<host>/api/v1`
**Format:** JSON. Send `Accept: application/json` on every request.

---

## Getting a token

Somebody with access to the FMS server issues the token for an account set
aside for HR. Use a separate account, not a teacher's own account:

```bash
php artisan teachers:issue-hr-token hr-system@diu.edu.bd
```

The command prints the token once and never shows it again. Store it the way
you store any other credential. The version history records every change under
that account's name.

To revoke it, for example if it leaks or the integration is retired, run:

```bash
php artisan teachers:issue-hr-token hr-system@diu.edu.bd --revoke
```

---

## `POST /hr/departures`

```http
POST /api/v1/hr/departures
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json

{ "employee_id": "710001234", "reason": "resigned", "leaving_date": "2026-10-15" }
```

| Field | Rules |
| --- | --- |
| `employee_id` | The employee id the FMS holds for the teacher. This is the same id HR uses. |
| `reason` | `resigned`, `retired` or `terminated` |
| `leaving_date` | `YYYY-MM-DD`: the last working day. It must be **today or earlier**, and it cannot be before the teacher joined. |

**Send the request on or after the leaving date.** A future date is refused. The
system takes the profile down as soon as it accepts the departure, and a
teacher who is still serving their notice should stay on the site until they go.

### 200

```json
{
  "status": "departed",
  "teacher": {
    "id": 88, "employee_id": "710001234", "name": "…",
    "employment_status": "Resigned", "leaving_date": "2026-10-15", "is_public": false
  },
  "service_period": {
    "joined_on": "2015-01-10", "left_on": "2026-10-15", "ended": true, "end_reason": "Resigned"
  }
}
```

`status` is `departed` when the profile changed. It is `unchanged` when the FMS
already held this departure. **Sending the same request again is safe**, so
retry freely after a timeout.

If you send a different date or reason for a teacher who has already left, the
FMS records it as a correction to their last period of service.

### What changes

The same things that happen when an administrator sets the status on the
teacher's Settings tab:

- The teacher disappears from the public site, the directory and the mobile API.
- The teacher can no longer sign in, and any app sessions they have are signed out.
- A retirement also archives the profile.
- The open period of service closes. If the teacher later rejoins, a new period
  opens and this one is kept.
- The change goes into the profile's version history. No approval is needed,
  and the teacher is notified.

### Errors

| Status | Meaning |
| --- | --- |
| 401 | No token, or it has been revoked. |
| 403 | The token was not issued for this endpoint (for example, a mobile app token). |
| 404 | No teacher has this employee id. |
| 409 | More than one profile has this employee id. An administrator must merge the duplicates first, then you can send the request again. |
| 422 | Validation failed. `errors` is keyed by field. |
| 429 | More than 60 requests a minute. `Retry-After` says how long to wait. |

---

## Rejoining

A teacher who comes back is not reported through this endpoint. An
administrator sets them to Active with the new joining date, and their earlier
period of service is kept.

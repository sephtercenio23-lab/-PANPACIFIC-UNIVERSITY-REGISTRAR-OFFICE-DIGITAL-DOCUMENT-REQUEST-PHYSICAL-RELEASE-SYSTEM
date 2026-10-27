# API reference — `api.php`

The PHP backend exposes one endpoint, `api.php`. It replaces
`google.script.run`: the browser bridge (`assets/serve-bootstrap.js`) sends the
Apps Script function name plus its positional arguments, and the router calls
the matching PHP function.

## Request format

```
POST /api.php
Content-Type: application/json

{ "action": "submitEvaluation", "args": [ { …payload… } ] }
```

* `action` — one of the whitelisted names below (the old Apps Script function names).
* `args` — positional arguments, in the same order as the old function signature.

Form-encoded requests also work (`action` + `args` fields, where `args` is a JSON
array), and `GET api.php?action=health` is handy for a quick check in a browser.

## Response format

```json
{ "ok": true,  "result": <whatever the function returned> }
{ "ok": false, "error": { "name": "Error", "message": "Please select a document." } }
```

`withSuccessHandler(fn)` receives `result`; `withFailureHandler(fn)` receives an
`Error` whose `message` is exactly the text above — the same behaviour the form
already relied on with Apps Script.

---

## Actions used by the SERVE form (`index.html`)

### `getStaff`

Returns the active staff directory for the "Who assisted you?" dropdown.

```bash
curl -s -X POST https://your-site/api.php \
  -H 'Content-Type: application/json' \
  -d '{"action":"getStaff","args":[]}'
```

```json
{ "ok": true, "result": [
  { "id": "STAFF-001", "name": "Registrar Staff 1", "position": "Registrar Office Staff" }
] }
```

### `submitEvaluation`

Arguments: the payload built by the form —
`studentType`, `email`, `fullName`, `staff`, `ratings[]`, `strengths`, `improve`.

```bash
curl -s -X POST https://your-site/api.php \
  -H 'Content-Type: application/json' \
  -d '{"action":"submitEvaluation","args":[{
        "studentType":"Present Student",
        "email":"juan@panpacificu.edu.ph",
        "fullName":"Juan Dela Cruz",
        "staff":"Registrar Staff 1",
        "ratings":[5,5,4,5,4,5,5,4,5,5],
        "strengths":"Very helpful.",
        "improve":"More seats in the waiting area."
      }]}'
```

```json
{ "ok": true, "result": { "success": true, "reference": "PU-SERVE-2026-482913" } }
```

Validation (same messages as before):

| Rule | Message |
|---|---|
| `studentType` must be `Alumni Student` or `Present Student` | `Please select your student type.` |
| valid e-mail | `Please enter a valid email address.` |
| Present Students need `@panpacificu.edu.ph` | `Present Students must use their university email ending in @panpacificu.edu.ph.` |
| staff must exist and be active | `The selected staff member is no longer in the active staff directory. Please refresh the page and select again.` |
| every rating 1–5 | `Please answer all evaluation questions.` |

Side effects: one row in `evaluations`, one row in `audit_logs`
(`EVALUATION_SUBMITTED`), one row in `notifications`
(`EVALUATION_CONFIRMATION`).

### `getEvaluationSummary`

Aggregated results for the registrar (handy for a dashboard):

```json
{ "ok": true, "result": { "total": 42, "average": 4.61,
  "perStaff": [ { "staff": "Registrar Staff 1", "responses": 18, "average": 4.72 } ] } }
```

---

## Document request system (ported from `Code.gs`)

There is no document-request page in this package yet — the endpoints below are
ready for it and behave exactly like the Apps Script functions.

### `getPublicConfig`

```bash
curl -s -X POST https://your-site/api.php -H 'Content-Type: application/json' \
  -d '{"action":"getPublicConfig","args":[]}'
```

```json
{ "ok": true, "result": {
  "universityName": "Panpacific University",
  "officeName": "Registrar Office",
  "blue": "#263f77",
  "green": "#406f27",
  "documents": [ { "code": "TOR", "name": "Transcript of Records", "description": "Official academic record",
                   "fee": 100, "days": 5, "requirements": ["Valid ID", "Student verification"] } ] } }
```

### `getDocumentTypes`

Same `documents` array as above.

### `requestOTP(email)`

```bash
curl -s -X POST https://your-site/api.php -H 'Content-Type: application/json' \
  -d '{"action":"requestOTP","args":["maria.santos@panpacificu.edu.ph"]}'
```

```json
{ "ok": true, "result": { "success": true, "token": "0c1f…-uuid",
                          "maskedEmail": "ma***@panpacificu.edu.ph" } }
```

* Only `@panpacificu.edu.ph` addresses, or personal addresses already present in
  `students` / `users`, are accepted.
* One request per 60 seconds per address: `Please wait before requesting another code.`
* With `dev_show_otp => true` in `config.php` the response also contains
  `devCode` (test deployments only — the code is bcrypt-hashed in the database).

### `verifyOTP(token, code, fullName, studentNumber, studentType)`

```bash
curl -s -X POST https://your-site/api.php -H 'Content-Type: application/json' \
  -d '{"action":"verifyOTP","args":["<token>","482913","Maria Santos","2026-00123","Present Student"]}'
```

```json
{ "ok": true, "result": { "success": true, "session": "<uuid>",
  "user": { "studentNumber": "2026-00123", "name": "Maria Santos",
            "email": "maria.santos@panpacificu.edu.ph",
            "studentType": "Present Student", "campus": "ALL" } } }
```

Errors: `Incorrect verification code.` / `The verification code has expired. Please request a new one.`

### `submitRequest(session, payload)`

`payload`: `documentCode`, `purpose`, `releaseMethod`, `representativeName`,
`representativeRelation`, `notes`.

```bash
curl -s -X POST https://your-site/api.php -H 'Content-Type: application/json' \
  -d '{"action":"submitRequest","args":["<session>",{
        "documentCode":"TOR","purpose":"Employment abroad","releaseMethod":"Representative",
        "representativeName":"Pedro Santos","representativeRelation":"Father","notes":""}]}'
```

```json
{ "ok": true, "result": { "success": true, "requestId": "PU-REQ-2026-482913",
  "documentName": "Transcript of Records", "fee": 100, "dueDate": "Nov 3, 2026" } }
```

`dueDate` = today + the document's typical days, counting **business days only**
(weekends skipped), formatted `MMM d, yyyy`.

### `getStudentRequests(session)`

```json
{ "ok": true, "result": [
  { "requestId": "PU-REQ-2026-482913", "document": "Transcript of Records",
    "purpose": "Employment abroad", "releaseMethod": "Representative",
    "status": "Submitted", "submittedAt": "Oct 27, 2026 5:21 pm",
    "dueDate": "Nov 3, 2026" } ] }
```

### `logout(session)`

Deletes the session row; returns `{"success": true}`.

---

## Maintenance

| Action | Notes |
|---|---|
| `health` | PHP version, driver, installed flag, counts, mail mode, server time. No arguments, no side effects. |
| `setupSystem` | Creates tables/seeds, returns `Setup completed successfully.` Disabled unless `allow_remote_setup => true` in `config.php`. Prefer `setup.php`. |

---

## Error handling summary

| Situation | HTTP | Body |
|---|---|---|
| Validation / business rule | 200 | `{"ok":false,"error":{...,"message":"<exact message>"}}` |
| Unexpected exception | 500 | generic message (details only when `debug => true`) |
| PHP fatal error | 500 | generic JSON (never a stack trace) |

All responses are JSON, `Cache-Control: no-store`, `X-Content-Type-Options:
nosniff`.

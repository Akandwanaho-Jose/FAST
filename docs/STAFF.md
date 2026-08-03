# Staff Directory Module

The staff directory is implemented and covered by automated tests.

## Implemented

- Permission-protected staff administration, search, filtering, and pagination
- Draft profile creation and editing
- In-place editing of published profiles by fully authorised publishers
- Staff identity, category, biography, research, teaching, supervision,
  contact, office, and display-order fields
- Primary department and current position assignment
- Secure profile-image upload or existing-media selection
- Draft, review, approval, publication, archive, and restore workflow
- Atomic **Create and publish**, **Save and publish**, and **Publish now**
  controls for fully authorised global administrators
- Revisions, approvals, and audit records
- Searchable published public staff directory and profile pages
- Dynamic department HOD cards and links driven by current published staff
- Automatic retirement of the previous current Dean or HOD assignment when a
  replacement is assigned

## Leadership relationship

Leadership is not duplicated on faculty or department records. A current HOD
is resolved through `staff_positions.department_id` and
`positions.code = 'head_of_department'`; a Dean uses
`staff_positions.faculty_id` and `positions.code = 'dean'`. The photograph is
always the staff profile's `profile_media_id`.

Only a published, non-deleted staff profile with a current, date-valid
appointment is publicly resolved. Replacing a current Dean or HOD closes the
previous current appointment.

## Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/staff` | Published staff directory |
| GET | `/staff/{slug}` | Published staff profile |
| GET | `/admin/staff` | Scoped administration list |
| GET/POST | `/admin/staff/create`, `/admin/staff` | Create a draft |
| GET/POST | `/admin/staff/{id}/edit`, `/admin/staff/{id}` | Edit a draft |
| POST | `/admin/staff/{id}/workflow` | Permitted workflow transition |

All state-changing requests require CSRF validation. Core creation and editing
currently require global scope because a profile can alter faculty-wide and
cross-department leadership assignments.

A complete profile with a short biography can be published immediately during
creation. A complete existing draft exposes **Publish now** on its detail page.
Both shortcuts atomically create the normal submitted, approved, and published
history and enforce all three permissions.

Published profiles expose **Edit** to fully authorised publishers. Opening the
form does not take the profile offline; **Save published changes** updates the
live record in place and creates a content revision plus audit entry. Users
without staff publishing access cannot alter live profiles.

## Extended profile data

The schema supports qualifications, expertise areas, external research links,
and historical concurrent appointments. The primary editing workflow manages
one primary department and one current position.

## Verification

Run:

```powershell
php tests\run.php
php tests\integration\run.php
php bin\department-check.php
```

The staff integration flow verifies creation, assignment, validation,
one-action publication, public visibility, HOD resolution, archiving,
restoration, and transaction cleanup.

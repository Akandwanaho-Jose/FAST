# Faculty and Departments Module

The faculty and departments module is implemented and covered by automated
tests.

## Administration routes

| Method | Route | Permission and purpose |
|---|---|---|
| GET | `/admin/faculty` | `departments.view`; read FAST faculty settings |
| GET | `/admin/departments` | `departments.view`; scoped listing, search, status filter, pagination |
| GET | `/admin/departments/create` | `departments.create` plus global scope; creation form |
| POST | `/admin/departments` | Create a validated draft |
| GET | `/admin/departments/{id}` | View a department in the user's scope |
| GET | `/admin/departments/{id}/edit` | `departments.edit` plus edit-level department access |
| POST | `/admin/departments/{id}` | Save a draft revision or save and publish |
| POST | `/admin/departments/{id}/workflow` | Perform a permitted workflow transition |

All state-changing routes require a valid CSRF token. Route permission checks
are reinforced by record-level checks in the department service.

## Workflow

```text
Draft → Under Review → Approved → Published → Archived
          ↓               ↓                       ↓
        Draft           Draft                   Draft
     changes requested  changes requested      restored
```

- Draft records can be edited by users with department edit access.
- Published records can be edited in place by users who also hold the complete
  review, approval, and publishing authority. The public page remains online;
  saving creates a revision and audit record without changing publication
  status or date.
- Editable drafts expose a prominent **Edit** button directly in the department
  listing and on the detail page.
- Published records expose the same **Edit** action to authorised publishers
  and use **Save published changes** in the live-content form.
- A verified overview is required before submission and publication.
- Requesting changes requires a comment.
- Submission requires `departments.edit` and edit-level department access.
- Requesting changes requires `content.review` and review-level access.
- Approval requires `content.approve` and review-level access.
- Publishing, archiving, and restoring require `departments.publish` and
  publish-level access.
- A user who holds edit, review, approval, and publishing access can use
  **Save and publish** from the edit form or **Publish now** from a complete
  draft. The shortcut atomically records the normal submitted, approved, and
  published transitions; it does not bypass permissions or history.
- Global users pass department-scope checks through `settings.manage`.
- Every create/edit creates a `content_revisions` record.
- Every workflow transition creates a `content_approvals` record.
- Creates, edits, and transitions create `audit_logs` records.

## Relationships

The form uses the existing authoritative relationships:

- `faculty_id` → `faculties`
- `location_id` → reusable `locations`
- `hero_media_id` → active image records in `media`
- Current HOD → published `staff` profile through the current
  `staff_positions` row whose `positions.code` is `head_of_department`
- HOD photograph → that staff profile's `profile_media_id` in `media`

No location or media table was duplicated. The module accepts JPEG, PNG, WebP,
and GIF hero images up to 20 MB. Each upload is verified from its file contents,
given a random stored filename, placed under
`public/uploads/departments/YYYY/MM`, and recorded in the existing `media`
table. `media.file_path` stores the generated relocatable public path and
`departments.hero_media_id` links it to the department. New uploads require
descriptive alternative text. Executable extensions and directory listing are
blocked in the upload directory.

An editor may also reuse an existing active image. A newly uploaded image takes
precedence over the selected existing image. If no image exists, the department
can safely remain unlinked.

Faculty settings are read-only because the schema has department
permissions but no faculty-specific edit permission. This avoids silently
granting a broader settings capability.

## Public routes

| Route | Behavior |
|---|---|
| `/departments` | Searchable, paginated directory of published departments |
| `/departments/{slug}` | Published department detail page |

Public queries require `status = published`, a non-null publication date not
in the future, and a non-deleted record. Draft, review, approved, archived, and
future-dated records return no public detail. Page titles and meta descriptions
are derived from verified department data; no new SEO columns were invented.

The public directory exposes only records that satisfy the publication rules;
it does not depend on a fixed number of seeded or published departments.

Published department pages automatically resolve their current Head of
Department from the staff directory. The leadership section can show the
staff member's photograph, formal name, position title, short biography,
institutional email, and the department's HOD message. Draft, archived,
future-dated, former, or mismatched-faculty staff assignments are not shown.
If no qualifying staff assignment exists, no empty leadership card is
rendered. Eligible leaders link to their published staff profiles.

## Verification

Run:

```powershell
php bin\department-check.php
php tests\run.php
php tests\integration\run.php
```

The transactional tests create temporary records and roll them back. They cover
validation, duplicate rejection, relationships, scope, search, pagination,
revisions, approvals, auditing, direct publication, workflow corrections,
publication visibility, archiving, restoration, upload validation, stored
media paths and metadata, and global-versus-department creation rules.
It also verifies that a published current HOD and profile image are resolved
through the staff-directory relationship.

## Deployment requirement

Use a least-privileged application database account in every shared or
production environment.

# Programme module

The academic programme catalogue and curriculum-management slice are
implemented and tested.

## Administration experience

- Search, filter, and paginate programme records.
- Create a draft or use **Create and publish** when the overview is complete.
- Use **Publish now** from a complete draft.
- Edit a published programme without taking its public page offline; **Save
  published changes** applies the revision immediately.
- Archive a published programme and restore it later as a draft.
- Assign one lead department and programme level.
- Reuse an active media record or upload a JPEG, PNG, WebP, or GIF image up to
  20 MB. Files are stored under `public/uploads/programmes/YYYY/MM`, while the
  reusable URL/path and metadata are stored in `media`.

All changes create revision and audit records. Workflow transitions also create
approval-history records. Department scope is applied to listing, viewing, and
mutating programmes.

## Public experience

- `/programmes` shows only currently published, non-deleted programmes.
- Visitors can search by name, code, or overview and filter by programme level.
- `/programmes/{slug}` presents the programme overview, study details, entry
  requirements, outcomes, careers, practical training, accreditation, lead
  department, and application link.
- Draft, review, approved, archived, future-published, and deleted records are
  never returned by public queries.
- A programme page shows its year/semester course structure only when its
  current curriculum version is published. Draft curricula and draft courses
  remain private.

## Curriculum management

- **Manage curriculum** is available from each programme record.
- Editors can create a version while adding the first course, then add, edit,
  move, classify, reorder, or remove course placements.
- A prominent **Publish curriculum** action makes the selected version current,
  publishes its courses, and calculates total credit units atomically.
- Authorized publishers can edit a published curriculum directly; saved
  changes remain live, matching the programme module's flexible live-edit UX.
- Course descriptions and approval metadata are optional, while course code,
  title, credit units, year, semester, and requirement type are validated.
- Course prerequisite editing remains a later enhancement; the existing
  `course_prerequisites` table is unchanged and ready for it.

## Handbook starter data

Five handbook-derived undergraduate programmes and their 2025/2026 curricula
were published on 2026-08-01. The approved batch contains 264 shared course
records and 294 programme placements, including the reviewed resolution of the
handbook's duplicate `BCE3123` code. The repeatable import workflow is described
in [HANDBOOK_SEED.md](HANDBOOK_SEED.md).

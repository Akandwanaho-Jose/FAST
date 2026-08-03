# Research module

Research units, projects, publications,
themes, SDG relationships, and partner relationships use the existing research
schema without database changes.

## Administration experience

- Search, filter, and paginate research units.
- Create a draft or use **Create and publish** once an overview is present.
- Use **Publish now** on a complete draft.
- Edit a published unit without taking its public page offline.
- Archive a published unit and restore it as a draft.
- Assign a faculty, lead department, research-unit type, location, contact
  details, and display order.
- Reuse an active media image or upload a JPEG, PNG, WebP, or GIF up to 20 MB.
  Upload paths and URLs are stored in the shared `media` catalogue under the
  research upload area.
- Add current staff members with research roles and optional role titles.
- Assigning a new lead ends the previous active lead appointment; assigning an
  existing staff member again replaces that person's current membership.

Research records use revision, approval-history, audit, permission, and
department-scope controls consistent with the department, staff, and programme
modules.

## Public experience

- `/research` contains only published, non-deleted research units.
- Visitors can search by name, acronym, or research focus and filter by unit
  type.
- `/research/{slug}` presents the overview, research focus, capabilities,
  student opportunities, industry/community services, contacts, location, lead
  department, external website, and published current research team.
- Staff names link back to their published directory profiles.
- Draft, review, approved, archived, future-published, and deleted records are
  not returned publicly.

## Research projects

The project slice is implemented on top of the research foundation:

- Search and filter projects by publication status and real-world project
  progress (`planned`, `ongoing`, `completed`, `suspended`, or `cancelled`).
- Create a draft, create and publish, publish a complete draft, edit while
  published, archive, and restore.
- Assign a required lead department and an optional lead research unit. This
  lets official projects begin before research-unit records are populated.
- Manage dates, budget/currency, funding reference, external project URL,
  public summary, objectives, methodology, expected outputs, outcomes, impact,
  and reusable/uploaded hero images.
- Add either internal FAST staff or external collaborators to a project team,
  with role, title, affiliation, contact, appointment dates, and display order.
- Enforce exactly one internal or external identity per member in the service
  layer, tightening the database's non-exclusive identity check.
- Public `/research/projects` and `/research/projects/{slug}` routes show only
  published projects and eligible team members.

Project operations use the same research permissions, department scope,
revision history, approval history, and audit logging as research units.

## Research publications

The publication slice is implemented across the research and staff modules:

- Nine seeded publication types: journal article, conference paper, book, book
  chapter, technical report, policy brief, thesis/dissertation, dataset, and
  other.
- Bibliographic metadata including title, abstract, journal/source, publisher,
  year/date, volume, issue, pages, DOI, ISBN, preferred citation, external URL,
  and access type.
- DOI normalization and uniqueness validation, plus year/date consistency.
- Ordered internal FAST staff and external authors, optional external
  affiliation/ORCID, and corresponding-author designation.
- Exactly one internal or external identity per author is enforced by the
  service layer.
- At least one author is required before review or publication.
- Optional project and research-unit links, with department scope derived from
  those links or internal authors.
- Draft, live published editing, review, approval, publication, archive, and
  restore behavior with revisions, approval history, and audit records.
- Searchable/filterable public `/research/publications` and
  `/research/publications/{slug}` pages.
- Published authored works automatically appear on matching public staff
  profiles.

Full-text and supplemental file attachments use the shared `documents`
catalogue.

## Research metadata

- A central `/admin/research/metadata` hub manages reusable hierarchical
  research themes and partner organizations.
- Theme and partner editors support draft creation, direct publication, live
  published editing, archiving, and restoration.
- Themes have a lead department for editing scope and can be assigned to
  projects as primary or supporting themes and to publications.
- All 17 official Sustainable Development Goals are seeded and can be assigned
  to projects with one primary goal.
- Published partner organizations can be reused across projects with an
  explicit role and contribution statement.
- Partner logos can reuse active media or be uploaded as JPEG, PNG, WebP, or
  GIF images up to 20 MB.
- Public project pages show published themes and partners plus assigned SDGs;
  public publication pages show published themes.
- Archiving a theme or partner immediately removes it from public display
  without deleting its project/publication relationships.
- All catalogue and assignment mutations enforce research permissions,
  department scope where applicable, CSRF protection, transactions, and audit
  logging.

Public pages expose only reviewed, published content.

## Verification

```powershell
php tests\run.php
php tests\integration\run.php
php bin\research-check.php
php bin\project-check.php
php bin\publication-check.php
php bin\metadata-check.php
```

The research-unit, project, publication, and metadata integration flows verify
validation, direct publication, public retrieval, live editing,
internal/external people, ordered authors, staff-profile linkage, member/lead
replacement, theme/SDG/partner assignment, metadata privacy, archiving,
restoration, and transaction rollback.

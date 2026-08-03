# Undergraduate handbook starter seed

## Source

The starter records were derived from `FAST Undergraduate Hand book
2025_2026.docx`, supplied by the project owner on 2026-08-01. The handbook's
programme sections begin on the following handbook pages:

- Biomedical Engineering: page 33
- Electrical and Electronics Engineering: page 49
- Petroleum Engineering and Environmental Management: page 64
- Mechanical and Industrial Engineering: page 81
- Civil and Building Services Engineering: page 98

The Word document was structurally extracted into ordered paragraphs and tables.
LibreOffice was unavailable, so rendered-page visual verification could not be
performed. The page references above come from the handbook's own table of
contents.

## Imported starter records

Five programme records were added as drafts:

1. Bachelor of Biomedical Engineering
2. Bachelor of Electrical and Electronics Engineering
3. Bachelor of Petroleum Engineering and Environmental Management
4. Bachelor of Mechanical and Industrial Engineering
5. Bachelor of Science in Civil and Building Services Engineering

Each record contains its handbook-derived overview, objectives, learning
outcomes, entry requirements, four-year duration, undergraduate level, and lead
department. One current draft curriculum shell named `Undergraduate Handbook
2025/2026` was created for each programme.

Programme codes, accreditation statements, application URLs, career summaries,
and images were not invented. The records remain drafts until explicitly
reviewed and published.

## Repeatable import

Preview without changing the database:

```powershell
php bin\seed-undergraduate-handbook.php
```

Apply missing records:

```powershell
php bin\seed-undergraduate-handbook.php --apply
```

The seed matches existing records by programme slug and skips them. It never
overwrites a programme that already exists. The apply operation is transactional
and records the responsible administrator, initial content revision, and audit
event.

## Curriculum follow-up

The separate normalization pass is complete, including the nested Petroleum
Engineering Word tables. It produced 294 placements with no missing credit
units. The reviewed `BCE3123-IOT` resolution was applied, and all five curricula
were imported and published atomically.

## Related handbook import

The handbook has also been prepared as a single reviewable batch for the
department and staff phases. It contains 46 deduplicated staff profiles, 44
mapped handbook portraits, three
department content updates, and the five programme records above. It was
reviewed, applied, and published transactionally on 2026-08-01. The associated
commands remain available in `bin/` for maintenance and verification.

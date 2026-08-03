# Database Schema

## Supported database

The application targets MySQL 8.0+ or MariaDB 10.5+. All application tables use
InnoDB, `utf8mb4`, and `utf8mb4_unicode_ci`.

The current schema contains 82 tables, 128 foreign keys, 62 unique keys, and
starter data for roles, permissions, departments, and the 17 Sustainable
Development Goals.

## Authentication and authorization

- `users` stores account state, password hashes, lockout counters, and security
  timestamps.
- `roles`, `permissions`, `user_roles`, and `role_permissions` provide
  permission-based authorization.
- `user_departments` limits non-global users to assigned departments and access
  levels.
- Authorization checks use permissions, never role names alone.

No administrator credentials are seeded. Create the first administrator with
the protected command documented in [AUTHENTICATION.md](AUTHENTICATION.md).

## Content and workflow

Faculty, department, programme, staff, research, and editorial records support
draft, review, approval, publication, archive, and restoration workflows.

- `content_revisions` stores version snapshots.
- `content_approvals` records workflow transitions.
- `audit_logs` records security-sensitive and editorial actions.

Polymorphic entity references are validated by application services because
database foreign keys cannot enforce those relationships directly.

## Application-enforced rules

The service layer transactionally enforces rules that cannot be expressed fully
by the schema, including:

- one active primary department per staff member;
- one current curriculum version per programme;
- consistent current-position flags and appointment dates;
- exactly one internal or external identity for project members and publication
  authors;
- valid entity types for polymorphic relationships;
- consistent publication status and publication timestamps; and
- exclusion of soft-deleted or non-public records from public queries.

## Database permissions

The application account should receive only `SELECT`, `INSERT`, `UPDATE`, and
`DELETE` on the application database. Schema changes should use a separate,
manually controlled migration account.

See [CONNECTIVITY.md](CONNECTIVITY.md) for configuration and verification.


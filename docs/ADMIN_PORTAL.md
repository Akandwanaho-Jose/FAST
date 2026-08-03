# Administration Portal

## Access

The portal is available at `/admin` beneath the configured application URL.

## Authorization model

- Effective permissions are loaded from the existing `user_roles`,
  `role_permissions`, and `permissions` tables.
- Routes are protected server-side with the same permission code used to
  decide whether a navigation item is visible.
- The implementation never grants access by comparing role names.
- A user with `settings.manage` has global department scope.
- Other users are limited to the departments and access levels recorded in
  `user_departments`.
- Department access levels are ordered as `view`, `create`, `edit`, `review`,
  `publish`, and `manage`.
- Missing permissions produce HTTP 403; missing authentication redirects to
  login.

## Dashboard

The dashboard reads current database values and displays only modules the
logged-in user may view:

- Departments
- Staff profiles
- Programmes
- Research units, projects, and publications
- News and events
- Pages and documents

Counts, recent activity, drafts, and review queues are filtered by permission
and department scope. Pages and documents have no department relationship in
the authoritative schema, so they are included only for global-scope users.
The review queue appears only to users with `content.review` or
`content.approve`.

## Navigation and module destinations

The sidebar is generated from a fixed application policy and filtered against
effective database permissions. Destinations are protected with the same
permission policy as their routes.

## Verification

Run:

```powershell
php bin\rbac-check.php
php tests\run.php
php tests\integration\run.php
```

The RBAC check reports aggregate values only. The transactional test
creates a temporary role/user inside a database transaction and rolls
everything back. It verifies:

- permitted and denied actions;
- permission-filtered navigation;
- department access-level ranking;
- department-filtered counts and recent content;
- global scope through `settings.manage`;
- all dashboard count and workflow queries.

## Deployment requirement

Configure a restricted application database account before deployment. The
application must not run with a database administrator or `ALL PRIVILEGES`.

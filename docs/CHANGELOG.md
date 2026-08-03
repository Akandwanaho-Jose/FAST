# Changelog

This file summarizes user-facing and architectural milestones. Detailed local
test transcripts and internal implementation notes are intentionally excluded.

## 2026-08-02

### Content management and homepage

- Added centralized management for institutional identity, navigation labels,
  service URLs, calls to action, and landing-page content.
- Rebuilt the homepage around programmes, departments, research, student
  resources, news, events, and configurable promotional sections.
- Added accessible carousel controls, responsive navigation, breadcrumbs, and
  mobile layout refinements.
- Increased the configurable image upload limit to 20 MB.

### Editorial modules

- Completed administration and public experiences for innovations, facilities,
  partnerships, impact, news, events, announcements, pages, and documents.
- Added shared media selection, secure uploads, publication workflows, revision
  history, and auditing across supported modules.

## 2026-08-01

### Research

- Added research units, projects, publications, themes, SDG assignments, and
  partner relationships.
- Added public research directories and staff-profile publication links.
- Added validation for DOI metadata and internal/external contributors.

### Programmes and curricula

- Added programme administration, public programme pages, curriculum versions,
  course placement, publication, and live editing.
- Imported the reviewed undergraduate handbook programme and curriculum data
  through repeatable transactional seed commands.

## 2026-07-31

### Application foundation

- Added routing, views, environment loading, PDO database access, security
  headers, safe error handling, and Apache public-directory protection.
- Added authentication, CSRF protection, login throttling, forced password
  changes, audit logging, and permission-based administration.
- Added department and staff administration, publication workflows, public
  directories, secure media handling, and leadership relationships.
- Added unit and transactional integration test runners.

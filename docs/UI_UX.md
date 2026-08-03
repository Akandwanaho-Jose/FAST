# FAST UI/UX

## Design system and navigation

The public interface uses MUST-aligned royal blue, gold, green, charcoal, and
white, with accessible focus, hover, active, desktop, tablet, and mobile states.

The primary navigation groups content into:

- Programmes
- Departments
- Research & Innovation
- People
- Engagement
- News & Events

Grouped desktop menus and the mobile menu support keyboard operation, Escape,
accurate expanded states, reduced motion, and progressive enhancement. Public
pages also include section-aware breadcrumbs and grouped footer navigation.

## Homepage

The homepage supports:

- up to three scheduled carousel slides with pause/play and position controls;
- a useful content fallback when managed slides are unavailable;
- editable quick links and section headings;
- published programmes and departments;
- research and innovation features;
- student resources and admissions calls to action; and
- news, event, announcement, and innovation sections that disappear cleanly
  when no approved content exists.

Editors manage homepage presentation through **Administration → Homepage** and
site-wide wording through **Administration → Site content**.

On a new database, initialize homepage configuration with:

```powershell
php database/seeds/homepage-sections.php
```

The seed is safe to rerun and preserves editor changes.

## Staff experience

The directory supports search, department/category/expertise filters, active
filter summaries, pagination, consistent portrait treatment, and accessible
empty states. Published profiles expose qualifications, expertise, contacts,
research relationships, publications, projects, and supervision information
when those records are available.

## Asset provenance

- The MUST logo is sourced from the official MUST website.
- The FAST building photograph was sourced from Uganda Radio Network.

Confirm reuse rights for third-party photographs before a production launch.
Use officially approved institutional artwork wherever possible.

## Quality checks

Before release, verify keyboard navigation, focus visibility, colour contrast,
reduced-motion behavior, responsive layouts, public-route status codes, browser
console output, image licensing, and content accuracy.

# PCSD School Fees

WordPress plugin for managing Provo City School District's per-school annual fee data.
Replaces the per-year `pcsd-school-fees-XXXX` plugin pattern with a single plugin where
year is *data*, not a separate post type.

## What this solves

The legacy pattern required duplicating a plugin, ~7 theme template files, and ~40
WordPress Pages every school year. Updating a single fee description meant editing it
in five separate posts (one per active year), and again in Spanish. Adding a new year
took hours of copy-paste.

This plugin replaces that with:

- **One CPT, one post per program.** "Culinary" is one post; its yearly fee data lives
  inside an ACF `yearly_data` repeater. Adding next year is one new row, not a new post.
- **One set of theme templates.** Year-agnostic, sourced from the program's data.
- **Tools → Generate School Fees Year** to create the next year's WP Pages and seed
  yearly_data rows from the prior year.
- **Tools → Migrate Legacy School Fees** to bring the legacy per-year CPT data into
  the new structure.

## Architecture at a glance

| Concern | How it's handled |
|---|---|
| Post type | `school_fees` (English), `pagos_escolares` (Spanish, parallel + strippable) |
| Year | `school_fee_year` taxonomy as a registry of valid years; per-row data picks one. Term meta `archived` hides old years from public listings. |
| URL for a fee | `/school-fees/<slug>/` — no year segment; the page shows all years stacked |
| Fee schema | Code-registered ACF group ([includes/acf-fields.php](includes/acf-fields.php)). Edit there, not in the ACF UI. |
| Year filter in admin | Auto-populated by the save hook in [includes/sync.php](includes/sync.php), which keeps the post's `school_fee_year` terms in lockstep with its yearly_data rows |
| General district fees | Boolean `is_general_district_fee` + `school_level` choice replaces the legacy hardcoded post IDs |

## File map

```
pcsd-school-fees/
├── pcsd-school-fees.php          Bootstrap + activation hook
├── includes/
│   ├── post-types.php            CPT registration
│   ├── taxonomy.php              school_fee_year + archive flag + admin column
│   ├── locations.php             Centralized school list + level lookup
│   ├── acf-fields.php            Code-registered ACF groups
│   ├── sync.php                  yearly_data → taxonomy term sync
│   ├── shortcodes.php            Dynamic year list shortcodes for landing pages
│   ├── templates.php             theme_page_templates + template_include filters
│   ├── admin.php                 Year filter dropdown on post lists
│   ├── migration.php             Tools → Migrate Legacy School Fees
│   ├── year-generator.php        Tools → Generate School Fees Year
│   └── help.php                  School Fees → How to Use admin page
├── templates/
│   ├── single-school_fees.php                    Program page (all years stacked)
│   ├── single-pagos_escolares.php                Spanish program page
│   ├── template-school-fee-menu.php              Year landing page (English)
│   ├── template-school-fees-by-location.php      Per-school fee table (English)
│   ├── template-pagos-escolares-menu.php         Year landing page (Spanish)
│   ├── template-pagos-escolares-by-location.php  Per-school fee table (Spanish)
│   └── taxonomy-school_fees_categories.php       Category archive
├── README.md                     This file
├── LICENSE                       GPL v2
└── .gitignore
```

## Installation

1. Drop this folder into `wp-content/plugins/` and activate it.
2. The activation hook registers the CPT + taxonomy and flushes rewrite rules.
3. Confirm under **School Fees → Years** that the year terms exist (or add them — name
   "2026-2027", slug "26-27").

## Admin UI

- **School Fees** menu — list of programs (English) with year filter and year column.
- **Pagos Escolares** menu — Spanish parallel.
- **School Fees → Years** — manage year terms and the archived flag.
- **Tools → Migrate Legacy School Fees** — one-time tool that merges legacy per-year
  CPT data into unified programs. Also includes a "Sync Categories" section that copies
  legacy category assignments to both English and Spanish programs. Idempotent; safe to re-run.
- **Tools → Generate School Fees Year** — creates the year term, year landing page,
  and per-school child pages for a new year. Optionally clones fee data from the prior year.
  Also includes a "Clean Up Year Pages" section to unpublish a year's pages when archiving.

## Adding a new year

1. Go to **Tools → Generate School Fees Year**, enter the slug (e.g., `26-27`), optionally
   clone prior-year fee data, and run it. This creates the year term, landing page, and
   per-school child pages in one step.
2. Open each program and add or update its `26-27` yearly_data row.
3. To hide an old year from public listings without deleting anything: **School Fees →
   Years**, edit the term, check **Archived**.

## Removing the Spanish CPT (if TranslatePress takes over)

The Spanish CPT is structured to be removable cleanly:

1. Remove the `register_post_type('pagos_escolares', …)` block from
   [includes/post-types.php](includes/post-types.php).
2. Remove the matching `pagos_escolares` location rule from each ACF field group in
   [includes/acf-fields.php](includes/acf-fields.php) (each rule is a separate top-level
   array entry, so deletion is one block of lines).
3. Delete `single-pagos_escolares.php` and any other `pagos_escolares`-specific
   templates from the theme.

The English side (`school_fees`) is unaffected.

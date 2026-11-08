# Submission candidate 0.5.2: final verification

Verified on 8 November 2026. Production source:
`c1107d63ff7767460fcbd198ee1efc6957aea37f`.
The package is prepared for review; no WordPress.org submission or publication
has been made.

## Installable package

| Property | Verified value |
| --- | --- |
| Filename | `rocketsky-antispam-0.5.2-wordpress-org.zip` |
| Size | 348,830 bytes |
| Production files | 73 |
| SHA-256 | `f747eac2b89b541ff9a50233dc5b3058f19ef616a914dfe8b03326b413ec57b9` |
| Top-level directory | `rocketsky-antispam/` |
| Contributors | `rocketsky`, `theacki`, `chrigu99` |

The archive was built from a clean committed checkout. An independent build
produced identical bytes. A separate GitHub-channel package differs only by
its GitHub Update URI header. The directory package uses WordPress.org updates.
Tests, development dependencies, documentation and directory screenshots are
excluded from the installable archive.

## Checks

[CI run 42470110283](https://github.com/rktsky/rocketsky-antispam/actions/runs/42470110283)
passed all 18 jobs on the production source above. It covers PHP 8.2–8.5,
WordPress 6.6 and current WordPress, MySQL 8.0, additional form providers,
WooCommerce, Multisite, the previous-name transition and browser protocol tests.

The official Plugin Check 2.2.0 returned **zero errors and zero warnings** for
the exact archive, both locally with runtime initialization and in strict CI.
All 73 installed files matched the archive before and after the local check.
No check or finding was excluded. The original raw output is preserved with
the archive manifest and checksum.

Four fresh local WordPress 7.1.4 / PHP 8.3.26 / SQLite Database Integration
3.1.3 fixtures passed 361 native assertions: single-site 88, Multisite 118,
upgrade from the actual old 0.5.0 source on single-site 71 and Multisite 84.
The runs verify native ZIP installation, activation, retained settings and
encrypted records, existing review references and replay protection, uninstall
with retention, reinstallation, and complete removal of owned data. Unrelated
WordPress data is preserved. All subprocess stderr streams were empty.

The translation checks cover all 329 catalogue keys in each of `de_CH`,
`de_DE`, `fr_FR`, `it_IT` and `es_ES`: 326 translatable source/header messages
and three fixed identity headers. English is the source language. GNU format
checks, native PO/MO comparison and all 1,645 native JIT lookups pass. Official
WP-CLI extraction finds no missing or stale key. Separate native translation
tests pass 198 assertions, including plugin-header descriptions, language-pack
priority, locale restoration, conflict notices and localized startup. The exact
ZIP also passes 20 fresh localized starts across the four fixtures.

The administration suite passes 329 assertions; the widget suite passes 22.
The 56 policy tests include the actual operative rejection rate. Updated
message assertions retain rejection, notification, retry and replay checks.
All 98 owned PHP files pass syntax checks, and the 52 production PHP files
pass WordPress Coding Standards without errors or warnings.

## Interface and directory images

The final layout review uses native Firefox 153.0 on macOS with 320, 390, 768
and 1440 CSS-pixel viewports: 100 main-page states and 40 empty/populated widget
states, followed by targeted checks of the final translations and quarantine
copy. These runs report no layout violations or JavaScript errors. This is
desktop viewport testing; the device and provider coverage limits in
[verification.md](verification.md) still apply.

Six English PNGs show the real administration and its actual empty states:
Overview, Integrations, Filter rules, Review spam, Settings and the WordPress
dashboard widget. They are supplied separately as
`rocketsky-antispam-0.5.2-directory-assets.zip`, with captions and image checksums.
Use `screenshot-1.png` through `screenshot-6.png` in the directory's SVN `assets/`
directory after approval. They do not belong in the plugin's `trunk/` package.
The corresponding descriptions and FAQ are in `readme.txt`.

The review link, voluntary Ko-fi contribution, Rocketsky sponsorship and Speo
promotion share the plugin's own administration layout. They use fixed external
links with no embeds or tracking parameters. No feature depends on a review or
donation, and no global reminder is added. The WordPress.org review destination
is prepared for the proposed slug and becomes usable when the listing is
published.

Local handoff artifacts are in
`outputs/rocketsky-antispam/submission-0.5.2/`: the archive and manifest,
Plugin Check raw/text/JSON output, native ZIP results, CI result, directory
images and their browser metadata. Earlier candidates remain separate from
this final handoff. Temporary browser users and records were removed; fixture
options and all six owned tables were restored and the browser server stopped.

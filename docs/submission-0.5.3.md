# Submission candidate 0.5.3: final verification

Verified on 8 November 2026. Production source:
`59be913873c313bb847235acad6717ca4b887894`.
No WordPress.org submission or publication has been made.

## Package

| Property | Verified value |
| --- | --- |
| Archive | `rocketsky-antispam-0.5.3-wordpress-org.zip` |
| Size | 358,792 bytes |
| Production files | 74 |
| SHA-256 | `e2562c052cb809ca5aa1681e4ac80faef506c2cfa5dbc3c8ea11c6bef1f08d0c` |
| Proposed directory slug | `rocketsky-antispam` |

Independent builds are byte-identical. A build after the final test-only commit
also produces the same archive. The GitHub-channel package differs only by its
Update URI header. Development files and directory screenshots are excluded
from the installable archive.

[CI run 42474439502](https://github.com/rktsky/rocketsky-antispam/actions/runs/42474439502)
passes all 18 jobs on `bb7767d1a064068cf089d5b8ba38df0772f2803f`. Its production
files are identical to the package source above. The matrix covers PHP 8.2–8.5,
WordPress 6.6 and current WordPress, MySQL 8.0, native provider engines,
WooCommerce, Multisite, previous-name transitions and browser protocol tests.

Plugin Check 2.2.0 reports **zero errors and zero warnings** for the exact ZIP,
with local runtime initialization and strict CI. All 74 installed files match
the archive before and after the local check. No checks or findings are excluded.

## Time zones and retained data

The new native time-zone suite passes 80 assertions, including Zurich's 23-
and 25-hour days, Kathmandu's quarter-hour offset, fixed WordPress offsets,
consecutive local dates, original-calendar reclassification, daily mail claims,
and WordPress-formatted quarantine dates. It also checks an actual old primary
key, a blocked schema alteration, the unchanged old data and replay protection,
the visible reporting failure state, and a successful retry. The fixture's six
owned tables and saved options are restored exactly afterwards.

The exact ZIP passes 515 native assertions across six fresh WordPress 7.1.4 /
PHP 8.3.26 / SQLite Database Integration 3.1.3 fixtures:

| Path | Assertions |
| --- | ---: |
| Fresh single-site | 88 |
| Fresh Multisite | 118 |
| Actual 0.5.0 name transition, single-site | 71 |
| Actual 0.5.0 name transition, Multisite | 84 |
| Actual 0.5.2 ZIP replacement, single-site | 68 |
| Actual 0.5.2 ZIP replacement, Multisite | 86 |

The same-name updates use Core's native ZIP overwrite, without deactivating,
deleting or reactivating the plugin. Fresh main-site and subsite requests load
0.5.3 and migrate schema 5. Existing settings, encrypted records, HMAC references,
used-token claims and cron due times are retained. Old daily values gain only
their original `calendar=UTC` provenance; their counts and dates are unchanged.
Retain/uninstall/reinstall and complete owned-data deletion are verified
separately. All subprocess stderr streams are empty.

All five bundled catalogues contain 335 keys: 332 translatable messages and
three fixed identity headers. GNU format checks, 1,675 native PO/MO comparisons
and 1,675 native JIT lookups pass. Official WP-CLI extraction finds no missing
or stale key. The native translation suite passes 198 assertions; the archive
also passes 30 fresh localized starts. All 100 owned PHP files pass syntax
checks, and all 53 production PHP files pass WordPress Coding Standards.

## Design and update verification

The reported support-area failure was reproduced in Firefox's real HTTP cache:
newer markup and an older stylesheet shared the 0.5.2 asset URL. Without clearing
the browser cache, native 0.5.3 markup requests `admin.css?ver=0.5.3` and loads
the matching support layout and SVG strokes. Server request counts and both
file hashes are recorded. No original WordPress or provider file was modified.

The final native UI review covers 100 plugin-page states and 40 widget states
across English, German, French, Italian and Spanish at 320, 390, 768 and 1440
CSS pixels. Disclosures are checked closed and expanded. Additional coverage
includes 77 assertions and 42 layout states for populated quarantine, Kathmandu
dates, historical calendars, damaged ciphertext, escaping, dirty-state flows,
nonce failures, deletion, expiry, statistics reset and subscriber restrictions.
The observed historical-disclosure padding defect is corrected.

The final matrix and six directory captures report no JavaScript errors.
A separate early navigation-abort diagnostic on Core's subscriber profile
page is retained in the private evidence; after waiting for login to finish,
the page works with and without the plugin and loads no plugin assets.
These are native Firefox tests on macOS with viewport emulation, not a physical
device, other-browser or accessibility certification.

Six English directory PNGs show the actual empty states with the fixture's
WordPress time zone set to Europe/Zurich. They are supplied separately in
`rocketsky-antispam-0.5.3-directory-assets.zip`, with captions and checksums.
The German support-footer preview is separate. All temporary users and records
are removed, options and all six owned tables restored, and private servers,
proxy and fault helpers stopped or removed.

The initial CI failure in one Formidable fixture was traced to random UUID
fragments matching its unchanged native denylist. A fixed UUID containing
`777-` reproduces that rejection; separated hex pairs with the same bits pass.
The positive fixture now uses those pairs. Spam status and the prohibition of
undeclared metadata are asserted separately; the provider's spam checks and
all existing metadata restrictions remain enabled.

Artifacts are under `outputs/rocketsky-antispam/submission-0.5.3/`: ZIP, manifest,
checksum, complete Plugin Check output, native package report, final CI result,
directory images and browser metadata. Cache reproduction and additional UI
evidence are under `outputs/rocketsky-antispam/update-0.5.3/`.

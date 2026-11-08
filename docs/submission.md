# WordPress.org submission handoff

| Field | Prepared value |
| --- | --- |
| Plugin name | Rocketsky Antispam |
| Proposed slug | `rocketsky-antispam` |
| Version | 0.5.2 |
| Publisher | Rocketsky |
| Confirmed WordPress.org contributors | `rocketsky`, `theacki`, `chrigu99` |
| Licence | GPL-2.0-or-later; bundled Speo vector asset under BSD-3-Clause |
| Minimum WordPress | 6.6 |
| Minimum PHP | 8.2 |
| Tested WordPress series | 7.1, with additional minimum-version tests on 6.6 |
| Source | https://github.com/rktsky/rocketsky-antispam |
| Installable archive | `rocketsky-antispam-0.5.2-wordpress-org.zip` |

The actual checksum and Plugin Check evidence belong in the final verification
record and external package manifest. The readme contributor names were
explicitly confirmed by the publisher; the account used for the actual upload
still determines the submission owner.

The [final 0.5.2 verification](submission-0.5.2.md) records the exact package,
successful CI run, Plugin Check result, native lifecycle tests and six prepared
directory screenshots. The FAQ and screenshot captions are in `readme.txt`.

## Prepared submission description

Rocketsky Antispam provides local spam protection for WordPress comments,
registration and supported contact forms. It combines signed single-use form
tokens, honeypots and optional local content rules. Supported integrations are
configured through the administration interface, without form-editor snippets.

The plugin makes no external spam or IP-lookup requests, adds no telemetry and
uses no visitor cookies or browser storage. Optional encrypted quarantine and
local reviewed-decision metadata have bounded retention and WordPress privacy
workflows. Native provider spam queues retain their own storage and moderation
settings. Technical rejections occur before native entry creation.

The plugin is GPL-licensed, includes all functionality without a paid tier and
ships readable source and bundled translations. Its WordPress.org archive uses
the native directory update channel. Source, integration contracts and test
evidence are maintained in the public GitHub repository.

## Before the actual upload

Use the intended WordPress.org account and a monitored publisher email address.
Verify the final name/slug in the submission form: public availability checks
do not reserve a slug. Upload only the reviewed installable archive. Keep its
manifest and checksum for correspondence with the review team. This preparation
does not include accepting terms, submitting the form, sending email or publishing
an SVN release.

Official entry point: [Add your plugin](https://wordpress.org/plugins/developers/add/).
The review team makes the final directory and naming decision.

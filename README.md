# Rocketsky Antispam

Rocketsky Antispam protects comments and forms in the background, without adding a CAPTCHA. All features are free, and spam checks run on your own website without sending submissions to an external spam service.

Developed by **Rocketsky**, licensed under GPL-2.0-or-later. The bundled Speo logo retains its BSD-3-Clause licence.

**Version 0.5.5 adds personal controls for integration notices and guidance for statistics storage errors.** Version 0.5.4 was [submitted to WordPress.org](docs/wordpress-org-submission.md) on 11 November 2026. See the [review verification](docs/review-2026-11-13.md), [email format](docs/email.md) and [tested versions and coverage](docs/verification.md).

## Installation

Requires WordPress 6.6 or later and PHP 8.2 or later. Form plugins have their own requirements.

1. Install the directory as `rocketsky-antispam` inside `wp-content/plugins/` and activate it.
2. Open **Settings → Rocketsky Antispam → Integrations** and enable the integrations you use. Protection is added to supported forms automatically.
3. Submit a normal message and test a rejection followed by a retry. Protected forms need JavaScript and access to the website's REST API.

WordPress comment protection starts enabled. Form, registration and checkout integrations start off; updates keep your saved choices. An unavailable integration stays disabled without losing its saved choice. Availability means that a compatible provider is active, not that a published form has been found.

Supported integrations are Contact Form 7, Gravity Forms, WPForms, Formidable Forms, Fluent Forms, WS Form, Elementor Forms, Divi 4 and 5 contact forms, WordPress registration, and WooCommerce checkout and registration. Install the provider you want to use; some, such as Elementor Pro, require their own paid licence. Rocketsky Antispam has no paid tier.

### Updating from the previous name

Version 0.5.1 changed the name to **Rocketsky Antispam**, with directory and text domain `rocketsky-antispam` and entrypoint `rocketsky-antispam.php`. WordPress treats the new path as a separate installation.

1. Back up the website and install the new directory without activating it.
2. Deactivate the previous **WP Antispam** copy. **Do not delete or uninstall it.**
3. Activate **Rocketsky Antispam** in the same scope: individually or network-wide.
4. Clear page and CDN caches, then load a protected form again and test it. Cached pages can still point to scripts in the old directory.
5. Check your settings, statistics and retained messages. Remove only the inactive old directory through the hosting file manager. Its WordPress **Delete** action can erase the shared data.

Before network activation, deactivate previous network and individual activations. Each site's settings and data remain separate. Active copies that share data are protected against conflicting activation and uninstall.

Existing settings, six tables, encryption keys, review references, token protocol and cron hook names stay unchanged. No records are copied, reset or re-encrypted. Custom translations outside the plugin need the new text domain and matching filenames. See [the transition contract](docs/renaming.md).

## Administration

- **Overview:** protection status, recent activity and reviewed results.
- **Integrations:** enable providers and configure the optional timing check.
- **Filter rules:** keyword, BBCode, regular expression, language and country filters.
- **Review spam:** open spam folders, read quarantine and assess delivered submissions.
- **Settings:** privacy, retention, email summaries and uninstall behaviour.

Each settings page saves its own fields and keeps other sections unchanged. Privacy-conscious mode is configured under Settings.

## Where rejected messages go

**Review spam** links to the available spam folders. WordPress comments stay under **Comments → Spam**. Form integrations use their provider's spam queue where supported and configured; Contact Form 7 can use Flamingo. Form-specific storage choices are respected. The review link for each integration also appears on its administration screen.

A failed verification check stops processing before a comment or form entry is saved. Messages caught by content rules can go to the provider's spam queue. Its storage, moderation and retention settings apply independently of Rocketsky Antispam privacy-conscious mode. A provider's **Not spam** action may run follow-up actions, such as notifications.

Optional encrypted **Rocketsky Antispam quarantine** can keep selected fields from rejected contact form submissions that do not go to a provider's spam queue. It must be enabled before those messages arrive. Account, password, order, payment and file contents are excluded. Quarantine lets you read, assess and delete retained messages; an assessment updates statistics without resending a form.

Provider versions and form-specific conditions are documented in [integration contracts](docs/integrations.md).

## Checks and scope

Signed tokens, a hidden field and one-use checks verify submissions. The optional minimum completion time starts at zero to accommodate fast users and autofill. Form plugins keep their own validation and spam checks.

Keyword, BBCode-link and regular-expression filters inspect selected fields on this website. WordPress comments can also be filtered by language or country. Language detection uses a local heuristic; short or ambiguous text can return an unknown result, whose handling you can configure.

Country filters need a local PHP GeoIP extension or a country header from explicitly trusted proxies. The proxy must replace headers supplied by visitors. Hosting maintains the local GeoIP source; the plugin does not download IP databases or contact a location service. VPNs and proxies can affect the result.

Divi, registration and checkout integrations use technical checks only; content filters and quarantine do not apply. Classic WooCommerce checkout and final Store API checkout requests are checked before processing or payment. WooCommerce controls existing orders and drafts. Coverage for gateways, uploads and commercial add-ons is recorded in the integration documentation.

A bot that runs JavaScript or reproduces the public verification protocol can obtain valid tokens. Passing a check does not prove that the sender is human or that a message was delivered. Manual spam and traffic attacks need additional measures.

REST clients that create comments must supply valid protection fields. Pingbacks and trackbacks start off. They can be allowed outside privacy-conscious mode; WordPress then verifies pingbacks by requesting the source page and may send it the sender's IP address. Rocketsky Antispam does not change other plugins' captcha or external-service settings.

## Privacy and retention

Privacy-conscious mode starts enabled. It prevents new quarantine storage, country filters, administrator summary emails and pingbacks/trackbacks. Content filters and statistics remain available. Existing quarantine records keep their expiry, and form plugins continue to follow their own storage settings. This mode cannot certify a website's compliance with GDPR or the Swiss FADP/nDSG.

Rocketsky Antispam sets no cookies and uses no browser storage, fingerprints, telemetry or external spam service. Its own tables store no IP addresses or user agents. The browser sends the provider ID, form ID and a signed public form reference to the website's own verification endpoint.

Stored data:

- **Used token digests:** expire within two hours and are removed by scheduled cleanup. Their expiry remains enforced even if cleanup runs late.
- **Daily counts:** contain the date in the WordPress website time zone, that time zone, integration, result reason and count. Earlier time zones stay separately available until their normal retention expires; their dates are not shifted or merged into the current period. Retention is 30 days by default, configurable from one to 90 days. The total rejected-comment count stays until reset.
- **Quarantine:** with explicit opt-in, keeps selected names, email addresses and message fields encrypted on this website. The limit is 500 records, for seven days by default and at most 30 days. Password, hidden, payment and file fields are excluded. WordPress privacy export and erasure support matching contact email addresses.
- **Review data:** keeps the original decision, a later assessment and timestamps for up to 90 days, also in privacy-conscious mode. Site-keyed HMAC values replace raw case and entry IDs, without copying message contents. They remain locally linkable while the source exists. Supported source-deletion and privacy workflows remove or detach references; reviewed totals can remain until their original expiry.

Scheduled cleanup depends on WordPress cron and can run late. Optional spam-comment cleanup permanently deletes WordPress comments older than the selected age that are already marked as spam. Set it to zero to turn this cleanup off. Form providers control their own spam cleanup.

Daily email summaries contain the preceding calendar day's rejection counts in the website time zone and use WordPress mail delivery. No message contents are included. **Delete activity statistics** removes daily counts and the total rejected-comment count, while keeping replay protection and the separate review data.

Deactivation stops cleanup without deleting data. Uninstall removes the plugin's own tables and options by default; you can choose to keep them. WordPress comments and form entries are kept.

WordPress, form plugins, hosting, mail delivery and backups handle data independently. Use the suggested text in WordPress's privacy-policy guide as a starting point and adapt it to your enabled settings, infrastructure, retention and legal basis.

## Activity and reviewed results

Activity counts show which submissions passed or were stopped, including verification failures. The rejection rate is the share stopped; it is not a measure of spam detection accuracy.

Precision, recall and accuracy use cases reviewed within the 90-day window. Unreviewed cases do not count as correct decisions. These rates describe the reviewed cases, rather than every submission to the website.

Review quarantine, use supported WordPress or form-plugin spam moderation, or assess a delivered submission with an entry ID or reference. Reusing a reference updates that case; removing it leaves the original message in place. Unknown historical entries are not automatically counted as missed spam. Assessments do not create automatic filter rules. See [the integration contracts](docs/integrations.md) for the supported moderation and privacy workflows.

## Releases and development

WordPress.org submission packages are built from a clean committed checkout:

```sh
python3 tools/build-release.py --channel wordpress-org --output ../outputs/rocketsky-antispam/release-0.5.5
```

The installable ZIP includes bundled languages and uses the directory update channel. Its manifest records the source commit and every file checksum. Development tools, tests and internal documentation are excluded. GitHub packages retain a separate Update URI and are updated manually. See [the release process](docs/releasing.md) and [submission details](docs/submission.md).

PHP classes live in `src/`, escaped templates in `templates/` and readable assets in `assets/`. Enabled integrations load as separate adapters. The plugin has no runtime build dependencies. WordPress, providers and vendor packages remain unchanged.

WordPress and provider records use documented APIs. Prepared queries operate only on six plugin-owned tables. A narrowly scoped Fluent Forms model insert stores verified spam without running notification or payment actions. Its boundary is documented in [the security model](docs/security.md).

Further documentation: [administration design](docs/design.md), [Multisite](docs/multisite.md), [tests](docs/verification.md) and [name transition](docs/renaming.md).

You can support development and maintenance with a voluntary donation at [Ko-fi](https://ko-fi.com/rocketsky). All features remain free.

# Rocketsky Antispam

An open-source WordPress plugin by **Rocketsky**, licensed under GPL-2.0-or-later. Local spam protection for WordPress comments and registration, Contact Form 7, Gravity Forms, WPForms, Formidable Forms, Fluent Forms, WS Form, Elementor Forms, Divi contact forms, and WooCommerce checkout and registration. All features are included, with no paid tier, external captcha, or spam service.

**Version 0.5.1 is a development candidate.** Actual test coverage and remaining release requirements are recorded in [verification](docs/verification.md). The plugin has not been approved by the WordPress.org plugin directory.

## Installation

1. Install the plugin directory as `rocketsky-antispam` inside `wp-content/plugins/` and activate it in WordPress. The technical minimum requirements are WordPress 6.6 and PHP 8.2; tested versions are listed in the verification document.
2. Open **Settings → Rocketsky Antispam → Integrations** and enable the providers you use. Supported forms receive protection automatically. No snippets or form-editor changes are required.
3. Test a normal submission and a rejection/retry case on your website. Protected forms require JavaScript and access to the local REST endpoint. Rocketsky Antispam does not change existing captcha or external-service settings in form plugins.

Comment protection is enabled by default. Form, account, and checkout integrations require explicit activation on new installations. Existing saved choices are preserved during updates. An unavailable provider has a disabled, unchecked control; its saved choice remains available if the provider is reactivated. Provider availability describes integration readiness, rather than the number of forms created or published.

REST comment creation also requires valid protection fields. External comment applications and custom integrations need to account for this requirement. Pingbacks and trackbacks are rejected by default. They can be explicitly allowed outside privacy-conscious mode; native WordPress pingback verification requests the source page and may forward the sender IP address.

Multisite supports individual and network activation, with separate settings and data for each website.

GitHub builds are updated manually. Their Update URI prevents WordPress from offering an unrelated plugin-directory update under the same development slug.

### Upgrading from the previous name

Version 0.5.1 renames the plugin to **Rocketsky Antispam**, with the directory and
text domain `rocketsky-antispam` and entrypoint `rocketsky-antispam.php`.
WordPress treats this as a different installation path.

1. Back up the website and install the new directory without activating it yet.
2. Deactivate the previous **WP Antispam** installation. **Do not delete or uninstall it.**
3. Activate **Rocketsky Antispam** in the same scope: individually or network-wide.
4. Check the existing configuration, activity statistics and retained entries.
   The previous directory can then be removed as files through the hosting file
   manager. Do not use its WordPress **Delete** action: older uninstall scripts
   share the same data and can erase it.

For Multisite, deactivate previous network and individual activations before
network-activating the renamed plugin. Settings and records remain separate for
each site. The new plugin rejects conflicting activation and blocks an obsolete
copy's native uninstall while it is active.

The rename preserves the existing `rktsky` settings, six tables, encryption keys,
feedback references, token protocol and scheduled hook names. No records are
copied, reset or re-encrypted. Custom translations outside the bundled language
directory need the new text domain and matching filenames; their files are not
rewritten automatically. See [the transition contract](docs/renaming.md).

## Administration

The administration is organised by task:

- **Overview:** protection status, activity, and reviewed detection results.
- **Integrations:** enable available providers and configure technical checks.
- **Filter rules:** configure content and comment policies.
- **Review spam:** open the appropriate spam queue, review quarantine, and classify delivered submissions.
- **Settings:** privacy-conscious mode, retention, notifications, and uninstall behaviour.

Each settings page saves its own fields while preserving other sections. Privacy-conscious mode is configured centrally under Settings.

## Where rejected submissions go

**Review spam** links to the review destinations of all enabled, available integrations. The same destinations appear beside each integration and in notices on relevant provider administration screens. WordPress comments remain under **Comments → Spam**. Supported form providers use their own spam queues where the required workflow is available; Contact Form 7 can use Flamingo. Form-specific storage settings are respected.

When suitable native storage is unavailable, selected rejected message fields can be kept in the optional encrypted **Rocketsky Antispam quarantine**. If retention is off, Rocketsky Antispam does not store those contents and cannot recover earlier rejections. The interface states this explicitly. Account, password, order, and payment contents are excluded from quarantine.

Invalid technical proofs stop processing before a native entry is created. Authentic submissions matching a content rule can be retained in the appropriate native spam queue. Native storage and retention follow the form provider's own settings, independently of Rocketsky Antispam quarantine and privacy-conscious mode. A native **Not spam** action may trigger that provider's follow-up actions; the provider controls its moderation workflow.

Provider versions, form-specific conditions, and verification limits are documented in [integration contracts](docs/integrations.md).

## Protection and limits

A signed form reference allows the browser to request a fresh, locally signed token. The server checks its provider and form scope, age, dynamically named honeypot, and atomic one-time use. The optional minimum completion time is off by default because it can reject fast submissions and autofill. Native provider permissions, validation, and spam checks remain in place.

Optional keyword, BBCode-link, and bounded regular-expression rules inspect selected declared fields locally. Comments can also be filtered by a local language heuristic or country policy. Language detection is unreliable for short or ambiguous text; the handling of unknown results is configurable.

Country checks require a local PHP GeoIP source or a country header supplied by explicitly configured trusted proxy addresses. A browser-provided header alone is not trusted. Hosting must maintain the local GeoIP source; the plugin does not download IP databases or call external location services.

A bot that runs JavaScript or reproduces the public challenge protocol can obtain valid tokens. Passing a local check does not establish that the sender is human or that a message was delivered. Manual spam, compromised websites, and volumetric attacks require additional measures.

WordPress registration, WooCommerce registration, and checkout use technical checks only. Passwords, account details, payment information, and addresses do not enter content rules or quarantine. Classic WooCommerce checkout and final Store API checkout requests are checked before processing. Existing orders and native drafts remain under the WooCommerce lifecycle. Unverified gateway transports and specialised commercial form add-ons need their own acceptance tests.

Divi contact forms use technical verification without content rules or message retention. See the documented version and transport coverage before relying on a particular setup.

## Privacy and retention

- The default **privacy-conscious mode** disables new content capture in Rocketsky Antispam quarantine, country checks, administrator digests, and native pingbacks/trackbacks. Local content and language rules remain available. Native provider storage follows separate settings. Enabling this mode does not delete existing quarantine records; they remain access-controlled until expiry or manual deletion. This mode does not certify a website's compliance with the Swiss FADP/nDSG or GDPR.
- With explicit opt-in, encrypted local quarantine retains selected rejected contact fields: up to 500 records, for seven days by default and at most 30 days. Hidden, password, payment, and file fields are excluded. WordPress personal-data export and erasure support matching contact email addresses. Rocketsky Antispam does not store IP addresses, user agents, or browser fingerprints in its own storage.
- The plugin sets no cookies and uses no local storage, telemetry, external spam service, or IP lookup. Optional daily administrator emails contain aggregate counts and use the website's configured WordPress mail delivery.
- The browser sends only the provider ID, form ID, and signed public form reference to the website's own challenge endpoint. Consumed random-token digests and their expiry are stored locally; a digest does not contain encrypted form contents.
- Tokens are valid for two hours. Hourly WP-Cron cleanup removes expired digests in bounded batches. Delayed cron can extend physical retention, while token validity remains unchanged.
- Aggregate counters contain the UTC day, provider, fixed result reason, and count. Default retention is 30 days, configurable from one to 90 days. The lifetime rejected-comment counter remains until statistics are reset. Technical rejections are included and are not presented as confirmed spam detections.
- **Delete activity statistics** removes daily counters and the lifetime rejected-comment total. Replay protection and the separate 90-day reviewed-outcome dataset remain intact. Deactivation stops scheduled cleanup and retains data. Uninstall removes owned tables and options by default; administrators can choose to retain them.
- Optional scheduled cleanup permanently deletes WordPress comments already marked as spam once they exceed the configured age. Zero disables this feature; the maximum is 90 days. It operates in bounded batches and is independent of quarantine retention. Other providers retain control of their own spam cleanup.

WordPress, form providers, hosting, mail delivery, and backups may process personal data independently. The WordPress privacy-policy guide includes editable disclosure text. Website operators must adapt it to the enabled features, infrastructure, retention, and applicable legal basis.

## Statistics and reviewed results

The dashboard shows daily activity, the rejection rate, and the total number of rejected comments. The operational rejection rate includes technical failures.

A separate quality view calculates precision, recall, and accuracy from cases reviewed within a 90-day window. Unreviewed cases remain separate and are excluded from quality denominators. These rates describe the reviewed dataset, rather than an unbiased measurement of every submission.

Quarantined rejections can be assessed as spam or normal messages. Supported native moderation changes update existing observations; unknown historical entries are not automatically treated as missed spam. Administrators can classify delivered cases using stable local references. Reusing a reference updates the same case, and references can be forgotten explicitly. Assessment alone does not publish comments or resend forms, and it does not train a new automatic filter rule.

The feedback ledger keeps immutable original decisions, subsequent labels, and bounded timestamps. Site-keyed HMAC references replace raw entry and case identifiers; they remain locally linkable pseudonyms while linked. Source deletion removes or detaches related references. Reviewed outcomes can remain within their original evaluation window after detachment. Confirmed privacy workflows use the supported native subject mappings described in the integration documentation.

Daily administrator notifications combine the preceding UTC day's aggregate rejections into one digest.

## Development

The plugin has no build dependencies at runtime. PHP classes live in `src/`, escaped templates in `templates/`, and readable owned assets in `assets/`. Functional changes, visual changes, and pure formatting use separate commits. WordPress, provider packages, and vendor code remain unchanged. Development tools and test installations are excluded from distribution.

The adapters are separate modules under `src/Integrations/`. Only enabled integrations with an available native contract are loaded. A shared provider registry defines supported scopes, labels, and statistics categories.

WordPress and provider records use their documented APIs. Prepared database operations are limited to the six approved owned tables. A separate, narrowly approved Fluent Forms model insert handles native spam storage where its normal submission workflow cannot safely stop notification and payment actions. The exact boundary is documented in [the security model](docs/security.md).

Project documentation:

- [Architecture, security, and data handling](docs/security.md)
- [Integration contracts and provider versions](docs/integrations.md)
- [Administration design](docs/design.md)
- [Test evidence and remaining coverage](docs/verification.md)
- [Release process](docs/releasing.md)
- [Multisite behaviour](docs/multisite.md)
- [Name transition and data preservation](docs/renaming.md)

Official references: [Plugin Handbook](https://developer.wordpress.org/plugins/), [Plugin Security](https://developer.wordpress.org/plugins/security/), [Inline Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/), and [Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).

=== WP Antispam ===
Tags: antispam, spam, contact form 7, gravity forms, wpforms
Requires at least: 6.6
Requires PHP: 8.2
Tested up to: 7.1
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local spam protection for comments and forms. No external spam service, tracking or captcha.

== Description ==

WP Antispam adds local checks to WordPress comments and registration, Contact Form 7, Gravity Forms, WPForms, Formidable Forms, Fluent Forms, WS Form, Elementor Forms, Divi contact forms and WooCommerce checkout and registration. Choose integrations under Settings → WP Antispam. New integrations require explicit activation; no form edits are needed.

Signed tokens, a dynamically named honeypot and atomic one-use checks protect submissions. An optional minimum completion time is disabled by default to accommodate autofill and fast users. Provider validation and existing spam decisions remain in place.

All functionality is included. No account, API key, paid plan or external spam service is required. The plugin is developed by Rocketsky and licensed GPLv2 or later.

= Privacy =

WP Antispam never stores IP addresses, user agents or fingerprints. Its default privacy-conscious mode disables new content storage, country checks and administrator mail. Optional local content rules inspect allowlisted fields. With explicit opt-in and privacy-conscious mode disabled, encrypted quarantine can retain selected contact fields for 1–30 days, up to 500 records, with WordPress privacy export and erasure. Password, hidden, payment and file fields are excluded. It sets no cookies and uses no browser storage or telemetry. Its challenge request goes only to the same website and contains a provider ID, form ID and signed public form descriptor.

The plugin stores digests of used random tokens and their expiry timestamps. Tokens expire after two hours. Expired digests are removed by hourly WordPress cron in bounded batches; delayed cron execution can extend physical storage. Local keyword, BBCode and bounded regular-expression rules can protect supported forms. Comment language checks use a local heuristic with an explicit unknown-language policy. Country checks need a local GeoIP source or a header from configured trusted infrastructure; no external IP lookup is performed.

Optional daily administrator emails contain aggregate counts only. Optional scheduled deletion removes old WordPress spam comments. The dashboard shows daily rejection counts and rates, plus a lifetime rejected-comment total until manual reset. Rejection rates include technical failures and are not measured detection accuracy.

Aggregate UTC-day counters store day, integration, result reason and count. Default counter retention is 30 days, configurable from 1 to 90 days. Scheduled deletion depends on cron execution. WordPress, hosting, backups and form providers have their own independent data handling.

Previously stored quarantine records remain access-controlled until expiry or manual deletion when privacy-conscious mode is enabled. Scheduled cleanup can run late. This mode cannot certify legal compliance of an entire website.

A suggested privacy-policy paragraph is available through WordPress's privacy-policy guide. Site operators must adapt their notice and evaluate their actual setup.

= Scope =

Protected forms require JavaScript and a working local REST API. REST comment creation requires protection tokens too. Native pingbacks and trackbacks are off by default and can be explicitly allowed outside privacy-conscious mode. WordPress may request the remote source and forward the sender IP for pingback verification. Multisite supports individual and network activation with separate site settings and storage.

This is a 0.3.0 development candidate. Browser-capable bots can obtain valid tokens; this plugin cannot guarantee human submissions or prevent volumetric attacks. See the source repository's verification and integration documentation before production use.

Source and verification: https://github.com/rktsky/wp-antispam

== Installation ==

1. Install the wp-antispam directory in wp-content/plugins and activate WP Antispam.
2. Open Settings → WP Antispam and choose integrations.
3. Save and test a normal submission and an error/retry case on your website.

== Frequently Asked Questions ==

= Do I need to edit forms or insert code? =

No. Supported native forms receive protection through their provider's hooks.

= Are other plugins' external services disabled? =

No. Review your form provider, captcha and hosting settings separately.

= Does deleting statistics reset replay protection? =

No. Used tokens remain protected until expiry.

= What happens on uninstall? =

Own tables and options are removed by default. You can opt to keep plugin data before uninstalling.

== Changelog ==

= 0.3.0 =
* Separates provider modules and adds native registration, Formidable, Fluent Forms, WS Form, Elementor Forms, Divi contact forms and WooCommerce integration.
* Adds retry-safe native checkout transports with technical checks only; account and payment fields are excluded from content retention.
* Groups integration settings and extends translated labels and provider statistics.

= 0.2.0 =
* Adds local content rules, privacy-conscious mode, encrypted opt-in quarantine, country/language policy, daily dashboard counts and optional maintenance/email digests.
* Adds German, French, Italian and Spanish translations alongside English source strings.

= 0.1.0 =
* Initial development candidate with local token protection, native integrations, settings and aggregate statistics.

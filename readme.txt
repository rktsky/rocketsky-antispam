=== Rocketsky Antispam ===
Contributors: rocketsky, theacki, chrigu99
Donate link: https://ko-fi.com/rocketsky
Tags: antispam, spam, contact form 7, gravity forms, wpforms
Requires at least: 6.6
Requires PHP: 8.2
Tested up to: 7.1
Stable tag: 0.5.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free local spam protection for comments and forms. No added CAPTCHA or external spam service.

== Description ==

Rocketsky Antispam protects comments and forms in the background, without adding a CAPTCHA. All features are free, and spam checks run on your own website without sending submissions to an external spam service.

Enable integrations under Settings → Rocketsky Antispam; no form edits or snippets are needed. Comment protection starts enabled. Other integrations start off, and updates keep your saved choices.

Integrations include Contact Form 7, Gravity Forms, WPForms, Formidable Forms, Fluent Forms, WS Form, Elementor Forms, Divi 4 and 5 contact forms, and WooCommerce checkout and registration. Install the provider you want to use; some providers require their own paid licence. All Rocketsky Antispam features are free, without an account, API key or external spam service.

Signed tokens, a hidden field and one-use checks verify submissions. Optional keyword, BBCode-link and regular-expression filters inspect selected fields. Comments can also be filtered by language or country. The optional minimum completion time starts at zero to accommodate fast users and autofill. Form plugins keep their own validation and spam checks.

Open Review spam to find the relevant spam folder or read quarantined messages. Messages caught by content rules can use the provider's own spam queue, including Flamingo for Contact Form 7, where supported and configured. Failed verification stops a submission before a comment or form entry is saved. Optional encrypted quarantine can keep selected fields from other rejected messages when the form allows storage.

= Privacy and retention =

Privacy-conscious mode starts enabled. It prevents new quarantine storage, country filters, administrator summary emails and pingbacks/trackbacks. Content filters and statistics remain available. It does not delete existing quarantine records or override form plugins' storage settings. This mode cannot certify a website's legal compliance.

The plugin sets no cookies and uses no browser storage, fingerprints, telemetry, external spam service or external IP lookup. Its own tables store no IP addresses or user agents. The browser sends only the provider ID, form ID and a signed public form reference to the website's own verification endpoint. Country filters use local GeoIP data or a country header from explicitly trusted proxies; VPNs and proxies can affect the result.

Used random-token digests expire within two hours. Daily counters use the WordPress website time zone and store the date, time zone, integration, result reason and count for 30 days by default, configurable from one to 90 days. Earlier time zones remain separately available until their normal retention expires. The total rejected-comment count stays until statistics are reset. Counts include verification failures and are separate from reviewed detection results.

Optional quarantine keeps selected names, email addresses and message fields encrypted on this website: up to 500 records, for seven days by default and at most 30 days. Password, hidden, payment and file fields are excluded. WordPress privacy export and erasure support matching contact email addresses. Existing records keep their expiry when quarantine is turned off.

Review statistics keep the original decision, a later moderator assessment and timestamps for up to 90 days, including in privacy-conscious mode. Entry and case references use site-keyed HMAC values; message contents are not copied. These references remain locally linkable while the source exists. Supported source-deletion and WordPress privacy workflows remove or detach related references. Reviewed totals can remain until their original expiry. No review data is sent to a third party.

Scheduled cleanup depends on WordPress cron and can run late. Optional cleanup permanently deletes WordPress comments older than the configured age that are already marked as spam. Form providers control their own spam retention. Optional daily email summaries contain counts only and use the website's WordPress mail setup.

WordPress, form plugins, hosting, mail delivery and backups have their own data handling. Adapt the suggested text in WordPress's privacy-policy guide to your enabled features and actual setup.

= Scope =

Protected forms need JavaScript and access to the website's REST API. REST comment creation requires protection fields too. Divi, account registration and checkout use technical checks; content filters and quarantine do not apply to those integrations.

Pingbacks and trackbacks start off and can be allowed outside privacy-conscious mode. WordPress verifies pingbacks by requesting the source page and may send it the sender's IP address. Multisite supports individual and network activation with separate settings and data for each site.

Version 0.5.4 updates the daily email summary. Version 0.5.3 was submitted to WordPress.org on 9 November 2026. This update has not replaced that submission. Bots that run JavaScript or reproduce the public verification protocol can obtain valid tokens. Passing a check does not prove that a sender is human. See the integration contracts and test evidence for the provider versions and workflows covered.

Source and verification: https://github.com/rktsky/rocketsky-antispam

== Installation ==

1. Install the rocketsky-antispam directory in wp-content/plugins and activate Rocketsky Antispam.
2. Open Settings → Rocketsky Antispam → Integrations and enable the integrations you use.
3. Test a normal submission and a rejection followed by a retry.

= Updating from the previous name =

Install the new directory, deactivate the previous WP Antispam copy without deleting or uninstalling it, then activate Rocketsky Antispam in the same scope. Before network activation, deactivate all previous individual and network activations.

Clear page and CDN caches, then load a protected form again and test it. Cached pages can still point to scripts in the old directory. Check existing settings, statistics and retained messages before removing only the inactive old directory through the hosting file manager. Its WordPress Delete action can erase the shared data.

Existing tables, encryption, review references and token signatures remain unchanged. Do not rename tables or change WordPress salts for this transition. Custom language files outside the plugin need the new rocketsky-antispam text domain.

== Frequently Asked Questions ==

= Do I need to edit forms or insert code? =

No. Enable a supported integration and protection is added to its forms automatically.

= Does the plugin send form data to an external service? =

No. Spam checks run on your WordPress website. Form plugins, hosting and mail delivery have their own data handling, which Rocketsky Antispam does not change.

= Where can I review rejected submissions? =

Open Settings → Rocketsky Antispam → Review spam. WordPress comments stay under Comments → Spam. Each form integration links to its spam queue or to Rocketsky Antispam quarantine. Quarantine must be enabled before it can keep selected rejected fields; messages already discarded cannot be recovered.

= Are other plugins' external services disabled? =

No. Check your form plugin, captcha and hosting settings separately.

= Can I restore a message marked as spam? =

Use Not spam in the original WordPress or form-plugin spam list. That provider's moderation workflow may then run follow-up actions, such as notifications. In Rocketsky Antispam quarantine, you can read, assess and delete messages. An assessment updates statistics without resending a form.

= Does protection require JavaScript? =

Yes. Protected frontend forms need JavaScript and access to this website's REST API. If verification fails, the visitor sees an error and can retry.

= How long are rejected messages retained? =

Optional quarantine keeps selected fields for seven days by default, configurable from one to 30 days, up to 500 records. WordPress and form-plugin spam queues have their own retention settings. Scheduled cleanup can run late if WordPress cron is delayed.

= What does privacy-conscious mode change? =

It turns off new quarantine storage, country filters, summary emails and pingbacks/trackbacks. Existing quarantine records keep their expiry, and form-plugin storage settings remain separate. Review data is kept for up to 90 days, also in this mode. The mode cannot certify a website's legal compliance.

= What do the statistics measure? =

Activity counts show which submissions passed or were stopped, including verification failures. Precision, recall and accuracy use cases that have received a later review within the 90-day window. Unreviewed cases do not count as confirmed correct decisions.

= Does the plugin support Multisite? =

Yes. Activate it individually or network-wide; each site keeps separate settings and data. Network maintenance runs in batches. Follow the name-transition instructions when replacing a previous installation.

= Does deleting statistics reset replay protection? =

No. Used tokens remain protected until expiry. The separate 90-day review data is also kept.

= What happens on uninstall? =

The plugin's own tables and options are deleted by default; you can choose to keep them in Settings. WordPress comments and form entries are kept. For a complete uninstall, first deactivate every copy on all sites and networks. Follow the separate instructions above when replacing the previous plugin name.

= How can I support development? =

You can help fund development and maintenance with a voluntary donation at https://ko-fi.com/rocketsky. All plugin features remain free.

== Screenshots ==

1. Overview with protection status, activity and reviewed results.
2. Integration controls showing availability and where rejected messages go.
3. Content filters, comment languages and country rules.
4. Spam folders, encrypted quarantine and assessments of delivered submissions.
5. Privacy, retention, email summaries and uninstall settings.
6. WordPress dashboard widget with a seven-day activity summary, the comment rejection total and links to review spam.

== Changelog ==

= 0.5.4 =
* Format daily email summaries with a readable HTML layout, website identity and direct activity and notification links.
* Include a plain text alternative without changing other WordPress emails or the existing daily sending limit.
* Translate email text in every bundled language and retain the website's date format and time zone.

= 0.5.3 =
* Use the website time zone for daily activity, daily email summaries and displayed record dates.
* Preserve earlier daily counts in their original time zones and verify the additive storage upgrade.
* Refresh versioned assets on update so the support area loads its matching design.

= 0.5.2 =
* Prepare an installable WordPress.org submission package with the native update channel.
* Load bundled translations on demand while preserving WordPress language-pack priority.
* Document reviewed-decision metadata and its retention in the privacy information.
* Include directory FAQs and screenshot descriptions for the main administration tasks.
* Simplify the dashboard widget and revise administration text in all bundled languages.
* Add voluntary review and Ko-fi links alongside the project sponsors.

= 0.5.1 =
* Rename the plugin, installation slug, text domain and bundled translations to Rocketsky Antispam.
* Preserve settings, retained entries, statistics, encryption and feedback references.
* Prevent conflicting activation and destructive removal of a previous installation sharing the same data.

= 0.5.0 =
* Use native spam queues for verified content rejections where supported, with direct review links and contextual protection notices.
* Keep invalid technical requests before native persistence and respect local quarantine and provider storage settings.

= 0.4.0 =
* Separates the administration into Overview, Integrations, Filter rules, Review spam and Settings.
* Centralizes privacy and retention settings and brings review queues directly into view.
* Keeps unrelated settings when saving a section and warns before leaving unsaved configuration.
* Simplifies the administration layout and sponsor links.


= 0.3.1 =
* Verifies Divi 4, standalone Divi Builder and Divi 5 against unchanged original packages.
* Checks native Divi requests before frontend, REST and AJAX rendering.
* Preserves visible rejection messages and reload links for custom form container IDs.
* Rejects unsupported native envelope identifiers instead of skipping protection.
* Requires opt-in for new form integrations and disables unavailable provider controls while preserving saved choices.
* Aligns administration controls, enlarges supporting text and improves translated narrow layouts.

= 0.3.0 =
* Separates provider modules and adds native registration, Formidable, Fluent Forms, WS Form, Elementor Forms, Divi contact forms and WooCommerce integration.
* Adds retry-safe native checkout transports with technical checks only; account and payment fields are excluded from content retention.
* Groups integration settings and extends translated labels and provider statistics.

= 0.2.0 =
* Adds local content rules, privacy-conscious mode, encrypted opt-in quarantine, country/language policy, daily dashboard counts and optional maintenance/email digests.
* Adds German, French, Italian and Spanish translations alongside English source strings.

= 0.1.0 =
* Initial development candidate with local token protection, native integrations, settings and aggregate statistics.

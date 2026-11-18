=== Rocketsky Antispam ===
Contributors: rocketsky, theacki, chrigu99
Donate link: https://ko-fi.com/rocketsky
Tags: antispam, spam, contact form 7, gravity forms, wpforms
Requires at least: 6.6
Requires PHP: 8.2
Tested up to: 7.1
Stable tag: 0.5.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free local spam protection for comments and forms. No added CAPTCHA or external spam service.

== Description ==

Free spam checks for comments and forms, run on your website. Rocketsky Antispam adds no CAPTCHA, sends no submissions to an external spam service and needs no account or API key.

Enable integrations in Settings → Rocketsky Antispam. Comments start enabled; others start off.

= Supported integrations =

WordPress comments and registration; Contact Form 7, Gravity Forms, WPForms, Formidable Forms, Fluent Forms, WS Form, Elementor Pro Forms, Divi 4 and 5 contact forms; WooCommerce checkout and My Account registration.

= Checks and review =

Signed tokens, a hidden field and one-use checks verify submissions. Optional keyword, BBCode-link and regular-expression rules inspect selected text; comments also support language/country rules. Divi, registration and checkout use technical checks only.

Counts include verification failures; accuracy uses reviewed cases only. Bots can obtain valid tokens. A pass does not prove a sender is human. Providers keep their own checks.

== Installation ==

1. Install and activate Rocketsky Antispam under Plugins → Add New, or upload its ZIP.
2. Enable providers under Settings → Rocketsky Antispam → Integrations.
3. Test a normal submission, rejection and retry.

Replacing WP Antispam? Deactivate it without uninstalling. Activate the new copy in the same scope, clear caches and check retained data. Remove the inactive old directory through your hosting file manager; WordPress Delete can erase shared data. Guide: https://github.com/rktsky/rocketsky-antispam/blob/main/docs/renaming.md

== Frequently Asked Questions ==

= Do I need snippets or form edits? =

No. Enable your integration.

= Is everything free? =

Yes. Providers may require their own paid licences. Donations are voluntary.

= Where can I review or recover rejected messages? =

Open Review spam. Supported native queues, including Flamingo for Contact Form 7, can store verified content-rule rejections when configured. Failed verification stops before native storage. Enable quarantine beforehand to retain selected contact fields where forms allow it. WPForms Lite has no native entry queue.

Not spam in the original queue may trigger provider notifications. Quarantine allows reading, assessment and deletion without resending. Discarded messages cannot be recovered. Providers manage their own retention.

= What does privacy-conscious mode change? =

On by default, it prevents new quarantine storage, country filters, summary emails and pingbacks/trackbacks. Content rules and statistics remain available. Existing records keep their expiry. Other plugins, hosting, mail and backups handle data separately. Adapt WordPress's suggested privacy text to your setup.

Optional emails contain counts only. Enabled pingbacks can disclose sender IPs to source websites.

= What data stays on my website, and for how long? =

The plugin uses no cookies, browser storage, fingerprints, telemetry or external spam/IP lookups. Its tables store no IP addresses or user agents.

Used token digests expire within two hours. Daily counters: 30 days by default (1–90), in the site time zone. The rejected-comment total stays until reset. Review records use locally linkable references without message copies for up to 90 days, also in privacy-conscious mode. Supported deletion/privacy workflows detach or remove links; reviewed totals can remain until expiry.

Opt-in quarantine encrypts selected names, emails and messages: seven days by default (1–30), up to 500 records. Password, hidden, payment and file fields are excluded. WordPress privacy export/erasure matches contact emails. Cron cleanup can run late. Optional comment cleanup permanently deletes old spam.

= What about performance, caching, JavaScript and REST? =

Checks run locally. Protected forms need JavaScript and the site's REST API. Keep verification requests out of caches; test cache/optimisation changes. REST comment creation needs protection fields too.

= Does it support multilingual sites and Multisite? =

The interface includes English, German, French, Italian and Spanish. Multisite supports individual/network activation with separate settings and data per site.

= Is every add-on or payment gateway supported? =

Custom transports, add-ons and express payments need checks. Contracts and coverage: https://github.com/rktsky/rocketsky-antispam

= What happens on uninstall? =

Uninstall deletes its tables/options by default; Settings can retain them. Comments and provider entries stay. First deactivate every copy on all sites/networks. See above for the previous plugin name.

= Where can I get help? =

Ask in this plugin's WordPress.org support forum with versions and reproduction steps. Omit private messages.

== Screenshots ==

1. Overview with protection status and recent activity.
2. Integration controls showing availability and where rejected messages go.
3. Content filters, comment languages and country rules.
4. Spam folders, encrypted quarantine and assessments of delivered submissions.
5. Privacy, retention, email summaries and uninstall settings.
6. WordPress dashboard widget with a seven-day activity summary, the comment rejection total and links to review spam.

== Changelog ==

= 0.5.7 =
* Fix an Elementor error when rejecting a submission.
* Request only the native hook arguments used by the affected callbacks and validate proxy addresses before decoding them.
* Use WordPress class filenames without filename-rule exclusions.

= 0.5.6 =
* Check personal notice preferences with a fixed native account permission instead of a submitted capability.
* Filter owned form and error HTML at its final output boundary while preserving technical controls and provider markup.

= 0.5.5 =
* Let each user hide integration notices on this website and restore them from Integrations or their own profile.
* Explain how to retry or diagnose an incomplete statistics storage upgrade.
* Remove site-specific notice preferences through native WordPress APIs when uninstall is configured to delete plugin data, and on site deletion.

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

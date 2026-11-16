# Coverage and checker exceptions

Assessment: 16.11.2026. Submitted version: **0.5.6**. Checked development
candidate: **0.5.7**, source `9c998776a28f88862e418a15d7b9a9dd211f97ff`.
Version 0.5.7 has not been uploaded to WordPress.org.

The original-provider checks already recorded for earlier versions remain valid
historical evidence. They are distinct from a fresh run on the current candidate,
public CI, synthetic adapter contracts and physical-device testing.

## Closed in this assessment

The fresh Elementor Free 4.3.2 / Pro 4.3.3 run reproduced a real 0.5.6 defect:
rejected submissions referenced `Guard` without importing its namespace. Normal
submissions worked; honeypot, replay and challenge-failure paths could return a
PHP fatal/HTTP 500 instead of a visible rejection. Version 0.5.7 imports the
actual service. A licence-free adapter regression now exercises both validation
and before-actions rejection. Removing that import from a disposable copy makes
the new test fail with the same fatal.

The corrected candidate passed all 23 original Elementor record assertions and
seven browser scenarios with 13 real AJAX submissions. Honeypot, challenge 503
and network failure now produce a visible error, retain native form values and
allow a fresh retry. Required-field recovery, repeated success, recreated proof
controls and the optional minimum time also passed. These are classic Form
widget tests in Firefox; they do not establish arbitrary add-on behavior.

Five additional real AJAX submissions on the exact installed 0.5.7 ZIP used a
configured native local no-op submit action. Valid submission, invalid proof,
corrected retry, content-rule rejection and corrected retry dispatched that
action respectively once, zero, once, zero and once. Rejections retained the
inputs; no real mail, webhook or payment action was configured or executed.

The Multisite translation-conflict test previously manufactured a single-site
capability instead of using Core's network permission mapping. It now uses real
fixture administrators, checks anonymous denial and registered notice hooks,
and restores the current user. The exact submitted 0.5.6 ZIP passed WordPress
6.6 and 7.1.4: 203 single-site and 204 Multisite assertions per version. The
same runner is now registered in both Multisite CI jobs, and removing that CI
registration invalidates its reviewed workflow binding.

Avoidable checker annotations were also removed:

- Three callbacks now request only the leading hook arguments they use. Native
  WordPress supports that contract through `accepted_args`.
- CIDR validation checks the address before calling `inet_pton`; 27 valid and
  malformed IPv4/IPv6 cases produce no PHP diagnostics.
- Four other callback annotations were redundant under the actual WordPress
  ruleset. Their necessary positional signatures remain intact; the annotations
  were removed and the configured check still passes.
- All 37 owned class files use WordPress filenames. Both project-wide filename
  exclusions were removed. Class names, provider IDs, storage names, signed
  protocol and schema remain unchanged.

Sources: [native hook argument selection](https://developer.wordpress.org/reference/functions/add_filter/),
[WordPress PHP naming conventions](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/#naming-conventions),
[native address conversion](https://www.php.net/manual/en/function.inet-pton.php).

## Remaining annotations

There are **90 production annotation sites in 18 groups**, down from 98 sites
and two project ruleset exclusions. There are now **no project ruleset
exclusions** and **no output-escaping suppressions**. An annotation site is not
the same as one checker finding or one security defect.

The exact scopes, official contracts and test references are in the
[reviewed inventory](../tools/quality-exceptions.json). No exception in this
assessment adds permission to access Core or provider tables directly.

| Group | Sites | Reason or remaining limitation |
| --- | ---: | --- |
| Owned storage | 26 | Previously approved prepared operations on the six owned tables, including atomic token claims and aggregates. |
| Review ledger | 10 | Prepared operations on the approved feedback table; concurrent classification, expiry and privacy detachment require immediate state. |
| Quarantine storage | 11 | Approved encrypted quarantine storage, bounded slots, expiry and deletion. |
| Native metadata queries | 3 | Two documented native queries: sender privacy lookup and network maintenance. Returned batches are bounded; large-dataset scanning cost remains unmeasured. |
| Read-only navigation | 10 | Allowlisted view selection and fixed result notices do not mutate data. Mutation handlers check permission and nonce separately. |
| Notice input boundaries | 4 | Scalar shape and byte bounds must precede sanitization and native nonce checks. Preferences affect the current account/site only. |
| Public form proof | 4 | Anonymous controls must be read before their signed proof can be verified; native action nonces remain provider-owned. |
| Native comment reply | 1 | Core reply intent is verified together with the actor and target-post permission. Public frontend comments retain protection. |
| Gravity Forms native intent | 3 | Canonical Save and Continue/resume bytes are bounded and verified without transforming the signed identity. |
| WPForms selected raw fields | 1 | An established native rejection selects only declared, bounded, sanitized safe fields; sensitive or unknown fields are excluded. |
| Foreign hook names | 3 | Existing documented WordPress/provider hook names cannot be renamed with this plugin's prefix. |
| Native moderation capabilities | 2 | Gravity Forms and Flamingo register these actual capabilities; static WPCS does not know their names. |
| Native mailer properties | 2 | PHPMailer requires its own `Body`, `ContentType` and `AltBody` casing. Only the narrow setup/restoration regions are exempted. |
| Signed token framing | 3 | Canonical base64 framing carries authenticated protocol bytes, with expiry and one-use claims. |
| Encrypted payload framing | 3 | Canonical base64 framing carries authenticated ciphertext, not executable source. |
| Expected regex failures | 2 | Invalid or resource-limited administrator patterns are handled through explicit failure states rather than public PHP warnings. |
| Optional local GeoIP lookup | 1 | Unknown results map to no country. The actual extension's missing-record path has not been executed in these fixtures. |
| Operational failure logging | 1 | Fixed failure text and native site/network IDs make missed maintenance observable; no visitor content is logged. |

The separate, previously approved Fluent Forms model insert is an architectural
exception, not a suppressed checker finding. After authentic one-use proof it
can create one native spam entry with bounded declared fields. It does not
modify the provider schema or run payment/notification actions.

The configured WPCS run passes with zero errors/warnings. A separate run ignoring
annotations exposes 45 errors and 144 warnings. These results are reported
separately; the configured pass is not an exception-free security certificate.

## Integration coverage

All listed integrations have existing tests. The incomplete areas concern
particular versions, original engines, browser workflows or extra modules.
The 130 output assertions and thirteen-variant browser probe from 0.5.6 are
synthetic provider contracts; they do not replace original-provider submissions.

| Integration | Current and historical evidence | Remaining named gap |
| --- | --- | --- |
| WordPress comments | Current native classic/REST CI and real Core browser submission; native moderation and reply evidence. | Broader browser/device and assistive-technology checks. |
| WordPress registration | Native CI and real single-site browser error/corrected retry; Multisite control rendering. | Complete public Multisite signup/activation browser workflow. Core inputs are available for that separate test. |
| Pingbacks/trackbacks | Original Core protocol tests still run in CI. | Provider-independent device tests are not relevant; external delivery is not asserted. |
| Contact Form 7 / Flamingo | Fresh CI uses CF7 6.2.5 / Flamingo 2.6.4 originals; native spam, moderation, storage opt-out and privacy tests. Earlier actual AJAX/retry tests exist. | Fresh browser run on current CF7; separate upload and third-party multistep/conditional transports. |
| Gravity Forms | Original 3.2.2 native submission, multipage, drafts/resume, moderation and privacy evidence; earlier iframe/modern AJAX browser tests. Public CI explicitly skips the licensed engine. | Fresh current-candidate original/browser run; conditional branches, Partial Entries and separate upload/payment add-ons. Provided older packages are available; the recorded 3.2.2 package has not been newly established here. |
| WPForms Lite | Fresh 2.0.4 original-engine CI; historical real AJAX rejection/retry matrix. | Fresh browser matrix on the current version and separate upload routes. |
| WPForms Pro | Historical 1.9.7.3 native spam, moderation, privacy and selected-field tests. | Fresh trusted-original acceptance; browser AJAX, multipage, conditional/conversational/lead forms, Save and Resume and payment modules. |
| Formidable Forms | Fresh 7.0 free-original engine, spam, feedback and privacy CI. | Fresh browser retry; Pro multipage/drafts/conversational and additional transports. No verified Pro package is available in this assessment. |
| Fluent Forms | Fresh 6.2.17 free-original engine, spam, feedback and privacy CI; earlier browser rejection/retry. | Fresh browser matrix, conditional/step forms and Pro edit/payment transports. No Pro package was established. |
| Elementor Pro | Fresh Free 4.3.2 / Pro 4.3.3 native and actual AJAX rejection/retry checks; a configured local no-op action also passes on the exact ZIP. | Atomic Forms and third-party widgets are outside implemented scope. Native Collect Submissions, step/conditional forms, real uploads, external CAPTCHA and third-party actions need separate acceptance. |
| Divi 4 theme / standalone builder | Original 4.27.9, separately: 28 native assertions and 18 actual browser scenarios each. | Fresh current-candidate repetition. Both original packages are available. Custom/third-party transports remain outside those scenarios. |
| Divi 5 | Original 5.19.0: 41 HTTP cases and 32 browser scenarios covering native, legacy and originally migrated forms. | Fresh current-candidate repetition; original ZIP is available. Add-on/custom actions and external CAPTCHA services need their own inputs. |
| WooCommerce classic/block checkout | Fresh 11.3.0 original-engine CI; historical actual classic/block retry matrix on 11.2.2. | Fresh browser checks on 11.3.0; real express wallets and gateway-specific transports require their own setup. |
| WooCommerce order-pay / registration | Native order-pay and account-registration CI cases. | Actual browser order-pay and Woo account-registration end-to-end checks. A local test gateway and Core fixtures can cover the ordinary routes. |
| WS Form Lite | Fresh 1.12.19 original engine, spam, moderation and privacy CI; historical actual `form_reload()` and retry checks. | Fresh browser matrix, repeaters/update, Save and Resume and multistep/conditional workflows. Current verified Pro originals are not established. |

Fresh repetitions of the ordinary free-provider browser flows, available Divi
originals and Core/Woo workflows are feasible with existing inputs. They have
not been silently marked complete by this assessment. Extra commercial modules
need their actual packages; live delivery/payment services need separate test
configuration and authorization.

Physical Safari/iOS, Android/Chrome, Windows/Edge, real touch devices and screen
readers remain unverified. Desktop Firefox emulation does not close those gaps.
An independent penetration test and a representative real-world spam corpus
assessment have not been performed.

## Candidate evidence

[CI run 43939076488](https://github.com/rktsky/rocketsky-antispam/actions/runs/43939076488)
passes all 18 jobs, including both Multisite translation runs and all 26 guard
mutations. The reviewed guard binds 26 test/workflow files. The candidate also
passed native policy, output, notice and translation checks locally.

The native CI matrix uses WordPress 7.1.4 with PHP 8.2.35, 8.3.36, 8.4.27 and
8.5.12, plus WordPress 6.6 with PHP 8.2.35. Its 15 native database-backed jobs
record MySQL 8.0.46. Commercial Gravity tests are explicitly skipped without
the original package; Divi's CI adapter tests do not load its engine. CF7 and
Flamingo are absent on the PHP 8.2 job because of their provider requirements;
the minimum WordPress job excludes current original-provider suites. Dedicated
email checks run on PHP 8.5, and the public browser bridge is a synthetic
Firefox contract. Passing 18 jobs does not extend those actual scopes.

The installable candidate is `rocketsky-antispam-0.5.7-wordpress-org.zip`,
77 files, 375,709 bytes; SHA-256:

```text
55ac6038db9e512aa0e07272340ef0dffdad4052a5b548e95e295882f6bd05be
```

The real same-slug Core updater replaced 0.5.6 with that exact archive while
retaining activation, individual settings, all six owned-table snapshots,
decryptable quarantine and old token behavior. All 37 new class paths resolve;
the 37 old filenames are absent. Ordinary activation checks and a slug-transition
test alone would not establish this same-slug update path.

Original Plugin Check 2.2.0 checked the exact installed archive with its early
runtime and original drop-in: zero errors/warnings, without checker overrides
or excluded checks/files/directories. Separate frontend and authenticated Core
Comments-admin requests returned HTTP 200 with debug display enabled and no new
debug bytes. All 77 product files, 3,339 original Core files and 3,039 original
checker files remained unchanged. The Core-only local proof uses WordPress
7.1.4, PHP 8.3.36 and MariaDB 10.11.19.

The upgrade itself emitted three offline Core update-check warnings. Their
messages match a run without loaded product code after removing timestamps;
the original logs are preserved. That is separate from the zero-debug Plugin
Check and subsequent HTTP results. This exact same-slug package update was
checked on single-site, not on a second Multisite package-upgrade fixture.

Private evidence is retained under `outputs/rocketsky-antispam/quality-gap-closure-2026-11-16/`,
the separately frozen Elementor provider records and the external release folder.
Earlier submitted archives and their historical reports are unchanged.

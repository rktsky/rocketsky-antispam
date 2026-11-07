# Native integrations

The integration adapters use public WordPress and provider hooks. They do not modify provider files or inject generated source patches. Version 0.2.0 selects bounded native text fields for configured local content rules and optional encrypted quarantine; technical and sensitive field types remain excluded. Enable or disable each detected provider in WP Antispam. Existing provider security controls remain enabled.

## WordPress comments

`comment_form` supplies the actual post ID and runs inside the form for both visitors and logged-in users. `preprocess_comment` checks native submissions before insertion. Pingbacks and trackbacks are explicitly rejected while comment protection is enabled. All other types, including product reviews, require valid protection fields; compatibility depends on their form actually using the native `comment_form` rendering and processing path. Logged-in users receive the same protection checks.

Core's authenticated backend reply action is preserved only when its exact AJAX action is currently running, its native reply nonce is valid, the supplied user is the current logged-in actor and that actor can edit the target post. This authorized editor operation is outside incoming-public-submission statistics and feedback. Frontend submissions by the same user still require protection.

The REST comments controller inserts directly through `wp_insert_comment`, so `rest_pre_insert_comment` independently protects creation. REST requests must provide valid protection fields for the target post; external applications and editor-based clients, including internal note creation, cannot create comment objects without these fields while this integration is enabled. Existing-comment REST edits keep the native authorization path and earlier `WP_Error` values are preserved. Third-party integrations that directly call `wp_insert_comment` are not covered by `preprocess_comment`; they must use a separately reviewed guard integration.

Sources: [comment_form](https://developer.wordpress.org/reference/hooks/comment_form/), [preprocess_comment](https://developer.wordpress.org/reference/hooks/preprocess_comment/), [REST creation hook](https://developer.wordpress.org/reference/hooks/rest_pre_insert_comment/), [Core REST insertion](https://developer.wordpress.org/reference/classes/wp_rest_comments_controller/create_item/).

## Contact Form 7

`wpcf7_form_elements` appends protection inside the native form. `wpcf7_spam` preserves every earlier positive spam decision and checks raw POST only when no earlier filter has rejected the submission. For local rejections it adds a fixed diagnostic reason through `WPCF7_Submission::add_spam_log()` and uses `wpcf7_display_message` to show a neutral retry instruction. CF7 retains its native spam response status and DOM event; mail is not sent.

Technical controls must be read from raw POST: CF7 removes some metadata from `get_posted_data()`, and its documentation expressly excludes that processed collection from validation and spam filtering. CF7 also copies unknown non-underscore POST keys into that collection. The adapter therefore removes only its exact `rktsky_antispam_token` key and reserved `rktsky_as_` plus twenty-hex-character trap names through `wpcf7_posted_data`; raw POST remains available for verification. This keeps technical proof out of CF7 mail/storage consumers. CF7 extensions such as Flamingo may separately store the actual messages and fixed spam logs; their storage policies are outside this adapter.

Sources: [custom spam filtering](https://contactform7.com/2020/07/18/custom-spam-filtering/), [raw input requirements](https://contactform7.com/2020/07/28/accessing-user-input-data/), [original rendering and message hooks](https://github.com/rocklobster-in/contact-form-7/blob/master/includes/contact-form.php), [original submission hooks](https://github.com/rocklobster-in/contact-form-7/blob/master/includes/submission.php), [DOM events](https://contactform7.com/dom-events/).

## Gravity Forms

Gravity Forms 2.7 or newer is required. `gform_form_tag` adds protection immediately after the opening form tag, preserving empty tags produced by other integrations. The generic `gform_validation` hook rejects failed protection with a visible retry message through `gform_validation_message`; a client-supplied target page cannot bypass checking. Existing failed validation remains failed.

Save and Continue can skip ordinary validation. The independent `gform_abort_submission_with_confirmation` hook therefore checks proof before draft or final persistence. A successful `form-submit` or `api-submit` validation grants exactly one request-local permit for the next abort hook, bound to that native form and exact token. `api-validate` grants none. Every validation invocation resets the form's previous permit and messages; every abort invocation consumes the permit even when another integration already requested an abort. Independent submissions still consume a new token. Existing abort decisions remain true.

For local aborts, `gform_confirmation` replaces the native successful-looking confirmation with an explicit rejection instruction. The adapter observes an already-positive `gform_entry_is_spam` result for statistics without changing that verdict. Persisted native GF spam remains in GF's native entry storage; it is not copied into a second quarantine object. An earlier native abort before entry persistence may retain selected fields in the optional quarantine. Our own protection uses validation/abort hooks before entry creation. Native GF spam checks continue to run normally. Partial Entries and other independent add-on persistence paths have not been verified and are outside this release's coverage.

Proof is required for final GFAPI submissions as well as browser submissions. An application submitting through GFAPI must carry reviewed protection fields; no API, administrator or logged-in bypass is added. Tests must cover the actual browser path. Where GFAPI is used as a test helper, set its sixth `$initiated_by` argument explicitly to `GFFormDisplay::SUBMISSION_INITIATED_BY_WEBFORM` and reproduce the real controls; its default is the API context. Browser tests still remain necessary for AJAX, multipage navigation, Save and Continue, validation retries and payment add-ons.

Sources: [opening-tag hook](https://docs.gravityforms.com/gform_form_tag/), [generic validation and contexts](https://docs.gravityforms.com/gform_validation/), [validation message](https://docs.gravityforms.com/gform_validation_message/), [abort hook and Save and Continue](https://docs.gravityforms.com/gform_abort_submission_with_confirmation/), [native spam behavior](https://docs.gravityforms.com/gform_entry_is_spam/), [GFAPI submission and context](https://docs.gravityforms.com/submitting-forms-with-the-gfapi/).

## WPForms

The shared Lite/Pro hooks `wpforms_frontend_output` and `wpforms_process` insert protection inside the native form and reject a failed check through the provider's documented process errors. The adapter preserves an earlier header error and runs before ordinary entry persistence and notifications. Submitted protection controls stay at form level and are read from unslashed raw POST; the expected form ID comes from the native form definition. WPForms' other validation, CAPTCHA and anti-spam controls retain their native behavior.

The shared hooks are present in the original public Lite source. This does not establish tested compatibility with every Pro add-on, payment method, conversational mode or future provider version. These flows require native end-to-end checks before claiming support.

Sources: [process hook and documented errors](https://wpforms.com/developers/wpforms_process/), [original Lite process implementation](https://plugins.svn.wordpress.org/wpforms-lite/trunk/includes/class-process.php), [original Lite form renderer](https://plugins.svn.wordpress.org/wpforms-lite/trunk/src/Frontend/Frontend.php).

## Verification scope

`tests/integrations.php` uses explicit provider and WordPress stubs to verify hook selection, form binding, prior rejection preservation, REST behavior and adapter control flow. Stubs are contract checks, not evidence of execution in installed provider versions. Native integration and browser checks must separately record WordPress, PHP and provider versions, complete form context and valid/invalid outcomes. No test may disable provider security merely to obtain a passing submission.


## Content rules and optional quarantine since 0.2.0

Selected fields are supplied separately to Guard; cryptographic proof, honeypot shape and configured timing must pass before local content rules run. Rules do not authorize the native provider. Their subject is bounded to thirty selected strings and eight KiB; a non-match is not a complete-message or human-authorship guarantee. A regex execution failure returns the technical rejection `pattern_limit` instead of silently accepting an unexamined subject. For comments, language detection receives only the content field. Country processing is confined to comments and the configured local/trusted source.

The same explicit field selection supplies optional encrypted quarantine. Capture must be enabled independently, and the datensparsam/privacy mode overrides it. Selected values are limited to thirty fields and four KiB per field; the quarantine service separately enforces its total plaintext budget, expiry and record limit. Empty, omitted, normalized and truncated values are not a faithful full submission archive.

- Comments: only content, author name, declared email and author URL. IP address, user agent, metadata and ping/trackback content are excluded. A new `wp_insert_comment` with native spam/trash status is observed only when the adapter previously saw that native/REST incoming path; arbitrary server imports and later manual deletion are excluded.
- CF7: declared `text`, `email`, `url`, `tel`, `textarea`, `select`, `checkbox`, `radio`, `number` and `date` tags, matched to processed submission values. Unknown keys, file tags, hidden tags and reserved protection fields are excluded. Earlier native spam verdicts are preserved and can be captured.
- GF: declared `text`, `textarea`, `email`, `website`, `phone`, `number`, `date`, `name`, `select`, `multiselect`, `checkbox` and `radio` field types and their actual scalar/compound input IDs. Administrative-only, hidden, password, payment and upload types are excluded. Native spam and abort verdicts are observed once per lifecycle. They never grant a security permit.
- WPForms: stored native field types must agree with sanitized field metadata; only the `value` property is selected. Types are `text`, `textarea`, `email`, `url`, `phone`, `number`, `name`, `select`, `checkbox`, `radio` and `date-time`. Hidden, password, payment, file and unknown types remain excluded.

WPForms' modern V3 spam check can return before formatting any fields. For that specific early-rejection observation, the adapter reads only IDs declared in the stored form definition, applies the same type allowlist, unslashes selected scalar values and sanitizes them as text, textarea or email. Flat compound parts are limited to native name parts (`first`, `middle`, `last`), date/time parts, and numeric choice indexes; nested arrays, objects and arbitrary extra parts are dropped. The native rejection is never disabled or bypassed to obtain formatted data. This bounded fallback makes standard early-rejected text fields reviewable without retaining a raw request dump.

WPForms' documented/original `wpforms_process_honeypot`, `wpforms_process_antispam` and `wpforms_process_after` interfaces supply native observations. The lifecycle is reset at `wpforms_process_before_form_data`, which runs before the modern early check. These observations retain native booleans/errors and must not be interpreted as a verified false-positive-free spam classifier.

Native spam observations use a separate statistical receipt: a later provider spam decision replaces an earlier counted local pass for that same form/token, and a locally rejected attempt is not counted again. Statistical receipts cannot authorize or skip Guard verification. Quarantine uses lifecycle markers to avoid duplicate records from overlapping native hooks.

## Reviewed quality measurements since 0.2.0

Incoming decisions and human reviews are separate. Only a comment or GF entry observed through this adapter's protected incoming lifecycle receives a native prediction. Newly persisted native spam and normal entries start unreviewed. Arbitrary imports and unknown historical objects do not become measured false negatives. Core already stores its native spam comments, so this integration does not duplicate their contents in quarantine.

The feedback ledger stores a site- and source-separated HMAC of the local object ID, the immutable prediction, an optional human label and bounded timestamps. It stores no message, email, IP or user agent. These references are local pseudonyms while linked; they are not claimed to be anonymous. Quality counts and precision/recall/accuracy use reviewed observations retained for at most ninety days. Unreviewed observations are pending and excluded from every quality denominator. These selected reviewed cases do not establish an unbiased site-wide detection rate.

Native authorized comment spam/approval changes and GF spam-to-active or active-to-spam changes supply labels only for already tracked objects. GF prediction tracking runs at `gform_entry_created`, after its native initial spam decision. Status updates use `gform_update_status`; full API and entry-detail edits use `gform_post_update_entry` and `gform_after_update_entry`. A changed numeric GF field removes the link to the original prediction, including field anonymization via `gform_save_field_value`. Deletion removes pending references and detaches reviewed outcomes. GF's deletion hook runs before its deletion attempt, so a later failed deletion can conservatively discard the reference. Provider rows are never rewritten by feedback.

WordPress's confirmed email export/erase workflow finds comment references across approved, pending, spam and trash statuses. Before native erasers anonymize their source identifiers, our callback removes or detaches the feedback links. GF matching follows privacy-enabled forms' configured identifying email field or registered-user mapping. It requests paginated IDs through GFAPI, at most fifty per page; it does not fetch all matching entry contents. Forms without that native privacy mapping cannot be located by email here. A known native ID can still be detached through the authorized reference action.

Optional quarantine exposes explicit Spam/Not spam review. CF7 and WPForms Lite have no generally available persisted normal-entry lifecycle through these interfaces: inbox reclassifications are not silently observed. Administrators may instead classify a delivered case with its stable local case reference. Only its HMAC is retained, repeated references are idempotent during the window, labels can be corrected and the reference can be forgotten. A known tracked native comment/GF ID reuses the existing observation. These manual references cannot be discovered from an email address that the ledger never stored.

When a source is removed or erased, reviewed references are replaced with random identifiers and event times reduced to UTC-day buckets without extending expiry. Outcomes remain in the bounded metrics until expiry. This is removal of the direct lookup, not a claim that linkage from other locally retained information is mathematically impossible. No transaction is started or committed in the host/provider database.

Sources: [GF entry creation](https://docs.gravityforms.com/gform_entry_created/), [native status changes](https://docs.gravityforms.com/gform_update_status/), [field-save contract](https://docs.gravityforms.com/gform_save_field_value/), [full entry update](https://docs.gravityforms.com/gform_post_update_entry/), [detail update](https://docs.gravityforms.com/gform_after_update_entry/), [pre-deletion event](https://docs.gravityforms.com/gform_delete_entry/), [paginated GFAPI IDs](https://docs.gravityforms.com/searching-and-getting-entries-with-the-gfapi/). Contracts were cross-checked against the unchanged original GF 3.2.2 fixture.


## Modular providers since 0.3.0

`Providers` is the shared allowlist for settings, signed scopes, counters, labels and quality feedback. IDs remain within the existing database widths. `Integrations` loads an owned provider file only when its native contract is available and its setting is enabled. Existing public callbacks forward through an explicit compatibility map; arbitrary helper dispatch and request-derived paths are rejected. Existing four provider settings retain their defaults. Newly added providers default to disabled, including on upgrades.

### WordPress registration

`register_form` supplies inside-form proof. `login_form_register` identifies the actual Core wp-login lifecycle. Its `registration_errors` gate runs before account insertion and activation/notification work. Original field validation errors remain intact. Other providers may call generic `register_new_user` or `wp_insert_user` APIs; those flows retain their own adapter and account-authorization contract, avoiding incompatible double checks. No broad administrator or logged-in bypass is added to public registration.

Anonymous native Multisite signup is identified by `before_signup_header`. `signup_hidden_fields` adds fresh proof to both account and account-with-site stages, and `wpmu_validate_user_signup` rejects before signup rows or activation mail. Generic use of that validator outside the actual `wp-signup.php` lifecycle is preserved; logged-in site creation is not anonymous account registration. Individual and network activation retain isolated per-site storage and settings. The network lifecycle is covered by the dedicated multisite fixture; full network signup browser flows remain a separate acceptance check.

Registration uses technical proof only. No account values enter content rules or quarantine. Core's `login_footer` prints the enqueued protection script. Sources: [register_form](https://developer.wordpress.org/reference/hooks/register_form/), [registration_errors](https://developer.wordpress.org/reference/hooks/registration_errors/), [original wp-signup.php](https://github.com/WordPress/WordPress/blob/master/wp-signup.php), [Core registration implementation](https://developer.wordpress.org/reference/functions/register_new_user/).

### Formidable Forms

The reviewed public contracts exist in unchanged originals 6.2 and 7.0. `frm_entry_form` renders proof inside the form. `frm_entries_before_create` applies the final guard after native validation and before native entry creation/actions. Preliminary AJAX and multipage validation do not consume proof. Native errors stay intact. Selected declared text types, flat name parts and choice values feed local content rules and optional quarantine; hidden, password, payment, file, embedded and administrative fields are excluded. Direct authorized `FrmEntry::create` calls are a separate server API. Commercial draft, conversational and Pro multipage paths need their own native acceptance checks.

Sources: [inside-form action](https://formidableforms.com/knowledgebase/frm_entry_form/), [pre-creation filter](https://formidableforms.com/knowledgebase/frm_entries_before_create/), [original public source](https://plugins.svn.wordpress.org/formidable/trunk/).

### Fluent Forms

The reviewed modern slash hooks exist in unchanged originals 6.0.0 and 6.2.17; older legacy-only hooks are not silently assumed compatible. `fluentform/form_element_start` renders proof, and `fluentform/validation_errors` rejects before insertion and submission actions. The native browser serializes controls into `POST[data]`, then its whitelist removes unknown technical keys before validation hooks. The adapter therefore selects only bounded own proof keys from the original serialized request. Native sanitized field data and stored definitions determine content selection. Passwords, payment inputs, hidden values, files and arbitrary nested values do not enter quarantine.

Sources: [developer hooks](https://developers.fluentforms.com/hooks/), [original public source](https://plugins.svn.wordpress.org/fluentform/trunk/).

### WS Form

The current reviewed original is Lite 1.12.19. `wsf_shortcode` adds a signed descriptor to the actual native form element. The shared browser bridge restores its own hidden controls after native child replacement; it changes no native fields. `wsf_submit_validate` appends a native visible error before persistence and configured actions. Native FormData carries proof as technical top-level controls, separate from native entry metadata. Stored field definitions govern bounded text selection and optional encrypted capture; excluded sensitive types remain excluded.

Sources: [shortcode filter](https://wsform.com/knowledgebase/wsf_shortcode/), [submission validation](https://wsform.com/knowledgebase/wsf_submit_validate/), [original public source](https://plugins.svn.wordpress.org/ws-form/trunk/).

### Elementor Pro Forms

The native Form widget is distinct from Elementor Atomic Form and third-party widgets. The adapter supplies inside-form controls and uses `elementor_pro/forms/validation` before processing/actions. A record- and token-bound one-use permit carries successful validation to its process hook; a second process call must pass the atomic replay check again. A trusted native `validation/skip_all` filter still reaches a protection fallback before submit actions. Native required-field errors remain visible and leave unused proof available for correction.

`elementor/frontend/the_content` adds persistent descriptors to actual native forms even when Elementor's element cache skips individual widget rendering. The original native FormData transports proof; Form_Record selects declared form fields, preventing technical controls from entering mail or saved entries. Only matching declared and processed text types enter local rules or quarantine. Sources: [Forms hooks](https://developers.elementor.com/docs/hooks/forms/), [native Forms overview](https://developers.elementor.com/docs/forms/), unchanged local Elementor Free 4.3.2 and Elementor Pro 4.3.0 originals.

### WooCommerce checkout and registration

WooCommerce 9.9 or newer is required for the documented public checkout extension action. Checkout and My Account registration are separate settings. Classic checkout renders fields in the native payment section, including AJAX replacements. `woocommerce_after_checkout_validation` rejects before customer/order/payment processing; update-totals requests are not final attempts. Existing-order payment has an order-specific proof and stops at `woocommerce_before_pay_action` after native payment authorization and before processing. Gateway callbacks are separate authenticated native flows and are not incoming checkout forms.

For checkout blocks, the adapter adds its descriptor automatically. A narrowly scoped native `wp.apiFetch` middleware supplies fresh proof immediately before each final Store API POST; `setExtensionData` keeps the documented checkout extension data consistent. The extension schema contains technical proof only. The WordPress `rest_dispatch_request` gate runs after endpoint permissions but before the final Checkout endpoint callback, covering internal and batch dispatch. GET, PUT and cart/shipping requests remain native. Missing proof has no gateway or express-payment exemption. External wallets and gateway-specific transports still need their own end-to-end verification; a rejected unsupported transport must not be described as supported.

A rejected guard does not create an account/order or invoke a gateway. Existing native drafts can predate that request. After a guard pass, WooCommerce owns all later native validation and may create/update a draft or account even when a later WooCommerce field check fails. The plugin neither deletes nor rewrites those native objects.

My Account uses `woocommerce_process_registration_errors` before `wc_create_new_customer`, preserving native field and nonce errors. Accounts requested during checkout use checkout proof, avoiding a second consumption. Both account adapters and checkout skip all content/country/language policies and retain no fields. Quarantine independently excludes these scopes at its persistence boundary.

Sources: [checkout extension data](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-data/), [checkout flow and events](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/checkout-flow-and-events/), [original Checkout implementation](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/includes/class-wc-checkout.php), [original Store API](https://github.com/woocommerce/woocommerce/tree/trunk/plugins/woocommerce/src/StoreApi), [WordPress dispatch boundary](https://developer.wordpress.org/reference/hooks/rest_dispatch_request/).


### Divi contact forms

The adapter covers the reviewed same-page transports of Divi 4.27.9 and Divi 5.4.0. Legacy theme initialization happens after `plugins_loaded`; repeated native theme lifecycle detection registers the adapter once. Documented D4/D5 output filters add persistent protection hosts. Before template rendering, a narrowly recognized native contact envelope is checked using technical proof only. Multiple target envelopes cannot share one proof. Native nonce/captcha checks remain Divi-owned.

The local AJAX error retains the container and error-marker shape selected by the original `jQuery.load` transport; a server-generated reload link allows recovery. Full-page rejection uses HTTP403. Contact data never enters content rules or quarantine. The original frontend engines and owned adapter contracts passed local tests; native commercial PHP processing remains a release-verification gate. Details and primary source links: [Elementor/Divi verification](../tests/provider-elementor-divi.md).

### Native pingbacks and trackbacks

These server protocols are disabled by default and locked off in privacy-conscious mode. Explicit allowance is limited to native Core hook intents with one-use source/target binding; ordinary frontend/REST comments cannot bypass proof by changing their type. Pingback rejection precedes Core's remote source fetch. When allowed, Core may fetch that source and forward the sender IP; native moderation/storage remain WordPress-owned. Protocol messages do not enter local quarantine or reviewed quality counts. See [protocol and privacy details](pingbacks.md).

Network scheduling and isolated site storage are documented in [Multisite](multisite.md).

# Native integrations

The integration adapters use public WordPress and provider hooks. They do not modify provider files, inject generated source patches, inspect message text or save submitted field values. Enable or disable each detected provider in WP Antispam. Existing provider security controls remain enabled.

## WordPress comments

`comment_form` supplies the actual post ID and runs inside the form for both visitors and logged-in users. `preprocess_comment` checks native submissions before insertion. Pingbacks and trackbacks are explicitly rejected while comment protection is enabled. All other types, including product reviews, require valid protection fields; compatibility depends on their form actually using the native `comment_form` rendering and processing path. Logged-in users receive the same protection checks.

The REST comments controller inserts directly through `wp_insert_comment`, so `rest_pre_insert_comment` independently protects creation. REST requests must provide valid protection fields for the target post; external applications and editor-based clients, including internal note creation, cannot create comment objects without these fields while this integration is enabled. Existing-comment REST edits keep the native authorization path and earlier `WP_Error` values are preserved. Third-party integrations that directly call `wp_insert_comment` are not covered by `preprocess_comment`; they must use a separately reviewed guard integration.

Sources: [comment_form](https://developer.wordpress.org/reference/hooks/comment_form/), [preprocess_comment](https://developer.wordpress.org/reference/hooks/preprocess_comment/), [REST creation hook](https://developer.wordpress.org/reference/hooks/rest_pre_insert_comment/), [Core REST insertion](https://developer.wordpress.org/reference/classes/wp_rest_comments_controller/create_item/).

## Contact Form 7

`wpcf7_form_elements` appends protection inside the native form. `wpcf7_spam` preserves every earlier positive spam decision and checks raw POST only when no earlier filter has rejected the submission. For local rejections it adds a fixed diagnostic reason through `WPCF7_Submission::add_spam_log()` and uses `wpcf7_display_message` to show a neutral retry instruction. CF7 retains its native spam response status and DOM event; mail is not sent.

Technical controls must be read from raw POST: CF7 removes some metadata from `get_posted_data()`, and its documentation expressly excludes that processed collection from validation and spam filtering. CF7 also copies unknown non-underscore POST keys into that collection. The adapter therefore removes only its exact `rktsky_antispam_token` key and reserved `rktsky_as_` plus twenty-hex-character trap names through `wpcf7_posted_data`; raw POST remains available for verification. This keeps technical proof out of CF7 mail/storage consumers. CF7 extensions such as Flamingo may separately store the actual messages and fixed spam logs; their storage policies are outside this adapter.

Sources: [custom spam filtering](https://contactform7.com/2020/07/18/custom-spam-filtering/), [raw input requirements](https://contactform7.com/2020/07/28/accessing-user-input-data/), [original rendering and message hooks](https://github.com/rocklobster-in/contact-form-7/blob/master/includes/contact-form.php), [original submission hooks](https://github.com/rocklobster-in/contact-form-7/blob/master/includes/submission.php), [DOM events](https://contactform7.com/dom-events/).

## Gravity Forms

Gravity Forms 2.7 or newer is required. `gform_form_tag` adds protection immediately after the opening form tag, preserving empty tags produced by other integrations. The generic `gform_validation` hook rejects failed protection with a visible retry message through `gform_validation_message`; a client-supplied target page cannot bypass checking. Existing failed validation remains failed.

Save and Continue can skip ordinary validation. The independent `gform_abort_submission_with_confirmation` hook therefore checks proof before draft or final persistence. A successful `form-submit` or `api-submit` validation grants exactly one request-local permit for the next abort hook, bound to that native form and exact token. `api-validate` grants none. Every validation invocation resets the form's previous permit and messages; every abort invocation consumes the permit even when another integration already requested an abort. Independent submissions still consume a new token. Existing abort decisions remain true.

For local aborts, `gform_confirmation` replaces the native successful-looking confirmation with an explicit rejection instruction. This adapter does not use `gform_entry_is_spam`, which can save rejected message content. Native GF spam checks continue to run normally. Partial Entries and other independent add-on persistence paths have not been verified and are outside this release's coverage.

Proof is required for final GFAPI submissions as well as browser submissions. An application submitting through GFAPI must carry reviewed protection fields; no API, administrator or logged-in bypass is added. Tests must cover the actual browser path. Where GFAPI is used as a test helper, set its sixth `$initiated_by` argument explicitly to `GFFormDisplay::SUBMISSION_INITIATED_BY_WEBFORM` and reproduce the real controls; its default is the API context. Browser tests still remain necessary for AJAX, multipage navigation, Save and Continue, validation retries and payment add-ons.

Sources: [opening-tag hook](https://docs.gravityforms.com/gform_form_tag/), [generic validation and contexts](https://docs.gravityforms.com/gform_validation/), [validation message](https://docs.gravityforms.com/gform_validation_message/), [abort hook and Save and Continue](https://docs.gravityforms.com/gform_abort_submission_with_confirmation/), [native spam behavior](https://docs.gravityforms.com/gform_entry_is_spam/), [GFAPI submission and context](https://docs.gravityforms.com/submitting-forms-with-the-gfapi/).

## WPForms

The shared Lite/Pro hooks `wpforms_frontend_output` and `wpforms_process` insert protection inside the native form and reject a failed check through the provider's documented process errors. The adapter preserves an earlier header error and runs before ordinary entry persistence and notifications. Submitted protection controls stay at form level and are read from unslashed raw POST, never from a user-editable form ID. WPForms' other validation, CAPTCHA and anti-spam controls retain their native behavior.

The shared hooks are present in the original public Lite source. This does not establish tested compatibility with every Pro add-on, payment method, conversational mode or future provider version. These flows require native end-to-end checks before claiming support.

Sources: [process hook and documented errors](https://wpforms.com/developers/wpforms_process/), [original Lite process implementation](https://plugins.svn.wordpress.org/wpforms-lite/trunk/includes/class-process.php), [original Lite form renderer](https://plugins.svn.wordpress.org/wpforms-lite/trunk/src/Frontend/Frontend.php).

## Verification scope

`tests/integrations.php` uses explicit provider and WordPress stubs to verify hook selection, form binding, prior rejection preservation, REST behavior and adapter control flow. Stubs are contract checks, not evidence of execution in installed provider versions. Native integration and browser checks must separately record WordPress, PHP and provider versions, complete form context and valid/invalid outcomes. No test may disable provider security merely to obtain a passing submission.

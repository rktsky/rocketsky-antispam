# Elementor Pro and Divi verification

Original provider packages run only in disposable fixtures and are excluded from the distribution.

## Elementor Pro

Verified local originals: Elementor 4.3.2 and Elementor Pro 4.3.0, with WordPress 7.1.4 and PHP 8.3.26 in a disposable local fixture. External WordPress HTTP and email delivery were blocked. The synthetic form used an empty native submit-actions list.

The [documented Form API](https://developers.elementor.com/docs/hooks/forms/) exposes validation before field processing and submit actions. The [widget output filter](https://developers.elementor.com/docs/hooks/render-widget-content.html) supplies the classic `form` widget output. Original files reviewed: `modules/forms/classes/{form-record,ajax-handler}.php`, `modules/forms/widgets/form.php`, `assets/js/elements-handlers.js`, and Elementor Core `includes/frontend.php` / `includes/base/element-base.php`.

- Native `Form_Record::validate()` and `process_fields()` passed 23 assertions: valid proof, one validation-to-processing permit, replay rejection, token swap, malformed scope, missing/array/wrong-scope proof, filled trap, preserved native errors, corrected fields, native `validation/skip_all` fallback and selected encrypted content.
- Password, hidden, file and undeclared fields were excluded from rules and quarantine. Original record fields never included top-level proof controls.
- The document content filter was checked against warm cached form output. Only actual `.elementor-form` elements with one bounded native `form_id` receive persistent descriptors. Unrelated forms do not enqueue the protection script.
- Actual local browser AJAX passed seven scenarios: repeated success on the same form; trap failure and retry; native required-field failure and retry; challenge HTTP503 and network abort followed by retry; removed protection controls restored from the host; optional two-second delay. The delay case completed after 2.617 seconds.
- A focused repeat after the shared retry fix showed exactly one failed refresh and one native submission, followed by one fresh challenge and one accepted retry. No automatic request loop remained. All checks used Firefox 153.0 headless at 390×844 CSS pixels on macOS; these are desktop emulation checks, not physical-device or Safari tests.

Scope: the classic Elementor Pro Form widget. Atomic Form, third-party Elementor widgets, direct server-side action calls and unrelated commerce/login widgets are not asserted as covered. The trusted native `skip_all` override bypasses original field validation; the fallback still runs before submission actions, although original per-field processing has already occurred.

Run the native assertions only with an unchanged, active provider in the explicitly marked disposable fixture:

```sh
RKTSKY_ANTISPAM_ALLOW_TESTS=1 RKTSKY_ANTISPAM_WP_ROOT=/absolute/disposable/wordpress php tests/provider-elementor.php
```

## Divi

Verified local originals, each in a separate disposable WordPress 7.1.4 / PHP 8.3.26 fixture with the unchanged official SQLite Database Integration 3.1.3 drop-in:

| Original package | Native PHP verification | Real browser verification |
| --- | --- | --- |
| Divi theme 4.27.9 | 28 assertions across 21 genuine HTTP scenarios with the original theme engine | 18 scenarios, Firefox 153.0 headless at 1280×900 CSS pixels |
| Standalone Divi Builder 4.27.9 | 28 assertions across 21 genuine HTTP scenarios with the original plugin engine | 18 scenarios, Firefox 153.0 headless at 1280×900 CSS pixels |
| Divi theme 5.19.0 | 41 HTTP cases covering native D5 blocks, original D4 compatibility rendering and forms converted by Divi's original conversion API | 32 scenarios, Firefox 157.0 headless |

WordPress HTTP requests and actual email delivery were blocked. Browser contexts allowed only their exact loopback fixture origin; fonts and other external assets were blocked too. Private observers counted calls to the original contact-submit action and `wp_mail`, then intercepted delivery. Guard rejections reached neither the native contact-submit action nor `wp_mail`; successful synthetic submissions reached exactly one original mail attempt. These are mail-path checks, not delivery tests. Native validation failures retain the provider's own behavior.

### Verified behavior

The D4 theme and standalone builder each passed valid, missing, malformed, wrong-scope, filled-trap and replay checks; fresh retry; native required/email/nonce errors; multiple forms and multi-target rejection; full-page POST; unrelated POST; the REST method-override rendering path; custom CSS IDs; unsupported native suffixes; timing; and exclusion of contact content from quarantine. Their browser suites additionally covered native honeypot behavior, warm cached HTML, challenge HTTP503/network failures, native AJAX failure, double clicks and JavaScript-disabled rejection. An invalid native D4 nonce requires the full page reload instructed by Divi's own error before retry. Although the replacement already contains a new native nonce and fresh protection proof, its original direct submit binding is absent until Divi initializes again. The same behavior was reproduced with the adapter disabled. The bridge does not rewrite native nonces or field metadata. An interrupted native D4 AJAX request retains Divi's empty message area; zero mail attempts and recovery after manual reload were checked.

The D5 suite exercised native, legacy and originally converted forms with missing/trap/valid/replay/fresh-retry proof, AJAX and full-page errors, native required fields and the REST method-override rendering path. It also checked the original arithmetic captcha, independent forms, multiple-target rejection, duplicate UUID coordination, invalid native nonce, optional minimum time, unusual/overlong native IDs, and public/private reload metadata. Unsupported native IDs were rejected both through ordinary rendering and REST, and remained rejected with otherwise valid proof.

D5 browser checks covered minimum times of zero and two seconds across native, legacy and converted forms, each with honeypot failure, native required-field failure, challenge HTTP503 and challenge network abort. Each corrected retry obtained one fresh challenge and produced one actual native submission. Further scenarios covered custom CSS IDs, identical cached HTML in independent browser contexts with distinct fresh proofs, two independent forms, the original local arithmetic captcha, a successful full-page POST and rejection with JavaScript disabled. No JavaScript errors occurred in either D4 suite or the D5 suite. Desktop browsers do not establish physical-device, Safari or other browser coverage.

### Rendering boundary and recovery

Theme detection is repeated at native lifecycle boundaries because Divi initializes after `plugins_loaded`; registration remains idempotent. Documented `et_module_shortcode_output` and `divi_module_wrapper_render` filters add descriptors to actual contact form tags without changing native fields.

Since 0.3.1 the guard runs at `wp_loaded` priority 100, before frontend, REST and AJAX rendering can invoke Divi's contact processing. It examines raw POST regardless of REST method overrides; there is no admin or REST exemption. Any of the native submit, field-list or nonce prefixes selects protection. Native target suffixes must contain one to 64 ASCII letters, digits, underscores or hyphens; unsupported suffixes and multiple distinct targets fail closed. The signed scope is fixed to `divi/contact`. Only technical token, honeypot and configured timing checks run; native field values and client-declared types never enter content, language or country rules or quarantine. Divi's own nonce, required-field and captcha controls remain active. The follow-up `et_pb_contact_form_submit` action is not treated as a mutable validation hook.

A rejected AJAX submission returns HTTP200 with the actual native container ID and `et_pb_contact_error_text`, allowing the original `jQuery.load` selector to display the error. Full-page rejection returns HTTP403. Bounded, escaped target/page metadata controls error presentation only; it cannot change proof scope or authorize processing. Reload links use a Core-generated permalink only for a publicly viewable page, falling back to the site home. Private page slugs, submitted field contents and request URLs are not reflected. Rejected submissions can be retried after the offered reload; submitted content is not retained by this adapter.

### Reproduction and limits

`tests/provider-divi.php` separately supplies 61 explicitly stubbed adapter assertions with the unchanged WordPress HTML API. Those contract assertions complement the original-engine tests. The older intercepted D4/D5 frontend harnesses establish serialization behavior only and are not counted as native PHP verification.

The commercial originals are private fixture inputs. They are not distributed in this repository or downloaded by public CI. ZIP entry lengths and CRCs matched every installed original file: 1575 files for the D4 theme, 1498 for the standalone builder and 3443 for the D5 theme, with no original changes. Other Divi versions, third-party modules and custom transports require separate acceptance checks. These suites do not establish delivery, third-party action or external CAPTCHA-service compatibility.

Native programs require the explicit disposable fixture marker, local configuration, active unchanged provider and private side-effect observer. Configure `RKTSKY_DIVI4_FIXTURE_LOG` or `RKTSKY_DIVI5_FIXTURE_LOG` as a private log path outside the public site root, with an existing parent directory. Install the owned observer using the corresponding `prepare-divi4.php observer` or `prepare-divi5.php observer` command before preparing synthetic content. The D5 installer rejects public or symlinked log paths; a configured local observer with missing safety configuration terminates bootstrap before delivery. Both D5 runners read this configured path. The isolated fixtures use loopback ports 8884/8886 for D4 and 8885 for D5. For an already prepared fixture:

```sh
RKTSKY_ANTISPAM_ALLOW_TESTS=1 RKTSKY_ANTISPAM_WP_ROOT=/absolute/disposable/divi4 php tests/native-divi4.php
RKTSKY_ANTISPAM_ALLOW_TESTS=1 RKTSKY_ANTISPAM_WP_ROOT=/absolute/disposable/divi5 php tests/native-divi5.php
```

The browser runners are `tests/browser-divi4.mjs` and `tests/browser-divi5.mjs`. Their real original engines must remain available in the marked fixtures; fault injection is confined to deliberate network-error cases. Local JSON results are recorded under `outputs/wp-antispam/` and the private fixture directory. No licence keys or original packages belong in test artifacts.

Primary sources reviewed on 2026-11-07:

- [Official module-output documentation](https://dev.elegantthemes.com/docs/tutorials/module/advanced/customize-module-settings-output/modifying-module-output/): D4 `et_module_shortcode_output`, D5 `divi_module_wrapper_render` and its `name` context.
- [Official module hook documentation](https://help.elegantthemes.com/en/articles/8657766-divi-module-hooks): `et_pb_contact_form_submit` is a follow-up action.
- [Official D5 ContactFormHandler API](https://dev.elegantthemes.com/api/php/Packages/ModuleLibrary/ContactForm/ContactFormHandler/) and [unique ID documentation](https://dev.elegantthemes.com/docs/explanations/module-library/contact-form/unique-id/).
- Unchanged local D4 theme/builder `includes/builder/module/ContactForm.php` and `js/scripts.min.js`; unchanged D5 `includes/builder-5/server/Packages/ModuleLibrary/ContactForm/ContactFormHandler.php`, its original conversion API and native contact-form frontend assets.

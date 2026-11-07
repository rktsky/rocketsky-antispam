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
WP_ANTISPAM_ALLOW_TESTS=1 WP_ANTISPAM_WP_ROOT=/absolute/disposable/wordpress php tests/provider-elementor.php
```

## Divi

The original commercial PHP engine was unavailable. Native server-side compatibility and end-to-end email suppression remain release-verification gates; contract and original frontend tests must not be presented as native Divi PHP tests.

Primary sources inspected on 2026-11-07:

- [Official module-output documentation](https://dev.elegantthemes.com/docs/tutorials/module/advanced/customize-module-settings-output/modifying-module-output/): D4 `et_module_shortcode_output`, D5 `divi_module_wrapper_render` and its `name` context.
- [Official module hook documentation](https://help.elegantthemes.com/en/articles/8657766-divi-module-hooks): `et_pb_contact_form_submit` is an action for follow-up handling. It is not used as an assumed mutable pre-delivery validator.
- [Official D5 ContactFormHandler API](https://dev.elegantthemes.com/api/php/Packages/ModuleLibrary/ContactForm/ContactFormHandler/) and [unique ID documentation](https://dev.elegantthemes.com/docs/explanations/module-library/contact-form/unique-id/).
- [Official D4 live demonstration](https://www.elegantthemes.com/layouts/business/consultant-contact-page/live-demo), serving Divi 4.27.9. Its rendered form uses `et_pb_contactform_submit_<number>=et_contact_proccess` and a native nonce control. Its [unchanged original script](https://www.elegantthemes.com/layouts/wp-content/themes/Divi/js/scripts.min.js?ver=4.27.9) serializes the form and uses same-page `jQuery.load` with the native container selector.
- [Official Divi 5 demo](https://demo.elegantthemes.com/), serving public version 5.4.0 assets. Its [original module renderer](https://demo.elegantthemes.com/app/themes/Divi/includes/builder-5/visual-builder/build/module-library.js?ver=5.4.0) uses `et_pb_contactform_submit_<UUID>=et_contact_process`; its [original contact transport](https://demo.elegantthemes.com/app/themes/Divi/includes/builder-5/visual-builder/build/module-library-script-contact-form.js?ver=5.4.0) serializes the form and uses same-page `jQuery.load`, with the UUID from `data-form_unique_id`.
- The official `@divi/module-library` 1.0.15 npm package provides type declarations only. It does not supply the commercial PHP runtime.

`tests/provider-divi.php` passed 50 explicitly stubbed adapter assertions while using the unchanged WordPress HTML API: D4/D5 host descriptors; numeric, UUID and hash envelopes; preserved builder structures and unrelated forms; fixed technical scope; proof-only Guard input; missing/trap/replay verdict handling; multiple-target rejection; escaped visible errors; and HTTP200 AJAX versus HTTP403 full-page failures.

Both unchanged original frontend scripts were additionally executed in Firefox 153.0 against a wholly intercepted local transport harness. Each passed ordinary serialization, trap rejection → the actual adapter error fragment → reload → retry, and challenge HTTP503 → visible failure → retry. Both serialized native field metadata plus the own token and dynamic trap. No external submission was made. Harness responses and challenge issuance were mocked; these checks establish original browser-transport behavior, not original PHP validation or mail behavior.

The adapter checks the known same-page envelope at `template_redirect` before template/module rendering. Its signed scope is fixed to `divi/contact`; multiple distinct native targets fail closed. Native field values and request-declared types are not read for content rules or quarantine. Administrator AJAX, REST, custom rewritten transports, future transport changes and unsupported third-party modules are outside this verified contract. A rejected native AJAX response includes the correct native container ID and `et_pb_contact_error_text`, so the original script displays a failure rather than following a success redirect. Retrying reloads the form; submitted contents are not reflected or retained.

```sh
WP_ANTISPAM_WP_ROOT=/absolute/unchanged/wordpress php tests/provider-divi.php
```

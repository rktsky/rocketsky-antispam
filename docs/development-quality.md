# Development acceptance

Acceptance connects the applicable WordPress rule to the affected entry points
and actual evidence. Reading a handbook or obtaining a clean configured tool
report is insufficient when the implementation bypasses the relevant check.

The [16 November review](review-2026-11-16.md) exposed a gap in this process:
shared HTML renderers were treated as sufficient reason to suppress output
warnings at their callers. The earlier Plugin Check results describe the
checker run; they do not establish the absence of inline source suppressions.
Historical submitted archives and reports remain unchanged.

## Boundary checks

| Requirement | Implementation and acceptance |
| --- | --- |
| Request values cannot choose authority | Notice preferences use a fixed native capability for the current account. Native provider capabilities still control their screens and entry actions. Tests cover other accounts/sites, malformed scope, revoked own-profile permission and unchanged protection settings. |
| Inputs meet their domain contract | Validate shape and bounds, unslash once, sanitize ordinary text and reject transformations into another valid identifier. Signed proof bytes retain strict format and authentication checks. Necessary pre-validation reads are reviewed exceptions. |
| HTML is checked at the output boundary | Owned control fragments use native `wp_kses` with explicit tags/attributes at terminal outputs and HTML-returning provider hooks. Tests discover shared consumers and verify their real rendered controls, no-JavaScript text, hostile attributes and provider-content preservation. |
| Configured results disclose exceptions | The production exception guard checks actual PHP comment tokens and ruleset exclusions against reviewed code scopes, reasons and compensating tests. It also binds the referenced test code. New, widened or stale exceptions and changed evidence fail early checks. |
| Evidence describes the shipped candidate | Build from committed source, retain member hashes, install that exact archive and run original Plugin Check plus native activation and ordinary requests. Record the tested platform and open coverage boundaries. |

WordPress's [sanitizing](https://developer.wordpress.org/apis/security/sanitizing/),
[escaping](https://developer.wordpress.org/apis/security/escaping/) and
[nonce](https://developer.wordpress.org/apis/security/nonces/) contracts govern
these checks. Native behavior, visual acceptance and directory requirements
remain separate parts of the same release decision.

## Reviewed exceptions

The exception inventory is a review record, not a list of automatically safe
code. Each contract needs a precise scope, reason, official source and a
meaningful native regression. A generated candidate inventory remains pending
until those contracts have been examined. Changed callers or method bodies
invalidate the previous code binding. Referenced PHP tests have separate native
token hashes: removing assertions or replacing a test with an empty PHP file
invalidates the evidence even when its filename remains. Comment and whitespace
changes do not alter that lexical code contract.

The guard inspects production PHP and the configured PHPCS ruleset. It rejects
production `EscapeOutput` suppressions and broad checker bypasses. Legitimate
exceptions include native provider names, the separately authorized owned-table
operations and bounded raw protocol/nonce checks that the static rule cannot
model. Their authorization and behavior must still be checked independently.

Development test data and test-framework observations have a different purpose
and are outside this production inventory. The package builder excludes tests,
tools and the inventory from the installable archive. This does not permit
weakening an assertion or changing a checker configuration to conceal a failure.

The guard enforces visibility and code binding; it does not run the tests or
judge whether their assertions establish the claimed behavior. Report its result alongside the configured
WPCS/Plugin Check totals, without turning either into a claim of complete security.

## Running the checks

```sh
composer quality:exceptions
python3 tools/test_quality_guard.py
composer lint
composer standards
```

The guard and builder require Python 3.10 or newer and PHP with its native
Tokenizer extension. They do not load WordPress or execute the inspected plugin.
Use `--php` or `RKTSKY_ANTISPAM_PHP` for an isolated PHP executable/wrapper when
PHP is not on PATH. Ordinary WordPress installations do not need these development
tools or Python.

The same guard runs before the standards job installs its development packages
and before the release builder writes a ZIP. The manifest records its scope,
reviewed-inventory hash and inspected PHP version. CI declares its PHP/Tokenizer
dependency before building, and independently checks the exact archive with
Plugin Check. The mutation tests modify disposable source copies and verify
rejection of new/widened exceptions, changed code, removed checks and stale
evidence; they do not approve the real inventory.

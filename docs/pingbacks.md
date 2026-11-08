# Native pingbacks and trackbacks

Introduced in Rocketsky Antispam 0.3.0. The option is part of the WordPress comments integration and is off by default. Privacy-conscious mode forces it off in validated settings and disables its administration control. Disabling the comments integration also removes its protocol protection hooks.

When the option is enabled outside privacy-conscious mode, native protocol requests continue through WordPress's existing target, duplicate, ping-status and moderation checks. They cannot obtain a JavaScript form token. Rocketsky Antispam therefore accepts only the server-side native protocol intent described below; a submitted `comment_type` does not grant an exception. Ordinary comments, custom comment types and frontend comments by administrators still need their normal protection proof.

## Privacy and retention

Enabling native pingbacks allows WordPress to request the remote source page. Current Core includes the requester's address in the verification user agent and `X-Pingback-Forwarded-For` header. The remote site therefore receives data directly from WordPress's protocol verification. Trackbacks use the submitted source information without this pingback verification fetch.

Core owns the resulting native comment, including any source URL, excerpt and address it normally retains. Native moderation and the site's comment retention rules remain authoritative. Permitted protocols bypass Rocketsky Antispam's form-token and additional content rules. They do not create a second quarantine copy, a successful token-check count or an automatic quality prediction. Protocol requests rejected by Rocketsky Antispam receive the existing `pingback` policy count without retaining their contents.

The option does not disable the entire XML-RPC API, change the site's native ping-status settings or override another plugin's protocol rejection. Core 7.1's default rejection of pings in non-production environments remains effective.

## Native boundaries

- `xmlrpc_call` clears both request-local permits on every observed method. A pingback permit can start only for `pingback.ping` with Core's `XMLRPC_REQUEST` flag and an actual `wp_xmlrpc_server` object. The source filter stops a disabled or unbound request before Core's URL validator and remote request. Its final result binds the accepted source to one subsequent native pingback insertion.
- `pre_trackback_post` can arm an intent only while the action is running and Core's endpoint function `trackback_response()` exists. The intent records the exact target post and bounded source URL in memory. A subsequent native trackback must match both values.
- Each consumption clears both protocol permits before a return or rejection. An ordinary comment, REST creation attempt or another observed XML-RPC method also clears outstanding permits. REST creation never receives a protocol exception.

These are native hook boundaries within trusted PHP execution. They are not capabilities granted by visitor-supplied values. A plugin with arbitrary PHP execution can also alter WordPress's own comment filters; hostile installed PHP is outside this boundary.

## Verification

`tests/native-pings.php` runs isolated PHP requests against an explicitly marked disposable WordPress site. It invokes the original `wp_xmlrpc_server::pingback_ping()` method and includes the unchanged native `wp-trackback.php` endpoint. It does not patch Core or disable its URL, target, duplicate or comment moderation checks.

Only one exact synthetic source URL receives a synthetic HTTP response, with the fixture's blanket HTTP and mail blocks still present. Its literal public address avoids a DNS query in Core's URL validator; no actual connection reaches that address. Trackback production-path cases use Core's documented environment opt-in filter inside the test only. A separate case retains and verifies the native local-environment block when that API exists.

The suite covers default settings and malformed values; privacy-mode enforcement; enabled and disabled native requests; source URLs containing query separators; native spam moderation; closed pings; source/target mismatch; one-use consumption; XML-RPC method reset; frontend/REST type spoofing; and a source-filter call without native intent. It checks that protocol processing does not add quarantine contents or automatic feedback rows and that accepted requests do not invent successful token checks. Synthetic posts are deleted through native WordPress APIs; original fixture settings and the affected owned aggregate rows are restored.

Run only on a disposable, isolated fixture:

```sh
RKTSKY_ANTISPAM_ALLOW_TESTS=1 RKTSKY_ANTISPAM_WP_ROOT=/path/to/disposable-wordpress php tests/native-pings.php
```

The Comments adapter passes the project's WordPress coding standard. The recorded native run uses WordPress 7.1.4 and PHP 8.3.26. It does not establish compatibility for a replacement XML-RPC implementation that omits Core's hooks or another trackback endpoint that does not use Core's native boundary.

## Official references

- [Native XML-RPC method boundary](https://developer.wordpress.org/reference/hooks/xmlrpc_call/)
- [Pingback source filter](https://developer.wordpress.org/reference/hooks/pingback_ping_source_uri/)
- [Native pingback implementation](https://developer.wordpress.org/reference/classes/wp_xmlrpc_server/pingback_ping/)
- [Trackback processing boundary](https://developer.wordpress.org/reference/hooks/pre_trackback_post/)
- [Safe native HTTP request API](https://developer.wordpress.org/reference/functions/wp_safe_remote_get/)

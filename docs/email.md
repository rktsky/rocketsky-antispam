# Daily administrator email

Since 0.5.4, the optional daily summary contains an HTML card with the website
name, completed reporting date, configured WordPress time zone and number of
stopped submissions. A button opens that site's activity overview; a separate
link opens its notification settings. Counts include failed form checks and
are not a claim about confirmed spam or detection accuracy.

The layout uses inline styles, system fonts and presentation tables with an
Outlook fixed-width fallback. It loads no images, fonts or other remote assets.
English, German, French, Italian and Spanish share the same layout. The
message includes a plain text alternative when the native HTML body is retained.
Mail plugins that replace the body, content type or text alternative retain
their own behavior.

The date and time zone come from the WordPress site's settings. No geographical
zone is assumed. Changing the email design does not change stored counts,
notification preferences, privacy mode, recipients or daily sending claims.
The only recipient remains the site's configured administrator address. No
visitor names, addresses or submission text are included.

Delivery uses [wp_mail](https://developer.wordpress.org/reference/functions/wp_mail/)
with a per-message HTML header. The temporary
[phpmailer_init](https://developer.wordpress.org/reference/hooks/phpmailer_init/)
callback adds the plain text part only to the matching digest body and is
removed in `finally`, including failures and mail short circuits. It restores
its own alternative after use. It does not alter sender identity, SMTP settings,
mail-plugin configuration or global content-type filters. WordPress's true
return value records mailer acceptance, not confirmed inbox delivery. Failed
attempts keep their existing daily claim and are not sent again automatically.

Version 0.5.3 remains the package submitted on 9 November 2026. Preparing this
update does not replace that submission or deploy it to a website.

## Verification on 11 November 2026

The native email runner passes 196 assertions on WordPress 7.1.4 / PHP 8.3.36
with SQLite Database Integration 3.1.3. It serializes real PHPMailer MIME while
replacing the final transport with a recorder in a network-isolated fixture.
Both decoded message parts match the localized output in English and all five
bundled locales, with singular and plural counts. Cases include hostile and
long site titles, ampersands, recipients, existing alternative text, mail short
circuits, exceptions, a nested call before mailer initialization and the next
ordinary plain text message. Hooks, locale, options and mailer are restored.
No message is actually delivered. The existing time-zone runner passes all
80 assertions, including calendar boundaries and duplicate-send prevention.

Native message HTML is checked in the in-app browser at 320, 390, 768 and
1440 CSS pixels for six locales: 24 states, with no horizontal overflow, text
below 14 pixels or external image/script/stylesheet assets. The activity link
has a 52-pixel click target and the settings link 44 pixels. This is desktop
viewport emulation of email HTML, not an Outlook, Gmail, Apple Mail, mobile
device or accessibility certification. Separate browser layout checks cover
long text, a seven-digit counter and RTL direction. Screenshots use synthetic
website identity and counts; the date uses the fixture's WordPress date format.

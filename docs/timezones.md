# Website time zones and updates

Since 0.5.3, daily activity follows the time zone configured in WordPress under
Settings → General. This applies independently to each Multisite website.
The plugin uses `wp_timezone()`, `current_datetime()` and `wp_date()`; it does
not change PHP's process-wide time zone or add an offset to Unix timestamps.

## Calendar days

The seven-day display includes today and the preceding six local calendar
dates. Yesterday's digest covers the completed local day. Calendar arithmetic
handles daylight-saving changes, including 23- and 25-hour days; subtracting
86,400 seconds is not used to select a calendar date. Each rendered report
and email uses one reference instant, so a request crossing midnight cannot
combine different reporting dates.

Quarantine lists, details and privacy exports format their existing absolute
timestamps in the website time zone. Lists and details use WordPress's date
and time formats. Machine-readable dates include their actual offset.
Token lifetimes, encryption metadata and expiry comparisons continue to use
real Unix timestamps. The native `comment_date_gmt` deletion query still uses
the matching GMT column semantics.

## Existing statistics

Earlier versions stored anonymous daily totals without individual event times.
Those totals cannot be accurately redistributed across different day boundaries.
The existing statistics table therefore gains a `calendar` column, with `UTC`
as the original basis for existing rows. No original count or date is rewritten.
The primary key includes the time zone so later records cannot merge unrelated
calendar days. Installation verifies the new columns and key before publishing
schema version 5.

The current period contains only the selected website time zone. Retained
totals from earlier time zones remain available inside the Activity section,
under Earlier time zones. The dashboard links there when needed. A later
WordPress time-zone change starts using that configuration immediately;
earlier groups retain their own dates and normal retention boundaries.
The lifetime rejected-comment total is unchanged by a time-zone change.

Native spam reclassification keeps the original observation's day and time
zone, even when moderation follows a local midnight or a settings change.
Daily mail claims use both the reporting day and its time zone. Existing
zero-offset claims remain effective, and a failed mail attempt is not blindly
retried. The existing hourly WordPress cron remains sufficient.

An incomplete schema upgrade does not receive the new schema marker. Reporting
stays idle until the required unique key is verified; replay protection remains
independent. The administration explains unavailable daily statistics instead
of presenting them as a genuine zero. A later normal upgrade can retry without
deleting the retained data.

## Matching markup and styles

The 0.5.3 update changes the native WordPress asset version. A browser that
cached an earlier 0.5.2 stylesheet requests the matching stylesheet at
`?ver=0.5.3`. The observed support-area failure was reproduced with a real
browser HTTP cache: newer markup and an older stylesheet shared the same
0.5.2 URL. The new version loaded the correct layout in the same browser,
without clearing its cache or modifying original WordPress files.

Do not distribute changed production files under a version already delivered
to users. Build and verify the complete archive before handing it over; keep
the archive and its checksum immutable. Markup and styles must be tested
together through the update path as well as on a clean installation.

Official references checked on 8 November 2026:
[wp_timezone()](https://developer.wordpress.org/reference/functions/wp_timezone/),
[current_datetime()](https://developer.wordpress.org/reference/functions/current_datetime/),
[wp_date()](https://developer.wordpress.org/reference/functions/wp_date/).

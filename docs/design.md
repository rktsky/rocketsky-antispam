# Settings experience

WP Antispam has one screen under Settings. The page keeps WordPress navigation
and notices intact; its locally loaded stylesheet is scoped to this screen.
There are no remote fonts, JavaScript dependencies or icon libraries. The small
shield is an original SVG path authored for this plugin.

The overview distinguishes checks passed from submissions stopped. A stopped
submission is not automatically confirmed spam: expiry and technical failures
are counted too. Empty statistics explain when counts appear. Available and
enabled integrations are separate states, so settings can be prepared before a
form plugin is activated.

The form uses the native Settings API, plain checkboxes, labelled number inputs
and one explicit save button. Statistics deletion is a separate authenticated
POST action under a disclosure, with an irreversible-action explanation. It
must not clear the replay ledger. Neither saving nor deletion requires JavaScript.

## Sources checked on 2026-11-07

- [WordPress submenu documentation](https://developer.wordpress.org/plugins/administration-menus/sub-menus/): settings live in the existing menu and access is checked before rendering.
- [WordPress Settings API](https://developer.wordpress.org/plugins/settings/settings-api/): form posts to options.php; capability and nonce handling use Core.
- [WCAG 2.2 focus guidance](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible.html): interactive elements retain persistent, visible keyboard focus.
- [WCAG 2.2 reflow guidance](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html): cards and controls reflow in one column, including at 320 CSS pixels.

## Verification scope

The markup provides native labels, visible text states, keyboard-operable
disclosures and focus styles. Responsive rules target narrow, tablet and desktop
layouts. These implementation checks are not user research or accessibility
certification. Actual browser/device, zoom, keyboard, save/error and screen-reader
checks must be recorded against the running WordPress screen before release.

A temporary PHP fixture rendered the actual settings template in Firefox 153.0
on macOS at 320, 390, 768 and 1440 CSS pixels on 2026-11-07. All visible inputs
had explicit labels and none of those widths produced horizontal document
overflow. Desktop and 320-pixel screenshots were visually reviewed. This fixture
does not establish native WordPress saving, notices or access control. Chromium
and WebKit launch attempts did not complete; neither counts as verified.

The actual WordPress 7.1.4 administration screen was then checked in the isolated
local SQLite fixture with Firefox 153.0 on macOS at the same widths. The scoped
stylesheet loaded, labels remained connected and no document overflow occurred.
The completion-time setting persisted after saving and reloading; the fixture
was restored to 0 seconds. A checkbox was operated with the Space key and its
3-pixel keyboard focus outline was confirmed. A redundant settings notice found
during this flow was removed because WordPress options-head.php already renders
Settings API notices.

Statistics deletion returned HTTP 405 for GET and HTTP 403 for an invalid POST
nonce. A valid authenticated POST completed and displayed its success notice.
Desktop and narrow screenshots of the actual administration screen were visually
reviewed. The native fixture used a synthetic administrator and no production
data. This is desktop browser automation; touch devices, real Safari/Edge,
screen readers and user testing remain unverified.

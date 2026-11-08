# Administration experience

Rocketsky Antispam has one entry under WordPress Settings and five linked views. Each
view has a specific task; opening one does not require a client-side router.
Core navigation, notices and authorization remain native. The plugin stylesheet
is scoped to its own screen, uses system fonts and requests no external assets.

- **Overview:** available protection status, seven-day activity and reviewed
  detection results. Operational rejections include technical failures; their
  rate is not presented as detection accuracy. A dataset without reviewed cases
  has an explicit empty state. Detailed daily counts and measurement explanations
  are secondary disclosures.
- **Integrations:** WordPress features and available form providers come first.
  Other supported providers are grouped in a closed native disclosure. A fresh
  installation enables comment protection; form integrations require opt-in.
  Unavailable providers are unchecked and disabled. Their earlier saved choices
  survive other saves and temporary deactivation. Availability does not claim
  that a form has been found on the site.
- **Filter rules:** keyword and BBCode controls are directly visible. Language,
  country and advanced-pattern sections show their current configuration in
  their summaries; configured restrictions open on the server. Technical proxy
  settings remain inside their own disclosure.
- **Review spam:** a compact directory links each enabled, available integration
  to its spam review destination, followed by the local quarantine list or its
  empty state. Native queues keep their provider's own retention policy;
  requests rejected before native storage have a separate local-retention path.
  Technical-only account, checkout and Divi scopes link to aggregate activity
  and do not imply that account, order or file contents can be reviewed. Assessing a
  delivered case and removing a saved reference are separate secondary forms.
  Record details keep classification, deletion and their consequences distinct.
- **Settings:** the single editable privacy mode, retention, quarantine capture,
  administrator mail and uninstall choice. Other views link to these settings
  instead of repeating global controls. Statistics deletion uses a separate
  authenticated POST form outside the settings form.

Configuration changes take effect after an explicit save. Native form values
and hidden preservation controls remain in the document when a disclosure is
closed. A configuration form with actual unsaved changes warns before leaving;
reverting the changes or submitting that configuration does not produce the
warning. The administration script does not persist field values in browser
storage or send them to a separate endpoint.

## Contextual protection guidance

Enabled, available integrations show one native information notice on a fixed
inventory of their relevant WordPress administration screens. Comments, forms,
entries and applicable account/order screens receive only their own context;
unrelated screens, fullscreen builders, AJAX, network administration and the
frontend receive no notice. The notice reports enabled protection, not the
discovery of an actual form or a guarantee that every rejected request is spam.

The current native menu capability controls visibility, including customized
provider roles. Comment moderators use `moderate_comments`. Native inbox links
also require that destination's menu capability. Rocketsky Antispam settings, activity
and encrypted quarantine links remain limited to `manage_options`; a form
manager without that permission receives explanatory text without an inaccessible
link. No entries, contents or spam counts are queried. Only a small scoped
14-pixel notice stylesheet is loaded on these provider screens.

Gravity Forms receives its own `gf-notice` classification so the original plugin
can place the notice in its native wrapper. WPForms receives the same read-only
message through its `wpforms_admin_header_after` action because its administration
removes unrelated WordPress notice callbacks. Neither integration changes the
provider's notice-filtering policy or its original files.

One shared presentation service supplies integration rows, the review directory
and notices. It distinguishes native spam handling, per-form WS Form retention,
optional local quarantine and technical-only rejections. With quarantine off,
the interface explicitly states that Rocketsky Antispam retains no rejected message
contents. Native provider storage remains independent of privacy-conscious mode.
Integration rows use a short destination-specific explanation; encryption,
capture limits and retention policy are explained once on the review page.
The directory does not repeat identical local-quarantine links for each native
inbox because its local list follows immediately below. On mobile, description
text spans the full provider row while the name and checkbox share its heading.

## Visual and interaction system

The interface uses the project's ink `#17142d`, purple `#50349b` and lime
`#d4ff52`, with quiet neutral surfaces. A compact product heading replaces a
marketing introduction. Available integrations use consistent rows; selecting a
provider does not turn the entire list into a success banner. All five section
links use the same visual treatment, wrap into rows on narrow screens and retain
one explicit current-page state.

Body text and field labels use 16 CSS pixels. Statuses, descriptions and legal
information remain at least 14 pixels. Text fields, selects and buttons have a
46-pixel minimum height. Selects reserve a 46-pixel right gutter for their native
arrow. Checkboxes use the browser's native 22-pixel control and the plugin's
accent; the additional Core SVG tick is suppressed within this screen so native
colour schemes cannot draw a second, displaced checkmark.

Cards, fields, primary saves, secondary actions and destructive actions have
shared component rules. Descriptions sit below their headings. Native details
keep keyboard operation without administration JavaScript; the visual chevron
is decorative. On narrow screens, a disclosure badge moves below its title to
avoid breaking words. Privacy explanations lose their checkbox indentation on
mobile so the text retains sufficient line width. Settings anchors leave space
for the WordPress administration bar. Keyboard focus uses a three-pixel outline.
Reduced-motion and forced-colour preferences are handled within the plugin.

Sponsorship appears once at the bottom of each view as a compact, light footer.
Rocketsky remains visibly linked as the developer and sponsor. Speo uses its
locally served owned mark, a short description and one outlined CTA. Its vector
arrow is part of the same anchor, with no separately underlined arrow character.
Both external destinations use a new tab with `noopener noreferrer`. The logo
retains its BSD-3-Clause attribution in `assets/branding/LICENSE`; no advertising
script, remote font or tracking request is added.

Since 0.5.2, the same footer groups a voluntary Ko-fi contribution and a neutral
WordPress.org review link above the sponsor row. A compact review link in the
plugin header keeps that action visible on long screens. The fixed destinations
carry no tracking parameters or submitted data. Nothing is embedded from those
services, and viewing the screen records no support or review state. The links
do not gate features or ask for a particular rating. The directory review link
is prepared for the proposed slug and becomes usable when its listing is
published. It is not an indication that the listing is already available.

The native WordPress dashboard widget has its own scoped stylesheet, loaded
only on the site dashboard for administrators. A seven-day summary precedes
collapsible daily activity; the rejection rate includes verification failures.
The cumulative comment total and later-reviewed results are distinct. Empty
review data has one explanatory state rather than three undefined metrics.
Body labels and buttons use 16 pixels, secondary details at least 14 pixels.
The widget contains no sponsor or donation block and loads no JavaScript.

## Sources and applied decisions

The following primary sources were checked on 2026-11-07. They inform the design;
they are not evidence of user testing or accessibility certification.

- [WordPress Settings API](https://developer.wordpress.org/plugins/settings/settings-api/): configuration stays in native authenticated `options.php` forms. Each screen exposes its own settings, with explicit saves and Core notices.
- [WordPress current_screen](https://developer.wordpress.org/reference/hooks/current_screen/) and [get_plugin_page_hook](https://developer.wordpress.org/reference/functions/get_plugin_page_hook/): contextual guidance uses registered native page hooks rather than guessed translated screen prefixes. Its fixed screen inventory does not use a submitted form or entry ID as authority.
- [WordPress wp_admin_notice](https://developer.wordpress.org/reference/functions/wp_admin_notice/): guidance uses a native information notice with escaped text and local review links, without adding global dismissible nags.
- [Nielsen Norman Group's usability heuristics](https://www.nngroup.com/articles/ten-usability-heuristics/): task labels use familiar words; current states and saved choices remain visible. Optional configuration is secondary, repeated global controls are removed, and the same action has the same presentation across views.
- [WCAG 2.2 reflow guidance](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html): navigation, fields, metadata and descriptions reflow at 320 CSS pixels. Text is not reduced to make the layout fit.
- [WCAG 2.2 focus-visible guidance](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible.html): links, native disclosures, checkboxes and form actions keep a visible keyboard-focus indicator, including under the WordPress Coffee colour scheme.

## Verification scope

Verification uses the real WordPress 7.1.4 administration in explicitly marked,
local, disposable SQLite fixtures. The browser is headless Firefox 153.0 on
macOS. English, Swiss German, French, Italian and Spanish are checked at 320,
390, 768 and 1440 CSS pixels. Closed and expanded configuration sections are
included. Browser requests to every other origin are blocked; the fixture also
blocks native WordPress outgoing HTTP and mail.

The full native save and unsaved-change flows are recorded separately in
`docs/verification.md`. Layout verification covers labels, field geometry,
arrow clearance, disclosure states, keyboard focus, native checkboxes, complete
page reflow, reduced motion and forced colours. Nonempty quality and quarantine
states use synthetic records created through plugin APIs in a separate private
fixture, which is discarded after inspection. Their computed rates are test
data, not a measurement of production detection performance.

Screenshots and machine-readable results live outside Git under
`outputs/wp-antispam/`; the 0.4.0 artifacts use versioned filenames. Desktop
viewport changes do not establish touch-device coverage. Physical phones and
tablets, real Safari/Edge/Chrome, screen readers and user research remain outside
this run's verified coverage.

The 0.5.0 routing extension was checked in 40 complete Integrations/Review views
(the five UI languages at four widths), 30 native comment/GF/WPForms notice views
(five languages at 320 and 1440 pixels), and two disabled/unrelated-screen cases.
The fixture reported WordPress 7.1.4, PHP 8.3.26, Gravity Forms 3.2.2,
Contact Form 7 6.2.4.1 and WPForms Lite 2.0.4. The run recorded no own-text
size violations, overflow or JavaScript exceptions; 164 foreign-origin browser
requests were blocked. Original options and the synthetic administrator's locale
were restored exactly. Native menu/role contract tests additionally cover the
fixed provider-screen inventory, restricted native inbox links and revocation
after screen preparation. These contracts do not establish browser coverage of
every provider's administration interface.
After shortening the routing copy and widening mobile descriptions, the changed
Integrations/Review views were checked again in the five languages at 320 and
1440 pixels. These 20 views and two negative notice cases passed without overflow,
own text below 14 pixels or JavaScript exceptions; options and locale were again
restored exactly. The narrower descriptions retain explicit no-content-storage
information without repeating the full privacy explanation for every switch.

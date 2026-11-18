# WordPress.org directory identity

The directory identity extends the plugin's established ink-and-lime `a.` circle
from `templates/admin-header.php` and `assets/admin.css`. Rocketsky remains the
visible publisher. The banner uses the full product name, the same mark and a
short description of the actual behaviour: local protection, free features and
no added CAPTCHA or external spam service. It adds no detection metrics or
WordPress endorsement claim.

The mark and banners are native vector compositions. All visible typography is
outlined for portable rendering. Roboto is used only as a local authoring source;
no font request, browser dependency or runtime plugin asset is added. The
unmodified source font and its complete SIL OFL licence are kept in `fonts/`.
The generated SVGs retain accessible title and description elements.

## Rebuild

On macOS with Swift/Core Text and `rsvg-convert` available, run from the repository
root:

```sh
sh .wordpress-org/branding/render.sh
```

`build.swift` creates `assets/icon.svg` and the five `branding/banner*.svg` sources.
`render.sh` exports PNGs at their exact native dimensions. `verify.py` checks file
names, PNG signatures/dimensions, WordPress.org file limits, portable SVGs and
actual localized content. `verification.json` records hashes of the rendered
publication files. It does not claim that the remote directory has refreshed.

English is the default. German, French, Italian and Spanish banner text follows
the corresponding bundled plugin descriptions; language-only filename suffixes
let WordPress.org serve them to all matching regional locales. Each localized
file contains translated copy. There are no duplicate English locale assets.

## Publication files

Only `.wordpress-org/assets/` is intended for the top-level SVN `assets/`
directory, beside `trunk/` and `tags/`. Do not put authoring files or fonts there.
The screenshot files have their own capture and verification workflow.

- `icon.svg`, with `icon-128x128.png` and `icon-256x256.png` fallbacks.
- `banner-772x250.png` and `banner-1544x500.png` for English.
- Both banner sizes with `-de`, `-fr`, `-it` and `-es` suffixes.

The current official contract is [How Your Plugin Assets Work](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/),
checked on 18.11.2026. Icons stay below 1 MB, banners below 4 MB. The high-DPI
banner supplements its normal-size counterpart. WordPress.org serves these
files through its own cache; publication and cache refresh remain separate from
local rendering.

`icons-preview.png`, `small-icons-preview.png` and `banners-preview.png` are local
review sheets. Native-size high-DPI images are in `assets/`; review those as well
when changing typography or geometry.

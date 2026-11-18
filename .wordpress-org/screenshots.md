# Directory screenshots

Captured on 18.11.2026 from the exact publication ZIP of Rocketsky Antispam
0.5.7, running in an isolated WordPress 7.1.4 / PHP 8.3.36 installation.
The website title identifies it as **Rocketsky Antispam Demo**. No production
website was changed and no customer messages were used.

The six views correspond to the captions in `readme.txt`: Overview,
Integrations, Filter rules, Review spam, Settings and the WordPress dashboard
widget. English is the default; the `-de` variants use the bundled German
(Switzerland) translation. Other directory locales use the English screenshots.

These are native browser captures, without image retouching. The dashboard
image uses the browser's screenshot clipping function to focus on the widget
and its WordPress context. Standard dashboard controls were used to arrange
the demo user's panels. No plugin, Core or provider markup was changed.

The visible activity comes from nine real local calls to the plugin's guard:
three passes, three reused-token rejections and three honeypot rejections.
They demonstrate the administration, not detection efficacy or provider browser
delivery. No counters or reviewed-result records were manufactured. The default
privacy-conscious mode remains on, with quarantine off. The screenshots therefore
show the real empty quarantine state and available native review destinations.

`screenshots.json` records the package identity, dimensions and file hashes.
Only `assets/screenshot-*.jpg` belongs in the top-level SVN `assets/` folder.
The browser supplied JPEG data; the extension and SVN MIME type reflect that.

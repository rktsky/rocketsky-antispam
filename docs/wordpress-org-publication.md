# WordPress.org publication

Rocketsky Antispam 0.5.7 was approved and published on 18.11.2026 under the
`rocketsky` account. The [directory page](https://wordpress.org/plugins/rocketsky-antispam/)
and its [direct download](https://downloads.wordpress.org/plugin/rocketsky-antispam.0.5.7.zip)
are publicly available.

| SVN revision | Published content |
| --- | --- |
| 3808388 | Initial release: `trunk`, `tags/0.5.7` and directory assets. |
| 3808398 | Focused English/German overview screenshots and matching first caption. Only four paths changed. |

The second revision changes both readmes and the two overview images. All 76
other package files remain byte-identical to the approved submission. The
reviewed submission archive and both publication checks remain separate records.

## Package verification

The final reproducible package contains 77 files, from Git commit
`16418052849065c711005d85400f830ebf28c5d4`. Its SHA-256 is
`e39dbe7527a2700db5f79fc389efed746b8550d63860427f2db2b8d0cd3a33ed`.

The public WordPress.org ZIP returned HTTP 200 and passed CRC and full inventory
checks. All 77 file contents match the final package. WordPress.org rebuilds
the archive: its ZIP SHA-256 is
`fe5d0147dcb9a76610ffe2814120a92353fb2cc033ee74ab302884493148885f`.
Trunk and tag match. All 25 directory assets were checked against source; the
four-path follow-up delta was verified separately.

The exact final package passed original Plugin Check 2.2.0 with **zero errors
and zero warnings**, including its early CLI runtime initialization. Stable
default checks ran without selected/excluded checks, ignored findings or file
exclusions; experimental checks were not enabled. The separate reviewed code
annotation inventory remains documented in the [coverage assessment](coverage-and-exceptions.md).

Native verification used WordPress 7.1.4 and PHP 8.3.36 in an isolated fixture.
The five administration views and dashboard loaded successfully. Core,
Gravity Forms, Contact Form 7 and Plugin Check files remained unchanged.
The fixture blocked mail and outbound WordPress HTTP. Core update/translation
warnings caused by that HTTP veto were preserved in the evidence, not hidden.
This publication check does not replace the documented provider coverage limits.

## Directory presentation

The established `a.` mark supplies the portable SVG icon and both PNG sizes.
Standard and retina banners are available in English, German, French, Italian
and Spanish. Six real administration screenshots have English and German
variants, with ten practical FAQs in the English readme. Sources, licences and
capture records are kept under [`.wordpress-org`](../.wordpress-org/branding/README.md).

The public page was checked in the browser, including the banner, icon,
enlarged overview image and spam-review FAQ. The public API confirms version
0.5.7, six screenshots and ten FAQ questions. The author's page separately
displays WordPress.org's approximately six-hour delay for delivery to websites;
the page and direct ZIP download are already available. No delay was bypassed.

Private, credential-free reports are retained under
`outputs/rocketsky-antispam/publication-2026-11-18/`, with separate initial and
final native/remote evidence. Credentials, cookies and third-party runtime
packages are excluded from this repository and the installable plugin.

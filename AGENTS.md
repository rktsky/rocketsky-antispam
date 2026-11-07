# WP Antispam development

- Apply the user's wordpress-plugin-development skill and global original-package/editorial rules.
- This is independently authored plugin source; WordPress and form providers stay unchanged.
- Namespace `Rktsky\Antispam`; prefix persistent names, handles and public routes with `rktsky_antispam`.
- Logic lives in src/, read-only escaped markup in templates/, assets in assets/.
- Full English WordPress PHPDoc, real @since 0.1.0, tabs and LF. No runtime dependencies.
- Logic, appearance and formatting use separate commits. No credentials, vendor or runtimes in Git.
- Never store IP addresses, fingerprints or user agents. Local rules may process whitelisted native text. Explicit opt-in quarantine stores bounded encrypted contact fields, with retention and native privacy export/erasure; no password, hidden, payment or file fields. Default privacy-conscious mode disables new quarantine capture, country checks and administrator mail. Optional mail contains aggregate counts only. No external spam/IP lookups or analytics.
- Public challenge endpoints are deliberately public and have no authority to change settings or submit forms.
- Document attack limits honestly: a browser-capable bot can request valid challenges. Preserve native provider validation and anti-spam decisions.
- Isolated tests only; suppress all mail. Commercial-provider and real-browser gaps remain explicit release gates.

- Visible publisher/sponsor branding is Rocketsky. `rktsky` remains the GitHub handle and the stable internal prefix, not a public brand abbreviation.
- Use official documented WordPress/provider APIs. Direct database operations, including custom `$wpdb` SQL, schema changes and own-table access, need the user's prior explicit approval of a concretely justified exception when no suitable API meets the requirement. A general development task is not approval. Approved exceptions still use only the native global `$wpdb`, prepared values/identifiers and `dbDelta`; never open separate SQL connections or directly query Core/provider tables.
- Project-specific permission granted by the user on 2026-11-07: prepared `$wpdb` reads/writes, schema setup and cleanup for exactly the six per-site `rktsky_antispam_tokens`, `rktsky_antispam_stats`, `rktsky_antispam_totals`, `rktsky_antispam_quarantine`, `rktsky_antispam_feedback` and `rktsky_antispam_jobs` tables. Core/provider records still require their documented APIs. New tables or other direct accesses need a separate approval.

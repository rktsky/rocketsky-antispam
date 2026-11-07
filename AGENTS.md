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

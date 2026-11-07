# WP Antispam

Eigenständiges Open-Source-Plugin von **rktsky**, GPL-2.0-or-later. Lokaler Spamschutz für WordPress-Kommentare, Contact Form 7, Gravity Forms und WPForms. Keine kostenpflichtigen Funktionen, externen Captchas oder Spamdienste.

**Stand: 0.1.0, Entwicklungskandidat.** Die tatsächlichen Prüfungen und offenen Freigabepunkte stehen in [docs/verification.md](docs/verification.md). Eine Freigabe durch WordPress.org ist damit nicht verbunden.

## Installation und Bedienung

1. Den Plugin-Ordner als `wp-antispam` nach `wp-content/plugins/` installieren und in WordPress aktivieren. PHP 8.2 und WordPress 6.6 sind die technischen Mindestanforderungen; die tatsächlich geprüften Versionen stehen im Prüfnachweis.
2. Unter **Einstellungen → WP Antispam** die gewünschten Integrationen aktivieren. Vorhandene Formulare erhalten die Schutzfelder automatisch. Keine Snippets oder Änderungen im Formular-Editor nötig.
3. Eine gültige Einsendung und einen Fehlerfall auf der eigenen Website prüfen. Formulare benötigen JavaScript und Zugriff auf den lokalen REST-Endpunkt. Bestehende Captchas und andere Dienste der Formularplugins werden durch WP Antispam nicht umkonfiguriert.

Die Integrationen sind standardmässig aktiviert und wirken, sobald der jeweilige Anbieter geladen ist. Kommentare über REST benötigen ebenfalls gültige Schutzfelder; externe Kommentar-Apps und eigene Integrationen müssen berücksichtigt werden. Trackbacks/Pingbacks werden bei aktivierter Kommentar-Integration abgewiesen. Netzwerkweite Aktivierung ist nicht unterstützt; auf Multisite pro Website aktivieren.

## Schutzverfahren und Grenzen

Ein im HTML signierter Formularbezug ermöglicht einen frischen, lokal signierten Token. Ein zufälliger Honeypot-Feldname, strikte Scope-/Zeitprüfung und atomare Einmalverwendung prüfen die Einsendung. Die optionale Mindestzeit ist standardmässig aus, weil sie schnelle Eingaben und Autofill beeinträchtigen kann. Native Berechtigungen, Validierung und Spamprüfungen der Anbieter bleiben zuständig.

WP Antispam liest keine Nachrichteninhalte für eine Klassifizierung. Es erkennt viele einfache Formularbots und verhindert Wiederverwendung gültiger Tokens. Ein Bot, der JavaScript ausführt oder das öffentliche Challenge-Protokoll nachbildet, kann eigene gültige Tokens erhalten. Manueller Spam, kompromittierte Websites und volumetrische Angriffe benötigen weitere Massnahmen. Ein bestandener lokaler Check ist kein Nachweis einer menschlichen Person oder einer zugestellten Nachricht.

## Daten und Betrieb

- Keine Speicherung von IP-Adressen, E-Mail-Adressen, Inhalten, User-Agents oder Browserprofilen durch dieses Plugin. Keine Cookies, Local Storage, Telemetrie oder ausgehenden Anfragen an Spamdienste.
- Der Browser sendet an den eigenen REST-Endpunkt ausschliesslich Formularanbieter, Formular-ID und den öffentlichen signierten Formularbezug. Der Server speichert beim Einlösen einen HMAC-Digest des zufälligen Tokens und dessen Ablaufzeit. Ein Digest ist keine Verschlüsselung von Formulardaten.
- Tokens sind zwei Stunden gültig. Verbrauchte Digests werden nach Ablauf durch die stündliche WP-Cron-Aufgabe in begrenzten Batches entfernt. Verspätete Cron-Ausführung kann die physische Aufbewahrung verlängern; die Gültigkeit verlängert sich dadurch nicht.
- Tageszähler enthalten UTC-Datum, Anbieter, festgelegten Prüfgrund und Anzahl. Standardaufbewahrung: 30 Tage, einstellbar von 1 bis 90 Tagen. Löschung erfolgt ebenfalls per Cron. Die Oberfläche zählt technische Ablehnungen mit und bezeichnet diese deshalb nicht pauschal als erkannten Spam.
- **Statistiken löschen** entfernt nur Zähler und bewahrt die laufende Replay-Sperre. Deaktivieren stoppt Cron und erhält Daten. Deinstallieren entfernt eigene Tabellen und Optionen, sofern die entsprechende Einstellung aktiviert ist.
- WordPress, Hosting, Backups und die Formularanbieter können unabhängig davon personenbezogene Daten speichern oder externe Dienste verwenden. Die native WordPress-Datenschutzerklärung erhält einen anpassbaren Textvorschlag. Die technische Ausgestaltung unterstützt nDSG-/DSGVO-Anforderungen; Rechtsgrundlage, konkrete Information, Anbieter und Betrieb gehören zur Beurteilung der jeweiligen Website.

Unter `docs/` stehen [Architektur und Bedrohungsmodell](docs/security.md), [Integrationsverträge](docs/integrations.md), [Gestaltung](docs/design.md), [Prüfnachweise](docs/verification.md) und [Veröffentlichung](docs/releasing.md).

## Entwicklung

Keine Build-Abhängigkeiten zur Laufzeit. PHP-Klassen in `src/`, escaped Templates in `templates/`, lesbare eigene Assets in `assets/`. Funktionale Änderungen und Gestaltung werden getrennt committed. Drittcode und installierte Originalpakete bleiben unverändert. Entwicklungswerkzeuge und Testinstallationen gehören nicht in das auszuliefernde Plugin.

Offizielle Referenzen: [Plugin Handbook](https://developer.wordpress.org/plugins/), [Security](https://developer.wordpress.org/plugins/security/), [PHPDoc](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/), [Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/). Die Quellen dienen den API- und Standardverträgen; der Plugin-Code wurde eigenständig entwickelt.

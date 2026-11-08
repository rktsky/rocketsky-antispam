# Release and submission process

Rocketsky Antispam 0.5.2 is a WordPress.org submission candidate. Preparing a
package does not submit it, reserve its slug or establish directory approval.
The proposed name is **Rocketsky Antispam**, with slug `rocketsky-antispam`.

Die GitHub-Distribution setzt seit 0.5.0 eine eigene `Update URI`. Damit bietet
WordPress keine fremde Verzeichnisaktualisierung unter einem gleichnamigen
Entwicklungsslug an. Ein GitHub-Updater wird dadurch nicht eingerichtet; Updates
erfolgen manuell. Name und Kandidaten-Slug sind seit 0.5.1 auf Rocketsky Antispam
und `rocketsky-antispam` festgelegt. Vor einer Verzeichniseinreichung ist der
Header für die tatsächliche WordPress.org-Distribution zu entfernen.
Quelle: [offizielle Header-Anforderungen](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/).
Der versionierte Paketbauer entfernt diesen Header aus dem tatsächlichen
WordPress.org-ZIP. Die CI prüft genau dessen entpackten Inhalt. GitHub-Quelle
und GitHub-Paket behalten den Header. Es wird keine Datei nur für den Checker
verändert und kein Befund ausgeblendet.

Der Namenswechsel entfernt den bisherigen Begriff `wp` aus Name, Slug und
Textdomain. Historische Plugin-Check-Befunde gelten für den damals geprüften
Stand und werden nicht als aktueller Report übernommen. Die tatsächliche
Slugverfügbarkeit und der gewöhnliche Verzeichnisreview bleiben vor einer
Einreichung zu prüfen.

Seit 0.5.2 nutzt die Sprachversorgung den dokumentierten Filter
`lang_dir_for_domain` als lokalen Fallback, wenn Core keinen Sprachpaketpfad
findet. Native WordPress-Sprachpakete behalten Vorrang. Der Hook ist ab der
unterstützten Mindestversion WordPress 6.6 verfügbar. Manuelle Sprachladung
und Änderungen am internen WordPress-Sprachregister entfallen.
Quelle: [öffentlicher Sprachpfadfilter](https://developer.wordpress.org/reference/hooks/lang_dir_for_domain/).

## Installierbares Paket bauen

Den geprüften Stand zuerst committen. Der Paketbauer verweigert einen
veränderten Checkout und liest nur versionierte Produktionsdateien aus dem
exakten Git-Commit. Tests, Werkzeuge, Vendor-Pakete, Git-Metadaten und interne
Dokumentation gelangen nicht ins ZIP.

```sh
python3 tools/build-release.py --channel wordpress-org --output ../outputs/rocketsky-antispam/submission-0.5.2
```

Das erzeugt `rocketsky-antispam-0.5.2-wordpress-org.zip`, eine SHA-256-Datei und
ein Manifest mit Quellcommit und Prüfsummen jeder archivierten Datei. Das ZIP
enthält genau einen obersten Ordner `rocketsky-antispam/`. Der Paketbauer prüft
CRC, vollständigen Inhalts-Readback, Versionskonsistenz und reproduzierbare
ZIP-Erzeugung. Abweichende vorhandene Artefakte werden nicht überschrieben.

Eine separate manuelle GitHub-Distribution wird mit `--channel github` in einem
anderen Ausgabeordner gebaut und behält die eigene Update URI. Nur das Paket
mit Kanal `wordpress-org` ist für die Verzeichniseinreichung vorgesehen.

## Das exakte ZIP prüfen

Das Archiv in eine frische, ausdrücklich markierte Testinstallation einspielen.
Mail und externe HTTP-Aufrufe blockieren. Aktivierung, Einstellungen, Kommentare,
Sprachen, verschlüsselte Bestandsdaten, Deaktivierung und beide
Deinstallationsentscheidungen prüfen. Den Netzwerklebenszyklus separat testen.
Niemals über einen Source-Symlink oder in eine Kundeninstallation installieren.

Plugin Check einschliesslich Laufzeitinitialisierung ausführen:

```sh
wp --require=/path/to/wordpress/wp-content/plugins/plugin-check/cli.php plugin check rocketsky-antispam --path=/path/to/wordpress --format=json
```

Den vollständigen Report mit der ZIP-Prüfsumme verknüpfen. Keine Checks oder
Befunde ausschliessen und keine entpackte Datei für die Prüfung verändern.
Die CI baut denselben Kanal, verifiziert Manifest und Archiv und prüft dessen
Inhalt mit strikter Behandlung aller Fehler und Warnungen.

Die vorbereiteten Angaben und der englische Einreichungstext stehen in
[submission.md](submission.md). In `Contributors` gehört nur ein bestätigter
WordPress.org-Benutzername; der GitHub-Handle belegt diese Identität nicht.

Vor einem stabilen Release:

- Offene Punkte in `verification.md` auflösen; insbesondere reale Browserabläufe, Anbieter-/Versionsmatrix, Mehrseitenformulare, Save & Continue, Wiederholung nach Validierungsfehlern und API-/Add-on-Wege.
- WordPress Coding Standards und offizielles Plugin Check ausführen, Befunde korrigieren und den tatsächlich geprüften Commit dokumentieren.
- Quellcode, GPL-Lizenz und Abhängigkeitsinventar prüfen. Zur Laufzeit sind keine fremden Bibliotheken enthalten. Die GPL-Lizenzschrift ist eine unveränderte Lizenzschrift und keine übernommene Programmlogik.
- Alle mitgelieferten Sprachkataloge und die POT prüfen. Keine Geheimnisse, Runtimes, Entwicklungsprogramme oder Testdaten ausliefern.
- Mindestversionen durch reale Matrixläufe belegen. Eine Mindestangabe im Header allein ist kein Kompatibilitätsnachweis. Stable tag, Header, Versionskonstante und Änderungsprotokoll gemeinsam erhöhen.
- GitHub-Repository unter dem autorisierten rktsky-Konto führen. Logik-/Regressionen und Optik-/Assetänderungen getrennt committen. Einen Release erst nach den Prüfungen taggen.
- Verzeichnisnamen/Textdomain/Slug gegen die Vorgaben des Pluginteams prüfen. Der gewünschte sichtbare Name ist Rocketsky Antispam; ein Slug ist bis zur Bestätigung reservierungslos.
- Den Releaseinhalt mit dem Paketbauer aus der festen Liste von Produktionsdateien packen, installieren, aktivieren, deaktivieren und deinstallieren. `.distignore` beschreibt die Entwicklungsausschlüsse zusätzlich. Einzel- und Netzwerkaktivierung, neue Websites, gebatchte Wartung und die getrennten Löschentscheidungen erneut prüfen. Öffentlich ausgelieferter Code bleibt lesbar.
- Beim Wechsel aus der früheren Installation den [Übergangsvertrag](renaming.md) prüfen: alte Kopie deaktivieren, keine Deinstallation ausführen, neue Kopie aktivieren und bestehende Daten nachweisen. Kryptografische Zwecke und Speicherkennungen bleiben stabil.
- Erst mit der passenden Freigabe einreichen. Der neutrale Bewertungslink und die freiwillige Ko-fi-Bitte stehen nur auf den eigenen Pluginseiten, zusammen mit Rocketsky und Speo. Keine Sterne verlangen, Funktionen an Bewertungen koppeln oder globale Erinnerungsmeldungen hinzufügen.

Referenzen: [Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [Readmes](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/), [Plugin Check](https://wordpress.org/plugins/plugin-check/).

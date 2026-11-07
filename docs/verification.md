# Prüfnachweis

Prüfdatum: 7. November 2026. Lokal geprüft ist der finale Produktcode 0.2.0 im Commit `2fe1955` mit Inhaltsregeln, Quarantäne, Sprach-/Länderregeln, Maintenance, Dashboard und Feedback. Der abschliessende sequentielle Gesamtlauf startete auf `114ec9a`; dessen einziger Unterschied zu `2fe1955` ist die CI-Konfiguration. Alle nachfolgend genannten nativen Programme bestanden diese Abschlussrunde ohne weitere Quellcodeänderung. Ein grüner lokaler Lauf wird nicht als bereits ausgeführter GitHub-Actions-Lauf ausgegeben.

## Native WordPress-Prüfungen

Die lokale, ausdrücklich markierte Testinstallation läuft mit WordPress 7.1.4, PHP 8.3.26 und dem unveränderten offiziellen SQLite Database Integration Drop-in. Die tatsächliche Datenbank ist SQLite. Die vom Adapter ausgegebene MySQL-Kompatibilitätsversion 8.0.38 ist kein Nachweis eines lokalen MySQL-Servers. WordPress, Formularanbieter und deren Vendor-Dateien wurden nicht angepasst. Die PHP-Testlaufzeit wurde getrennt unter `/tmp` aus dem Originalquellpaket gebaut. E-Mail und externe WordPress-HTTP-Aufrufe sind in diesem isolierten Fixture gesperrt.

| Programm | Tatsächlich geprüft |
| --- | --- |
| `tests/native.php` | 39 bestandene Assertions: signierte Token, kanonische Kodierung, Anbieter-/Formulargrenzen, manipulierte/abgelaufene/zukünftige Token, fehlende und als Array eingereichte Kontrollen, dynamischer Honeypot, erneutes Einlösen im selben und in getrennten Requests, optionale Mindestzeit, begrenzte Einstellungen, signierter REST-Scope und `no-store`, datensparsame Tabellenschemata, feste Statistikdimensionen, Löschen der Statistik mit erhaltenem Replay-Schutz, Ablaufbereinigung. Auch eine tatsächlich fehlende eigene Token-Tabelle wird geprüft: Die Einsendung scheitert mit dem internen Zustand `storage`, danach wird ausschliesslich die eigene Tabelle wiederhergestellt. |
| `tests/claim-worker.php`, durch `native.php` gestartet | Vier getrennte PHP-Prozesse mit unabhängigen Datenbankverbindungen versuchen denselben Digest einzulösen. Genau ein Prozess erhält die Freigabe. |
| `tests/quarantine.php` | 59 bestandene native Assertions: erzwungener Privacy-Modus, getrennte öffentliche Capture-/privilegierte Leserechte, authentifizierte Verschlüsselung, unterschiedliche Nonces, keine Klartextspalten, UTF-8-/Feldgrenzen, Metadatenallowlist, Saltrotation und Manipulation, Retentionsverkürzung, sofortige Ablaufprüfung und physische Bereinigung, 52 passende E-Mail-Datensätze über feste Export-/Eraserbereiche, voller Speicher und vier Prozesse am letzten freien Slot, beschränkte Listen, endgültige Löschung und tolerierter Ausfall der optionalen Ablage. |
| `tests/policies.php` | 54 bestandene native Assertions: Settings-Serverregeln und Tab-Erhalt, Sprachschätzung, IPv4/IPv6/CIDR und gefälschte Header, unbekannte Quellen, keine WordPress-HTTP-Aufrufe durch die lokale Policy, selektive Spamkommentarlöschung, Dashboard-Berechtigung und UTC-Reihe, abgeleitete Rate, dauerhafter Kommentarzähler und Reset, aggregierte Tagesmail, fehlgeschlagener Versand ohne automatische Wiederholung sowie vier parallele Worker mit exakt einem Mailversuch. Der native Mailer wird durch Testfilter abgefangen. |
| `tests/feedback.php` | 52 bestandene native Assertions: tatsächliche TP-/FP-/FN-/TN-Zuordnung, Pending-Abstention und korrekte Nenner, idempotente und korrigierte Labels, manuelle Referenzen ohne Klartext, Rechte, Quarantäne-/Kommentar-Löschereignisse, Datenschutzstatus einschliesslich Spam und Papierkorb, Eraser vor Core-Anonymisierung, rückrollbare fremde Transaktion, parallele Reviews und Review/Forget-Race, native Disallowed-Key-Entscheidung und Moderator-Korrektur, Ablauf. |
| `tests/gravity-feedback.php` | 130 bestandene Assertions mit originalem Gravity Forms 3.2.2: tatsächlich empfangene WEBFORM-Eingänge, finale Spam-/Aktiventscheidung ohne doppelte Q-Kopie oder automatische menschliche Labels, native Statuskorrekturen, Feld- und vollständige Entry-Edits, keine Vorhersagen für historische Importe, paginierter Datenschutzexport/-eraser von 54 verknüpften Fällen inklusive Spam/Papierkorb, native `created_by`-Identifizierung und Löschereignisse. In der öffentlichen CI wird dieser Test ohne lizenziertes Paket ausdrücklich als übersprungen gemeldet. |
| `tests/pattern-limits.php` | Pathologisches verschachteltes PCRE-Muster mit 14001 Bytes Eingabe: technische Ablehnung als `pattern_limit` in lokal gemessenen 0.000383 Sekunden. Ungültige und zu lange Regeln werden verworfen. Das ist ein einzelner lokaler Grenzfall, kein allgemeiner Performancewert. |
| `tests/native-providers.php` | 16 bestandene Fälle mit originalen Anbietern, unten aufgeführt. |
| `tests/uninstall.php` | Sechs bestandene Assertions: Daten behalten, Daten löschen, eigene Optionen löschen, Cron entfernen und unverändertes Inventar aller WordPress-/Anbietertabellen. Alle fünf eigenen Tabellen einschliesslich Quarantäne, Lebenszeitzähler und Feedback bestanden den abschliessenden Retain-/Delete-Inventarvergleich. |
| `tests/integrations.php` | 68 bestandene Adapter-Assertions mit ausdrücklich benannten Stubs. Diese Tests ergänzen die nativen Läufe und ersetzen sie nicht. |
| `tests/content-rules.php` | 20 bestandene isolierte Policy-Assertions mit ausdrücklich benannten Randbedingungen. |
| `tests/lint.php` | 42 eigene PHP-Dateien ohne Syntaxfehler nach der finalen WordPress-Formatierung. |
| WordPress Coding Standards | PHPCS 3.13.6 / WPCS 3.4.1: keine Fehler und keine Warnungen im geprüften Produktionscode. |

Die 16 nativen Anbieterfälle verwenden Contact Form 7 6.2.4.1, Gravity Forms 3.2.2 und WPForms Lite 2.0.4:

- Contact Form 7: gültige Einsendung, fehlender Nachweis und ausgefüllter Honeypot. Technische Schutzfelder fehlen anschliessend in den verarbeiteten CF7-Formulardaten.
- Gravity Forms: gültige Einsendung, fehlender Nachweis im expliziten `WEBFORM`- und im `API`-Kontext, Save & Continue ohne und mit gültigem Nachweis, zwei Einsendungen im selben PHP-Request, API-Validierung gefolgt vom Versuch, denselben Token einzulösen, gültiger Wechsel zur zweiten Formularseite und abgewiesener Seitenwechsel ohne Nachweis. Die Tests übernehmen die original gerenderten Kontrollen und den öffentlich an das native JavaScript ausgegebenen Versionswert. Die eingebauten GF-Prüfungen bleiben aktiv.
- WPForms Lite: gültige Einsendung, fehlender Nachweis und ausgefüllter Honeypot über den originalen Verarbeitungsablauf mit erhaltenen nativen Schutzkontrollen. Zusätzlich wird ein echter früher Abbruch des modernen nativen Honeypots bei noch leeren normalisierten Feldern geprüft. Der originale `listen()`-/`wpforms_process_after`-Ablauf erzeugt einen verschlüsselten Quarantäneeintrag mit erlaubtem Namen, Text und E-Mail; deklarierte Hidden-/Passwort-/Zahlungs-/Uploadfelder sowie unbekannte Honeypot-Werte fehlen darin. Die synthetische Definition und Einstellungen werden anschliessend wiederhergestellt.

Gravity Forms kann bei mehreren GFAPI-Aufrufen für dasselbe Formular innerhalb eines PHP-Requests die frühere Antwort in eigenen statischen Eigenschaften behalten. Deshalb prüft der Replay-Test die tatsächliche Persistenz und den erneuten Guard-Aufruf: kein zweiter Eintrag und genau eine zusätzliche Replay-Ablehnung. Es wird kein GF-Zustand gepatcht, um einen günstigeren Rückgabewert zu erzeugen.

## Browser und Bedienung

Geprüft wurde mit Firefox 153.0 Headless auf macOS 14.8.3 ARM64. Es handelt sich um einen echten Browser mit Desktop-Viewport-Emulation, nicht um einen Test auf einem iPhone oder Android-Gerät.

- Native Verwaltungsseite bei 320, 390, 768 und 1440 CSS-Pixeln, mit `en_US` und `de_CH`. Kein horizontaler Überlauf im geprüften Ausgangsstand. Einstellungen speichern und zurücksetzen, Checkbox mit Leertaste bedienen und sichtbaren Tastaturfokus prüfen. Statistiklöschung: GET liefert 405, POST mit ungültigem Nonce 403 und berechtigter POST mit gültigem Nonce wird ausgeführt.
- Contact Form 7: gültige Browser-Einsendung und abgewiesener Honeypot mit verständlicher Rückmeldung. Der synthetische Versand wurde mit der originalen CF7-Einstellung übersprungen.
- Gravity Forms: Single-Formular über nativen AJAX-Weg sowie Mehrseitenformular mit Seitenwechsel, neuem Token und erfolgreicher letzter Einsendung.
- WPForms Lite: gültige Einsendung und sichtbare Ablehnung eines ausgefüllten Honeypots.
- WPForms-AJAX-Wiederholungsmatrix: Mindestzeit 0 beziehungsweise 2 Sekunden, jeweils kombiniert mit Honeypot-Fehler, serverseitigem Pflichtfeldfehler, Challenge-HTTP-503 oder unterbrochenem Challenge-Netzwerkabruf. Alle acht Fälle konnten danach im selben Formular erfolgreich erneut eingereicht werden. Die Wiederholung löste jeweils genau eine neue Challenge und eine Einsendung aus; keine JavaScript-Fehler wurden beobachtet. Die Testkonfiguration wurde danach wiederhergestellt.

Die erweiterte Oberfläche wurde ebenfalls nativ geprüft: Settings über mehrere Register bleiben erhalten; der Privacy-Modus erzwingt die deaktivierten optionalen Prozesse auch bei manipulierten Formularwerten. Die Quarantäneliste enthält nur Metadaten, die Detailseite zeigt eingereichtes Markup als Text. Lesen, Löschen, Nonce-Fehler, fehlender Eintrag nach Löschung und alle vier Viewports wurden geprüft. Auch das Dashboard bleibt in diesen Ansichten innerhalb der verfügbaren Breite.

Die nachträgliche Qualitätsansicht wurde mit echten Quarantäne- und manuellen Fallreferenzen geprüft: Spam/legitim umklassifizieren, Duplikate, abgeleitete Raten und die beiden Nonce-/HTTP-Methodengrenzen. Gezieltes Entfernen einer manuellen Referenz und nicht zurückbleibende Pending-Zeilen wurden geprüft. Ein authentischer WordPress-Kommentar-Reply über die native UI funktioniert ohne Frontend-Proof. Ein ungültiger Reply-Nonce erhält 403, ein ausgeloggter Ajax-Aufruf 400. Ein öffentlicher Kommentar-POST als eingeloggter Administrator bleibt ohne Proof abgewiesen, auch bei gefälschtem `action=replyto-comment` und gültigem Ajax-Nonce. Der Reply-Kontext gibt keine pauschale Admin-Ausnahme frei. Die eigenen Testkommentare wurden gelöscht.

Die lokalen Bildschirmaufnahmen und der maschinenlesbare Wiederholungsnachweis liegen im Arbeitsbereich unter `outputs/wp-antispam/` (`admin-desktop.png`, `admin-mobile.png`, `browser-retry-results.json`, `feedback-browser-results.json`, `core-reply-browser-results.json`).

## Offizielles Plugin Check

Plugin Check 2.2.0 wurde mit `cli.php` vor dem WordPress-Start und damit einschliesslich Laufzeitprüfungen auf dem tatsächlichen Distribution-Verzeichnis ausgeführt. Entwicklungsprogramme, Tests, Composer-Abhängigkeiten und interne Dokumentation wurden über `.distignore` ausgeschlossen.

Ergebnis auf dem frisch aufgebauten finalen Distributionstand `2fe1955` einschliesslich Feedback und Quarantäne: keine Fehler, vier Warnungen. Der offizielle Check wurde nach dem bestandenen nativen Gesamtlauf erneut mit seiner Laufzeitinitialisierung ausgeführt. Drei betreffen den gewünschten Namen und vorläufigen Slug mit `wp`. Die originale Prüfliste kennzeichnet `wp` ausdrücklich als erlaubt, gibt aber eine Warnung aus; ihre generische Formulierung ist kein belegtes Namensverbot. Eine Warnung betrifft `load_plugin_textdomain`, das die mitgelieferten Übersetzungen bei GitHub-Installationen verfügbar macht. Die Warnungen bleiben sichtbar. Details und der erforderliche Verzeichnisentscheid stehen in [releasing.md](releasing.md).

Die während eines Aufrufs gegen einen zweiten Distribution-Pfad bei gleichzeitig aktivem Quellcheckout sichtbare Übersetzungs-Frühstartmeldung ist gesondert zu prüfen. Sie wird nicht als bewiesener Fehler des gewöhnlichen Browserstarts dargestellt und nicht durch Änderung von WordPress unterdrückt.

## Wiederholbare Prüfungen

Die nativen Tests starten nur als CLI-Programm mit `WP_ANTISPAM_ALLOW_TESTS=1`, einem `WP_ANTISPAM_WP_ROOT`, dem exakten Marker `.rktsky-antispam-fixture` mit Inhalt `rktsky-antispam-disposable`, der Konstante `WP_ANTISPAM_TEST_FIXTURE=true` und `WP_ENVIRONMENT_TYPE=local`. Sie schreiben und löschen bewusst synthetische Daten und dürfen nicht auf einer produktiven Installation ausgeführt werden.

Die GitHub-Workflowdatei wurde als YAML gelesen; die siebzehn enthaltenen Shellskripte bestehen die Syntaxprüfung und alle referenzierten PHP-Testdateien existieren. Die CI verwendet PHP 8.2, 8.3, 8.4 und 8.5 mit aktuellem WordPress und echtem MySQL 8.0. Das aktuelle Contact Form 7 setzt PHP 8.3 voraus und wird deshalb nur in den 8.3-/8.4-/8.5-Jobs installiert; WPForms Lite wird auch mit PHP 8.2 geprüft. WordPress 6.6 wird getrennt für die Plugin-/Coreverträge geprüft, ohne aktuelle Formularanbieter mit möglicherweise höheren Mindestversionen zu aktivieren. Gravity Forms ist keine öffentlich herunterladbare CI-Abhängigkeit und wird ohne separat verfügbares lizenziertes Originalpaket nicht als in GitHub getestet ausgewiesen.

Der Workflow läuft bei Push, Pull Request, manuell und montags um 05:23 UTC. Er lädt die aktuellen originalen CF7- und WPForms-Lite-Pakete. WPCS und offizielles Plugin Check prüfen zusätzlich den Quell- beziehungsweise Distributionstand. Warnungen bleiben beim Plugin Check als Annotationen sichtbar; Fehler lassen den Schritt scheitern. Ein zeitgesteuerter Test ist kein automatisches Update einer Website.

## Ausgeführter GitHub-Abschlusslauf

[GitHub Actions, Lauf 42387775511](https://github.com/rktsky/wp-antispam/actions/runs/42387775511) prüfte Commit `6fb2ebec167256f3f0936aa44811d692f6c7188c`. Alle sieben Jobs bestanden: WordPress Coding Standards und JavaScript-Syntax, offizielles Plugin Check sowie fünf native MySQL-Jobs. Getestet wurden PHP 8.2, 8.3, 8.4 und 8.5 mit WordPress 7.1.4 und zusätzlich PHP 8.2 mit WordPress 6.6. Der 8.5-Job bestätigt PHP 8.5.12 und echten MySQL 8.0.46 ohne SQLite-Drop-in.

Die verfügbaren nativen Programme für Token, Quarantäne, Maintenance, Feedback, Datenschutz, Parallelität und Deinstallation bestanden auf der Matrix. Contact Form 7 wurde mit PHP 8.3–8.5, WPForms Lite auch mit PHP 8.2 nativ geprüft. Der lizenzpflichtige Gravity-Forms-Test meldet in der öffentlichen CI ausdrücklich SKIP; seine 130 nativen Assertions wurden separat lokal mit dem unveränderten Originalpaket bestanden. Plugin Check bestand mit den vier oben dokumentierten Hinweisen. Eine Bestätigung durch das WordPress-Pluginteam wird daraus nicht abgeleitet.

Der frühere Lauf auf dem Baseline-Commit scheiterte nur beim Versuch, das aktuelle CF7 unter PHP 8.2 zu installieren. Der getrennte aktuelle Lauf bestätigt die korrigierte Provider-Matrix und sämtliche Erweiterungen. Nachfolgende reine Dokumentationsänderungen wiederholen die Prüfungen nicht; Produkt- und Laufzeitdateien bleiben identisch zum bestandenen Commit.

## Offene Abnahmen

- WPForms Pro, weitere Provider-Versionen und Add-ons sind nicht geprüft. Die Integrationen müssen nach relevanten Anbieterupdates erneut ausgeführt werden.
- Safari/iOS, Chrome/Android, Windows/Edge, echte Touchgeräte und assistive Technologien wurden nicht praktisch geprüft. Eine Desktop-Emulation ist kein entsprechender Gerätenachweis.
- Verfügbarkeit des endgültigen Verzeichnis-Slugs und den gewöhnlichen Verzeichnisreview vor Einreichung abschliessen. Die WP-Warnung ist kein festgestelltes Namensverbot. Ein fehlerfreier automatisierter Check ist keine zugesagte Annahme im WordPress-Verzeichnis.
- Es wurde kein externer Penetrationstest und keine rechtliche Zertifizierung durchgeführt. Das dokumentierte technische Datenmodell ermöglicht keine pauschale Zusage zur gesamten Website.

Quellen für die Prüfmethode: [WordPress Plugin Check](https://wordpress.org/plugins/plugin-check/), [offizielle Plugin-Check-Action](https://github.com/WordPress/plugin-check-action), [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards), [GFAPI-Formulareinsendung](https://docs.gravityforms.com/submitting-forms-with-the-gfapi/).

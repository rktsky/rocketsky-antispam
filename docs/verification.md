# Prüfnachweis

Prüfdatum: 7. November 2026. Dieses Dokument enthält getrennte Nachweise für die ursprüngliche Abschlussrunde 0.2.0, die Erweiterung 0.3.0 und die lokalen Divi-Originaltests für 0.3.1. Die Baseline 0.2.0 wurde im Commit `2fe1955` geprüft; ihr sequentieller Gesamtlauf startete auf `114ec9a`, dessen einziger Unterschied die CI-Konfiguration ist. Die weiter unten verlinkten GitHub-Läufe belegen jeweils ihren angegebenen Commit. Die anschliessende lokale Divi-Abnahme wird als eigener Nachweis ausgewiesen.

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


## Erweiterung 0.3.0


Der abschliessende [GitHub-Lauf 42407222571](https://github.com/rktsky/wp-antispam/actions/runs/42407222571) prüfte Commit `1938c38185cc5d18d8f4f2087b7737ac22f91a7a` am 7. November 2026. Alle 14 Jobs bestanden: Standards, fünf native Basisjobs, zwei WooCommerce-Jobs, zwei zusätzliche Formularanbieter-Jobs, zwei Multisite-Jobs, Browserprotokoll und offizielles Plugin Check. Die Datenbankjobs verwendeten echten MySQL 8.0.46. Die Matrix umfasst PHP 8.2–8.5 mit aktueller WordPress-Version und zusätzlich WordPress 6.6; Multisite wurde gesondert mit WordPress 6.6/PHP 8.2 und WordPress 7.1.4/PHP 8.5 geprüft.

Plugin Check 2.2.0: keine Fehler und vier bekannte Hinweise. Drei Hinweise betreffen den gewünschten `WP`-Namen/Slug, einer die lokale Sprachdatei-Ladung für GitHub-Installationen. Die originalen Gravity-Forms-Lizenzpakete stehen der öffentlichen CI nicht zur Verfügung; diese Fälle werden dort ausdrücklich übersprungen und bleiben durch die tatsächliche lokale Prüfung mit Gravity Forms 3.2.2 belegt.


Die Anbieter sind jetzt intern getrennte Module. Die Auslieferung enthält keinen kommerziellen Anbieter und keine Testlaufzeit. Neue Integrationen sind nach einem Update zunächst ausgeschaltet. WordPress, Originalplugins und deren Abhängigkeiten bleiben unverändert.

Zusätzliche isolierte Prüfungen gegen WordPress 7.1.4/PHP 8.3.26:

| Prüfung | Nachweis |
| --- | --- |
| WordPress-Registrierung | 38 Assertions gegen `register_new_user`, native Fehler, Honeypot, Scopewechsel, Replay, technische Prüfungen ohne Kontofeldaufbewahrung, API-Abgrenzung und Anbietergrenzen. |
| Formidable 7.0, Fluent Forms 6.2.17, WS Form Lite 1.12.19 | 30 Fälle über die echten Originalengines: Persistenzgrenzen, Ablehnung, Replay/frischer Retry, native Fehler und ausgewählte verschlüsselte Felder. Zusätzlich 25 Adapterverträge. |
| Elementor Free 4.3.2/Pro 4.3.0 | 23 native Assertions, sieben echte Browserabläufe und gezielte Ausfall-/Retry-Prüfungen nach der gemeinsamen Scriptkorrektur. Warmcache und entfernte Schutzfelder sind enthalten. |
| Divi Theme und Standalone Builder 4.27.9 | Je 28 native Assertions und 18 echte Browserfälle gegen die unveränderten Originalpakete. Die nachträgliche Abnahme für 0.3.1 ist unten beschrieben. |
| Divi Theme 5.19.0 | 41 native HTTP-Fälle und 32 echte Browserfälle mit nativen D5-, Legacy- und original konvertierten Formularen; Einzelheiten unten. |
| WooCommerce 11.2.2 | 33 native Fälle, 16 echte Browser-Retryfälle, zusätzlich Order-Pay und My-Account-Registrierung. Konten, Bestellungen und Gatewayaufrufe bleiben bei eigener Ablehnung aus. Native Fehler nach bestandener Prüfung können dem WooCommerce-Lebenszyklus entsprechend bereits angelegte Entwürfe/Konten betreffen. |
| Gemeinsames Browserprotokoll | Zehn Firefox-Prüfungen mit vollständig abgefangenem synthetischem Transport: erste Einsendung, frischer Retry, unveränderte Trapwerte, Host-Rebuild, unsichere Origin, Ausfall und gezielte Wiederholung ohne automatische Endlosschleife. |
| Pingbacks/Trackbacks | 22 echte Core-Protokollfälle: frühe Sperre ohne Quellabruf, ausdrückliche Freigabe, Moderation, native Umgebungsgrenzen, Query-URLs, Methodenwechsel, einmalige Quell-/Zielbindung und Schutz gegen Typ-Spoofing. |
| Multisite | 94 native Assertions über 28 tatsächlich erzeugte Websites: Netzwerkaktivierung, begrenzte Wartung, neue/gelöschte Sites, anonyme Schema-Migration, getrennte Daten/Schlüssel/Einstellungen/Berechtigungen sowie Deaktivierung und Aufbewahrungs-/Löschpfade. Site-Auswahl und Fortschritt verwenden ausschliesslich `get_sites`, Site-Metadaten und dokumentierte Core-APIs. |
| Verspätet geladene Themes | Vier native Hookprüfungen: kein Modul bei fehlendem Theme, spätere Erkennung und genau eine Registrierung trotz mehrfacher Lifecycle-Hooks. |

WS Form wurde mit echtem `form_reload()` geprüft: der native Canvas ersetzt die Formularinhalte, danach stehen genau ein Schutzcontainer und ein frischer Token bereit. Eine native Fehlermeldung und anschliessende erfolgreiche Wiederholung wurden tatsächlich übermittelt. `form_hide` muss ein boolesches `false` sein; der String `off` versteckt im Originalclient das Formular. Fluent Forms besitzt eine native Submitdrossel, deren Ablauf beim Wiederholungstest abgewartet wurde.

Für WooCommerce wurden Browserabläufe mit Mindestzeit 0 und 2 Sekunden, ausgefülltem/entferntem Honeypot sowie Challenge-503/Netzabbruch geprüft. Die Wiederholung verwendete jeweils einen neuen Nachweis. Externe proprietäre Wallet-/Gatewaytransporte bleiben gesonderte Abnahmefälle. Die lokale Datenbank war SQLite mit originalem Integration-Drop-in; MySQL-Kompatibilitätsangaben des Drop-ins sind kein MySQL-Nachweis.

Die Oberfläche wurde nach der Erweiterung gesamthaft geprüft: fünf Sprachen, Übersicht/Regeln/Quarantäne, jeweils 320/390/768/1440 CSS-Pixel, Tastaturfokus, reduzierte Bewegung, erzwungene Farben und beschriftete Felder. Rocketsky- und Speo-Ziele, neue Tabs und `rel`-Werte sind geprüft; sämtliche Grafiken laden lokal. Diese Desktop-Browseremulation ersetzt keine physische Geräte-/Safari-Abnahme.

Weitere Details: [Formularverträge](integrations-extra-forms.md), [Elementor-/Divi-Nachweis](../tests/provider-elementor-divi.md) und [Sicherheitsmodell](security.md). Die CI enthält separate Originalanbieter-/WooCommerce-/Multisite-Jobs mit MySQL sowie das isolierte Browserprotokoll. Der oben verlinkte vollständige Workflowlauf belegt den genannten 0.3.0-Stand. Die nachfolgenden Divi-Produktänderungen haben einen eigenen lokalen Nachweis.


## Divi-Originaltests für 0.3.1

Die lokale 0.3.1-Abschlussrunde bestand zusätzlich zu den Divi-Originaltests: 54 Core-/Token-/Settings-Assertions, 38 Registrierungs-, 59 Quarantäne-, 54 Policy-, 52 Feedback-, 130 native Gravity-Forms- und 16 native Anbieterprüfungen. Der Pattern-Grenzfall bestand ebenfalls. Hinzu kommen 69 stubbasierte Integrations- und 20 Inhaltsregelprüfungen, vier Theme-Lifecycle-Prüfungen und 22 echte Pingback-/Trackback-Protokollfälle. Die getrennten Browser-Bridge-Verträge bestanden zwölf Fälle. Diese Zahlen sind lokale Nachweise und werden nicht dem älteren GitHub-Lauf zugerechnet.

Plugin Check 2.2.0 wurde gegen die 0.3.1-Distribution mit Laufzeitinitialisierung erneut ausgeführt: keine Fehler und die vier bereits dokumentierten Hinweise zu `WP` im Namen/Slug beziehungsweise lokaler Sprachdatei-Ladung. Daraus wird keine Annahme durch das WordPress-Pluginteam abgeleitet.

Die unveränderten Originalpakete wurden in getrennten, markierten lokalen WordPress-7.1.4-Installationen mit PHP 8.3.26 und SQLite Database Integration 3.1.3 ausgeführt. Vor der Abnahme wurden die erkannten Umgehungen über REST-Inhaltsausgabe und ungewöhnliche native Ziel-IDs geschlossen. Die finale Schutzgrenze liegt an `wp_loaded` Priorität 100 und prüft den rohen POST vor nativer Frontend-, REST- oder AJAX-Verarbeitung. Ein `_method=GET` umgeht sie nicht. Die jetzigen Tests ersetzen die frühere Einschränkung auf Adapterverträge und abgefangene Frontend-Transporte.

| Originalpaket | Native Verarbeitung | Echter Browser |
| --- | --- | --- |
| Divi Theme 4.27.9 | 28 bestandene Assertions über 21 HTTP-Szenarien | 18 bestandene Szenarien in Firefox 153.0 Headless, 1280×900 CSS-Pixel |
| Divi Builder 4.27.9 als eigenständiges Plugin | 28 bestandene Assertions über 21 HTTP-Szenarien | 18 bestandene Szenarien in Firefox 153.0 Headless, 1280×900 CSS-Pixel |
| Divi Theme 5.19.0 | 41 bestandene HTTP-Fälle mit nativen D5-Blöcken, Original-Legacyrenderer und originaler D4-zu-D5-Konvertierung | 32 bestandene Szenarien in Firefox 157.0 Headless auf macOS ARM64 |

Die D4-Abnahmen umfassen gültige und fehlende Nachweise, Honeypot, Scope-/Typfehler, Replay und frischen Retry, native Pflichtfeld-/E-Mail-/Nonce-Fehler, mehrere Formulare und Ziele, vollständige POSTs, unabhängige POSTs, REST-Methodenwechsel, Custom-CSS-ID, ungültige Suffixe, Mindestzeit und ausgeschlossene Inhaltsaufbewahrung. Browserfälle ergänzen warm gecachte Ausgabe, nativen Honeypot, Challenge-503/Netzabbruch, nativen AJAX-Ausfall, Doppelklick und deaktiviertes JavaScript. Ein ungültiger nativer D4-Nonce wurde nach der von Divi verlangten vollständigen Seitenaktualisierung erfolgreich erneut eingereicht. Nach dem nativen Formularersatz waren neue Nonce und Proof bereits vorhanden, aber die originale direkte Submit-Bindung wurde erst beim Reload wieder initialisiert. Dasselbe Verhalten wurde mit deaktiviertem Adapter beobachtet. Bei einem nativen AJAX-Netzausfall blieb Divis Nachrichtenbereich leer; geprüft sind null Mailversuche und die anschliessende Wiederholung nach manuellem Reload.

D5 wurde für native, Legacy- und original migrierte Formulare mit fehlendem/gefülltem/gültigem Nachweis, Replay, frischem Retry, nativen Pflichtfeldern und REST-Ausgabe geprüft. Weitere Fälle betreffen den originalen Rechencaptcha, mehrere unabhängige Formulare, geteilte Ziele, originale Koordination bei doppelter UUID, ungültigen nativen Nonce und Mindestzeit. Ungewöhnliche sowie überlange Ziel-IDs werden über Frontend und REST abgewiesen, auch mit ansonsten gültigem Proof. Eine echte private WordPress-Seite wurde als manipulierte Reload-Metadatenreferenz eingesetzt: ihr Permalink und Slug bleiben verborgen, während der öffentliche Seitenbezug den korrekten Reload-Link erzeugt.

Die D5-Browsermatrix prüfte Mindestzeit null beziehungsweise zwei Sekunden für alle drei Formulartypen, jeweils mit Honeypot, Pflichtfeldfehler, Challenge-503 und Challenge-Netzabbruch. Die korrigierte Wiederholung erhielt jeweils genau eine neue Challenge und führte genau eine native Einsendung aus. Ergänzend bestanden Custom-CSS-ID, identische gecachte Originalausgabe in unabhängigen Browserkontexten mit unterschiedlichen frischen Tokens, zwei unabhängig eingereichte Formulare, der lokale Rechencaptcha, ein erfolgreicher vollständiger POST und die sichere Ablehnung ohne JavaScript. In sämtlichen drei Browserabnahmen wurden keine JavaScriptfehler beobachtet. Andere Browser und physische Geräte sind damit nicht geprüft.

Private Testbeobachter zählten die originale Kontakt-Submit-Aktion und `wp_mail`-Aufrufe. Vom Guard abgewiesene Übermittlungen erzeugten keinen dieser Aufrufe; erfolgreiche synthetische Übermittlungen genau einen originalen Mailversuch. Der tatsächliche Versand blieb gesperrt. Alle externen WordPress-HTTP-Aufrufe und alle Browseranfragen ausserhalb der jeweiligen lokalen Origin waren blockiert. Weder Zustellung noch externe Captcha-Dienste oder zusätzliche Drittanbieteraktionen werden daraus als geprüft abgeleitet.

Die bereitgestellten ZIPs und entpackten Originale wurden über Dateilängen und CRCs verglichen: 1575 unveränderte Dateien für das D4-Theme, 1498 für den D4-Builder und 3443 für D5. Keine Originaldatei wurde angepasst. Die kommerziellen Pakete bleiben private Testeingaben und werden nicht im Repository oder der öffentlichen CI verteilt.

Die reproduzierbaren Programme sind `tests/native-divi4.php`, `tests/native-divi5.php`, `tests/browser-divi4.mjs` und `tests/browser-divi5.mjs`. Die eigenen versionierten Beobachter liegen unter `tests/fixtures/`; die jeweiligen `prepare-divi*.php observer`-Aufrufe installieren sie ausschliesslich im markierten lokalen Fixture. Der D5-Nachweis wurde nach dem Wechsel auf diesen versionierten Beobachter erneut nativ ausgeführt. Die private Logdatei wird über `RKTSKY_DIVI5_FIXTURE_LOG` konfiguriert und von beiden Runnern gelesen. Die lokalen JSON-Artefakte liegen im Arbeitsbereich und privaten Fixture-Verzeichnis; die D5-Ergebnisse sind unter `outputs/wp-antispam/` als `native-divi5-results.json`, `browser-divi5-results.json`, `browser-divi5-extended-results.json` und `divi5-original-integrity.json` abgelegt. Zusätzlich bestehen 61 ausdrücklich stubbasierte Divi-Adapterassertions; diese bleiben von den Originaltests getrennt. Ausführliche Quellen und Vertragsgrenzen: [Elementor-/Divi-Nachweis](../tests/provider-elementor-divi.md).

## GitHub-Abschluss 0.3.1

Der [vollständige Workflowlauf 42412221642](https://github.com/rktsky/wp-antispam/actions/runs/42412221642) auf Commit `e5e3417d63be0503e703f2146158a68b4512f00f` bestand im ersten Versuch alle 14 Jobs. Der geprüfte Stand enthält die Divi-Korrekturen, die überarbeiteten Integrationszustände und die Backendgestaltung mit den vollständigen Sprachkatalogen. Logik, reine Testformatierung und Optik sind getrennt committet.

Die fünf nativen Basisjobs verwenden PHP 8.2.35, 8.3.36, 8.4.27 und 8.5.12 mit WordPress 7.1.4 sowie WordPress 6.6 mit PHP 8.2.35. Beide WooCommerce-Jobs bestehen je 33 Originalfälle, beide zusätzlichen Formularanbieter-Jobs je 30 und beide Multisite-Jobs je 94 Assertions. Der Browser-Bridge-Job besteht 12 Prüfungen. Alle elf Datenbankjobs verwenden nach den tatsächlichen Containerlogs MySQL 8.0.46. Die 61 Divi-Adapterverträge laufen in der nativen Matrix; die oben dokumentierten kommerziellen Originaltests bleiben getrennte lokale Nachweise. Der lizenzabhängige Gravity-Forms-Test ist in der öffentlichen CI ausdrücklich übersprungen.

Die Syntaxprüfung besteht für 75 eigene PHP-Dateien; WordPress Coding Standards melden keine Befunde im produktiven Quellcode. Plugin Check 2.2.0 mit Laufzeitinitialisierung meldet keine Fehler und vier bekannte sichtbare Warnungen: drei zum gewünschten Namen beziehungsweise Slug mit `wp`, eine zur lokalen Sprachdateiladung. Die Verzeichnisfreigabe wird daraus nicht abgeleitet.

Das separat erstellte Installationspaket `wp-antispam-0.3.1.zip` enthält 60 Produktionsdateien und stimmt dateiweise mit diesem Quellstand überein. SHA-256: `b02ecaa44f6f78fcd23e147ac7994dccaf953de21b8544bee801ed9620813ff8`. Originalanbieter, Tests und Entwicklungsabhängigkeiten sind nicht Teil der Auslieferung. Die anschliessende Aktualisierung dieses Prüfnachweises ändert keinen produktiven Code.

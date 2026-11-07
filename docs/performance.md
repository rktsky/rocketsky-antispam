# Lokaler Performance-Nachweis

**Aktueller Stand 0.2.0**, gemessen am 7. November 2026 um 15:38:42 UTC (16:38:42 MEZ) in der isolierten WordPress-Testinstallation. Das Plugin blieb aktiv; Einstellungen wurden weder geändert noch zurückgesetzt. Mailversand und ausgehende WordPress-HTTP-Aufrufe blieben durch die Fixture blockiert.

## Bedingungen und Verfahren

- WordPress 7.1.4, PHP 8.3.26 CLI, SQLite Database Integration 3.1.3 mit Drop-in-Kennung 1.8.0 (`WP_SQLite_DB`, PDO-SQLite). Kein MySQL-/MariaDB-Messlauf.
- Contact Form 7 6.2.4.1, Gravity Forms 3.2.2 und WPForms Lite 2.0.4 waren geladen. Der Benchmark rief ihre Einsendungsprozesse nicht auf.
- Die vorhandene Konfiguration entsprach dem datensparsamen Standardpfad: Mindestzeit 0, keine Schlüsselwörter, Regex-, BBCode-, Sprach- oder Länderregeln, Quarantäne ausgeschaltet. Diese Bedingungen wurden vorab geprüft. CLI-OPcache war ausgeschaltet.
- `Guard` erhielt dieselben aktuellen Dienste wie beim Pluginstart, einschliesslich `ContentRules`, `Language` und `Country`. Die Prüfung verwendete den ausschliesslich synthetischen CF7-Formularbezug `9912345` und ein festes synthetisches Textfeld. Es gab keine Besucherdaten, echten Kommentare oder Formulareinträge.
- `Token::issue()` wurde mit 1'000 neuen Zufallstokens gemessen, `Token::verify()` danach 1'000-mal mit einem gültigen Token. `Guard::check()` prüfte 50 unterschiedliche gültige Tokens sowie 50 Einsendungen ohne Token.
- `hrtime(true)` erfasste jede Operation separat. Das 95. Perzentil verwendet den aufgerundeten Rang. Queryzahlen sind Differenzen von `$wpdb->num_queries`, keine Zählung interner SQLite-Adapteroperationen. WordPress-Bootstrap, Tokenvorbereitung und Aufräumen liegen ausserhalb der Guard-Einzelzeiten.

## Gemessene Funktionspfade

| Pfad | Operationen | Median | 95. Perzentil | WordPress-SQL-Aufrufe je Operation |
| --- | ---: | ---: | ---: | ---: |
| Token ausgeben | 1'000 | 6,334 µs | 6,583 µs | 0 |
| Token verifizieren | 1'000 | 9,334 µs | 10,041 µs | 0 |
| Guard akzeptiert gültigen CF7-Token | 50 | 0,604 ms | 1,094 ms | 2 |
| Guard weist fehlenden CF7-Token ab | 50 | 0,372 ms | 0,421 ms | 1 |

Der akzeptierte Pfad umfasst die aktuelle Inhaltsprüfung mit deaktivierten Regeln, die atomare Einmalverwendung und den anonymen Tageszähler. Die Ablehnung ohne Token schreibt nur den Tageszähler. Alle 50 gültigen Prüfungen wurden akzeptiert; alle 50 Prüfungen ohne Token lieferten `missing`.

Anschliessend wurden genau die 50 selbst erzeugten Replay-Digests über ihre vollständigen Werte entfernt. Die eigenen Tageszähler-Zuwächse von je 50 für `accepted` und `missing` wurden nach Gegenprüfung abgezogen und mit dem vorherigen Zustand verglichen. Vorhandene QA-Zeilen, Quarantäne- und Feedbackdaten blieben erhalten. Es entstanden keine Quarantäne- oder Feedbackobjekte, weil die Messung den Guard direkt aufrief.

## Assetumfang und tatsächliche Einbindung

Dateigrössen beim aktuellen Messlauf; Gzip ist lokale Kompression mit Stufe 9 und festem Zeitstempel, keine gemessene HTTP-Transfergrösse.

| Datei | Unkomprimiert | Gzip |
| --- | ---: | ---: |
| `assets/protection.js` | 5'428 Bytes | 2'171 Bytes |
| `assets/admin.js` | 1'066 Bytes | 521 Bytes |
| `assets/admin.css` | 22'899 Bytes | 4'371 Bytes |

Lesende HTTP-Abrufe gegen den lokalen Testserver bestätigten die tatsächliche Einbindung mit Pluginversion 0.2.0:

| Route | Status | Schutzcontainer | WP-Antispam-Scripts | WP-Antispam-Styles |
| --- | ---: | ---: | ---: | ---: |
| Startseite ohne Formular, `/` | 200 | 0 | 0 | 0 |
| Formular-Fixture, `/?page_id=5` | 200 | 4 | 1 | 0 |

Das Schutzscript erschien einmal für vier geschützte Formulare. Verwaltungsassets erschienen auf keiner öffentlichen Route. Nachgelagerte Browseranfragen an den Challenge-Endpunkt sind in dieser HTML-Inventur nicht enthalten.

## Frühere Baseline und Grenzen

Die frühere 0.1.0-Baseline verwendete denselben grundsätzlichen Ablauf vor Inhaltsregeln, Quarantäne und Feedback. Damals betrugen die Mediane für Tokenausgabe/-prüfung 6,375/9,458 µs und für den Kommentar-Guard mit/ohne Token 0,596/0,382 ms. Der aktuelle Messlauf benutzt CF7 und die vollständige heutige Guard-Verkabelung. Daraus wird kein prozentualer Geschwindigkeitsgewinn oder -verlust abgeleitet.

Die neuen Rohresultate liegen während der Entwicklung unter `/tmp/rktsky-antispam-runtime/performance-result-02.json` und `performance-http-assets-02.json`; das Messscript liegt dort als `performance-02.php` und gehört nicht zum Release. Die frühere Baseline bleibt in `performance-result.json`, `performance-http-assets.json` und `performance.php` nachvollziehbar. Eine Wiederholung benötigt eine ausdrücklich freigegebene disposable Fixture, dieselben Vorbedingungen und neu erzeugte Tokens.

Diese Messung belegt die begrenzten Funktionskosten des aktuellen Standardpfads in einem bereits gestarteten CLI-Prozess. Aktivierte Inhaltsregeln, Verschlüsselung, Quarantänezugriffe, Feedbackschreibvorgänge und Administratorabfragen sind damit nicht zeitlich vermessen. Sie misst weder den gesamten anonymen Seitenaufruf noch PHP-FPM, MySQL/MariaDB, Spitzenlast, Mobilgeräte, öffentliche Serverlaufzeiten, Lighthouse oder den Speed Index. Unterschiedliche Seiteninhalte erlauben keinen Plugin-Ladezeitvergleich anhand der beiden HTTP-Antworten. Eine Erkennungsquote oder Belastbarkeit unter Angriff folgt aus diesen Messwerten ebenfalls nicht.

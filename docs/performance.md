# Lokaler Performance-Nachweis

Gemessen am 7. November 2026, 15:26 Uhr MEZ, in der ausdrücklich isolierten WordPress-Testinstallation. Das Plugin wurde für diese Messung weder deaktiviert noch umkonfiguriert. Mailversand und ausgehende WordPress-HTTP-Aufrufe blieben durch die bestehende Fixture blockiert.

## Bedingungen und Verfahren

- WordPress 7.1.4, PHP 8.3.26 CLI, SQLite Database Integration 1.8.0 (`WP_SQLite_DB`, PDO-SQLite); kein MySQL-/MariaDB-Messlauf.
- Contact Form 7 6.2.4.1, Gravity Forms 3.2.2 und WPForms Lite 2.0.4 waren geladen. Der Benchmark rief ihre Einsendungsprozesse nicht auf.
- `minimum_seconds` war bereits 0. Optionen wurden vor der Messung im selben PHP-Prozess eingelesen. CLI-OPcache war ausgeschaltet. WordPress-Bootstrap, Tokenausgabe und Aufräumen liegen ausserhalb der Guard-Einzelzeiten.
- `Token::issue()` wurde mit 1'000 neuen Zufallstokens gemessen, `Token::verify()` danach 1'000-mal mit einem gültigen Token. Beide Funktionen sind zustandslos.
- `Guard::check()` prüfte 50 unterschiedliche gültige Tokens und 50 Einsendungen ohne Token, jeweils für den ausschliesslich synthetischen Kommentar-Formularbezug `9912345`. Es gab keine Namen, E-Mail-Adressen, Nachrichten oder echten Kommentare.
- `hrtime(true)` erfasste jede Operation separat. Der Median und das 95. Perzentil beziehen sich auf diese Einzelzeiten; das Perzentil nutzt den aufgerundeten Rang. Die Queryzahlen sind Differenzen von `$wpdb->num_queries`, keine Zählung interner SQLite-Adapteroperationen.

## Gemessene Funktionspfade

| Pfad | Operationen | Median | 95. Perzentil | WordPress-SQL-Aufrufe je Operation |
| --- | ---: | ---: | ---: | ---: |
| Token ausgeben | 1'000 | 6,375 µs | 6,625 µs | 0 |
| Token verifizieren | 1'000 | 9,458 µs | 10,000 µs | 0 |
| Guard akzeptiert gültigen Token | 50 | 0,596 ms | 0,936 ms | 2 |
| Guard weist fehlenden Token ab | 50 | 0,382 ms | 0,523 ms | 1 |

Der akzeptierte Guardpfad umfasst die atomare Einmalverwendung und das Erhöhen des anonymen Tageszählers. Die Ablehnung ohne Token schreibt nur den Tageszähler. Alle 50 gültigen synthetischen Prüfungen wurden akzeptiert, alle 50 Prüfungen ohne Token mit `missing` abgewiesen. Anschliessend wurden genau die 50 selbst erzeugten Replay-Digests über ihre vollständigen Werte entfernt. Gemeinsame QA-Tageszähler wurden wegen gleichzeitig laufender Tests nicht zurückgesetzt; sie sind keine Nutzungs- oder Spamwirksamkeitsmessung.

## Assetumfang und tatsächliche Einbindung

Dateigrössen zum Messzeitpunkt; Gzip ist eine lokale Kompression mit Stufe 9 und festem Zeitstempel, keine gemessene HTTP-Transfergrösse.

| Datei | Unkomprimiert | Gzip |
| --- | ---: | ---: |
| `assets/protection.js` | 5'428 Bytes | 2'171 Bytes |
| `assets/admin.css` | 12'496 Bytes | 2'715 Bytes |

Zwei echte, lesende HTTP-Abrufe gegen den lokalen Testserver bestätigten:

| Route | Status | Schutzcontainer | WP-Antispam-Scripts | WP-Antispam-Styles |
| --- | ---: | ---: | ---: | ---: |
| Startseite ohne Formular, `/` | 200 | 0 | 0 | 0 |
| Formular-Fixture, `/?page_id=5` | 200 | 4 | 1 | 0 |

Das Script wurde auf der Formularseite einmal eingebunden, obwohl dort mehrere geschützte Formulare stehen. Das Verwaltungsstylesheet erschien auf keiner der beiden öffentlichen Routen. Nachgelagerte Browseranfragen an den Challenge-Endpunkt sind in dieser HTML-Inventur nicht enthalten.

## Nachvollziehbarkeit und Grenzen

Die lokalen Rohresultate liegen während der Entwicklung unter `/tmp/rktsky-antispam-runtime/performance-result.json` und `performance-http-assets.json`; das Messscript liegt dort als `performance.php` und gehört nicht zum Release. Die Produktionsfunktionen stehen in `src/Token.php`, `src/Guard.php` und `src/Store.php`. Eine Wiederholung benötigt eine explizit freigegebene disposable Fixture, denselben Ablauf, aktivierte Mail-/HTTP-Sperren und neu erzeugte Tokens.

Die Messung beschreibt begrenzte Funktionskosten in einem bereits gestarteten CLI-Prozess und die tatsächliche Assetauswahl zweier lokaler HTML-Antworten. Sie misst weder den gesamten anonymen Seitenaufruf noch PHP-FPM, MySQL/MariaDB, Spitzenlast, Mobilgeräte, öffentliche Serverlaufzeiten, Lighthouse oder den Speed Index. Wegen parallel laufender QA und unterschiedlicher Seiteninhalte wird aus den HTTP-Abrufen kein Plugin-Ladezeitvergleich abgeleitet. Eine Aussage zur Erkennungsquote oder zur Belastbarkeit unter Angriff folgt daraus ebenfalls nicht.

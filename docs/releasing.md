# Veröffentlichung

Rocketsky Antispam ist ein Entwicklungskandidat. Eine Einreichung im WordPress-Pluginverzeichnis ist nicht Teil der bisherigen Veröffentlichung und benötigt den tatsächlichen WordPress.org-Account, einen bestätigten verfügbaren Slug und den Reviewprozess.

Die GitHub-Distribution setzt seit 0.5.0 eine eigene `Update URI`. Damit bietet
WordPress keine fremde Verzeichnisaktualisierung unter einem gleichnamigen
Entwicklungsslug an. Ein GitHub-Updater wird dadurch nicht eingerichtet; Updates
erfolgen manuell. Name und Kandidaten-Slug sind seit 0.5.1 auf Rocketsky Antispam
und `rocketsky-antispam` festgelegt. Vor einer Verzeichniseinreichung ist der
Header für die tatsächliche WordPress.org-Distribution zu entfernen.
Quelle: [offizielle Header-Anforderungen](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/).
Die CI-Prüfung für WordPress.org entfernt nur diesen Header in einer getrennten
Prüfkopie. Der GitHub-Quellstand und das GitHub-Installationspaket behalten ihn.
Der vollständige lokale GitHub-Report führt den erwarteten Verzeichnisbefund
`plugin_updater_detected` sichtbar; es wird kein Verzeichnis-Upload freigegeben.

Der Namenswechsel entfernt den bisherigen Begriff `wp` aus Name, Slug und
Textdomain. Historische Plugin-Check-Befunde gelten für den damals geprüften
Stand und werden nicht als aktueller Report übernommen. Die tatsächliche
Slugverfügbarkeit und der gewöhnliche Verzeichnisreview bleiben vor einer
Einreichung zu prüfen.

`load_plugin_textdomain` registriert derzeit die mitgelieferten Übersetzungen für die GitHub-Installation. Plugin Check weist auf die automatische Sprachversorgung für auf WordPress.org gehostete Plugins hin. Beim Übergang auf das Verzeichnis diesen Vertrag erneut prüfen; die gegenwärtige lokale Sprachversorgung nicht für einen kosmetisch leeren Report entfernen.

Vor einem stabilen Release:

- Offene Punkte in `verification.md` auflösen; insbesondere reale Browserabläufe, Anbieter-/Versionsmatrix, Mehrseitenformulare, Save & Continue, Wiederholung nach Validierungsfehlern und API-/Add-on-Wege.
- WordPress Coding Standards und offizielles Plugin Check ausführen, Befunde korrigieren und den tatsächlich geprüften Commit dokumentieren.
- Quellcode, GPL-Lizenz und Abhängigkeitsinventar prüfen. Zur Laufzeit sind keine fremden Bibliotheken enthalten. Die GPL-Lizenzschrift ist eine unveränderte Lizenzschrift und keine übernommene Programmlogik.
- Alle mitgelieferten Sprachkataloge und die POT prüfen. Keine Geheimnisse, Runtimes, Entwicklungsprogramme oder Testdaten ausliefern.
- Mindestversionen durch reale Matrixläufe belegen. Eine Mindestangabe im Header allein ist kein Kompatibilitätsnachweis. Stable tag, Header, Versionskonstante und Änderungsprotokoll gemeinsam erhöhen.
- GitHub-Repository unter dem autorisierten rktsky-Konto führen. Logik-/Regressionen und Optik-/Assetänderungen getrennt committen. Einen Release erst nach den Prüfungen taggen.
- Verzeichnisnamen/Textdomain/Slug gegen die Vorgaben des Pluginteams prüfen. Der gewünschte sichtbare Name ist Rocketsky Antispam; ein Slug ist bis zur Bestätigung reservierungslos.
- Den Releaseinhalt anhand `.distignore` packen, installieren, aktivieren, deaktivieren und deinstallieren. Einzel- und Netzwerkaktivierung, neue Websites, gebatchte Wartung und die getrennten Löschentscheidungen erneut prüfen. Öffentlich ausgelieferter Code bleibt lesbar.
- Beim Wechsel aus der früheren Installation den [Übergangsvertrag](renaming.md) prüfen: alte Kopie deaktivieren, keine Deinstallation ausführen, neue Kopie aktivieren und bestehende Daten nachweisen. Kryptografische Zwecke und Speicherkennungen bleiben stabil.
- Erst mit der passenden Freigabe einreichen. Keine Sterne-/Bewertungsaufforderungen, Upsells oder externe Frontendcredits hinzufügen.

Referenzen: [Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [Readmes](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/), [Plugin Check](https://wordpress.org/plugins/plugin-check/).

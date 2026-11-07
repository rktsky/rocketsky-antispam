# Veröffentlichung

WP Antispam ist ein Entwicklungskandidat. Eine Einreichung im WordPress-Pluginverzeichnis ist nicht Teil der bisherigen Veröffentlichung und benötigt den tatsächlichen WordPress.org-Account, einen bestätigten verfügbaren Slug und den Reviewprozess.

Der offizielle Plugin-Check meldet für den ausdrücklich gewünschten Namen `WP Antispam` und den vorläufigen Slug `wp-antispam` eine `trademarked_term`-Warnung zum Begriff `wp`. Name und Slug werden deshalb nicht als vom Verzeichnis akzeptiert dargestellt und nicht eigenmächtig geändert. Diesen konkreten Befund vor Einreichung klären; die [offiziellen Namensregeln](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/) sind massgeblich.

`load_plugin_textdomain` registriert derzeit die mitgelieferten Übersetzungen für die GitHub-Installation. Plugin Check weist auf die automatische Sprachversorgung für auf WordPress.org gehostete Plugins hin. Beim Übergang auf das Verzeichnis diesen Vertrag erneut prüfen; die gegenwärtige lokale Sprachversorgung nicht für einen kosmetisch leeren Report entfernen.

Vor einem stabilen Release:

- Offene Punkte in `verification.md` auflösen; insbesondere reale Browserabläufe, Anbieter-/Versionsmatrix, Mehrseitenformulare, Save & Continue, Wiederholung nach Validierungsfehlern und API-/Add-on-Wege.
- WordPress Coding Standards und offizielles Plugin Check ausführen, Befunde korrigieren und den tatsächlich geprüften Commit dokumentieren.
- Quellcode, GPL-Lizenz und Abhängigkeitsinventar prüfen. Zur Laufzeit sind keine fremden Bibliotheken enthalten. Die GPL-Lizenzschrift ist eine unveränderte Lizenzschrift und keine übernommene Programmlogik.
- Deutsche/Schweizer Übersetzung und POT prüfen. Keine Geheimnisse, Runtimes, Entwicklungsprogramme oder Testdaten ausliefern.
- Mindestversionen durch reale Matrixläufe belegen. Eine Mindestangabe im Header allein ist kein Kompatibilitätsnachweis. Stable tag, Header, Versionskonstante und Änderungsprotokoll gemeinsam erhöhen.
- GitHub-Repository unter dem autorisierten rktsky-Konto führen. Logik-/Regressionen und Optik-/Assetänderungen getrennt committen. Einen Release erst nach den Prüfungen taggen.
- Verzeichnisnamen/Textdomain/Slug gegen die Vorgaben des Pluginteams prüfen. Der gewünschte sichtbare Name ist WP Antispam; ein Slug ist bis zur Bestätigung reservierungslos.
- Den Releaseinhalt anhand `.distignore` packen, installieren, aktivieren, deaktivieren und deinstallieren. Netzwerkaktivierung muss eine verständliche Ablehnung liefern. Öffentlich ausgelieferter Code bleibt lesbar.
- Erst mit der passenden Freigabe einreichen. Keine Sterne-/Bewertungsaufforderungen, Upsells oder externe Frontendcredits hinzufügen.

Referenzen: [Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/), [Readmes](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/), [Plugin Check](https://wordpress.org/plugins/plugin-check/).

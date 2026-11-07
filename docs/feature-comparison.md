# Funktionsabgleich: WP Armour, Antispam Bee und WP Antispam

Stand: 7. November 2026. Geprüft wurden die offiziellen Produktbeschreibungen, FAQ, Funktionsübersichten und einschlägigen öffentlichen Änderungsnotizen. Konkurrenzcode wurde nicht als Implementierungsvorlage verwendet. Referenzstand: WP Armour 2.4.02 und Antispam Bee 2.11.13.

**WP Antispam übernimmt nicht sämtliche Funktionen und Anbieteradapter der Referenzen.** Der erste Stand 0.1.0 enthielt den technischen Formularschutz. Für 0.2.0 wurden die danach ausdrücklich gewünschten lokalen Inhaltsregeln, Sprach-/Länderregeln, Benachrichtigungen, Löschfristen, Dashboardzahlen und die optionale Quarantäne ergänzt. Die folgenden Statusangaben unterscheiden implementierte Kandidaten von ausstehenden beziehungsweise bewusst anders gelösten Referenzfunktionen; tatsächliche Prüfungen stehen im Verifikationsnachweis.

Die nachgereichte Benutzerliste ist in dieser Bewertung berücksichtigt. «Implementiert» bezeichnet den Entwicklungsstand, keine zugesicherte Erkennungsquote. Verbleibende Adapter- und Diagnosewünsche werden dadurch nicht stillschweigend als erledigt erklärt.

## Gemeinsamer technischer Ansatz und WP Armour

Die Referenzfunktionen und die Trennung zwischen kostenloser Ausgabe und Extended stammen aus der [offiziellen WordPress.org-Beschreibung einschliesslich FAQ und Changelog](https://wordpress.org/plugins/honeypot/). Aufzeichnung und IP-Sperren werden zusätzlich auf der [Herstellerseite](https://wp-armour.com/) und der [Extended-Seite](https://dineshkarki.com.np/buy-wp-armour-extended) beschrieben.

| Funktion der Referenz | Status in WP Antispam | Bewertung und nächster Schritt |
| --- | --- | --- |
| Schutz ohne CAPTCHA oder sichtbares Rätsel | Vorhanden | Keine Benutzeraufgabe; die tatsächliche Browserprüfung bleibt notwendig. |
| Automatische Einrichtung ohne Formular-Snippets | Vorhanden für die unterstützten Adapter | Einstellbare Anbieter, native Hooks; keine globalen Eingriffe in fremde Formulare. |
| Durch JavaScript ergänzte Schutzfelder | Vorhanden | Frische lokale Challenge, dynamischer leerer Honeypot. JavaScript ist erforderlich und wird im Fehlerfall ausdrücklich genannt. |
| Individueller Honeypotname | Vorhanden, pro Token | Kein manuell gepflegter Name notwendig; zusätzlich Signatur, Formularbindung, Ablauf und atomare Einmalverwendung. |
| Honeypotname im Backend ändern | Nicht separat angeboten | Per-Token-Wechsel übernimmt den Zweck. Eine zusätzliche statische Einstellung würde Bedienaufwand und Fehlkonfiguration erzeugen. |
| Fehlermeldung im Backend anpassen | Fehlt | Übersetzbare feste Retrymeldung vorhanden. Eine redaktionell anpassbare Meldung wäre möglich; Sicherheitsdetails dürfen darin nicht offengelegt werden. Mit der Wunschliste abgleichen. |
| Keine externen Prüfservices, kein Tracking/Cookie | Vorhanden im eigenen Plugin | Native Formularanbieter und WordPress behalten ihre unabhängigen Datenflüsse. |
| Diagnose-/Testwidget am Formular für Administratoren | Fehlt | Sinnvolle Diagnose, sofern sie ohne Schutzbypass arbeitet und weder Token noch Besucherinformationen protokolliert. Noch nicht umgesetzt. |
| Statistiken | Vorhanden, ab 0.2.0 auch Dashboard | Tageswerte und Ablehnungsquote sowie dauerhafter Kommentar-Ablehnungszähler; technische Fehler sind enthalten. Keine behauptete Erkennungsgenauigkeit. |
| Statistikzugriff für Redaktionsrollen | Nicht freigegeben | Aktuell `manage_options`. Quarantäneinhalte verlangen eine ausdrückliche Berechtigungsentscheidung; keine stillschweigende Freigabe für alle Editors. |
| Filter nach Formular und IP-Suche | Fehlt; IP-Suche widerspricht bisherigem Datenmodell | Formularfilter wäre ohne Personenprofil möglich. IP-Suche setzt IP-Erfassung voraus und wird unter dem vereinbarten Verzicht darauf nicht übernommen. |
| Spam-Inhalte aufzeichnen und ansehen | In 0.2.0 implementiert | Ausdrückliches Opt-in, begrenzte lokale verschlüsselte Feldwerte, Frist und Berechtigung. Datenschutzrelevant; im datensparsamen Modus deaktiviert. Keine vollständige Kopie unbekannter Formulare. |
| IP-Protokoll und automatische IP-Sperre | Nicht vorhanden | Empfehlung: nicht übernehmen. Keine zusätzliche IP-Historie; gemeinsame Anschlüsse und Proxys können echte Benutzer treffen. Lastbegrenzung benötigt ein separates, überprüftes Konzept. |
| Schlüsselwortfilter | In 0.2.0 implementiert | Begrenzte wörtliche Regeln über ausdrücklich ausgewählte native Textfelder; keine technischen, Zahlungs-, Datei- oder Passwortfelder. Native Kommentarregeln bleiben zusätzlich aktiv. |
| Anbieter einzeln ein-/ausschalten | Vorhanden | Globale Anbieterwahl ohne Codeeingriff. Eine Auswahl einzelner Formulare fehlt. |
| Betrieb ohne jQuery | Vorhanden | Eigenes Schutzscript ohne jQuery-Abhängigkeit. |
| Schutz gegen Zahlungs-/Card-Testing-Missbrauch | Nicht vorhanden | Keine behauptete Checkout-Sicherheit aus einem allgemeinen Honeypot ableiten. Zahlungsspezifische Grenzen und native Gateways brauchen eigene Verträge und Tests. |
| Kostenlose und kostenpflichtige Funktionsstufen | Eine freie Open-Source-Ausgabe | Kein Lizenzschlüssel, keine Premiumschranke und keine freizuschaltenden Schutzfunktionen vorgesehen. |

Die Werbeaussagen der Referenz, Bots könnten generell kein JavaScript ausführen oder eine bestimmte Erkennungsquote sei garantiert, werden nicht übernommen. Browserfähige Bots können auch in WP Antispam gültige öffentliche Challenges abrufen.

## Vollständige veröffentlichte WP-Armour-Adapterliste

Die Liste bildet die aktuelle offizielle Übersicht ab. Die Herkunft «frei» oder «Extended» beschreibt die Referenz, keine geplante Bezahlstufe von WP Antispam. Weitere Herstelleradapter werden nicht allein wegen eines gemeinsamen Formulartags als unterstützt bezeichnet.

| Integration | WP Armour | WP Antispam: tatsächlicher Stand |
| --- | --- | --- |
| Native WordPress-Kommentare | Frei | Vorhanden; Kommentare sind Pflichtbestandteil. |
| Native WordPress-Registrierung | Frei | Fehlt. |
| bbPress | Frei | Fehlt. |
| Contact Form 7 | Frei | Vorhanden. |
| Gravity Forms, einzelne Seite ohne AJAX | Frei | Vorhanden. |
| Gravity Forms, AJAX/mehrere Seiten | Extended | Eigener Adapter vorhanden; native/Browsertests gesondert nachweisen. |
| WPForms | Frei | Gemeinsame Hooks vorhanden; Lite geprüft, Pro-/Add-on-Umfang nicht pauschal zusagen. |
| Formidable Forms | Frei | Fehlt. |
| Caldera Forms | Frei | Fehlt; vor Priorisierung aktuelle Projektpflege und Migrationsbedarf prüfen. |
| Toolset Forms | Frei | Fehlt. |
| Elementor Forms | Frei | Fehlt. |
| Fluent Forms | Frei | Fehlt. |
| Divi-Kontaktformular | Frei | Fehlt. |
| Theme My Login | Frei | Fehlt. |
| WooCommerce Reviews Pro | Frei | Kein dedizierter Vertrag. Native Kommentarformulare können technisch erfasst werden; keine pauschale Pro-Kompatibilität. |
| WooCommerce Checkout/Registrierung | Extended | Fehlt. |
| Easy Digital Downloads Checkout/Registrierung | Extended | Fehlt. |
| QuForm | Extended | Fehlt. |
| Ninja Forms | Extended | Fehlt. |
| MC4WP: Mailchimp for WordPress | Extended | Fehlt. |
| s2Member | Extended | Fehlt. |
| Ultimate Member | Extended | Fehlt. |
| Bricks Builder | Extended | Fehlt. |
| BricksForge Pro Forms | Extended | Fehlt. |
| Beaver Builder Contact Form Module | Extended | Fehlt. |
| Strong Testimonials | Extended | Fehlt. |
| FormCraft | Extended | Fehlt. |
| Forminator | Extended | Fehlt. |
| WS Form | Extended | Fehlt. |
| YITH Easy Login & Register Popup | Extended | Fehlt. |
| UsersWP | Extended | Fehlt. |
| Youzify | Extended | Fehlt. |
| MailPoet | Extended | Fehlt. |
| Brevo / Sendinblue | Extended | Fehlt. |
| JetFormBuilder | Extended | Fehlt. |
| HTML Forms | Extended | Fehlt. |
| Avia / Enfold | Extended | Fehlt. |
| BuddyPress | Extended | Fehlt. |
| BuddyBoss | Extended | Fehlt. |
| SureForms | Extended | Fehlt. |
| Everest Forms | Extended | Fehlt. |

Im offiziellen Changelog zusätzlich genannte Oberflächen sind ebenfalls offen: Elementor Login, Passwort-zurücksetzen, User Registration & Membership, Divi Engine Forms, EDD Reviews und Beaver-Builder-Kommentarformulare. Einzelne historische Fehlerkorrekturen beweisen keine heutige vollständige Produktunterstützung. Insbesondere Anmeldung, Passkeys, Passwort-Reset, Registrierung und Checkout benötigen jeweils einen eigenen Ablaufvertrag; ein globaler Authentifizierungsfilter wäre dafür ungeeignet.

## Antispam Bee: Filter und Verarbeitung

Basis sind die [offizielle Funktionsübersicht und FAQ](https://wordpress.org/plugins/antispam-bee/) sowie das [Herstellerhandbuch](https://antispambee.pluginkollektiv.org/documentation/). Das Handbuch enthält historische Passagen: Die dort noch erläuterte Fake-IP-Prüfung wurde laut offiziellem Changelog in 2.9.2 wegen unzuverlässiger Ergebnisse entfernt. Solche Beschreibungen werden nicht als aktuelle Sollfunktion übernommen.

| Funktion der Referenz | Status in WP Antispam | Bewertung und nächster Schritt |
| --- | --- | --- |
| Native Kommentare prüfen | Vorhanden | Eigener Formularschutz plus bestehende WordPress-Prüfungen. |
| Honeypot | Vorhanden | Anwesenheit und exakt leerer Wert; dynamisch und an den signierten Token gebunden. |
| Bereits freigegebenen Kommentierenden vertrauen | Native Freigabelogik bleibt erhalten; kein Schutzbypass | Empfehlung: keine eigene pauschale Ausnahme. Eine angegebene E-Mail-Adresse beweist keine Identität und darf den technischen Schutz nicht ausschalten. |
| Gravatar-Vertrauen | Nicht vorhanden | Empfehlung: auslassen. Eine externe Prüfung eines E-Mail-Ableitungswerts passt nicht zum Verzicht auf Drittanbieterabfragen und beweist keinen guten Kommentar. |
| Kommentarzeit berücksichtigen | Vorhanden, optional | Mindestalter 0–10 Sekunden; Voreinstellung 0. Schnelle Bedienung und Autofill dürfen nicht automatisch als Spam gelten. |
| BBCode-Links erkennen | In 0.2.0 optional implementiert | URL-Tags werden erkannt, sonstige Formatierungen nicht pauschal abgewiesen. Websites mit erlaubtem BBCode müssen die Regel ausgeschaltet lassen; Codezitate können Fehlalarme auslösen. |
| Vordefinierte/erweiterbare Regexregeln | In 0.2.0 begrenzte eigene Regeln | Administratoren können einzelne Pattern-Bodies hinterlegen. Syntaxprüfung, Anzahl-/Längen-/Subjectgrenzen und PCRE-Rechenlimits begrenzen Risiken. Ein Ausführungsfehler führt zu einer technischen Ablehnung statt einer ungeprüften Freigabe. Keine kopierte Regelbibliothek. |
| Lokale Wiedererkennung früherer Spammer | Nicht vorhanden | Empfehlung: keine automatische IP-/E-Mail-Reputation aus ungeprüften Einsendungen. Identitätsbehauptungen sind manipulierbar; Fehlalarme könnten echte Benutzer dauerhaft treffen. |
| Länderfilter | In 0.2.0 lokal implementiert | Explizite Erlaubnis-/Sperrliste mit unbekanntem Ergebnis als eigenem Fall. Ausschliesslich lokale GeoIP-Daten oder verifizierte Infrastrukturheader; keine externen Abfragen und keine IP-/Länderhistorie. IP-Verarbeitung ist datenschutzrelevant und im datensparsamen Modus gesperrt. |
| Sprachfilter | In 0.2.0 lokal implementiert | Kleine eigene Worthäufigkeitsheuristik für unterstützte Sprachen, nur Kommentartext. Kurze/unklare Ergebnisse bleiben standardmässig erlaubt; keine Nachricht wird zur Erkennung versendet. Fehlalarme und Umgehungen bleiben möglich. |
| Spam markieren statt sofort verwerfen | Optionale Quarantäne in 0.2.0 | Eigene und unterstützte vor der Speicherung abgelehnte Einsendungen können begrenzt aufbewahrt werden. Nativ gespeicherte WordPress-/GF-Spameinträge bleiben beim Anbieter und erhalten keine zusätzliche Inhaltskopie. Datenschutzrelevant; standardmässig ausgeschaltet. |
| Benachrichtigungsmails | In 0.2.0 als tägliche Zusammenfassung | Nur aggregierte Zahlen an die native Administratoradresse, keine Besuchertexte. Ausdrücklich einzuschalten; datenschutz-/infrastrukturrelevant und im datensparsamen Modus gesperrt. Keine Mail für jede einzelne Spamnachricht. |
| Einzelnen Ablehnungsgrund anzeigen/ausblenden | Gründe und optionale Einzelfälle vorhanden | Aggregierte feste Gründe ohne Inhalte; bei aktivierter Quarantäne zusätzlich berechtigt einsehbare Einträge. Keine freie personenbezogene Logdatei. |
| Spam nach X Tagen löschen | In 0.2.0 getrennte Fristen | Quarantäne 1–30 Tage; optionale native WordPress-Spamkommentar-Löschung nach konfiguriertem Alter. Zähler und Replay-Schutz besitzen getrennte Aufbewahrung. Keine Löschung freigegebener Kommentare vorgesehen. |
| Unterschiedliche Behandlung von Kommentaren und Pings | Teilweise vorhanden | Kommentare werden geprüft, Pingbacks/Trackbacks derzeit ausdrücklich abgewiesen. Es fehlt eine separate Ping-Policy im Backend. Keine heimliche Freigabe unbekannter Typen. |
| Nach Ablehnungsgrund sofort löschen | Fehlt als differenzierte Inhaltsoption | Erst zusammen mit Quarantäne sinnvoll. Ein technischer Fehler ist kein sicherer Beweis für unerwünschten Inhalt. |
| Pingbacks/Trackbacks von Prüfung ausnehmen | Nicht vorhanden | Empfehlung: keine ungeprüfte Ausnahme. Eine alternative Policy muss ausdrücklich gewünscht und fachlich geprüft werden. |
| Kommentarformulare auf Archiven | Technisch über native `comment_form`-Hooks erfasst | Tatsächliche Post-ID aus dem Hook; kein eigener Ortsschalter nötig. Konkrete Theme-/AJAX-Fälle separat prüfen. |
| Dashboardverlauf und Gesamtsumme | In 0.2.0 implementiert | Native Dashboardtabelle mit Tageswerten, täglicher Ablehnungsquote und dauerhaftem Kommentar-Ablehnungszähler. Kein Diagramm erforderlich; Werte sind beobachtete Entscheidungen inklusive technischer Fehler. |
| Erkennungsqualität anhand nachträglicher Bewertungen | In 0.2.0 als geprüftes 90-Tage-Fenster | TP/FP/FN/TN aus unveränderter Vorhersage und korrigierbarer menschlicher Bewertung; ungeprüfte Fälle getrennt. Native beobachtete Kommentare/GF-Einträge, Quarantänebewertung und ausdrücklich manuell klassifizierte zugestellte Fälle. Keine behauptete vollautomatische Inboxbeobachtung für CF7/WPForms Lite. |
| Öffentlicher Zähler im Theme | Fehlt | Kein Kernbedarf für Spamschutz; würde eine neue öffentliche Ausgabe-/Integrationsschnittstelle erfordern. |
| Fail2Ban-Logdatei | Nicht vorhanden | Empfehlung: keine zusätzliche personenbezogene Logdatei. Vorhandene Serverabwehr separat betreiben. |
| Externe Kommentarsysteme/iframes | Kein dedizierter Adapter | Keine pauschale Unterstützung für Disqus, Jetpack oder wpDiscuz. Das ist auch laut Bee-FAQ ein begrenzter Bereich. |
| Eigene Erweiterungshooks | Keine stabile öffentliche Inhaltsregel-API | Bedarf aus echten Integrationen ableiten, dokumentieren und versionieren; keine Kompatibilität zu Bee-Hooks behaupten. |
| Deinstallation eigener Daten | Vorhanden | Eigene Optionen/Tabellen mit wählbarer Entfernung; neue Quarantänedaten müssen derselben Entscheidung folgen. |

Ein Sprach-/Länder-/Gravatarfilter wird nicht allein durch die Datenschutz-Werbeaussage einer Produktseite zu einer rein lokalen Funktion. Die beschriebenen optionalen Datenflüsse sind für unseren Auftrag separat zu beurteilen. Ebenso wenig wird die im Handbuch behauptete Abwesenheit von Fehlalarmen übernommen.

## Was WordPress bereits lokal leistet

WordPress besitzt native Moderationsbegriffe, eine Linkgrenze, Regeln für bereits freigegebene Kommentierende, eine Liste unzulässiger Begriffe sowie Duplikat- und Floodprüfungen. WP Antispam lässt diese Abläufe aktiv. Deshalb sind sie kein Anlass, dieselbe Logik samt zweiter Konfiguration erneut zu bauen. Quellen: [check_comment()](https://developer.wordpress.org/reference/functions/check_comment/), [wp_check_comment_data()](https://developer.wordpress.org/reference/functions/wp_check_comment_data/), [wp_check_comment_disallowed_list()](https://developer.wordpress.org/reference/functions/wp_check_comment_disallowed_list/), [wp_allow_comment()](https://developer.wordpress.org/reference/functions/wp_allow_comment/).

Die nativen Prüfungen haben unterschiedliche Ergebnisse: Eine Linkgrenze kann Moderation auslösen, ein unzulässiger Begriff kann zu Spam/Papierkorb führen, ein Duplikat zu einer Fehlermeldung. Das darf nicht nachträglich als eigene sichere Spamklassifikation oder eigener Quarantänefall ausgegeben werden. Die vorhandenen Optionen verändern wir nicht ungefragt.

## Umsetzung der Wunschliste und verbleibende Grenzen

Die ausdrücklich nachgereichten Funktionen sind in 0.2.0 als gemeinsam konfigurierte Erweiterung angelegt: Schlüsselwörter, optionale BBCode-URL-Regel, begrenzte eigene Regexregeln, lokale Kommentar-Spracherkennung, lokale Länderpolicy, tägliche aggregierte Administratorbenachrichtigung, getrennte Löschfristen und Dashboardwerte. Die Quarantäne verwendet ausgewählte fachliche Feldtypen; unbekannte oder sensible technische Felder werden ausgeschlossen. Alle Kandidaten benötigen die im Verifikationsnachweis genannten tatsächlichen Prüfungen.

Der datensparsame Modus erzwingt die Abschaltung von Quarantäne, Länderprüfung und Mailbenachrichtigung auch serverseitig. Zugehörige Bedienelemente werden gesperrt. Rein lokale temporäre Textregeln und die anonyme Statistik bleiben nutzbar. Eine Checkbox kann keine pauschale nDSG-/DSGVO-Zertifizierung der Website leisten; Hosting, native Formularspeicherung, Rechtsgrundlage und tatsächlicher Betrieb bleiben zu berücksichtigen.

Noch offen sind insbesondere die zahlreichen zusätzlichen Anbieteradapter, eine Auswahl einzelner Formulare, eine anpassbare Retrymeldung und ein echtes Administrator-Testwidget am Formular. Es bestehen keine pauschalen Zusagen für externe Kommentarsysteme, sämtliche kommerziellen Add-ons oder Zahlungsabläufe.

Die Inhaltsprüfung verarbeitet einen begrenzten Ausschnitt von höchstens acht KiB aus ausgewählten nativen Textfeldern. Quarantänewerte sind ebenfalls begrenzt und teilweise normalisiert. Ein gespeicherter Eintrag ist deshalb keine vollständige Originaleinsendung. Sehr lange, ungewöhnliche oder unbekannte Feldstrukturen werden nicht vollständig klassifiziert oder archiviert. Das Plugin behauptet weder vollständige Erkennung menschlichen Spams noch eine nicht gemessene Erkennungsquote.

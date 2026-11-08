# Formidable Forms, Fluent Forms und WS Form ab 0.3.0

Die Anbieter sind interne Module des Hauptplugins. Erkannte Integrationen lassen sich im Interface einschalten. Gespeicherte Anbieterformulare und Originalpakete werden nicht verändert. Die Module verwenden den gemeinsamen Guard mit lokalen Inhaltsregeln und der unabhängig aktivierbaren verschlüsselten Quarantäne.

| Anbieter | Deklarierte Untergrenze | Originalquelle zusätzlich geprüft | Native Abnahme |
| --- | --- | --- | --- |
| Formidable Forms | 6.2 | 6.2 und 7.0 | Lite 7.0 |
| Fluent Forms | 6.0.0 | 4.3.22, 6.0.0 und 6.2.17 | Free 6.2.17 |
| WS Form | 1.12.19 | Lite 1.12.19 | Lite 1.12.19 |

Die Untergrenzen bezeichnen geprüfte Schnittstellenverträge. Ein unverändert heruntergeladenes älteres Paket ersetzt keinen vollständigen Laufzeittest dieser Version. Kostenpflichtige Erweiterungen, Zahlungen, Teilpersistenz und Conversational-Modi erhalten dadurch keine pauschale Freigabe.

## Formidable Forms

`frm_entry_form($form, $form_action, $errors)` rendert die Schutzfelder innerhalb der nativen Form. Zusatzcontrols werden standardmässig nicht als Eintragsfelder gespeichert. Die Form-ID kommt aus dem nativen Formularobjekt.

Der Guard läuft am finalen `frm_entries_before_create($errors, $form)`, nach nativer Validierung und vor Eintragserstellung/Formularaktionen. Bestehende Fehler bleiben erhalten. Eine eigene Ablehnung erscheint als nativer formularweiter Fehler mit verständlicher Retrymeldung. Vorläufige AJAX-/Mehrseitenvalidierung verbraucht den Token nicht. Damit benötigt die anschliessende Verarbeitung keine allgemeine Memoisierung einer früheren Freigabe.

Für Regeln und Quarantäne werden höchstens 200 deklarierte Felddefinitionen gelesen und maximal 30 ausgewählte Textwerte übernommen: Text, Textarea, E-Mail, URL, Telefon, Zahl, Datum, Zeit, Auswahl, Radio, Checkbox und Name. Administratorfelder, eingebettete/wiederholte Formulare, versteckte Felder, Passwörter, Dateien und Zahlungsfelder werden ausgeschlossen. Namen akzeptieren nur die Standardbestandteile; Auswahlarrays nur flache numerische Indizes.

Serverseitige Direktaufrufe von `FrmEntry::create()` umgehen den öffentlichen Formularablauf und sind durch diesen Adapter nicht abgedeckt. Pro-AJAX-/Mehrseiten-, Entwurfs- und Bearbeitungsrouten benötigen eigene native Nachweise.

Quellen: [Render-Hook](https://formidableforms.com/knowledgebase/frm_entry_form/), [finaler Verarbeitungshook](https://formidableforms.com/knowledgebase/frm_entries_before_create/), [vorläufige Validierung](https://formidableforms.com/knowledgebase/frm_validate_entry/), [Originalpaket](https://downloads.wordpress.org/plugin/formidable.latest-stable.zip).

## Fluent Forms

`fluentform/form_element_start($form)` setzt Controls nach dem tatsächlichen öffnenden Form-Tag ein. `fluentform/validation_errors($errors, $formData, $form, $fields)` stoppt fehlgeschlagene Prüfungen über das native Fehlerformat vor Speicherung, Mail und Integration-Actions. Vorhandene Fehler bleiben erhalten.

Fluent übermittelt die Browserform als URL-kodiertes `POST[data]`. Die native Aufbereitung entfernt unbekannte Felder vor dem Validierungshook. Deshalb liest der Adapter ausschliesslich seine reservierten technischen Namen aus dem ursprünglichen Transport. Er fügt Token und Honeypot nicht in `formData` ein. Die Dekodierung ist auf ein MiB und 4'096 Paare begrenzt; doppelte technische Namen und Arrayformen schlagen fehl. Sehr grosse oder anders transportierte Sonderclients benötigen einen separat geprüften Vertrag.

Inhalte stammen aus der nativen bereinigten Feldsammlung und müssen mit den deklarierten Originaltypen übereinstimmen. Erlaubt sind Standardtext, Textarea, E-Mail, URL, Name, Zahl, Datum und einfache Auswahlfelder. Versteckte, Passwort-, Adress-, Datei-/Bild- und Zahlungsfelder sowie unbekannte Typen werden ausgeschlossen. Name- und Auswahlbestandteile bleiben begrenzt.

Quellen: [Formular-Actions](https://developers.fluentforms.com/hooks/actions/form/), [Validierungsfilter](https://developers.fluentforms.com/hooks/filters/form/), [Ablauf einer Einsendung](https://developers.fluentforms.com/submission-lifecycle/), [Originalpaket](https://downloads.wordpress.org/plugin/fluentform.latest-stable.zip).

## WS Form

WS Form erstellt und ersetzt den Formularinhalt im Browser. Ein einmalig innerhalb der PHP-Hülle angehängter Input würde dabei verschwinden. `wsf_shortcode($html, ...)` ergänzt deshalb signierte Attribute nur an echten `form.wsf-form[data-id]`-Elementen. Die native WordPress-HTML-API übernimmt das Escaping. Das gemeinsame Schutzscript setzt daraus Controls in den Form-DOM und stellt sie nach einem Canvas-Neuaufbau wieder her. Original Lite verwendet `wsf-rendered`; die offizielle Eventdokumentation nennt `wsft-rendered`. Beide Lebenszyklen werden berücksichtigt.

`wsf_submit_validate($error_actions, $post_mode, $submit)` läuft nach nativer Feldaufbereitung vor Persistenz und Actions. Frühere Fehleraktionen bleiben erhalten. Eine eigene Ablehnung ergänzt eine native sichtbare Danger-Meldung und lässt die Form verfügbar. Auch ein aufgerufener Save-Pfad erhält keine ungeprüfte Ausnahme; nativ abgenommen wurde hier der Lite-Submitpfad.

Der Standardbrowser sendet echte `FormData` einschliesslich eigener Top-Level-Controls. Token und Trap werden nicht zu nativen Feldmetadaten. Sonderclients mit JSON in `POST[data]` sind nicht als Transport freigegeben und erhalten ohne direkte Schutzcontrols eine Ablehnung.

Die Inhaltsauswahl gleicht gespeicherte Definitionen mit den vorbereiteten öffentlichen Metadaten ab. ID und Typ müssen übereinstimmen. Erlaubt sind Text, Textarea, E-Mail, URL, Telefon, Zahl, Datum/Zeit und einfache Auswahlfelder. Geschützte Metadaten, versteckte Felder, Passwörter, Uploads, Signaturen und Zahlungen werden nicht übernommen. Native Ersatzkopien wiederholter Werte werden übersprungen.

Quellen: [Validierungshook](https://wsform.com/knowledgebase/wsf_submit_validate/), [JavaScript-Ereignisse](https://wsform.com/knowledgebase/javascript-events/), [Originalpaket](https://downloads.wordpress.org/plugin/ws-form.latest-stable.zip).

## Tatsächliche Prüfungen

Die unveränderten Originalpakete liefen in einer separaten frischen WordPress-7.1.4-Installation mit PHP 8.3.26 und SQLite Database Integration 3.1.3. Mail und ausgehende WordPress-HTTP-Aufrufe waren blockiert. Native Anbieter-Schutzmechanismen blieben eingeschaltet.

`tests/adapters-extra-forms.php` besteht mit 25 Assertions. Dieser isolierte Vertragstest verwendet ausdrücklich benannte Anbieter-/Abhängigkeitsstubs; sofern WordPress verfügbar ist, zusätzlich die unveränderte native HTML-API für WS-Descriptor-Escaping. Er belegt keine nativen Einsendungen.

`tests/setup-extra-forms.php` legt synthetische Formulare über Anbieter-APIs an. `tests/native-extra-forms.php` besteht mit 30 Fällen in jeweils frischen PHP-Prozessen: Rendering, gültige Einsendung, fehlender Token, gefüllte Trap, echte erste Einsendung und anschliessender Replay, Ablehnung und frischer Retry, Schlüsselwortregel und optionale Quarantäne. Geprüft werden native Persistenzdeltas, sichtbare Fehler und das Stoppen vor Actions/Mail. Quarantäne erhält ausgewählte Texte/E-Mail, keine technischen Controls oder eingeschleusten sensiblen Zusatzwerte. Die ursprünglichen Fixture-Einstellungen werden wiederhergestellt.

Die nativen Pfade sind Formidables vollständiges `FrmEntriesController::process_entry()`, Fluents `SubmissionHandlerService::handleSubmission()` und WS Forms `WS_Form_API_Submit::api_post()`. Formidable erhält auch seine eigene öffentlich gerenderte Antispamkontrolle. WS Forms standardmässiger E-Mail-Versuch wird bei gültigen Einsendungen von der Fixture blockiert; die Prüfung behauptet deshalb keine erfolgreiche Mailzustellung.

Zusätzlich geprüft in echtem Headless-Firefox 153 auf der separaten lokalen Testseite: Alle drei Originalrenderer besitzen je einen befüllten Schutzcontainer. WS Forms tatsächliches `form_reload()` baut den Canvas neu auf; danach existieren genau ein Container und ein frischer Token. Eine gefüllte Trap führt zu sichtbarer nativer Ablehnung, der anschliessende frische Retry wird angenommen. Die Fehleraktion verwendet ausdrücklich den booleschen Wert `form_hide=false`; ein String wie `off` wäre im nativen Client wahr und würde das Formular verbergen. Fluent zeigt seine native Fehlermeldung mit Status 423 und nimmt den frischen Retry mit Status 200 an. Dabei bleibt Fluents eigene Submitdrossel von 1'500 ms unverändert. In diesen Browserfällen traten keine JavaScript-Ausnahmen auf.

```sh
RKTSKY_ANTISPAM_ALLOW_TESTS=1 RKTSKY_ANTISPAM_WP_ROOT=/path/to/disposable/wordpress php tests/setup-extra-forms.php
RKTSKY_ANTISPAM_ALLOW_TESTS=1 RKTSKY_ANTISPAM_WP_ROOT=/path/to/disposable/wordpress php tests/native-extra-forms.php
```

Diese neuen Adapter beobachten nicht automatisch nachträgliche native Spamklassifikationen gespeicherter Anbieterobjekte. Für Qualitätskennzahlen bleiben ausdrücklich bewertete eigene Quarantänefälle und die im Interface angebotenen manuellen Fallzuordnungen massgeblich. Andere Anbieter speichern Inhalte unabhängig von unseren Einstellungen und Löschfristen.

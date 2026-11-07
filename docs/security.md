# Sicherheits- und Datenschutzmodell

## Grenzen und Datenfluss

Ein fremder Besucher darf das öffentliche Formular lesen und Challenges für dessen signierten Anbieter-/Formularbezug abrufen. Er darf dadurch weder Optionen ändern noch Formulare im Namen anderer Benutzer autorisieren. Die Challenge ist keine Benutzeranmeldung, kein WordPress-Nonce und kein Menschlichkeitsnachweis.

Die lokale REST-Route nimmt nur begrenzte Stringwerte an; Ausgabe ist `no-store`. Die HMAC-Nachrichten sind nach Protokollzweck und WordPress-Site-ID getrennt und verwenden das native Auth-Salt. Ein Token enthält Version, Anbieter, Formular-ID, UTC-Zeit und kryptografischen Zufall. Kanonische Base64-Kodierung verhindert mehrere Schreibweisen desselben signierten Payloads. Scope, Framing, Signatur und Alter werden serverseitig geprüft. Der dynamische Honeypot muss vorhanden und exakt leer sein; Arrays werden nicht als gültige Werte akzeptiert.

Der Replay-Schutz verwendet ausschliesslich die offizielle WordPress-Datenbank-API `$wpdb` und eigene Plugin-Tabellen. Ein eindeutiger Schlüssel erzwingt den einmaligen Verbrauch auch bei parallelen Anfragen. Datenbankfehler im Replay-Schutz führen zur Ablehnung; ein Fehler wird nicht durch einen ungeschützten Fallback umgangen. Der Gravity-Forms-Adapter reicht ausschliesslich zwischen Validierung und dem einmaligen unmittelbar zugehörigen Abort-Hook ein formular- und tokengebundenes Ergebnis weiter. Eine neue Validierung oder der Verbrauch des Ergebnisses beendet diesen begrenzten Vorgang. Es gibt keine pauschale Freigabe desselben Tokens im PHP-Request. Statistiken löschen erhält das Replay-Ledger.

Der Browser verwendet weder Cookies noch persistente Browserdaten. Challenge-Fetches haben `credentials: omit`, `referrerPolicy: no-referrer`, Zeitlimit und dieselbe Origin. Der globale MutationObserver verarbeitet nur neu eingefügte Teilbäume, um nach AJAX gerenderte Formulare anzubinden. Er verändert keine fremden Eingabefelder und übermittelt keine Formularinhalte.

## Angreifer und verbleibende Risiken

- Direkte Einsendungen ohne Schutzfelder, manipulierte oder fremde Tokens, abgelaufene Tokens, ausgefüllte Honeypots und Wiederverwendung werden abgewiesen.
- Ein Bot kann öffentliche Formulare und den Challenge-Abruf nachbilden. Seine eigenen frischen, korrekten Einsendungen können bestehen. Signaturen machen aus öffentlichem Browsercode kein Geheimnis.
- Es gibt kein Fingerprinting und keine externe Reputation. Optionale Länderregeln nutzen nur eine lokale Quelle oder explizit vertrauenswürdige Infrastruktur; Sprachregeln sind begrenzte Heuristiken, keine verlässliche Identifikation. Die optionale Mindestzeit ist nur eine zusätzliche Hürde; Wartezeit beweist keine menschliche Eingabe.
- Inhaltsregeln finden die konfigurierten Muster. Menschlicher Spam und abweichende Formulierungen können bestehen. WordPress-Kommentarmoderation und native Anbieterprüfungen bleiben relevant.
- Verteilte Lastangriffe gehören vor WordPress begrenzt. Der öffentliche Challenge-Endpunkt schreibt keine Datenbankzeilen; HTTP-Bootstrap und kryptografische Arbeit kosten dennoch Ressourcen.
- Fremder Code mit Server-/Datenbankrechten, Salt-Leaks, XSS oder kompromittierte Administratoren liegen ausserhalb dieser Schutzgrenze. Sicherheitsupdates, Backups und angemessene Serverkonfiguration bleiben erforderlich.

## Divi: frühe Grenze vor nativer Verarbeitung

Seit 0.3.1 prüft der Divi-Adapter erkannte native Kontakt-POSTs an `wp_loaded` mit Priorität 100. Diese Grenze liegt vor der Formularverarbeitung während Frontend-, REST- und AJAX-Ausgaben. Ein REST-Methodenwechsel wie `_method=GET` ändert den eingegangenen POST nicht; der Kontext gewährt keine Ausnahme. Die nativen Präfixe für Submit, Feldliste und Nonce wählen den Schutz auch dann aus, wenn der folgende Zielbezug ungültig ist. Unzulässige oder überlange Suffixe und mehrere verschiedene Ziele führen zur Ablehnung. Unterstützt sind ein bis 64 ASCII-Buchstaben, Ziffern, Unterstriche oder Bindestriche.

Die Signatur bleibt an `divi/contact` gebunden. Geprüft werden der technische Nachweis, Honeypot und die konfigurierte Mindestzeit. Der Adapter übernimmt keine Kontaktwerte in Inhalts-, Sprach- oder Länderregeln und speichert sie nicht in der Quarantäne. Divis eigene Nonce-, Pflichtfeld- und Captcha-Prüfungen bleiben aktiv. Eine Ablehnung durch WP Antispam stoppt vor Divis Kontakt-Submit-Aktion und Mailversuch. Die native Folgeaktion ist keine veränderbare Validierungsschnittstelle.

Die tatsächliche Formular-Container-ID und die Seiten-ID werden nur für die sichtbare Fehlerrückgabe verwendet, begrenzt und bei der Ausgabe escaped. Diese Angaben autorisieren keine Verarbeitung. Ein Reload-Link verwendet ausschliesslich einen von Core erzeugten Permalink einer öffentlich sichtbaren Seite, sonst die Startseite. Manipulierte private Seitenreferenzen legen weder private Permalinks noch Slugs offen. AJAX erhält das vom nativen Selektor benötigte Fehlerfragment mit HTTP200; ein vollständiger abgewiesener POST erhält HTTP403. Formularinhalte werden nicht zurückgespiegelt.

Die Originalpakete Divi Theme/Builder 4.27.9 und Divi Theme 5.19.0 wurden mit echten PHP- und Browserabläufen einschliesslich REST-Ausgabe geprüft. Native D4-Nonce-Fehler verlangen den von Divi angewiesenen vollständigen Seitenreload. Nach dem nativen Formularersatz sind neue Nonce und Proof bereits vorhanden, die originale direkte Submit-Bindung wird jedoch erst beim Reload wieder initialisiert. Das wurde auch bei deaktiviertem Adapter beobachtet; die Bridge schreibt weder native Nonces noch Feldmetadaten um. Einzelheiten und Versionsgrenzen stehen im [Divi-Prüfnachweis](../tests/provider-elementor-divi.md).

## Privilegierte Oberfläche

WordPress Settings API (`options.php`) prüft Berechtigungen und CSRF-Nonce. Das Plugin akzeptiert ausschliesslich bekannte Keys, boolesche Schalter und begrenzte Zahlen. Die eigene Statistiklöschung verlangt POST, `manage_options` und eine native Nonce. Templates escapen am Ausgabeort. SQL nutzt vorbereitete Werte und `%i` für die ausschliesslich intern gebildeten eigenen Tabellennamen. Assets sind auf den eigenen Adminscreen bzw. tatsächlich gerenderte Formulare begrenzt.

## nDSG und DSGVO

Datensparsame Voreinstellungen, lokale Verarbeitung, zweckgebundene kurze Sicherheitszustände und nachvollziehbare Löschung orientieren sich an Datenschutz durch Technik und Voreinstellungen. Es werden keine IP-/E-Mail-Hashes angelegt. In der optionalen Quarantäne werden ausgewählte Kontaktfelder verschlüsselt gespeichert. Gespeicherte Token-Digests sind aus zufälligen technischen Werten abgeleitet; keine Nachricht oder Person wird darin kodiert. Die optional gespeicherten Quarantäneinhalte sind über die native WordPress-Auskunft und -Löschung nach der zugehörigen Kontakt-E-Mail erfasst. Nicht zuordenbare oder nach Schlüsselrotation unlesbare Datensätze benötigen die manuelle Prüfung oder Ablaufbereinigung. Die Datenverarbeitung des Formularanbieters bleibt unabhängig davon.

Die Aktivierung des datensparsamen Modus verhindert neue Inhaltsaufbewahrung, Länderprüfungen und Administrator-Digests. Vorhandene Quarantänedaten verbleiben geschützt bis zur Löschung. Er ist keine Rechtskonformitätsgarantie. Verschlüsselung hebt den Personenbezug nicht auf; eine Sicherung von Datenbank und Site-Salts gemeinsam ermöglicht weiterhin die Entschlüsselung.

30 Tage Statistikaufbewahrung, maximal 30 Tage optionale Quarantäne und zwei Stunden Tokengültigkeit sind Produktentscheidungen, keine gesetzlichen Fristen. Cron-Latenz, Backups, Hosting-Logs und Formularanbieter müssen in der tatsächlichen Website berücksichtigt werden. Technische Massnahmen allein begründen keine pauschale Rechtskonformität aller Installationen. Der WordPress-Textvorschlag beschreibt den tatsächlichen Datenfluss und verlangt Anpassung an den Betrieb.

Qualitätsmetriken verwenden ein eigenes 90-Tage-Fenster mit unveränderlicher ursprünglicher Vorhersage und späterer Bewertung. Nicht bewertete Datensätze werden separat gezählt. HMAC-Referenzen auf native Einträge oder manuelle Fälle sind lokal zuordenbare Pseudonyme und keine anonymen Besucherkennungen. Nach Quellenlöschung werden bewertete Beobachtungen entkoppelt und auf Tagesdaten vergröbert; ein pauschaler Nachweis irreversibler Anonymität wird daraus nicht abgeleitet. Native Datenschutzpfade und die gezielte Entfernung einer manuellen Fallreferenz dienen der Bereinigung.

Gelesene Primärquellen, 7. November 2026:

- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/), [Settings API](https://developer.wordpress.org/plugins/settings/settings-api/), [Privacy](https://developer.wordpress.org/plugins/privacy/), [REST Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [EDÖB: neues Datenschutzgesetz](https://www.edoeb.admin.ch/de/das-neue-datenschutzgesetz-aus-sicht-des-edob)
- [DSGVO: Art. 5, 25 und 32](https://eur-lex.europa.eu/eli/reg/2016/679/oj/eng)
- [WordPress Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)



## Datenbankgrenze ab 0.3.0

WordPress- und Anbieterdaten werden über ihre dokumentierten Objekt-, Einstellungs- und Formularschnittstellen verarbeitet. Eigene vorbereitete Abfragen sind auf die sechs Tabellen für Token, Tagesstatistik, Gesamtzähler, verschlüsselte Quarantäne, Bewertungsmetadaten und Versandsperren begrenzt. Schemaeinrichtung verwendet `dbDelta`; es gibt keine separate Datenbankverbindung. Die Versandsperre liegt in der eigenen Tabelle `rktsky_antispam_jobs`, während Einstellungen und Versandstatus die Options-API verwenden. Ihre internen Aufgabenkennungen enthalten keine Formularinhalte und werden nach spätestens zwei Tagen zur geplanten Bereinigung fällig.

Die Gleichzeitigkeit wird durch eindeutige Schlüssel und atomare Operationen über `$wpdb` abgesichert. Eine Folge unabhängiger Options-Lese- und Schreibaufrufe würde die einmalige Token- oder Aufgabenannahme nicht gleichwertig garantieren. Die Netzwerkverwaltung verwendet die nativen Site-/Metadaten-APIs. Es werden keine Core- oder Anbietertabellen mit eigenen SQL-Abfragen gelesen oder verändert.

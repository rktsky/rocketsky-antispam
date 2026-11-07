# Sicherheits- und Datenschutzmodell

## Grenzen und Datenfluss

Ein fremder Besucher darf das öffentliche Formular lesen und Challenges für dessen signierten Anbieter-/Formularbezug abrufen. Er darf dadurch weder Optionen ändern noch Formulare im Namen anderer Benutzer autorisieren. Die Challenge ist keine Benutzeranmeldung, kein WordPress-Nonce und kein Menschlichkeitsnachweis.

Die lokale REST-Route nimmt nur begrenzte Stringwerte an; Ausgabe ist `no-store`. Die HMAC-Nachrichten sind nach Protokollzweck und WordPress-Site-ID getrennt und verwenden das native Auth-Salt. Ein Token enthält Version, Anbieter, Formular-ID, UTC-Zeit und kryptografischen Zufall. Kanonische Base64-Kodierung verhindert mehrere Schreibweisen desselben signierten Payloads. Scope, Framing, Signatur und Alter werden serverseitig geprüft. Der dynamische Honeypot muss vorhanden und exakt leer sein; Arrays werden nicht als gültige Werte akzeptiert.

Das Einlösen wird durch einen eindeutigen Digest-Primärschlüssel mit atomarem `INSERT IGNORE` geschützt. Zwei Prozesse können denselben Token nicht beide erstmals eintragen. Datenbankfehler führen zur Ablehnung; ein Fehler wird nicht durch einen ungeschützten Fallback umgangen. Der Gravity-Forms-Adapter reicht ausschliesslich zwischen Validierung und dem einmaligen unmittelbar zugehörigen Abort-Hook ein formular- und tokengebundenes Ergebnis weiter. Eine neue Validierung oder der Verbrauch des Ergebnisses beendet diesen begrenzten Vorgang. Es gibt keine pauschale Freigabe desselben Tokens im PHP-Request. Statistiken löschen erhält das Replay-Ledger.

Der Browser verwendet weder Cookies noch persistente Browserdaten. Challenge-Fetches haben `credentials: omit`, `referrerPolicy: no-referrer`, Zeitlimit und dieselbe Origin. Der globale MutationObserver verarbeitet nur neu eingefügte Teilbäume, um nach AJAX gerenderte Formulare anzubinden. Er verändert keine fremden Eingabefelder und übermittelt keine Formularinhalte.

## Angreifer und verbleibende Risiken

- Direkte Einsendungen ohne Schutzfelder, manipulierte oder fremde Tokens, abgelaufene Tokens, ausgefüllte Honeypots und Wiederverwendung werden abgewiesen.
- Ein Bot kann öffentliche Formulare und den Challenge-Abruf nachbilden. Seine eigenen frischen, korrekten Einsendungen können bestehen. Signaturen machen aus öffentlichem Browsercode kein Geheimnis.
- Es gibt keine IP-/Länder-/Sprachsperren, kein Fingerprinting und keine externe Reputation. Die optionale Mindestzeit ist nur eine zusätzliche Hürde; Wartezeit beweist keine menschliche Eingabe.
- Spam durch Menschen und missbräuchliche Inhalte werden hier nicht klassifiziert. WordPress-Kommentarmoderation und native Anbieterprüfungen bleiben relevant.
- Verteilte Lastangriffe gehören vor WordPress begrenzt. Der öffentliche Challenge-Endpunkt schreibt keine Datenbankzeilen; HTTP-Bootstrap und kryptografische Arbeit kosten dennoch Ressourcen.
- Fremder Code mit Server-/Datenbankrechten, Salt-Leaks, XSS oder kompromittierte Administratoren liegen ausserhalb dieser Schutzgrenze. Sicherheitsupdates, Backups und angemessene Serverkonfiguration bleiben erforderlich.

## Privilegierte Oberfläche

WordPress Settings API (`options.php`) prüft Berechtigungen und CSRF-Nonce. Das Plugin akzeptiert ausschliesslich bekannte Keys, boolesche Schalter und begrenzte Zahlen. Die eigene Statistiklöschung verlangt POST, `manage_options` und eine native Nonce. Templates escapen am Ausgabeort. SQL nutzt vorbereitete Werte und `%i` für die ausschliesslich intern gebildeten eigenen Tabellennamen. Assets sind auf den eigenen Adminscreen bzw. tatsächlich gerenderte Formulare begrenzt.

## nDSG und DSGVO

Datensparsame Voreinstellungen, lokale Verarbeitung, zweckgebundene kurze Sicherheitszustände und nachvollziehbare Löschung orientieren sich an Datenschutz durch Technik und Voreinstellungen. Es werden keine IP-/E-Mail-Hashes angelegt. Gespeicherte Token-Digests sind aus zufälligen technischen Werten abgeleitet; keine Nachricht oder Person wird darin kodiert. Es gibt deshalb keine eigene Suche oder Export-/Löschzuordnung nach E-Mail-Adresse. Formulardaten verbleiben im Verantwortungsbereich des jeweiligen Anbieters.

30 Tage Statistikaufbewahrung und zwei Stunden Tokengültigkeit sind Produktentscheidungen, keine gesetzlichen Fristen. Cron-Latenz, Backups, Hosting-Logs und Formularanbieter müssen in der tatsächlichen Website berücksichtigt werden. Technische Massnahmen allein begründen keine pauschale Rechtskonformität aller Installationen. Der WordPress-Textvorschlag beschreibt den tatsächlichen Datenfluss und verlangt Anpassung an den Betrieb.

Gelesene Primärquellen, 7. November 2026:

- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/), [Settings API](https://developer.wordpress.org/plugins/settings/settings-api/), [Privacy](https://developer.wordpress.org/plugins/privacy/), [REST Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [EDÖB: neues Datenschutzgesetz](https://www.edoeb.admin.ch/de/das-neue-datenschutzgesetz-aus-sicht-des-edob)
- [DSGVO: Art. 5, 25 und 32](https://eur-lex.europa.eu/eli/reg/2016/679/oj/eng)
- [WordPress Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

WP Armour und Antispam Bee wurden auf ihren öffentlichen Funktionsbeschreibungen als Produktreferenzen angesehen. Ihre Implementierungen sind keine Codevorlage und es wurde daraus kein Quellcode übernommen.

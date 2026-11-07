# Lokale Quarantäne

Die Quarantäne ist seit 0.2.0 eine optionale Inhaltsablage zur Prüfung abgewiesener Einsendungen. Sie ist standardmässig ausgeschaltet. Der datensparsame Modus verhindert neue Speicherung auch dann, wenn ein manipulierter Einstellungsrequest die Quarantäne aktivieren will. Das Ausschalten der Speicherung oder Einschalten dieses Modus löscht vorhandene Einträge nicht rückwirkend. Einträge bleiben bis zur manuellen Löschung oder ihrem Ablauf zugänglich.

## Gespeicherte Daten

Die Integrationen übergeben ausschliesslich ausgewählte, erklärte Textfelder. Versteckte Felder, Passwörter, Zahlungsdaten, Uploads, technische Schutzfelder und unbekannte Eingaben werden ausgeschlossen. Zusätzlich kann die Integration eine gültige Kontakt-E-Mail für die Zuordnung im WordPress-Datenschutzwerkzeug übergeben. Einträge ohne solche Adresse bleiben administrativ prüfbar, sind aber keinem E-Mail-basierten Datenschutzrequest automatisch zugeordnet.

Der Speicher begrenzt jede Einsendung auf höchstens 30 Felder, 4096 Bytes pro Wert und 16384 Bytes für alle Feldbezeichnungen und Werte zusammen. UTF-8-Zeichen werden nicht mitten im Zeichen abgeschnitten. Formulardaten werden bei der Anzeige als Text ausgegeben; eingereichtes HTML wird nicht ausgeführt. Die Listenabfrage enthält nur Anbieter, Formularbezug, Prüfgrund und Zeitangaben, keine Nachrichteninhalte.

Ein eindeutiger Slotindex erlaubt ausschliesslich die von der Implementierung vergebenen Slots 0 bis 499. Gleichzeitige Captures konkurrieren atomar um dieselben Slots. Ein vorgelagerter Zähler allein entscheidet nicht über die Speichergrenze. Bei voller Ablage wird kein vorhandener Inhalt überschrieben; zusätzliche Einsendungen werden nicht aufgenommen. Pro Capture werden höchstens drei konkurrierende Einfügeversuche ausgeführt. Die Ablehnung durch den Spamschutz bleibt davon unabhängig.

## Verschlüsselung und Schlüsselwechsel

Die Datenbank enthält öffentlich erklärbare Metadaten und einen Base64-kodierten, authentifiziert verschlüsselten Payload. Dazu werden die nativen `sodium_crypto_secretbox`-Funktionen verwendet. Fehlt die PHP-Sodium-Erweiterung, stellt WordPress seine originale ParagonIE-Sodium-Kompatibilität bereit. Das Plugin enthält keine eigene Kryptografieimplementierung und kopiert keine zusätzliche Bibliothek.

Jede Verschlüsselung verwendet einen neuen kryptografischen Zufallsnonce mit 24 Bytes. Der 32-Byte-Schlüssel wird aus dem WordPress-Auth-Salt, dem Site-Bezug und einem eigenen Protokollzweck abgeleitet. Auch Anbieter, Formular, Prüfgrund, Erstellungszeit und ursprünglicher Ablauf sind authentifiziert. Eine nachträglich verkürzte Aufbewahrung bleibt zulässig; eine Verlängerung über den ursprünglichen authentifizierten Ablauf wird nicht akzeptiert.

Verschlüsselte Nachrichten bleiben personenbezogene Daten. Ein gemeinsam mit den WordPress-Salts erbeutetes Datenbankbackup kann entschlüsselt werden. Die Verschlüsselung schützt nicht vor kompromittiertem PHP-Code oder einem Administrator mit Zugriff auf dieselbe Installation.

Nach Änderung der Auth-Salts sind ältere Inhalte mit dem neuen Schlüssel nicht mehr lesbar. Die Oberfläche zeigt diesen Zustand; manuelle Löschung und Ablaufbereinigung bleiben möglich. Ein automatischer Datenschutzexport ordnet unlesbare Inhalte keiner Person zu. Der Eraser meldet unlesbare oder nicht löschbare Daten zur administrativen Prüfung, statt eine erfolgreiche Zuordnung zu behaupten.

## Aufbewahrung und Berechtigungen

Die Standardeinstellung beträgt sieben Tage; zulässig sind ein bis dreissig Tage. Eine kürzere neue Einstellung verkürzt auch den Ablauf vorhandener Einträge. Abgelaufene Einträge werden bei Lesen, Listen, Zählen und Datenschutzexport sofort ausgeblendet. Die physische Löschung erfolgt über den WordPress-Cron und beim nächsten Capture. Ein verzögerter Cron kann die physische Datenhaltung verlängern.

Lesen, Listen, Zählen und manuelle Löschung prüfen im Repository selbst `manage_options`. Der HTTP-Controller prüft für Löschungen zusätzlich POST und einen an die konkrete Eintrags-ID gebundenen Nonce. Öffentliche Captures vergeben keinerlei Leserechte.

Die WordPress-Exporter und -Eraser prüfen zusätzlich die nativen Fähigkeiten `export_others_personal_data` beziehungsweise `erase_others_personal_data`. Sie durchlaufen jeweils feste Slotbereiche von fünfzig Slots pro Seite. Dadurch verschiebt das Löschen früherer Einträge keine noch zu prüfenden Offsetseiten. Ausschliesslich übereinstimmende, gültige, normalisierte Kontaktadressen werden exportiert oder gelöscht. Die Bestätigungs- und Requestverwaltung bleibt beim nativen WordPress-Datenschutzablauf.

Die Website muss ihre Datenschutzerklärung, Rechtsgrundlage, Aufbewahrung und gegebenenfalls Backupfristen an die tatsächlich aktivierte Speicherung anpassen. WordPress, Formularanbieter, Hosting und E-Mail-Verarbeitung haben eigene Datenflüsse. Die Quarantäne ist keine rechtliche Zertifizierung.

## Prüfung

`tests/quarantine.php` prüft die echten Datenbank- und WordPress-Verträge einschliesslich Saltrotation, manipuliertem Ciphertext und Metadaten, Rollen, Export/Eraser, UTF-8-Grenzen, Aufbewahrung und vier gleichzeitig auf den letzten freien Speicherplatz zugreifenden Prozessen. Der native WPForms-Test prüft zusätzlich dessen frühen Honeypot-Abbruch, bei dem noch keine normalisierten Felder vorliegen: Die deklarierte Typauswahl bleibt erhalten und sensible beziehungsweise unbekannte Felder gelangen nicht in die Ablage.

Referenzen: [WordPress-Exporter](https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-exporter-to-your-plugin/), [WordPress-Eraser](https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-eraser-to-your-plugin/), [PHP Secretbox](https://www.php.net/manual/en/function.sodium-crypto-secretbox.php).

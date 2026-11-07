# WordPress Multisite

WP Antispam unterstützt ab 0.3.0 die einzelne Aktivierung pro Website und die Netzwerkaktivierung. Jede Website verwaltet ihre Einstellungen im eigenen WordPress-Backend. Anbieterfreigaben, Tabellen, Aufbewahrung, Quarantäneschlüssel und Tokenbezüge bleiben pro Website getrennt. Die Netzwerkaktivierung übernimmt keine pauschalen Inhalts- oder Länderregeln für andere Websites.

Die zentrale Netzwerkwartung startet stündlich auf der Hauptwebsite. Ein Aufruf bearbeitet höchstens 20 Websites und berücksichtigt ein weiches Zeitbudget von fünf Sekunden. Offene Arbeit wird mit einem nativen einmaligen Cron-Ereignis nach frühestens einer Minute fortgesetzt. WordPress-Cron hängt weiterhin von der tatsächlichen Ausführung ab. Normale Frontendbesuche durchlaufen keinen vollständigen Netzwerkloop.

Die Fortschrittsauswahl verwendet `get_sites()` und native Site-Metadaten mit einer Zykluskennung. Gelöschte Websites verschieben dadurch keine nachfolgenden Einträge aus einem Offsetfenster. Die Metadaten enthalten nur die interne Wartungskennung. Es werden keine selbst geschriebenen SQL-Fragmente gegen Coretabellen benötigt.

Neue Websites erhalten ihre eigenen Tabellen nach `wp_initialize_site`. Beim ersten Aufruf einer noch nicht migrierten Website wird die installierte eigene Schemaversion vor der Formularprüfung aktualisiert. Diese Migration folgt der festen Pluginversion; Besucher können darüber keine Tabellen oder Optionen auswählen.

Die Netzwerkdeaktivierung entfernt den zentralen Dispatcher und seine Fortsetzungen. Einzelne weiter aktivierte Websites verwenden ihre lokale Wartung. Die Deinstallation entfernt auch die zentralen Ereignisse und Wartungsmetadaten. Ihre Datenlöschentscheidung wird pro Website respektiert. Das ausdrückliche Löschen einer ganzen Website erfolgt über den nativen WordPress-Löschpfad einschliesslich ihrer eigenen Plugin-Tabellen.

Nachweis: `tests/multisite.php` prüfte 94 native Bedingungen über 28 tatsächlich angelegte Websites. Lokal lief WordPress 7.1.4 mit dem originalen SQLite-Integrationspaket. Die GitHub-Matrix prüft zusätzlich WordPress 6.6/PHP 8.2 und die aktuelle WordPress-Version/PHP 8.5 mit echtem MySQL. Der konkrete erfolgreiche Prüflauf steht in [verification.md](verification.md).

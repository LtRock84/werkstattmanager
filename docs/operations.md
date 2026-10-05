# Betrieb und Datenschutz

Stand: 2026-10-02. Gilt für den internen Prüfkandidaten, nicht als Betriebsfreigabe.

## Einrichtung und laufender Betrieb

Eine Werkstattorganisation je Installation. Rollen serverseitig über Nextcloud-Gruppen;
Administratoren dürfen Gruppen konfigurieren. Meister sollten eigene Konten nutzen.
Tablet-Konten nur der Tabletgruppe zuordnen und nicht mit Kundendateien oder anderen
vertraulichen Nextcloud-Freigaben ausstatten. Mitarbeiter-Auswahl ist kein Login.
HTTPS verwenden. Fahrerlinks erlauben jedem Besitzer den eingeschränkten Fahrzeugzugang;
bei Verlust neu erzeugen oder widerrufen. Alte Sitzungen werden bei jedem Zugriff geprüft.

Das Dashboard lädt bei sichtbarer Seite alle zehn Sekunden nach. Geöffnete Dialoge und
Einstellungseingaben werden nicht durch Polling ersetzt. Bei verlorener Verbindung
keine neue Zeitbuchung vornehmen. Bereits serverseitig gestartete Zeiten laufen weiter.
Nach unklarer Antwort aktualisieren und den Zustand prüfen, bevor eine neue Aktion erfolgt.

System-Cron muss Nextcloud-Hintergrundjobs ausführen. Maintenance arbeitet in Batches
von 100 und frühestens alle fünf Minuten. Zustellversuche verwenden Backoff und enden
nach zehn Fehlschlägen. Dashboard-Einträge bleiben unabhängig davon verfügbar.
Fehlgeschlagene Zustellungen werden im Meisterdashboard angezeigt. Unter Einstellungen
kann der Meister sie seitenweise prüfen und einzeln erneut vormerken. Die App prüft die
Empfängerrechte erneut und verwirft Zustellungen an ehemalige Meister. Wiederholungsversuche
und die vorherige Versuchszahl werden im Änderungsprotokoll festgehalten. Bei wiederholtem
Fehlschlag Nextcloud-Logs prüfen und die Ursache beheben. Die Zustellung erfolgt weiterhin
durch Cron; Vormerken bedeutet noch nicht erfolgreiche Zustellung.
Keine Zugangsdaten oder kompletten Nutzdaten in Fehlerprotokolle übernehmen.

## Sicherung, Aktualisierung und Rückkehr

Datenbank und Nextcloud-AppData bilden zusammen einen Sicherungsstand; zusätzlich
Nextcloud-Konfiguration und exakten App-Quell-/Buildstand aufbewahren. Bei Object Storage
auch dessen Sicherungsverfahren berücksichtigen. Dateizugriffe erfolgen über OCP-APIs.
Wiederherstellung nur als zusammenpassendes Paar aus Datenbank und Dateien testen.

Vor jeder Aktualisierung Testkopie erstellen, Migrationen ausführen und Zahl sowie Inhalte
von Fahrzeugen, Aufträgen, Intervallen und Anhängen vergleichen. Keine Migrationen
rückwärts ausführen. Bei Fehlern vollständigen Backupstand wiederherstellen. Die erste
Version hat keinen veröffentlichten Upgradevorgänger; das ist kein bestandener Upgradetest.
Deaktivieren/Entfernen der App ist keine fachliche Datenlöschung. Es existiert bewusst
keine automatische Deinstallationsroutine zum Löschen von Werkstattakten.

## Daten und Aufbewahrung

Kundenkontakte, Mitarbeiterzuordnungen, Fahrername, Kilometerstände, Mängel, Fotos,
Auftragsprotokolle und Zeitintervalle werden lokal in Nextcloud gespeichert. Keine
Lieferantenanbindung, Telemetrie oder Weitergabe an externe Dienste. Browsercode enthält
keine Secrets. Die Entwicklung lädt Bibliotheken; das ist kein Datenfluss aus Fahrzeugakten.

Fachobjekte werden standardmäßig archiviert. Vor Produktivbetrieb muss der Betreiber
Aufbewahrungsdauer, zulässige Zeitkorrekturen und Zuständigkeiten bestimmen. CSV-Exporte
enthalten interne Informationen; exportierte Dateien ebenfalls schützen und fristgerecht
löschen. CSV-Export ist auf 100.000 Datensätze begrenzt und schützt gegen Formelauswertung.

Administrative Anonymisierung leert definierte strukturierte Felder. Freitext, Fotos,
Kennzeichen, FIN und exportierte Kopien werden dadurch nicht automatisch anonymisiert.
Die kontrollierte Fahrzeuglöschung erfordert Archivierung, keine aktive Belegung und eine
explizite Bestätigung der Fahrzeug-ID. Zugehörige Buchungen und Verknüpfungen werden
bereinigt; Dateilöschung erfolgt nachgelagert. Vorher Export/Backup und Löschumfang prüfen.
Nextcloud-Benutzerlöschung entfernt historische Mitarbeiter-/Auditdaten nicht automatisch.

## Leistungsgrenzen

Hauptlisten sind paginiert, das Dashboard zeigt begrenzte Ausschnitte und weist darauf hin.
Bei mehr als 100 offenen Fristen ist zusätzlich die vollständige Fristenliste zu prüfen;
die Übersicht garantiert derzeit keine globale Dringlichkeitssortierung. Detailhistorien
und Auswahlbibliotheken werden seitenweise vollständig nachgeladen. Große Bestände müssen
im Pilot vermessen werden. Keine automatische Einsatzplanung oder Offline-Zeitbuchung.

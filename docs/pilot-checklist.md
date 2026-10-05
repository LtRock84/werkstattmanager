# Prüfung auf der tatsächlichen Nextcloud-Installation

Stand 2026-10-05: Die folgenden Punkte bilden die vollständige Abnahmeliste.
Aktuelle Nachweise stehen im [Prüfbericht](test-report.md); nicht ausdrücklich
nachgewiesene Teilprüfungen bleiben offen.
Nur mit Testkonten und Testfahrzeugen in einer isolierten Instanz durchführen.

## Installation und Datenbestand

- Paket-SHA-256, Quellstand, Nextcloud/PHP/GD/DB-Version, Cron, Storage und Proxy festhalten.
- Frische Installation aus dem tatsächlichen Paket; Migrationen, fünf Vorlagen und fünf
  Textbausteine prüfen. Wiederholte Aktivierung darf keine Duplikate erzeugen.
- Daten anlegen, App deaktivieren/aktivieren, Backup/Restore prüfen. Anlagen müssen weiterhin
  herunterladbar sein. Kein behaupteter Upgradetest ohne realen vorherigen Datenstand.

## Meister und Tablet

- Standardauftrag aus Fahrzeugakte mit „Neuer Auftrag“, Vorlage, „Freigeben“ erzeugen.
- Freitext, mehrere Bausteine, freie Sollzeit, getrennte Termine, Entwurf und Freigabe prüfen.
  Ungültige Terminreihenfolge zurückweisen; Vorlagenänderung verändert alten Auftrag nicht.
- Mit zwei gleichzeitig abgeschickten Requests denselben Mitarbeiter bzw. Auftrag starten:
  genau eine erfolgreiche Buchung, keine offene verwaiste Zeit und keine doppelte Belegung.
  Anschließend zwei verschiedene Mitarbeiter auf zwei Aufträgen gleichzeitig testen.
- Pause, Übernahme, Browserneustart, Antwortverlust/Wiederholung und Fertigmeldung prüfen.
  Mitarbeiter-Auswahl wird nach Erfolg zurückgesetzt. Pausen zählen nicht.
- Teile anfordern, mit Pause anfordern, bestellen, empfangen, ablehnen/stornieren. Offene
  Anforderungen blockieren Abnahme. Nacharbeit und begründete Zeitkorrektur einschließlich
  Überlappungsverbot und unveränderter Originalwerte im Journal kontrollieren.
- Dauerüberschreitung darf Arbeit nicht blockieren. Zeitdarstellung über beide Zeitumstellungen
  prüfen. Kontrollieren, dass eine geplante Uhrzeit keine Zeitbuchung startet.

## Rollen, Fahrer und Dateien

- Unberechtigtes Konto, Tablet und Meister gegen sämtliche API-Routen prüfen. Veränderte
  Objekt-IDs dürfen Tablet keinen Zugriff auf Entwürfe, Kunden, Exporte oder Fahrzeugdateien geben.
- Ohne CSRF-Token und mit fremder Herkunft schreiben: Ablehnung. Öffentliche Anmeldung darf
  nur einen gültigen Fahrzeugtoken akzeptieren; Fragment wird aus der Adresse entfernt.
- Link widerrufen/rotieren und Fahrzeug archivieren: bestehende Sitzung verliert Zugriff.
  Fahrerantwort darf keine internen Notizen, Namen von Mitarbeitern, Zeiten oder Fotos enthalten.
- Neue Meldung bleibt ungeprüft; veröffentlichen, zusammenführen, beheben und öffentliche Liste
  prüfen. Fremde Fahrzeug-/Mängel-ID beim Upload und Zusammenführen zurückweisen.
- Erlaubte Bilder, falsche MIME, Nichtbilder, übergroße Dateien, übergroße Pixelzahl und
  sechstes Foto testen. Metadatenbereinigung, Uploadwiederholung und verwaiste Datei prüfen.
- Wiederkehrende Frist nach Datum/km, ODER-Kriterium, Bestätigung externer Kilometer und
  Notification-Wiederholung nach Zustellausfall prüfen. Nach zehn Fehlschlägen muss der
  Meister den Eintrag sehen und erneut vormerken können. Mit entzogenem Empfängerrecht
  wird die Zustellung verworfen; doppeltes Wiederholen erzeugt keine zweite Zustellung.

## Oberfläche und Betrieb

- Tablet Hoch-/Querformat, Tastatur ohne Maus, Screenreader, Zoom 200 %, Dark Mode und
  Kontraste gegen WCAG AA prüfen. Lange Bezeichnungen und Fehlertexte einbeziehen.
- Zehnsekunden-Aktualisierung darf keine geöffneten Eingaben überschreiben. Netzverlust testen.
- Druckbericht, CSV-Formelschutz, große Datenmengen und sämtliche Pager prüfen.
- Erst nach behobenen kritischen Befunden, validierten Metadaten und dokumentierter
  Verantwortlichkeit für Aufbewahrung den begrenzten Pilotbetrieb freigeben.

# Begrenzter Pilot und Rückkehrverfahren

Stand 2026-10-11, Version 0.4.0. Vorbereitung, noch keine Freigabe für den Produktivbetrieb.

## Freigabepunkte

- Das genaue Paket/Commit muss grüne CI-Prüfungen und einen dokumentierten
  Installationstest haben. SHA-256 festhalten, Paket unverändert aufbewahren.
- Echter iPad-Test in Safari und Chrome: Hoch-/Querformat, große Touch-Schaltflächen,
  Auswahl des Namens, Start/Pause/Übernahme/Fertigmeldung, Eingaberücksetzung und
  verständliche Netzfehler. Dieser Gerätetest steht noch aus.
- Meister- und Tabletrollen getrennt einrichten. Ein Tablet-Konto hat keine Admin-
  oder Meistergruppen. Andere Nextcloud-Apps werden durch diese App-Rolle nicht
  automatisch gesperrt: keine vertraulichen Dateien oder Freigaben für das Tablet,
  gegebenenfalls Nextcloud-App-Zugriff je Gruppe separat einschränken.
- Verantwortlichkeit für Backup, Aufbewahrung, Korrekturen und Abnahme festlegen.
- Einen aktuellen Sicherungsstand von Datenbank, Dateien/AppData, Konfiguration und
  App-Code erstellen und dessen Wiederherstellung in der Testkopie bestätigen.

## Vorschlag für den ersten Einsatz

Zunächst ein Meister, ein Tablet und zwei Mitarbeiter mit drei bis fünf ausgewählten
Flottenfahrzeugen. Für eine Woche einfache Aufträge parallel zum bisherigen
Werkstattverfahren führen. Keine Fahrzeugfreigabe allein aus der App ableiten.
Täglich Start-/Endzeiten, pausierte Aufträge, Teile und Meisterabnahmen vergleichen.
Täglich Nextcloud-Fehler und fehlgeschlagene Benachrichtigungen kontrollieren.

Für Kundenmails anschließend wenige ausdrücklich ausgewählte Testkunden verwenden.
SMTP-Absender, personalisierte Vorlagen, Kalender und reale Testmailbox abnehmen.
Bestehende Kunden bleiben beim Upgrade von automatischen Fristenmails ausgenommen.
Vor Aktivierung letzte Erledigung, Fälligkeit und Kundenwunsch kontrollieren:
auch bereits überfällige offene Fristen können einmal erinnert werden.
Eigene Plan-/Istbeträge mit dem bisherigen Verfahren abgleichen; Auswertung ist
keine Rechnung oder Buchhaltung.

## Installation im freigegebenen Wartungsfenster

1. Nextcloud-/PHP-/Datenbankversion und freien Speicher mit dem Prüfbericht abgleichen.
2. Backup und SHA-256 des freigegebenen Pakets kontrollieren.
3. Archiv vor Extraktion mit `scripts/check-package.py` prüfen; ausschließlich
   `werkstattmanager` in das tatsächlich konfigurierte `custom_apps`-Verzeichnis legen.
   Nextcloud-Webbenutzer muss die Dateien lesen können. Keine fremden App-Ordner ersetzen.
4. Als Nextcloud-Webbenutzer `php occ app:enable werkstattmanager` ausführen.
   Bei einem Upgrade `php occ upgrade` ausführen und dessen Ergebnis dokumentieren.
5. Meistergruppe `werkstatt-meister` und Tabletgruppe `werkstatt-tablet` verwenden
   oder als Administrator in den App-Einstellungen passend konfigurieren.
6. Über die normale HTTPS-Adresse `/apps/werkstattmanager/` anmelden.
   Das Tablet-Konto landet automatisch in der eingeschränkten Werkstattansicht.
7. Einen fiktiven Auftrag starten, pausieren, fertig melden und abnehmen; parallel
   Nextcloud-Dateien und Office prüfen. Danach erst die gewählten Pilotfahrzeuge anlegen.

Die Befehle müssen innerhalb des echten Nextcloud-App-Containers unter dessen
Webbenutzer ausgeführt werden. Dieses Dokument enthält bewusst keinen pauschalen
Installationsbefehl, der einen ungeprüften Container oder Pfad überschreiben könnte.

## Abbruch und Wiederherstellung

Bei fehlerhaften Buchungen, Datenverlust, neuen wiederholten Nextcloud-Fehlern oder
Ausfall anderer Apps Pilot sofort anhalten. Laufende Zeiten und offenen Zustand
festhalten; als Webbenutzer `php occ app:disable werkstattmanager` ausführen.
Das deaktiviert die App und löscht ihre Fachakten nicht.

Keine Datenbanktabellen manuell löschen und keine alte App-Version über neue
Migrationen kopieren. Bei beschädigtem Zustand das zusammenpassende Backup von
Datenbank, Dateien/AppData, Konfiguration und App-Code nach dem vorhandenen
Nextcloud-Wiederherstellungsverfahren zurückspielen und Datenbestand kontrollieren.
Erst nach Ursachenklärung wieder aktivieren. Siehe [Betrieb](operations.md).

## Ende des Piloten

Abweichungen dokumentieren, kritische Fehler beheben und erforderliche Tests erneut
ausführen. Erst anschließend Umfang vergrößern. Eine Store-Veröffentlichung ist
eine gesonderte Entscheidung; siehe [Release](release.md).

# Vorbereitete Store-Beschreibung

Entwurf vom 2026-10-05. Noch nicht eingereicht; öffentliche Veröffentlichung,
Signatur und Geräteabnahme stehen aus. Die internen Pilotkennzeichnungen in
`info.xml` bleiben bis zur tatsächlichen Freigabe erhalten.

## Vorgesehene Angaben

| Feld | Inhalt |
| --- | --- |
| App-ID | werkstattmanager |
| Name | Werkstattmanager |
| Kategorie | Organisation |
| Kurzbeschreibung | Fahrzeuge, Aufträge und Werkstattzeiten |
| Lizenz | AGPL-3.0-or-later |
| Quellcode | https://github.com/LtRock84/werkstattmanager |
| Fehlerhilfe | https://github.com/LtRock84/werkstattmanager/issues |
| Kompatibilität | Nextcloud 35; PHP 8.3–8.5; PostgreSQL und MariaDB geprüft |

Die GitHub-Adressen sind derzeit privat. Sie dürfen erst nach der ausdrücklichen
Freigabe des Eigentümers als öffentlich erreichbar beschrieben werden.
Eine separate MySQL-Prüfung und neue Nextcloud-Hauptversionen stehen aus.

## Vorgesehener Beschreibungstext

Werkstattmanager unterstützt die tägliche Arbeit in einer KFZ-Werkstatt:
Kundenfahrzeuge und eigene Flottenfahrzeuge verwalten, Termine und Fristen im
Blick behalten und Arbeitsaufträge mit Vorlagen oder freier Eingabe erstellen.

Auf dem Werkstatt-Tablet wählen Mitarbeiter ihren Namen, starten einen Auftrag,
pausieren ihn oder melden die Arbeit fertig. Arbeitszeiten bleiben pro Mitarbeiter
nachvollziehbar. Jeder Mitarbeiter kann einen aktiven Auftrag bearbeiten;
verschiedene Mitarbeiter können gleichzeitig an verschiedenen Aufträgen arbeiten.
Pausierte Aufträge können Kollegen übernehmen. Der Meister bestätigt den Abschluss.

Teile können aus dem Auftrag angefordert werden. Fahrer melden Mängel über einen
widerrufbaren Fahrzeuglink ohne Nextcloud-Konto. Der Meister prüft neue Meldungen,
bevor kurze Informationen öffentlich für Fahrer sichtbar werden.

Vorausgesetzt werden eine Werkstattorganisation, eingerichtete Meister- und
Tabletgruppen sowie regelmäßige Nextcloud-Hintergrundjobs. Die Namensauswahl am
gemeinsamen Tablet ist eine Zuordnung ohne persönlichen Identitätsnachweis.
Die erste Version umfasst keine Rechnungsstellung, Lagerbuchhaltung, automatische
Personalplanung oder Offline-Zeitbuchung.

## Datenschutztext

Werkstattdaten und Anhänge werden in der eigenen Nextcloud gespeichert. Es gibt
keine Telemetrie und keine Lieferantenanbindung. Fahrzeuglinks geben ihren Besitzern
Zugriff auf die vom Meister freigegebenen Fahrerinformationen; sie sind widerrufbar.
Der Betreiber bestimmt Aufbewahrung, Rollen und Zugriff auf exportierte Dateien.
Weitere Informationen: [Betrieb und Datenschutz](operations.md).

## Noch vor Einreichung erforderlich

- Öffentlich erreichbarer Quellcode und Support; bewusst freigegebene Sichtbarkeit.
- Screenshots mit ausschließlich fiktiven Daten, beispielsweise Meisterübersicht,
  schnelle Auftragserstellung und Tabletansicht. Keine echten Kennzeichen oder Namen.
- Nextcloud-App-Zertifikat, final signiertes Paket und erreichbare Download-URL.
- Tablet-/Integrationsabnahme, Release- und Installationsnachweis des signierten Pakets.
- Eigentümerkonto im App Store; Beschreibung und unterstützte Versionen kontrollieren.

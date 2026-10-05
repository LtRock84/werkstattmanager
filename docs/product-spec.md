# Werkstattmanager – verbindliche Produktspezifikation

Stand 2026-10-05. Eine Werkstattorganisation pro Installation,
beliebig viele Meister und Tablets.

Automatische Qualitätsprüfungen umfassen echte PostgreSQL-/MariaDB-Installationen.
Interne Prüfkandidaten bleiben unsigniert. Öffentlicher Quellcode, Support,
Zertifikat und signiertes Paket sind Voraussetzungen für eine Store-Veröffentlichung.
Ein Pilot benötigt Geräteabnahme und dokumentiertes Sicherungs-/Rückkehrverfahren.
Veröffentlichungsunterlagen enthalten keine standortspezifischen Betriebsdaten,
persönlichen Arbeitsplatzpfade oder Zugangsdaten. Private Prüfunterlagen und
Signierschlüssel gehören nicht in Quellrepository oder Installationspaket.

## Rollen
Meister: persönlicher NC-Account, konfigurierbare Gruppe. Tablet: dedizierter NC-Account,
eigene App-Rolle, Mitarbeiterwahl ohne PIN. Diese Wahl ist keine Identitätsbestätigung.
Fahrer: Fahrzeuglink ohne NC-Account, nur Meldungen und freigegebene offene Mängel.

## Schneller Auftrag
Aus Fahrzeugakte: Neuer Auftrag → Vorlage → Freigeben (höchstens drei Aktionen).
Fahrzeugsuche nach Kennzeichen, Nummer, FIN, Kunde; zuletzt verwendete Fahrzeuge.
Vorlagen kopieren Titel, Beschreibung, Schritte und optionale Sollminuten.
Mehrere Textbausteine ergänzen Schritte ohne automatische Zeitänderung; freie Eingabe.
Vorlagen und Bausteine sind editierbar, sortierbar und favorisiert. Auftrag als Vorlage speichern.
Nur Fahrzeug und Titel sind Pflicht. Sollminuten, Beginn, Fertig-bis sind unabhängige
optionale Felder; Ende darf nicht vor Beginn liegen. Schnellzeiten 15/30/45/60/90/120.
Entwurf ist nicht am Tablet sichtbar. Geplanter Beginn startet keine Buchung.

## Auftragsablauf
Entwurf → Offen → In Arbeit → Zur Abnahme → Abgeschlossen; zusätzlich pausiert und storniert.
Genau ein laufender Auftrag je Mitarbeiter und genau ein aktiver Mitarbeiter je Auftrag.
Start, Pause, Übernahme, Fertigmeldung sind serverseitig atomar und wiederholbar.
Pause beendet das Intervall. Kollegen dürfen pausierte Aufträge übernehmen.
Browserende beendet keine Zeit. Mitarbeiterwahl wird nach erfolgreicher Buchung zurückgesetzt.
Fertigmeldung erfasst Arbeitsschritte/Notizen; Abnahme durch Meister, Nacharbeit möglich.
Zeit je Auftrag/Mitarbeiter, keine Schritt-Timer, Pausen zählen nicht. Korrekturen mit
Grund und erhaltenem Original. Lange Buchungen ab 10 Stunden sichtbar. Soll/Ist/Vergleich.

## Fahrzeuge, Termine, Mängel
Kunden/Firmen, Kundenfahrzeuge/eigene Flotte, Stammdaten, Historie und Anhänge.
Datums- und Kilometerfristen, ODER-Verknüpfung, Wiederholung ab bestätigter Erledigung.
Warnungen standardmäßig 30/7 Tage, konfigurierbar. Kilometerhinweise nennen Datenalter.
Externe Kilometerstände erst nach Meisterbestätigung übernehmen.
QR-Code für Flottenfahrzeuge; widerrufbarer Token. Meldung: Kategorie, Beschreibung,
Dringlichkeit, optional Name/Kilometer und bis fünf Fotos. Keine automatische Veröffentlichung
oder Auftragserstellung. Meister veröffentlicht eine bereinigte Kurzinfo; keine Fotos,
Kontaktdaten, internen Texte, Mitarbeiter oder Zeiten im öffentlichen Formular.
Dubletten zuordnen; Mängel beim Abschluss einzeln bestätigen. Keine Fahrfreigabe durch App.

## Teile und Ausgaben
Teil/Menge/optionale Nummer/Dringlichkeit/Notiz/Foto; Auftrag, Fahrzeug, Mitarbeiter automatisch.
Anfordern oder Anfordern und pausieren. Angefordert → Bestellt → Eingetroffen,
zusätzlich abgelehnt/storniert. Bestellungen außerhalb der App. Offene Anforderungen
verhindern Abnahme. Dauerhafter Eingang plus Nextcloud-Benachrichtigung.
CSV-Export für Meister, druckbarer Auftragsbericht, QR-Druck.

## Sicherheit und Betrieb
OCP-APIs, objektbezogene Autorisierung, CSRF, begrenzte validierte Uploads, Rate Limits,
gehashte öffentliche Tokens im URL-Fragment; Sitzung prüft Widerruf bei jedem Zugriff.
AppData für Anhänge. DB-Migrationen und eindeutige aktive Belegungen. Keine Secrets in Logs.
UTC intern, Europe/Berlin standardmäßig, Tagesfristen ohne UTC-Verschiebung.
Polling alle zehn Sekunden nur sichtbar; keine Offline-Buchung. Übersetzbar, Dark Mode,
Tastatur und Screenreader/WCAG AA als Prüfziel. Wiederholbare Jobs und Benachrichtigungen.
Archivieren statt automatische Löschung. Personenbezogene Daten gezielt anonymisieren;
Export vor endgültiger administrativer Löschung; Aufbewahrungsfristen vor Produktivstart festlegen.
Keine fachliche Löschung bei Deaktivierung oder Entfernung der App.

## Grenzen
Keine Abrechnung, Lagerbuchhaltung, Lieferanten-API, Telematik, automatische Personalplanung,
Kalender-Synchronisation oder Offline-Zeitbuchung. Zunächst internes Paket/Pilotbetrieb.
Produktionsfreigabe erst nach Installation und vollständiger Testmatrix auf
der vorgesehenen Betriebsumgebung.

## Umsetzungsstand 2026-10-02

Kernfunktionen als interner Prüfkandidat implementiert. Erstinstallation erhält fünf
editierbare Auftragsvorlagen und fünf Textbausteine; Sollzeiten sind Beispielwerte.
Fachtests, statische Prüfung und Frontendbuild liegen vor. Maßgeblich für tatsächliche
Abnahme und verbleibende Funktionsgrenzen ist [docs/test-report.md](test-report.md).
Die dort genannten offenen Punkte sind keine implizite Änderung der Anforderungen.

## Bedienbarkeit

Die Oberfläche richtet sich ausdrücklich an Personen ohne Computererfahrung.
Jede Hauptansicht erklärt den nächsten Schritt in kurzer Alltagssprache. Wichtige
Hinweise sind sichtbar; Zusatzhilfen lassen sich per Antippen und Tastatur aufklappen.
Auftragserstellung: drei klar benannte Schritte, optionale Details einklappbar,
Freigabe und Entwurf erklärt. Der schnelle Vorlagenablauf bleibt erhalten.
Tablet: Namensauswahl und gesperrte Aktionen erklären; Start/Pause/Fertigmeldung
mit konkreter Bestätigung der Zeitbuchung. Pflichtfelder kennzeichnen, fehlgeschlagene
Buchungen nicht als Erfolg anzeigen, Eingaben beim Fehler erhalten. Fahrer können
versehentlich ausgewählte Fotos vor dem Senden entfernen. Keine Datenmodell- oder
Berechtigungsänderung durch diese Überarbeitung. Visuelle Abnahme und Tests mit
ungeübten Anwendern bleiben Voraussetzung für eine belegte Usabilitybewertung.

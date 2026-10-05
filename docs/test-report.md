# Prüfbericht – Werkstattmanager

Stand 2026-10-05. **Interner Prüfkandidat, keine vollständige Produktionsfreigabe.**
Dieser Bericht beschreibt die Reichweite der Prüfungen ohne standortspezifische
Betriebsdaten. Automatische Prüfungen sind über die
[GitHub-Pipeline](https://github.com/LtRock84/werkstattmanager/actions/workflows/quality.yml)
nachvollziehbar; bei privatem Repository benötigen sie entsprechende Zugriffsrechte.
Ein neues Paket muss die Pipeline erneut bestehen.

## Erfolgreich ausgeführt

| Prüfung | Ergebnis und Reichweite |
| --- | --- |
| PHP 8.3/8.4/8.5 | Native Syntaxprüfungen und jeweils 24 Fachtests auf Version 0.1.5; In-Memory-Tests ersetzen keine SQL-Integration |
| PHP-Analyse | Coding Standard und Psalm unter PHP 8.3 bestanden; Psalm 6 hat eine Werkzeuggrenze unter PHP 8.5 |
| Frontend | Node 24.18.0/npm 11.16.0: Typecheck, ESLint, 13 Vitest-Tests, Runtime-Audit, Build und drei Starttests des fertigen Browser-Bundles auf 0.1.5 bestanden |
| Metadaten und Paket | Offizielles App-Store-XSD, Versionsgleichheit, sichere Archivgrenzen und bytegleiche wiederholte Paketbauten auf 0.1.5 bestanden |
| CI-Installation | Exaktes Kandidatenpaket 0.1.5 in Nextcloud 35.0.1 mit PostgreSQL 17 und MariaDB 11.8 installiert; Vorlagen, HTTP-/SQL-Abläufe und Datenerhalt nach Reaktivierung/Neustart geprüft |
| Gleichzeitigkeit | Konkurrierende Starts für denselben Mitarbeiter oder Auftrag: genau einer erfolgreich; verschiedene Mitarbeiter können verschiedene Aufträge parallel bearbeiten |
| Zeiten und Status | Idempotente Pause, Übernahme, Mitarbeiterzuordnung, geschlossene Intervalle, Fertigmeldung und Meisterabnahme geprüft; verlorene Antworten und Neustart erzeugen keine Doppelbuchung |
| Rollen | Getrennte Meister-/Tabletrollen; Tablet erhält keinen Zugriff auf Kundenverwaltung, Einstellungen oder Entwürfe; ungültige Terminreihenfolge zurückgewiesen |
| Uploads und Fahrerzugang | Gezielte Integrationstests auf 0.1.4: Fahrzeugbindung, Widerruf, CSRF, Größen-/Pixelgrenzen, fünf Fotos, Metadatenbereinigung und geschützte Downloads geprüft; kein vollständiges Sicherheitsaudit |
| Wiederherstellung | Zusammengehörige Datenbank- und Dateisicherung nach App-Nutzung in separater Instanz wiederhergestellt; Werkstattdaten und Anlagen anschließend abrufbar |
| HTTPS-Werkstattablauf | Chrome auf 0.1.4: Vorlagenauftrag, Start, Teileanforderung mit Pause, Übernahme, Protokoll und Abnahme nach Teileeingang erfolgreich |
| Unabhängige Zeitmessung | HTTPS auf 0.1.4: Auftrag A mit 12 + 10 Sekunden = 22 Sekunden; 8 Sekunden Pause ausgeschlossen. Auftrag B parallel 20 Sekunden. Mitarbeiterzuordnung gegen Laufzeitmessung geprüft |
| Office | EuroOffice-Connector 11.0.5/Dokumentserver 9.3.4.37: fiktive DOCX über HTTPS geöffnet, bearbeitet, gespeichert und erneut geöffnet; Inhalt zusätzlich über TLS-geprüftes WebDAV im DOCX nachgewiesen |
| Bildschirmgrößen | Chrome bei 768×1024 und 1024×768 ohne horizontalen Überlauf; dies ersetzt keinen physischen Tablet-/Touch-Test |
| App-Upgrade | Kandidatenpaket 0.1.5 über 0.1.4 installiert; bestehende Fahrzeuge, Aufträge, Zeiten, Mitarbeiter, Vorlagen und Textbausteine unverändert |

0.1.5 und 0.1.6 verändern gegenüber 0.1.4 keine Fachlogik oder Migrationen.
0.1.6 bereinigt Veröffentlichungsunterlagen und beschränkt die Paketdokumentation
auf eine explizite Freigabeliste. Die Paketwerkzeuge müssen dafür erneut geprüft werden.

## Nicht ausgeführt / offen

- Physisches iPad mit Safari und Chrome: Touch, Hoch-/Querformat, Netzverlust und Bedienung durch ungeübte Anwender.
- Separate MySQL-Prüfung; neue Nextcloud-Hauptversionen und vollständige Kombination aller PHP-/DB-Versionen.
- Office-Mehrbenutzerkonflikte und vollständiges Zusammenspiel aller Integrationen einer Betriebsumgebung.
- Allgemeiner Cronbetrieb, Dauerlast, Datenbank-/Storage-Ausfälle und vollständige Missbrauchs-/Rate-Limit-Prüfung.
- Vollständige WCAG-AA-/Screenreader-Abnahme und zusätzlicher Übersetzungskatalog.
- Nextcloud-App-Zertifikat, finale Signierung, Installation des signierten Pakets und Store-Veröffentlichung.

## Bekannte Grenzen

- Dashboardbereiche zeigen höchstens 100 Einträge und kennzeichnen die Begrenzung.
  Bei umfangreichen Beständen die vollständige Fristenliste prüfen.
- Administrative Aktionen verwenden Versionskonflikte; nicht jede Aktion speichert eine Wiederholungsantwort.
- Mitarbeiterwahl am Tablet ist eine Zuordnung ohne persönlichen Identitätsnachweis.
- App-Rollen begrenzen nicht automatisch andere Nextcloud-Apps oder Freigaben des Tabletkontos.
- Aufbewahrung, Sicherung und Rückkehrverfahren müssen vor Produktivbetrieb festgelegt sein.

## Fehlgeschlagen / blockiert

Die frühere Metadatenprüfung ohne Supportadresse und frühere Browserstart-/Kontrastfehler
wurden behoben. Es besteht keine offene technische Sperre für die durchgeführten Prüfungen.
Die oben aufgeführten, noch nicht ausgeführten Abnahmen verhindern eine vollständige
Produktionsfreigabe. Ein bestandener Build oder eine grüne CI allein genügt dafür nicht.

## Nächste Abnahmen

Das genaue Kandidatenpaket anhand der [Pilotcheckliste](pilot-checklist.md) auf der
vorgesehenen Testumgebung prüfen. Danach einen begrenzten Pilot nach
[Pilot- und Rückkehrverfahren](pilot-rollout.md) durchführen. Store-Vorbereitung:
[Release](release.md). Es findet keine automatische Veröffentlichung statt.

# Architektur und Schnittstellen

## Zielmatrix und Werkzeuge (2026-10-05)

- Ziel: Nextcloud 35, PHP 8.3–8.5. Die CI installiert Nextcloud 35.0.1 mit
  PostgreSQL 17 und MariaDB 11.8. MySQL ist noch nicht separat geprüft.
- PHP-Syntax und Fachtests: 8.3/8.4/8.5; Coding Standard und Psalm 6 unter 8.3.
  Psalm 6 wird wegen seiner Werkzeuggrenze nicht unter PHP 8.5 ausgeführt.
- Frontend: Node 24.18.0, npm 11.16.0, Vue 3/TypeScript, Vite und Nextcloud Vue 9.
  Ein npm-Lockfile und `.nvmrc` legen die reproduzierbare Toolchain fest.
- Klassische Nextcloud-App mit öffentlichen OCP-Schnittstellen; AppAPI nicht erforderlich.
- Die Metadatenmatrix ist keine Produktionsfreigabe. Tatsächliche Prüfergebnisse
  und noch offene Abnahmen stehen im [Prüfbericht](test-report.md).

## Komponenten und Persistenz

Controller übernehmen Request/Response und sichere Fehlerübersetzung; Services autorisieren
und koordinieren Fachvorgänge. Store verwendet ausschließlich öffentliche OCP QueryBuilder-
Verträge und eine feste Tabellen-/Spaltenliste. JSON wird nur für geordnete Schritte,
Protokolldetails, Warnschwellen und idempotente Antwortkopien verwendet.

Fachobjekte liegen in wm_*-Tabellen; IDs sind zufällige 128-Bit-Kennungen.
Versionszähler erzwingen Compare-and-swap bei Änderungen. Die Belegung wm_active besitzt
separate Unique Constraints für employee_id und order_id. Start, Ende, Belegung,
Auftragsstatus, Änderungsprotokoll und Wiederholungsantwort sind Teil einer Transaktion.
Parallel entstandene Unique-/Deadlock-/Versionskonflikte werden als HTTP 409 ausgegeben.
Keine neue Zeiterfassung bei geplantem Beginn, Browserende oder Reload.

Mitarbeiterzeilen serialisieren Start und Zeitkorrektur. Fahrzeugzeilen koordinieren
Auftragserstellung mit Archivierung. Bereits veröffentlichte Migrationen unverändert lassen;
neue Änderungen brauchen eine neue Migration. Die Erstversion hat keinen Upgradevorgänger.

## Rollen

- Nextcloud-Administratoren: initiale Einrichtung und Datenschutzfunktionen, außerdem Meisterrechte.
- Meistergruppe: alle Werkstattdaten und Abläufe; Benachrichtigungen an diese Gruppe.
- Tabletgruppe: freigegebene Aufträge, minimierte Fahrzeugdaten, Mitarbeiterliste,
  Buchungen/Arbeitsprotokoll/Teileanforderungen und Auftragsanhänge.
- Nicht zugeordnet: kein App-Zugriff.
- Fahrer: temporäre Sitzung, an genau einen widerrufbaren Fahrzeuglink gebunden.

Tablet-Konto und ausgewählte Mitarbeiter-ID bleiben getrennte Angaben. Auswahl ist keine
Authentifizierung der Person. Ein dediziertes Tablet-Konto ohne weitere Nextcloud-Freigaben
verwenden; App-Rechte beschränken nicht automatisch andere Apps des Kontos.

## HTTP-Verträge

Basis `/apps/werkstattmanager` (mit Nextcloud-URL-Generator auch in Unterverzeichnissen).

| Methode / Pfad | Zweck |
| --- | --- |
| GET `/api/v1/records/{kind}` | Paginierte Listen, 100 Ergebnisse, `offset`, `id`, `q`, Objektfilter |
| POST `/api/v1/records/{kind}` | Meister-Stammdaten; `{id?, data}` und bei Änderung `data.version` |
| POST `/api/v1/orders` | Auftrag erstellen; `{data}` mit Fahrzeug/Titel, optionalen Feldern und `request_id` |
| POST `/api/v1/orders/{id}/{action}` | start/pause/finish/release/approve/rework/cancel; Version und Vorgangskennung |
| POST `/api/v1/actions/{action}` | Teile, Mängelprüfung, Kilometer, Fristabschluss, Links, Datenschutz |
| GET/PUT `/api/v1/settings` | Meister liest; Administrator setzt existierende, unterschiedliche Gruppen |
| POST `/api/v1/attachments` | Multipart `file` und Zielreferenz |
| GET `/api/v1/attachments/{id}` | Autorisierter Download, keine öffentlichen Fotos |
| GET `/api/v1/export/{kind}` | CSV für Meister, Formel-Injection geschützt |
| POST `/public/v1/session` | Fahrzeugtoken gegen eingeschränkte Sitzung tauschen |
| GET `/public/v1/vehicle` | Fahrzeugkennung und veröffentlichte offene Kurzmeldungen |
| POST `/public/v1/defects` | Mangel melden, Wiederholungskennung erforderlich |
| POST `/public/v1/photos` | Fotos ausschließlich zur gerade gemeldeten Meldung |

Fehlerformat `{error, message}`, HTTP 400/401/403/404/409/429/500.
Datumswerte: YYYY-MM-DD. Zeitpunkte: ISO8601 mit Zeitzone, normalisiert UTC.
Zeiten in Intervallen: Unix-Sekunden; Sollzeit: Minuten. Listen-Antwort `{items, more, offset}`.

## Öffentliche Tokens und Dateien

256-Bit-Token nur beim Erzeugen anzeigen; Datenbank speichert SHA-256. Token liegt im
URL-Fragment, wird nach Einlesen aus der Browseradresse entfernt und nicht in Initial State
oder Logs ausgegeben. Fahrersitzung gültig für zwei Stunden, Fotoberechtigung für eine Stunde.
Jeder Aufruf prüft Fahrzeugart, Archivierung und Widerruf neu. Zustandsänderungen behalten
CSRF-Schutz. Anonyme und angemeldete Aufrufer unterliegen eigenen Rate Limits.
Keine Fremdskripte, Telemetrie oder Datenübertragung an externe Dienste.

Fotos: JPEG/PNG/WebP, höchstens 8 MB und 12 Megapixel; GD dekodiert und erzeugt neues JPEG
(ohne ursprüngliche Metadaten). Maximal fünf Bilder je Fahrermeldung, wiederholte identische
Uploads werden erkannt. Intern zusätzlich PDF als Download, nicht als HTML dargestellt.
Dateien liegen ausschließlich in AppData/SimpleFS. Reservierungen werden vor Schreiben
protokolliert; alte unvollständige Uploads durch den Job bereinigt.

## Hintergrundarbeiten

Maintenance läuft frühestens alle fünf Minuten unter Nextcloud-Cron; kein Echtzeitversprechen.
Fristen in begrenzten Batches mit Cursor; Hinweise bleiben im Dashboard unabhängig von
Notifications sichtbar. Outbox speichert Empfänger und Ereignisschlüssel, Wiederholungen mit
Backoff, maximal zehn Versuche. Empfangsrechte vor Zustellung und Darstellung erneut prüfen.
Bei Crash nach Zustellung und vor Commit kann die Plattform erneut benachrichtigt werden;
kein systemübergreifendes Exactly-once-Versprechen. Fachobjekte werden niemals dadurch dupliziert.

## Zustellungsdiagnose

Meister lesen fehlgeschlagene Zustellungen über `GET /api/v1/records/outbox` mit
Pagination; die Antwort enthält nur Kennung, Version, Zeitpunkt, Empfänger, Kategorie
und Versuchszahl. `POST /api/v1/actions/notification-retry` mit `data.id` und
`data.version` setzt eine fehlgeschlagene Zustellung transaktional auf pending zurück.
Bei entzogenen Empfängerrechten wird sie stattdessen verworfen. Tablet-Zugriff ist
gesperrt. Ein Versionskonflikt verhindert doppeltes Zurücksetzen; vorherige Versuche
bleiben im Audit nachvollziehbar.

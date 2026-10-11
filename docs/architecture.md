# Architektur und Schnittstellen

## Zielmatrix und Werkzeuge (0.4.0, 2026-10-11)

- Ziel: Nextcloud 35, PHP 8.3–8.5. Die CI installiert Nextcloud 35.0.0 und 35.0.1 jeweils mit
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
Verträge und eine feste Tabellen-/Spaltenliste. JSON speichert begrenzte strukturierte
Inhalte wie Arbeitsschritte, Termin-Leistungsmomentaufnahmen, Protokolldetails,
Warnschwellen, Nachrichtenvorlagen und idempotente Antwortkopien.

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
- Meistergruppe oder direkte Meisterfreigabe: Werkstattdaten und Abläufe, einschließlich
  Auswertung und Nachrichtenvorlagen; Benachrichtigungen berücksichtigen beide Zugänge.
- Terminannahme über Gruppe oder direkte Freigabe: Termine und erforderliche Kunden-/
  Fahrzeuginformationen; keine Finanzdaten, Werkstattaktionen oder Verwaltung.
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
| GET/PUT `/api/v1/settings` | Meister liest; Administrator verwaltet Gruppen und direkte Benutzerfreigaben |
| GET/PUT `/api/v1/mail-templates` | Meister liest/bearbeitet Kundenvorlagen und Fristenvorlauf, mit Versionsprüfung |
| GET `/api/v1/statistics` | Meister-Auswertung mit Zeitraum und Fahrzeugfilter |
| POST `/api/v1/statistics/revenue` | Meister erfasst eigene Nettobeträge und Umsatzdatum, mit Version und Änderungsgrund |
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
Keine Fremdskripte oder Telemetrie; keine Werkstattdaten an Rechercheanbieter.
Nur ausdrücklich konfigurierte Mail- und CalDAV-Verbindungen übertragen die für
Kundennachrichten beziehungsweise Kalenderereignisse erforderlichen Daten.

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

## Darstellung der Fahrzeughistorie (0.1.7)

`Catalog::list('audit')` ergänzt für jede Seite einen begrenzten Kontext über
`Journal::withContext`. Meisterrechte werden in beiden Einstiegspunkten geprüft.
Referenzen werden pro Objekttyp gesammelt und in höchstens fünf Abfragen geladen;
die Antwort enthält nur die für die Anzeige benötigten Felder. Der Kontext wird
nicht in der Audit-Tabelle gespeichert. Mitarbeiteranonymisierung wirkt dadurch
auch auf alte Ereignisse. `src/audit.ts` übersetzt bekannte Ereignisse und
formatiert freigegebene Details; unbekannte Ereignisse erhalten einen neutralen
Text ohne rohe Kennungen oder JSON. Neue Pause-/Fertigmeldungen speichern die
validierten Arbeitsnotizen und Schritte, Teileanforderungen zusätzlich das Pausenflag.

Die Fahrzeugkomponente bleibt beim Detailaufruf montiert und hält ihren Bereich.
Ihr nativer Dialog wird vorübergehend geschlossen, danach erneut geöffnet und
aktualisiert; es gibt jeweils einen aktiven modalen Arbeitsbereich.

## Verbindungsanzeige (0.1.7)

Die App prüft die Erreichbarkeit durch ihre API-Antworten. Browserereignisse
`online`/`offline` lösen eine erneute Prüfung aus; `navigator.onLine` ist kein
Grund, einen tatsächlich erreichbaren Server zu sperren. Sichtbare Seiten fragen
auch nach einem Verbindungsfehler wieder alle zehn Sekunden an, sofern kein
Bearbeitungsdialog offen ist. Schreibaktionen benötigen immer eine Serverantwort;
es gibt keine Offline-Warteschlange. Bei ungewissem Ausgang behält eine explizite
Wiederholung denselben Idempotenzschlüssel. Netzwerkheuristik dokumentiert bei
[MDN](https://developer.mozilla.org/en-US/docs/Web/API/Navigator/onLine), geprüft am 2026-10-05.

## Leistungskatalog und Planung ab 0.2.0

Neue Migration `Version000200Date20261005000000` ergänzt `wm_services`, `wm_prices`,
`wm_planning`, `wm_appointments`, `wm_capacity`, `wm_cost_profiles`, `wm_deliveries`.
Aufträge erhalten Arbeitskopien/Tagesverteilungen sowie indizierte Datumsgrenzen;
Fahrzeuge eine PKW-/Transporterklasse. Historische Migrationen bleiben unverändert.
Initialisierung verwendet stabile IDs; vorhandene Katalogzeilen werden nicht überschrieben.
Preisbeobachtungen sind nach Inhaltskennung versioniert. Bundle-Version 2026-10-05.1.

`ServiceLibrary` autorisiert Meister, validiert eigene Leistungen und speichert ausschließlich
freigegebene Arbeitsfelder in Vorgängen. `Pricing` berechnet vergleichbare Anbietergruppen
und Centbeträge. `AppointmentBook` koordiniert Termin, Kapazität, Wiederholungsantwort,
Historie und Warteschlange in einer Transaktion. `PlanningLedger` verwendet eine versionierte
Singleton-Zeile für konkurrierende Kapazitätsänderungen. Gleichzeitige Änderungen können
HTTP 409 auslösen; sie verlieren keine Buchung. Aktive Zeitbuchungen brauchen diesen
Planungslock nicht. Ein Unique Constraint sichert die Termin-Auftrag-Verknüpfung.

Datumsgrenzen begrenzen SQL-Abfragen; Seiten von 500, maximal 2.000 Termine pro Anfrage
und ein Jahr Zeitraum. Verknüpfungen außerhalb des sichtbaren Zeitraums werden zusätzlich
geprüft, damit Aufträge nicht doppelt zählen. Große Bestände bleiben im Pilot zu vermessen.

Neue Meister-APIs unter `/api/v1`: `services`, `services/{key}/prices`, `appointments`,
`appointments/{id}/order`, `capacity`, `pricing`, `deliveries`, `deliveries/{id}/retry`,
`calendar-settings`. Kalenderkonfiguration zusätzlich nur Administrator; Tablet und Fahrer
haben keinen Zugriff. Bestehende Auftrags-APIs nehmen validierte Leistungskopien entgegen.

`AppointmentDispatch` (60 Sekunden, kein paralleler Lauf) verarbeitet fällige Zustellungen
mit begrenztem Batch und 45-Sekunden-Budget zwischen Aufträgen. `AppointmentDelivery`
beansprucht Zeilen optimistisch, verwirft überholte Daten und unterscheidet reine interne
Statusänderungen von kundenrelevanten Änderungen. Kalenderereignisse verwenden je
stabiler UID eine separate Ressource. Wiederholtes PUT/DELETE erzeugt keine Duplikate.
SMTP kann nach Verbindungsverlust keinen genau-einmal-Versand garantieren: unklarer
Ausgang oder abgestürzter Versand erfordert manuelle Prüfung/Wiederholung. Vorbereitungs-
und CalDAV-Fehler verwenden Backoff, nach zehn Versuchen `failed`.

`CalendarDelivery` nutzt öffentliche `IClientService`, `ICrypto`, `IURLGenerator`, `IConfig`;
Mail `IMailer`. HTTPS ist verpflichtend, Weiterleitungen aus, TLS-Prüfung an. Zielhost/Port
und Installationspfad müssen zur eigenen Nextcloud gehören; Kalenderpfad ist auf das
Integrationskonto begrenzt. Keine fremde DAV-Implementierung und keine privaten App-APIs.
Die kanonische Cron-Adresse `overwrite.cli.url` muss konsistent konfiguriert sein.

Kalender-/Mailintegration: [Betrieb](operations.md#kalender-und-kundennachrichten-ab-020).
Öffentliche Preisquellen bleiben statische Bundle-Daten; keine Werkstattdaten gehen an
Rechercheanbieter. Native Prüfungen gegen installierte Nextcloud 35 bestätigen die
DI und öffentlichen Verträge. API-Grundlagen: [NC-35-App-Dokumentation](https://docs.nextcloud.com/server/35/developer_manual/app_development/index.html),
[CalDAV](https://docs.nextcloud.com/server/stable/admin_manual/groupware/calendar.html),
[Kalenderfreigaben](https://docs.nextcloud.com/server/35/user_manual/en/groupware/calendar.html).
Prüfdatum 2026-10-05; konkrete Testergebnisse getrennt im Prüfbericht.

`CatalogueUpdate` führt einen feldweisen Drei-Wege-Vergleich zwischen aktuellem Eintrag,
letzter Seed-Baseline und neuem Seed aus. Nullable `services.seed_snapshot` wird durch
zusätzliche Migration `Version000200Date20261005210000` eingeführt; ursprüngliche
Migration bleibt unverändert. Version-1-Alteinträge sind unbearbeitet; ältere bearbeitete
Zeilen ohne Baseline erhalten ihre Inhalte zunächst vollständig. Eigene Preise und
Einstellungen bleiben stets erhalten. Ein erneuter Repair mit identischem Seed ändert
keine Version. Tests und echte DB-Upgrades belegen den jeweiligen Stand im Prüfbericht.

Vergleichszielpreise sind ausdrücklich ausgewählte Browser-Kalkulationsdaten; sie
verändern keinen Arbeitslohn oder Materialeinkauf automatisch. Bestätigung der
Einheitenzuordnung und Quellen-VAT-Umrechnung gehen der Übernahme voraus. Erst eine
weitere ausdrückliche Aktion speichert den Nettopreis über die versionsgeprüfte Meister-API.

### Interne Termincheckliste (0.2.1)

Additive nullable JSON-Spalte `wm_appointments.checklist`; Domainvalidator mit festen Boolean-/Text-/Kilometerschlüsseln. Altclients bewahren vorhandene Checklisten bei fehlendem Feld; vollständige explizite Objekte ersetzen sie. Schreiben bleibt in derselben transaktionalen, versionierten und idempotenten Meisteraktion. Das Journal speichert geänderte Felder mit vorher/nachher. Auslieferungen verwenden weiterhin die bestehende Feld-Whitelist ohne Checkliste; Auftragskopien/Tabletantworten erhalten keine Annahme- oder Kundennotizen. Datenschutzexport und kontrollierte Fahrzeuglöschung behandeln den bestehenden Terminbereich vollständig.

Vorschläge freier Tage sind reine Frontend-Auswertung der bestehenden serverseitig berechneten Tagesbudgets. Die endgültige Bestätigung prüft konkurrierende Kapazitätsänderungen erneut. Terminfilter verändern keine Budgets. Folgetermine kopieren ausschließlich geplante Arbeitsinhalte; aktuelle Fahrzeug-/Kundenreferenzen liefern die E-Mail.

## Werkstattauswertung ab 0.3.0

`StatisticsController` und `Statistics` stellen ausschließlich Meistern
GET `/api/v1/statistics?from=YYYY-MM-DD&to=YYYY-MM-DD&scope=all|customer|fleet`
und POST `/api/v1/statistics/revenue` bereit. Die zweite Route erwartet ein
`data`-Objekt mit `source_kind` (orders/appointments), `source_id`,
`version`, optionalen ganzzahligen `planned_net_cents`/`actual_net_cents`,
`revenue_date` und verpflichtendem `reason`. Istbeträge benötigen einen
abgeschlossenen Kundenauftrag und ein Umsatzdatum. CSRF bleibt aktiv.

Die additive Migration 000300 ergänzt `wm_revenues`, getrennt von Tablet- und
Terminannahme-Payloads. Ein eindeutiger Quellbezug und Versionsvergleich verhindern
Doppelwerte und verlorene Betragsänderungen. Die gemeinsame Planungssperre und eine
Quellzeilensperre koordinieren Betragserfassung mit Statusänderungen. Übernommene
Terminwerte werden beim ersten Bearbeiten am freigegebenen Auftrag übertragen;
spätere Nacharbeit bewahrt bereits erfasste Istwerte. Änderungen werden protokolliert.

`WorkshopReport` aggregiert Geld in Cent und Zeit in Sekunden. Berliner Tagesgrenzen
werden in UTC umgerechnet; Intervalle werden am Auswertungszeitraum und an der
Serverzeit abgeschnitten. Die gesamte Auftragszeit bleibt für den Soll-/Ist-Vergleich
erhalten. Bestätigter Termin und verknüpfter Auftrag zählen gemeinsam einmal;
Mehrtagesplanwerte werden durch kumulative Rundung centgenau verteilt. Flottenarbeit
bleibt interne Zeit, ohne Kundenumsatz. Kapazität zeigt stets die gesamte Werkstatt.

Die Daten werden über stabile ID-Seiten zu je 500 Datensätzen eingelesen. Mehr als
20.000 Datensätze je Bereich erzeugen einen sichtbaren Fehler statt Teilsummen.
Die Auswertung ist eine betriebliche Übersicht aus eigenen Beträgen, kein
Rechnungs-, Zahlungs-, Steuer- oder unveränderliches Buchführungsjournal.

## Kundennachrichten 0.4.0

Migration 000400 ergänzt Kundenwunsch, Termin-Abholwunsch, letzte Fristenerledigung,
Fahrzeug-/Fristenbezug der bestehenden deliveries-Tabelle und eine versionierte
communications-Einstellung. GET/PUT mail-templates sind meistergeschützt; Empfang
und Tablet erhalten keine Vorlagen-/Fristenadministration. Vorlagen ersetzen bekannte
Plaintext-Platzhalter ohne Auswertung von Code, HTML oder rekursiven Ersetzungen;
Header-Steuerzeichen und unbekannte Platzhalter werden abgewiesen.

AppointmentDispatch entdeckt Fristen in begrenzten 100er-Seiten und benutzt die
vorhandene Zustellungslogik. Ereignis-Unique-Key, Transaktionen und optimistische
Zustellungsübernahme begrenzen doppelte Nachrichten. Wunsch, Mailadresse, aktuelle
Fälligkeit und Fahrzeug-/Kundenbindung werden vor Versand geprüft. SMTP-Ausgänge
nach Übertragungsbeginn bleiben bei Fehlern unklar statt automatisch wiederholt.
Eine bereits gesendete Nachricht kann bei späterem Widerruf nicht zurückgeholt
werden. Änderungen der Vorlagen betreffen erst zukünftige Sendeversuche. Direkte
Katalogänderungen überschreiben keine Termin-/Auftrags-Momentaufnahmen.

Es werden dieselben öffentlichen OCP-Mail-, Config-, BackgroundJob- und
Schema-APIs wie im bestehenden Versand verwendet, ohne neue Abhängigkeit oder
neuen externen Dienst. Geschützte Fahrzeuglöschung prüft und entfernt auch neue
Fristenzustellungen; offene/unklare Sendungen dürfen dabei nicht übergangen werden.

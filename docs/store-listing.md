# Vorbereitete Store-Beschreibung

Stand für die stabile Version0.5.1 vom 19.10.2026. Die beiden signierten Betas wurden veröffentlicht und installiert geprüft. Der konkrete stabile Kandidat benötigt vor der Einreichung erneut Signatur-, Installations- und Upgradeprüfung. Geräte- und Betriebsabnahmen bleiben getrennt offen.

## Vorgesehene Angaben

| Feld | Inhalt |
| --- | --- |
| App-ID | werkstattmanager |
| Name | Werkstattmanager |
| Herausgeber | Thomas Keilhaupt |
| Kategorie | Organisation |
| Kurzbeschreibung | Fahrzeuge, Termine, Werkstattaufträge und Auswertungen |
| Lizenz | AGPL-3.0-or-later |
| Quellcode | https://github.com/LtRock84/werkstattmanager |
| Fehlerhilfe | https://github.com/LtRock84/werkstattmanager/issues |
| Kompatibilität | Nextcloud 35; PHP 8.3–8.5; PostgreSQL und MariaDB geprüft |

Quellcode und Issue-Tracker sind seit der ausdrücklichen Freigabe vom16.10.2026 öffentlich. Die drei HTTPS-Bildadressen wurden ohne Anmeldung geprüft; alle Bilder sind bytegleich zu den vorbereiteten fiktiven Aufnahmen.
Eine separate MySQL-Prüfung und neue Nextcloud-Hauptversionen stehen aus.
Der öffentliche Herausgeber wird über das `author`-Element in `info.xml` angegeben.
Der Kontobenutzername ist davon unabhängig. Eine private Konto-E-Mail gehört weder
in dieses Dokument noch in das optionale öffentliche `author`-Mailattribut.
Ab 0.4.1 ist ein fester PayPal-Link für freiwillige Projektunterstützung vorbereitet.
Er schaltet keine Zusatzfunktionen frei; es wird keine Spendenbescheinigung ausgestellt.
Die tatsächliche Kontoeignung muss der Empfänger vor öffentlichem Betrieb bestätigen.

## Vorgesehener Beschreibungstext

**Werkstattmanager führt Fahrzeugakten, Termine, Arbeitsaufträge und Werkstattauswertungen in Nextcloud zusammen. Vor dem Produktivbetrieb Rollen, Hintergrundjobs, Sicherungen und optionale Integrationen für die eigene Installation einrichten und die eigenen Abläufe und Geräte in einem Pilotbetrieb prüfen.**

Werkstattmanager unterstützt die tägliche Arbeit in einer KFZ-Werkstatt:
Kundenfahrzeuge und eigene Flottenfahrzeuge verwalten, Termine und Fristen im
Blick behalten und Arbeitsaufträge mit Vorlagen oder freier Eingabe erstellen.

Das Terminbuch zeigt freie Tageskapazität, mehrere ausgewählte Arbeiten addieren
ihre Dauer. 295 Werkstattleistungen sind vorinstalliert und für Termine, Aufträge
und Kalkulation verfügbar. Ein Termin wird zum verknüpften Auftragsentwurf.
Assistenzkräfte können einen begrenzten Zugang ausschließlich zur Terminannahme
erhalten. Ein eigener Preisrechner verwendet Betriebskosten und produktive Stunden;
Vergleichspreise bleiben mit Prüfdatum, Leistungsumfang und Datenlücken gekennzeichnet. Unternehmensnamen und Anbieterlinks werden nicht veröffentlicht.

Meister sehen eigene Plan-/Istumsätze, Monatswerte, Tagesauslastung, Mitarbeiterzeiten
und Soll-/Ist-Abweichungen. Beträge werden ausdrücklich erfasst; die Auswertung
ersetzt keine Buchhaltung.

Nach Einrichtung von SMTP und CalDAV erscheinen bestätigte Termine im eigenen
Nextcloud-Kalender. Kunden erhalten auf Wunsch Termin- und Abholbereit-Nachrichten.
Sieben personalisierte Textvorlagen sind bearbeitbar. Fahrzeugfristen können Kunden
automatisch zur Terminvereinbarung erinnern; Wunsch, Empfänger und aktuelle
Fälligkeit werden vor Versand geprüft. Bestehende Kunden werden beim Upgrade
nicht automatisch für Fristenmails aktiviert.

Meister können einen Mitarbeiter für einen Auftrag vorsehen, ändern oder die Vorplanung aufheben. Am Tablet steht der Name mit einem auffälligen Hinweis. Die Vorplanung startet keine Arbeitszeit; Kollegen dürfen übernehmen.

Auf dem Werkstatt-Tablet wählen Mitarbeiter ihren eigenen Namen, starten einen Auftrag,
pausieren ihn oder melden die Arbeit fertig. Arbeitszeiten bleiben pro Mitarbeiter
nachvollziehbar. Jeder Mitarbeiter kann einen aktiven Auftrag bearbeiten;
verschiedene Mitarbeiter können gleichzeitig an verschiedenen Aufträgen arbeiten.
Pausierte Aufträge können Kollegen übernehmen. Der Meister bestätigt den Abschluss.

Teile können aus dem Auftrag angefordert werden. Fahrer melden Mängel über einen
widerrufbaren Fahrzeuglink ohne Nextcloud-Konto. Der Meister prüft neue Meldungen,
bevor kurze Informationen öffentlich für Fahrer sichtbar werden.

Vorausgesetzt werden eine Werkstattorganisation, eingerichtete Meister-/Tabletrollen
sowie regelmäßige Nextcloud-Hintergrundjobs. Zusätzliche Meister und
Terminannahme-Zugänge verwalten Administratoren auch über direkte Benutzerfreigaben. Die Namensauswahl am
gemeinsamen Tablet ist eine Zuordnung ohne persönlichen Identitätsnachweis.
Die erste Version umfasst keine Rechnungsstellung, Lagerbuchhaltung, automatische
Personalplanung oder Offline-Zeitbuchung.

## Datenschutztext

Werkstattdaten und Anhänge werden in der eigenen Nextcloud gespeichert. Es gibt
keine Telemetrie und keine Lieferantenanbindung. Optional konfigurierte Mail- und
Kalenderdienste erhalten nur die für Kundennachrichten beziehungsweise
Kalenderereignisse erforderlichen Daten. Fahrzeuglinks geben ihren Besitzern
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
- Wiederverwendungsbedingungen der Preisquellen und verbleibende Betriebsabnahmen prüfen.

## Freigabestand vom 19. Oktober 2026

| Voraussetzung | Stand |
| --- | --- |
| Herausgeber und deutschsprachige Beschreibung | Vorbereitet: Thomas Keilhaupt; keine öffentliche E-Mail |
| App-Store-Konto | Anmeldung im Nutzer-Tab am16.10.2026 bestätigt; Kontodaten unverändert |
| Quellcode und Support | Seit16.10.2026 nach Eigentümerfreigabe öffentlich |
| Zertifikatsanfrage | Öffentlicher CSR eingereicht: nextcloud/app-certificate-requests#1345; ein geändertes CSR-File, kein privater Schlüssel |
| Nextcloud-Zertifikat | Am16.10.2026 ausgestellt; öffentliches Zertifikat zum bestehenden Schlüssel geprüft |
| Installationspaket | Signierte Betas veröffentlicht und installiert geprüft; signiertes Stable-Endarchiv0.5.1 wird nach erneuter CI- und Paketprüfung erstellt |
| Bilder | Drei fiktive0.5.1-beta.1-Aufnahmen öffentlich per HTTPS ohne Anmeldung geprüft |
| Qualität und Datenrechte | Beta2-Matrix und Store-Upgrade bestanden; Stable-Endpaket sowie Geräte-/Betriebsabnahmen und Rechteprüfung noch offen |

Eine Anmeldung allein erzeugt keinen Store-Eintrag. Die App-Registrierung und
Release-Einreichung erfolgen erst nach den oben genannten Freigaben und der
Zertifikatsausstellung. Der vorhandene Pilotstand wird nicht als fertige
Produktionsversion beworben.

Offizielle Quellen, erneut geprüft am 16.10.2026:
[Autor, Registrierung und Release](https://nextcloudappstore.readthedocs.io/en/latest/developer.html),
[Zertifikatsverfahren](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html),
[Store-Regeln](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/publishing.html).


## Prepared English listing (0.5.1)

**Werkstattmanager brings vehicle files, appointments, work orders and workshop reporting together in Nextcloud. Before productive use, configure roles, background jobs, backups and optional integrations for your installation, then complete a pilot with your own workflows and devices.**

**Workshop management for Nextcloud: vehicles, appointments, work orders and reporting.**

Werkstattmanager helps a vehicle workshop manage customer vehicles and its own fleet, book appointments with daily capacity, and turn agreed work into workshop orders. Reusable templates and a service catalogue simplify routine work. The pricing calculator uses your own operating costs; published comparison prices are clearly labelled reference data with a review date and limitations. Company names and supplier links are not published.

A workshop supervisor can suggest an employee for a draft, open or paused order. The workshop tablet displays the planned name prominently. This is a planning hint: colleagues can take over, and assigning a name starts no timer. Employees select their own name when they actually start, pause or finish work. The supervisor reviews and approves the completed job. Parts requests and vehicle defects remain linked to the order.

Drivers can report defects and mileage through a revocable vehicle link without a Nextcloud account. Internal reports remain private until the supervisor publishes a short driver-facing update. Supervisors can review planned and actual net revenues, working time and daily capacity; the reports are not an accounting system.

Optional email and calendar integration supports appointment notices, collection-ready messages and vehicle-deadline reminders. Administrators can grant full supervisor access or a restricted appointment-reception role. A shared tablet's employee choice records attribution, not individual identity verification. There is no invoicing, inventory accounting, automatic staff scheduling or offline time booking.

This release is intended for one workshop organisation. Configure roles, background jobs, retention and optional integrations, then validate your workflows and devices before productive use. Data stays in your Nextcloud; optional mail/calendar providers receive the data required for the configured messages and events. There is no telemetry. A voluntary PayPal link supports project costs without unlocking features or offering a donation receipt.

### Image provenance and remaining publication checks

The three images under `docs/screenshots/0.5.1-beta.1/` show the shipped 0.5.1-beta.1 bundle with explicitly labelled synthetic UI-fixture data. They are not photographs of a real workshop or proof of physical iPad/Nextcloud integration. Their raw-GitHub HTTPS URLs are listed in `info.xml`. All three images were checked without authentication on16October2026 and match the local files byte for byte.

The signing certificate was issued on16October2026. Source publication was explicitly approved and completed on16October2026. Certificate request https://github.com/nextcloud/app-certificate-requests/pull/1345 has been merged and the certificate issued. Source-data reuse review, final signatures, immutable archive hosting and installation of the exact signed artifact remain outstanding. The offline inventory tool does not complete or waive these checks.

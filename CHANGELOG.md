# Changelog

## 0.5.1 – Erste stabile Store-Version (19.10.2026)

- Der auf Nextcloud 35, PostgreSQL und MariaDB geprüfte Stand der zweiten Beta wird ohne Vorabversionssuffix für den stabilen App-Store-Kanal veröffentlicht.
- Fahrzeugakten, Terminbuch, Leistungskatalog, Kalkulation, Aufträge, Mitarbeiter-Vorplanung, Tablet-Zeiterfassung, Teileanforderungen, Fahrer-QR-Meldungen, Kundennachrichten und Auswertungen sind gemeinsam enthalten.
- Gegenüber `0.5.1-beta.2` gibt es keine Änderung an Datenmodell oder Laufzeitlogik. Metadaten und öffentliche Dokumentation beschreiben den stabilen Store-Kanal und die weiterhin erforderliche betriebliche Pilotabnahme.
- Physisches iPad, reale NAS-/Werkstattmailbox-Abnahme, vollständige Barrierefreiheitsprüfung und längerer Dauerbetrieb bleiben installationsabhängige Prüfungen vor dem produktiven Einsatz.

## 0.5.1-beta.2 – Optionalen Kalender korrigiert (17.10.2026)

- Optionale CalDAV-Integration erzeugt ohne Kalenderadresse keine Zustellungen. Fällige alte Warteschlangeneinträge werden ohne weitere Warnung abgebrochen; Kunden-E-Mails bleiben unabhängig. Entzogene Zugangsdaten bei bestehender Adresse und echte Kalenderausfälle bleiben sichtbare Fehler.

- Regressionen für ausgeschalteten Kalender, Kundenmail, Altjobs und Wiederaktivierung ergänzt; Passwortentzug und echte CalDAV-Ausfälle bleiben geprüft.
- Betriebsdokumentation zur PDF-Standardzuordnung bei Mail/EuroOffice ergänzt. Die App verändert keine fremden Office-Einstellungen.

## 0.5.1-beta.1 – Erste öffentliche Test-Beta

- Ausdrücklicher früher Teststatus in Deutsch/Englisch und Beta-Version für den Store.
- Alle 334 Vergleichspreise bleiben erhalten; 32 unabhängige Preisgeber erscheinen neutral, ohne Unternehmensnamen oder Anbieterlinks. Die API berücksichtigt auch Altbestände, ohne diese umzuschreiben.
- Berechnung, Anbieterzählung, bestehende Preis-IDs und eigene Werkstattpreise bleiben erhalten. Originalquellen sind getrennt intern dokumentiert; historische Git-Angaben bleiben bestehen.
- Drei aktuelle fiktive UI-Bilder, ausgestelltes Nextcloud-Zertifikat und Signierungsvorbereitung. Geräte-/Betriebsabnahme und Preisquellenrechte bleiben getrennt offen.

## 0.5.0 – Mitarbeiter für Arbeitsaufträge vorplanen

- Deutsche und englische Store-Metadaten kennzeichnen die App ausdrücklich als sehr frühe Testversion zum Ausprobieren.

- Testwerkzeug Vitest auf 4.1.11 aktualisiert; vollständige npm-Sicherheitsprüfung einschließlich Entwicklungsabhängigkeiten in CI aufgenommen.

- Meister können beim Erstellen und bei Entwürfen, offenen oder pausierten Aufträgen einen Mitarbeiter vorsehen, ändern oder die Vorplanung aufheben.
- Tablet und Meister sehen den Namen als auffälligen Hinweis. Kollegen dürfen weiterhin übernehmen; die Vorplanung startet keinen Timer und reserviert keinen Mitarbeiter.
- Namensfilter berücksichtigt Vorplanung und letzten Bearbeiter; Versionskonflikte, Wiederholungsschutz, archivierte Mitarbeiter und lesbares Änderungsprotokoll sind berücksichtigt.
- Neue additive Migration ergänzt ein optionales Feld; vorhandene Aufträge bleiben ohne Vorplanung erhalten.
- Aktuelle fiktive Store-Bilder, deutsche/englische Beschreibung und Offline-Paketinventar vorbereitet. CI prüft zusätzlich Upgrades von 0.4.1 mit PostgreSQL und MariaDB. Keine automatische Store-Veröffentlichung.

## 0.4.1 – Freiwillige Projektunterstützung (lokaler Prüfkandidat)

- „Projekt unterstützen“ unten in der Meisteransicht, mit Erklärung vor dem Wechsel zu PayPal.
- Festes, vom Eigentümer aktiviertes PayPal.Me-Profil `Werkstattmanager`, ohne Betragsvorgabe oder Empfänger-E-Mail im Link; keine Änderung über App-Einstellungen oder Werkstattdaten.
- Keine Zusatzfunktionen, keine Spendenbescheinigung und kein Versprechen steuerlicher Begünstigung.
- Kein Zahlungs-SDK oder Tracking; keine Werkstattdaten im Zahlungslink. Tablet, Fahrer und Assistenz bleiben frei von Zahlungsaufrufen.
- Scrollbare Meister-Navigation hält den unteren Bereich auf größeren Bildschirmen erreichbar; auf kleinen Bildschirmen fließt er mit der Seite.
- Offizielle Nextcloud-Unterstützungsmetadaten ergänzt. Kein neuer Backend-Endpunkt, keine Datenbankmigration oder Abhängigkeit.
- Herausgeber in App-Metadaten und Veröffentlichungsunterlagen auf Thomas Keilhaupt gesetzt; Store-Beschreibung und Freigabecheckliste vorbereitet. Keine Veröffentlichung oder Konto-E-Mail im Quellcode.
- Direkter PayPal.Me-Unterstützungslink in der GitHub-README und `.github/FUNDING.yml` für GitHubs Sponsor-Funktion; freiwillig und ohne Zusatzleistungen. Repository bleibt privat.

## GitHub-Aktualisierung vom 11. Oktober 2026: 0.2.4 → 0.4.0

- **Auswertung:** eigene Netto-Ist-/Planumsätze, Monatswerte, Tagesauslastung,
  Mitarbeiterzeiten und Soll-/Ist-Abweichungen, mit CSV-Export und Druckansicht.
- **Kundennachrichten:** optionale Abholbereit-Mail und sieben anpassbare,
  personalisierte Textvorlagen für Termin- und Fahrzeugnachrichten.
- **Fristenerinnerungen:** Kundenwahl, letzte Erledigung, HU-/Inspektionsvorgaben
  und einstellbarer Vorlauf; Bestandskunden werden beim Upgrade nicht aktiviert.
- **Dokumentation:** Bedienung, Einrichtung, Rechte, Prüfnachweise und Pilotabläufe
  auf 0.4.0 aktualisiert. Die folgenden Versionsabschnitte beschreiben die Details.

70 PHP-Fachszenarien je PHP 8.3/8.4/8.5 und 98 Frontendtests lokal bestanden;
Nextcloud 35.0.0/35.0.1 jeweils mit PostgreSQL/MariaDB installiert und geprüft,
zusätzlich Upgrade und isoliertes HTTPS/SMTP/CalDAV/Cron. Die GitHub-Pipeline
prüft ihren eigenen Quell- und Paketstand erneut. Physischer iPad-Test,
reale NAS-/Office-/Mailbox-Abnahme und weitere Freigabepunkte bleiben offen.
Das Repository bleibt privat; keine Store-Einreichung oder Produktionsfreigabe.

## 0.4.0 – Kundennachrichten und Fristenerinnerungen

- Optionale Abholbereit-Mail beim ausdrücklichen Fertigstatus im Terminbuch, einmal pro Termin und Kundenadresse.
- Sieben fertige personalisierte Textvorlagen, vom Meister unter Einstellungen bearbeitbar; Platzhalter für Name, Fahrzeug, Termin, Abholung, Leistung, Frist und Werkstatt.
- Kundenschalter für automatische Fahrzeugfristen-Erinnerungen: bei neuen Kunden vorausgewählt, vorhandene Kunden beim Upgrade deaktiviert.
- Letzte Erledigung und HU-/Inspektionsvorgaben zur Berechnung wiederkehrender Fristen; einstellbarer Vorlauf, standardmäßig 30 Tage. Kilometerhinweise verwenden bestätigte Stände.
- Bestehende Versandwarteschlange mit Empfänger-/Wunschprüfung, eindeutigen Ereignissen, Fehleranzeige und Fahrzeug-Datenschutzlöschung erweitert. Keine zusätzliche Kalendereinladung für Fertig- und Fristennachrichten.
- Additive Migration; keine automatische Terminbuchung, keine Änderung der produktiven Nextcloud.

## 0.3.0 – Umsatz und Werkstattauswertung

- Neuer Meister-Menüpunkt „Auswertung“ mit Zeiträumen, Fahrzeugfilter, Monatsumsätzen, Tagesauslastung, Mitarbeiterzeiten und Soll-/Ist-Vergleich abgeschlossener Aufträge.
- Plan- und abgerechnete Istbeträge ausdrücklich in Euro netto erfassen, mit Umsatzdatum und begründeter Änderungsprotokollierung. Fehlende Beträge bleiben als Datenlücken sichtbar; keine erfundenen Erlöse aus Vergleichspreisen oder Stunden.
- Verknüpfte Termine und Aufträge zählen einmal; Mehrtages-Planbeträge werden centgenau auf die Arbeitsplanung verteilt. Eigene Flotte bleibt interne Arbeit ohne Kundenumsatz.
- Berliner Zeitgrenzen einschließlich Zeitumstellung, laufende Buchungen bis Serverzeit, Pausen ausgeschlossen; Soll-/Ist-Auftragsvergleich über die gesamte Auftragslaufzeit.
- Geschützte Finanzdaten, additive Migration, Versionskonflikte statt Überschreiben, CSV-Export, Druckansicht und Löschung mit der Fahrzeugakte.
- Keine Rechnungsstellung, Zahlungskontrolle, Gewinnermittlung oder automatische Produktionsfreigabe.

## GitHub-Aktualisierung vom 6. Oktober 2026: 0.1.6 → 0.2.4

Diese Aktualisierung enthält die nachfolgend beschriebenen Versionen 0.1.7 bis
0.2.4. Das Repository bleibt privat; eine Store-Veröffentlichung oder
Produktionsfreigabe ist damit nicht verbunden.

### Neue Funktionen

- **Leistungskatalog:** 295 unterschiedliche Leistungen für PKW und leichte
  Transporter, mit Kategorien, Suchbegriffen, Favoriten, bearbeitbaren Zeiten,
  Mengen und Arbeitsschritten. Dieselben Leistungen sind für Termine,
  Mechanikeraufträge und Kalkulation verfügbar. Eigene Leistungen und Pakete
  ergänzen den Katalog; Hinweise helfen, enthaltene Arbeiten nicht doppelt anzusetzen.
- **Vergleichspreise:** 334 veröffentlichte Preisangaben von 32 Preisgebern,
  mit Quellen und getrennten Gruppen für Region, Fahrzeugklasse, Arbeitsumfang,
  Material, Umsatzsteuer sowie Fest- und Einstiegspreise. 57 Leistungen besitzen
  mindestens eine Preisangabe; für 238 fehlen geeignete Quellen. Einzelangebote
  werden nicht als belastbarer Durchschnitt ausgegeben.
- **Terminbuch:** Tages-, Wochen- und Monatsansicht, Arbeitsstundenbudgets,
  Fahrzeuglimits, Schließtage und Mehrtagesplanung. Mehrere ausgewählte Arbeiten
  addieren ihre Dauer. Überbuchungen benötigen eine Begründung; ein Termin und
  sein Arbeitsauftrag belegen Kapazität nur einmal.
- **Terminannahme:** Wochenkalender direkt im Formular mit Wochentagen,
  vorhandenen Terminen, Ankunft und Abholung, freien Stunden und Auslastung.
  Normales Speichern bestätigt den Termin; Entwürfe bleiben getrennt.
  Freie Tage suchen, Folgetermine vorbereiten, Statusfilter, Annahme-/Abholcheckliste
  und Hinweise auf bereits offene Fahrzeugaufträge erleichtern den Ablauf.
- **Vom Termin zum Auftrag:** Ein verknüpfter Auftragsentwurf übernimmt Leistungen,
  Arbeitsschritte und Sollzeit. Die Freigabe für Mechaniker bleibt ausdrücklich.
  Spätere Katalog- oder Terminänderungen überschreiben gespeicherte Arbeitsinhalte
  nicht unbemerkt.
- **Weitere Benutzerzugänge:** Administratoren können einzelne Nextcloud-Benutzer
  als zusätzliche Meister oder ausschließlich für Terminannahme freigeben.
  Die Assistenz sieht benötigte Termin-, Kunden- und Fahrzeuginformationen;
  Werkstattaktionen, interne Notizen, Preise, Zeiten und Verwaltungsfunktionen
  bleiben serverseitig gesperrt. Benutzerlöschung entfernt direkte Freigaben.
- **Dynamische Vorschläge:** Die acht meistverwendeten Leistungen stehen zuerst.
  Bestätigte Termine und freigegebene Aufträge zählen je Leistung und Vorgang einmal;
  Wiederholungen, Mengen und verknüpfte Termine/Aufträge führen nicht zu Doppelzählungen.
- **Preisrechner:** Jahreskosten und abrechenbare Stunden ergeben einen
  kostendeckenden Nettostundensatz. Gewinnaufschlag, Material, Fremdleistungen,
  Umsatzsteuer und Bruttopreis werden getrennt berechnet. Die Übernahme von
  Vergleichspreisen oder eigenen Preisen in den Katalog erfolgt ausdrücklich.
- **Kalender und Kundennachrichten:** Einseitige Übertragung bestätigter Termine
  in einen eingerichteten Nextcloud-Kalender über HTTPS-CalDAV; Bestätigung,
  Änderung, Absage und optionale Erinnerung mit Kalenderdatei. Wiederholbare
  Hintergrundaufträge und sichtbare Fehler unterstützen die Zustellung.
- **Firmenprofil und Werkstatt-Mail:** Firmenname und geprüftes Logo erscheinen
  in den Appkopfbereichen. Ein Administrator kann ein eigenes Konto aus Nextcloud
  Mail als gemeinsamen Werkstatt-Absender delegieren. Zugangsdaten verbleiben
  in Nextcloud Mail. Rechteentzug und Kontowechsel lösen keinen stillen
  Absenderwechsel aus; „an Mail übergeben“ bedeutet keinen Zustellnachweis.

### Verbesserungen und Fehlerkorrekturen

- Fahrzeughistorie mit verständlichen Ereignissen, Aufträgen, Mitarbeitern,
  Teilemengen, Notizen und Zeitkorrekturen; feststehende Bereichsleiste und
  Rückkehr zum vorherigen Fahrzeugbereich.
- Schutz ungespeicherter Eingaben, verständlichere Statusmeldungen,
  korrigierte Monats-/Datumsdarstellung und Erhalt manueller Auftragstitel.
- Katalogaktualisierungen erhalten eigene Anpassungen. Veraltete Such- und
  Preisantworten überschreiben keine neueren Eingaben.
- Fahrerformular: sichere Wiederholung nach Antwortverlust, Prüfung von Fotos
  und Kilometerständen sowie Zurücksetzen der Fotoauswahl nach vollständigem Erfolg.
- Schreibaktionen funktionieren auch in einer lokalen HTTP-Demo, wenn der Browser
  `crypto.randomUUID` nicht bereitstellt: UUIDs werden weiterhin kryptografisch
  erzeugt. Produktionszugänge bleiben auf HTTPS ausgelegt.

### Prüfungen und begleitende Demo

- Neue Regressionen und reale SQL-/HTTP-Prüfungen für Rollen, Rangliste,
  konkurrierende Buchungen, Termin-/Auftragsverknüpfungen, Fahrer-Mangel/km/Fotos,
  Teile, Nacharbeit, Abnahme, Fristen, Exporte, Kalender und Mail.
- Dokumentierter Stand: 90 Frontendtests für 0.2.4; 53 native PHP-Szenarien und
  echte Nextcloud-Mail-/SMTP-/IMAP-Prüfung für den unveränderten Backendstand 0.2.3.
  Installations- und Upgrade-Ergebnisse sind im [Prüfbericht](docs/test-report.md)
  ihrer jeweiligen Version zugeordnet.
- Separat zur App wurde die lokale Demo für Tablet-Zugriff im gemeinsamen WLAN,
  Adressverwaltung, Start/Stop und Wachhalten vorbereitet. Die Demo-Laufzeit erhielt
  eine JIT-Korrektur und einen gesicherten Redis-Start; keine Nextcloud-Coredateien
  wurden geändert. Diese lokalen Starter sind nicht Bestandteil des App-Repositories.
- Separate Tutorialvideos erklären den gesamten Werkstattablauf und Fahrer-QR,
  Mangelmeldung und Kilometerstand. Zusätzliche Fassungen enthalten leise eigene
  Hintergrundmusik. Videos und interne Aufnahmeskripte sind nicht Teil dieses Uploads.
- Physisches iPad/Touch/Kamerascan, reale NAS-/Office-/Mailbox-Abnahme,
  vollständige Barrierefreiheits- und Dauerlastprüfung bleiben offen.

## 0.2.4 – Lokale Tablet-Demo

- Schreibaktionen und Fahrerformular funktionieren auch auf lokalen HTTP-Adressen: kryptografische UUIDs bleiben verfügbar, wenn der Browser randomUUID nur über HTTPS anbietet.
- Keine Änderung an Berechtigungen, Datenmodell oder Migrationen. Produktivzugänge weiterhin über HTTPS betreiben.

## 0.2.3 – Firmenprofil und Werkstatt-Absender

- Firmenname und geprüftes Logo in allen Appkopfbereichen; Pflege unter Stammdaten.
- Gemeinsames eigenes Konto aus Nextcloud Mail als ausdrücklich delegierter Absender, einschließlich Erinnerungen; Credentials bleiben in Mail.
- Rechteentzug/Kontowechsel/Benutzerlöschung ohne stillen SMTP-Fallback, sichere Übergabestatus.


## 0.2.2 – Terminannahme, zusätzliche Zugänge und häufige Leistungen

- Administratoren können einzelne Nextcloud-Benutzer für den vollständigen Meisterbereich oder ausschließlich für die Terminannahme freigeben; optional eigene Terminannahmegruppe. Benutzerlöschung entfernt zusätzliche Freigaben.
- Assistenz sieht nur Termine und benötigte Kunden-/Fahrzeugdaten. Werkstatt, Dateien, interne Checklisten, Kalkulation, Exporte und Kapazitätsänderungen sind serverseitig gesperrt.
- Die acht häufigsten Leistungen erscheinen zuerst, auch bei Auftragserstellung. Wiederholungen, Mengen und verknüpfte Termine/Aufträge erhöhen die Rangliste nicht mehrfach; Entwürfe und abgesagte Vorgänge zählen nicht. Bestandsdaten werden in begrenzten, wiederholbaren Schritten übernommen.
- Wochenkalender direkt im Terminformular: Wochentage, vorhandene Ankunfts-/Abholtermine, freie Stunden, Auslastung, Fahrzeuglimit und explizite Datumswahl.
- Reguläres Speichern bestätigt den Termin. „Bestätigt“ ersetzt die missverständliche Anzeige „Geplant“; Entwürfe bleiben ausdrücklich getrennt.
- Neue additive Nutzungsmigration, Rechte-/Ranglisten-/Kalenderregressionen. Keine Änderung der produktiven Nextcloud und kein Store-Release.

## 0.2.1 – Annahme und tägliche Terminübersicht

- Ankunftszeit in der Fahrzeughistorie als lesbares Berliner Datum mit Uhrzeit; sichere Anzeige auch bei ungültigen Alteinträgen.

- Interne Fahrzeugannahme-/Abholcheckliste mit Kilometer-, Zustands- und Rückrufnotiz; nachvollziehbare Änderungen in der Fahrzeughistorie.
- Terminsuche und Statusfilter, schnelle Übersicht wartender und abholbereiter Fahrzeuge; vollständige Tagesauslastung trotz Filter.
- Freie Tage für den Arbeitsumfang unter Berücksichtigung des Fahrzeuglimits suchen.
- Folgetermine aus Arbeitskopien vorbereiten, ohne alte Absprachen oder interne Notizen zu übernehmen.
- Vorhandene unerledigte Fahrzeugaufträge bei Annahme und Auftragserstellung anzeigen.
- Additive Migration, Checklistenvalidierung und zusätzliche Frontend-/PHP-/API-Regressionen.

## 0.2.0 – Leistungskatalog, Terminbuch und Preisrechner

- Eingaben beim Bereichswechsel bewahren und geänderte Formulare vor Verwerfen schützen.
- Monatswechsel korrigieren, Tagesanteile/Auftragsstatus sichtbar und schnelle Terminstatusaktionen anbieten.
- Absagen ausdrücklich bestätigen; neuen Kunden vorauswählen und freie Zusatzarbeiten direkt aufnehmen.
- Veraltete Preisantworten verwerfen und Katalogübernahme an aktuellen Eingabestand binden.
- 295 unterschiedliche Leistungen für PKW und leichte Transporter; gemeinsame Auswahl mit Mengen, Zeiten, bearbeitbaren Schritten, Kategorien, Synonymen und Favoriten.
- 334 nachvollziehbare öffentliche Preisangaben von 32 Preisgebern; getrennte Vergleichsgruppen, Quellen und ausdrücklich sichtbare Datenlücken. Keine erfundenen Preise oder Herstellerzeiten.
- Tages-, Wochen- und Monatsplanung, Tagesbudgets, Ausnahmen, Mehrtagesarbeiten und begründete Überbuchungen.
- Genau ein verknüpfter Arbeitsauftrag als Entwurf; unveränderliche Leistungskopien und keine doppelte Kapazitätszählung.
- Einseitiges HTTPS-CalDAV und Nextcloud-Mail mit Kalenderdatei über dauerhafte Warteschlangen; Fehleranzeige und kontrollierte Wiederholung.
- Bewusste Übernahme regionaler Vergleichszielpreise mit Netto-Umrechnung und Umfangsbestätigung; feldweises Katalogupdate erhält eigene Änderungen.
- Automatische Leistungsüberschriften erhalten manuelle Titel; Vorlagenzeit und zusätzliche Leistungen werden addiert.
- Versandfehler zeigen Termin/Fahrzeug; entfallende Abholung erhält gesonderte Kunden-ICS-Absage.
- Jahreskostenrechner mit produktiven Stunden, Gewinn-/Materialaufschlag, Fremdleistungen und getrennten Netto-/Steuer-/Bruttobeträgen.
- Neue additive Migration, Meisterrechte und reduzierte Tabletantworten. Bestehende Verbesserungen von 0.1.7 erhalten.
- Internes, unsigniertes Paket; GitHub bleibt privat, keine Store-Einreichung und keine Änderung einer produktiven Nextcloud.

## 0.1.7 – Verständliche Fahrzeughistorie und Bereichswechsel

- Ereignisse im Änderungsprotokoll in Alltagssprache, mit Auftrag, Mitarbeiter, Teilemenge, Arbeitsnotizen und Zeitkorrekturen.
- Bereichsleiste der Fahrzeugakte bleibt beim Scrollen sichtbar.
- Aufträge, Mängel und Bearbeitungsdialoge führen zurück zur gewählten Fahrzeugansicht; Tastaturfokus bleibt erhalten.
- Verbindungsanzeige anhand tatsächlicher Serverantworten; ein irreführender Offline-Hinweis des Browsers sperrt erreichbare Nextcloud-Instanzen nicht.
- Lange Historienbeschriftungen brechen auch mit den Nextcloud-Standardstilen um.
- Keine neue Datenbankmigration; zusätzliche Regressionstests für Anzeige, Rücknavigation und Meisterrechte.

## 0.1.6 – Veröffentlichungsvorbereitung

- Öffentliche Dokumentation auf allgemeine Installation, Bedienung und Prüfergebnisse begrenzt.
- Installationspakete enthalten nur ausdrücklich freigegebene Dokumentationsdateien.
- Keine Änderung an Fachlogik oder Datenbankschema.

## 0.1.5 – Release-Vorbereitung

- Automatische GitHub-Prüfungen für PHP, Frontend, Metadaten, Paket und echte SQL-Installationen.
- GitHub-Issue-Tracker als Supportadresse; bleibt wegen privatem Repository vorerst intern.
- Versionen und Archivgrenzen vor Paketierung prüfen; Release- und Pilotverfahren dokumentiert.
- Keine Änderung an Fachlogik oder Datenbankschema.

## 0.1.4 – Korrekte Bestätigung bei Teileanforderungen

- Nach „Anfordern und pausieren“ erscheint der bestätigte Pausenstatus statt der früheren Startmeldung.

## 0.1.3 – Lesbare Rückmeldungen

- Zusammenpassende Text- und Hintergrundfarben für Erfolgs- und Fehlermeldungen; behebt den im echten Browser gefundenen zu geringen Kontrast.

## 0.1.2 – Verständlichere Bedienung

- Kurze Anleitungen in allen Hauptansichten; klarere Aktionen und Pflichtfeldhinweise.
- Auftragserstellung in drei Schritten, zusätzliche Angaben aufklappbar; Warnung beim Schließen ungespeicherter Auftragseingaben.
- Tablet erklärt fehlende Namensauswahl und belegte Mitarbeiter; konkrete Rückmeldung zu laufender, pausierter und beendeter Zeit.
- Fahrerformular mit verständlicher Anleitung, Fotoentfernung und aufklappbarer Fotohilfe.
- Zusätzliche Tablet-Komponententests für Sperren, Bestätigung und fehlgeschlagene Buchungen.

## 0.1.1 – Browserstart korrigiert

- JavaScript-Abbruch `process is not defined` im ausgelieferten IIFE behoben: Node-Umgebungswerte werden beim Produktionsbuild aufgelöst.
- Fertiges Browserpaket startet im Buildtest ohne Node-Globals und ohne Komponenten-Mocks in Meister-, Tablet- und Fahreransicht.
- Meisterdashboard in echtem Chrome geladen und visuell geprüft. Versionswechsel erneuert die Asset-Cachekennung.

## 0.1.0 – interner Prüfkandidat

- Fahrzeugakten, Kunden, Mitarbeiter und Datums-/Kilometerfristen.
- Schnelle Aufträge mit Vorlagen, Textbausteinen, Sollzeit und unabhängigen Terminen.
- Tabletworkflow, exklusive aktive Belegung, Arbeitsprotokoll, Meisterabnahme und Zeitkorrektur.
- Teileanforderungen, öffentliche Fahrzeuglinks und moderierte Mängelmeldungen.
- Geschützte Anhänge, CSV, Druckansichten, Änderungsprotokoll und Benachrichtigungsjob.
- Noch keine Produktionsfreigabe; verbindlicher Prüfstatus in docs/test-report.md.

- Editierbare Startvorlagen, Mitarbeiterfilter für pausierte/übernommene Aufträge und gezieltes Nachladen zugehöriger Fahrzeugdaten.
- Korrekturen für Sommer-/Winterzeit, Benachrichtigungs-API, Dublettenänderungen und faire Verarbeitung älterer Hintergrundaufgaben.
- Installations-/Betriebsanleitung, Pilotcheckliste, Lizenznachweise und reproduzierbares internes Kandidatenpaket.
- Meister können fehlgeschlagene Benachrichtigungen prüfen und erneut vormerken; Rechteentzug, Doppelklick und Versuchshistorie werden berücksichtigt.
- Reale Nextcloud-35.0.1-Installation: reservierte Aktionsparameter im Routing ersetzt; initiale Vorlagen zusätzlich im Install-Repair-Hook angelegt.
- Isolierte Testinstanz mit fiktiven Beispieldaten und erfolgreichen PostgreSQL-Paralleltests.


### Fahrerformular: erfolgreiche Fotoauswahl zurücksetzen

Nach vollständig bestätigter Meldung samt Fotos wird auch das Dateiauswahlfeld geleert. Bei fehlgeschlagenen Foto-Uploads bleibt die Auswahl für den erneuten Versuch erhalten.

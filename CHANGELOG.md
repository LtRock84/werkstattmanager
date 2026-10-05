# Changelog

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

# Werkstattmanager für Nextcloud

Interner Prüfkandidat 0.1.6 für eine Werkstattorganisation. Fahrzeugakten für Kunden
und eigene Flotte, schnelle Aufträge, Tablet-Zeiterfassung, Teileanforderungen und
externe Mängelmeldungen. Der aktuelle Stand ist **nicht für Produktivbetrieb freigegeben**.
Verbindliche Prüfergebnisse und offene Punkte: [Prüfbericht](docs/test-report.md).

## Bedienung

- **Meister:** Fahrzeug öffnen → „Neuer Auftrag“ → Vorlage → „Freigeben“.
  Fünf editierbare Startvorlagen werden installiert. Ihre Zeiten sind Vorschläge,
  keine Herstellervorgaben. Textbausteine ergänzen Arbeitsschritte; Dauer, geplanter
  Beginn und Fertigstellung bleiben unabhängig und optional.
- **Tablet:** Namen wählen und starten. Nur ein aktiver Auftrag je Mitarbeiter und
  nur ein Mitarbeiter je Auftrag. Pausieren gibt die Belegung frei. Kollegen können
  übernehmen. Fertigmelden führt zur Meisterabnahme; Pausen zählen nicht als Arbeitszeit.
- **Teile:** Aus dem Auftrag anfordern, wahlweise mit Pause. Der Meister bearbeitet
  die Anforderung und bestellt außerhalb der App. Offene Anforderungen verhindern
  den endgültigen Auftragsabschluss.
- **Fahrer:** Fahrzeuglink/QR-Code öffnen, Mangel melden und freigegebene offene
  Kurzmeldungen ansehen. Keine Anmeldung erforderlich. Neue Meldungen und Fotos bleiben
  intern, bis der Meister eine kurze Fahrerinformation veröffentlicht.

## Voraussetzungen und Installation auf einer Testinstanz

Ziel: Nextcloud 35, PHP 8.3–8.5 mit GD und Fileinfo. Reale Integration geprüft mit
Nextcloud 35.0.0/35.0.1, PHP 8.5.11, PostgreSQL 17.11/18.6 sowie MariaDB 11.8.
Vor einem Pilotbetrieb müssen die tatsächliche Serverumgebung und ihre Integrationen
separat geprüft werden.
MySQL wurde noch nicht separat getestet; MariaDB-Ergebnisse sind kein MySQL-Nachweis.
Eine Versionsangabe in `info.xml` ist kein Nachweis getesteter Kompatibilität.

1. Vollständiges Backup von Nextcloud-Datenbank, Konfiguration und AppData sicherstellen.
2. Quellstand mit Node 24.18.x und npm 11.16.x bauen: `npm ci`, `npm run build`.
   PHP benötigt im Betrieb keine mitgelieferten Composer-Bibliotheken; öffentliche
   OCP-Schnittstellen werden von Nextcloud bereitgestellt.
3. Metadaten gegen das offizielle Schema validieren und das Kandidatenpaket prüfen.
   Die eingetragene GitHub-Supportadresse ist wegen des privaten Repositories noch intern.
4. Paket in das konfigurierte Nextcloud-Verzeichnis für eigene Apps entpacken, sodass
   `werkstattmanager/appinfo/info.xml` vorhanden ist. Eigentümer und Leserechte an die
   Testinstallation anpassen. Keine produktive App überschreiben.
5. Auf der Testinstanz als Webserver-Benutzer `php occ app:enable werkstattmanager`
   ausführen. Installation einschließlich Migrationen prüfen.
6. Gruppen `werkstatt-meister` und `werkstatt-tablet` in Nextcloud anlegen. Meisterkonten
   und ein dediziertes Tabletkonto zuordnen. Administratoren haben Einrichtungsrechte;
   Empfänger von Werkstattbenachrichtigungen müssen in der Meistergruppe sein.
7. Als Administrator App öffnen und bei Bedarf unter Einstellungen andere existierende
   Gruppen wählen. Mitarbeiterdatensätze, Kunden und Fahrzeuge anlegen.
8. Nextcloud-System-Cron einrichten. Mit Testdaten alle Schritte aus
   [Pilotprüfung](docs/pilot-checklist.md) durchführen.

Namensauswahl am Tablet dient der Zuordnung und ist kein persönlicher Identitätsnachweis.
Die App-Rolle begrenzt keine anderen Nextcloud-Apps oder Freigaben des Tablet-Kontos.

## Entwicklung und Prüfungen

```sh
npm ci
npm run typecheck
npm run lint
npm test
npm run build
composer install
composer test:unit
composer cs:check
composer psalm
```

PHP-Fachtests verwenden eine In-Memory-Datenbank und schmale OCP-Doubles. Sie ersetzen
keine Nextcloud-/SQL-Integration. `npm run build` startet zusätzlich das fertige
Browser-Bundle ohne Node-Globals in drei Rollen; separat mit `npm run test:bundle`.
Dieser Starttest ersetzt keine visuelle Browserprüfung. Falls natives PHP fehlt, unterstützt `npm run test:php`
eine isolierte PHP-WASM-CLI über `WM_PHP_WASM_CLI`; Ergebnisse müssen erfolgreich
abgeschlossen sein, ein Prozess-Exitcode allein reicht bei dieser Laufzeit nicht.
Psalm 6.5 wird unter PHP 8.3 ausgeführt; unter PHP 8.5 trat ein Fehler im Analysewerkzeug auf.

Das offizielle Schema von `https://apps.nextcloud.com/schema/apps/info.xsd` lokal
speichern und mit `python3 scripts/validate-metadata.py /pfad/info.xsd` prüfen.
`python3 scripts/package.py --candidate --schema /pfad/info.xsd` erzeugt einen
unsignierten internen Prüfkandidaten. Paket und SHA-256-Datei liegen in `dist/`.
Die GitHub-Pipeline erzeugt und installiert exakt ihr geprüftes Paket mit PostgreSQL
und MariaDB. Sie veröffentlicht nichts im App Store. Ablauf und Grenzen:
[Release-Vorbereitung](docs/release.md).

## Dokumentation

- [Anforderungen](docs/product-spec.md)
- [Architektur, Rechte und API](docs/architecture.md)
- [Betrieb und Datenschutz](docs/operations.md)
- [Pilotprüfung](docs/pilot-checklist.md)
- [Pilot und Rückkehrverfahren](docs/pilot-rollout.md)
- [Release und Store](docs/release.md)
- [Prüfergebnisse](docs/test-report.md)

Lizenz: AGPL-3.0-or-later; vollständiger Text in `LICENSE`. Bibliotheksnachweise werden
beim Build in `js/third-party-notices.txt` erzeugt. Vor Weitergabe auch den zugehörigen
Quellstand samt Lockfiles und Buildskripten bereitstellen.

# Werkstattmanager für Nextcloud

Herausgeber: **Thomas Keilhaupt** · GitHub: [LtRock84](https://github.com/LtRock84).
Die App-Store-Veröffentlichung wird vorbereitet; es gibt noch keinen Store-Eintrag.

**[♥ Projekt über PayPal unterstützen](https://www.paypal.me/Werkstattmanager)**
– freiwilliger Beitrag zu Projektkosten und Weiterentwicklung, ohne Zusatzleistungen
oder Spendenbescheinigung. Die App bleibt kostenlos nutzbar.

Interner Prüfkandidat 0.4.1 für eine Werkstattorganisation. Fahrzeugakten für Kunden
und eigene Flotte, schnelle Aufträge, Tablet-Zeiterfassung, Teileanforderungen und
externe Mängelmeldungen, Terminbuch, Leistungskatalog, Preiskalkulation,
Umsatz-/Arbeitszeitauswertung und anpassbare Kundennachrichten mit Fristenerinnerungen. Der aktuelle Stand ist **nicht für Produktivbetrieb freigegeben**.
Verbindliche Prüfergebnisse und offene Punkte: [Prüfbericht](docs/test-report.md).
Änderungen einschließlich Projektunterstützung in 0.4.1: [Changelog](CHANGELOG.md).

Neu hinzugekommen sind der Menüpunkt **Auswertung**, optionale **Abholbereit-Mails**,
sieben bearbeitbare Nachrichtenvorlagen und automatische **Fahrzeugfristen-Erinnerungen**.
Das Repository bleibt privat; Installationspakete entstehen als interne CI-Artefakte.

## Bedienung

- **Meister:** Fahrzeug öffnen → „Neuer Auftrag“ → Vorlage → „Freigeben“.
  Fünf editierbare Startvorlagen werden installiert. Ihre Zeiten sind Vorschläge,
  keine Herstellervorgaben. Textbausteine ergänzen Arbeitsschritte; Dauer, geplanter
  Beginn und Fertigstellung bleiben unabhängig und optional.
- **Terminannahme:** Vom Administrator freigegebene Assistenzkräfte können Termine
  vereinbaren, ändern und absagen sowie vorhandene Kunden/Fahrzeuge auswählen.
  Werkstattverwaltung und Kalkulation bleiben den Meistern vorbehalten.
- **Tablet:** Namen wählen und starten. Nur ein aktiver Auftrag je Mitarbeiter und
  nur ein Mitarbeiter je Auftrag. Pausieren gibt die Belegung frei. Kollegen können
  übernehmen. Fertigmelden führt zur Meisterabnahme; Pausen zählen nicht als Arbeitszeit.
- **Teile:** Aus dem Auftrag anfordern, wahlweise mit Pause. Der Meister bearbeitet
  die Anforderung und bestellt außerhalb der App. Offene Anforderungen verhindern
  den endgültigen Auftragsabschluss.
- **Fahrer:** Fahrzeuglink/QR-Code öffnen, Mangel melden und freigegebene offene
  Kurzmeldungen ansehen. Keine Anmeldung erforderlich. Neue Meldungen und Fotos bleiben
  intern, bis der Meister eine kurze Fahrerinformation veröffentlicht.

## Terminbuch, Leistungen und Preise

Weitere Hilfen vereinfachen das Tagesgeschäft: Fahrzeugannahme-/Abholcheckliste, Rückrufnotizen, Terminsuche und Filter, passende freie Tage, vorbereitete Folgetermine und Hinweise auf bereits unerledigte Aufträge. Die [Recherche und Funktionsgrenzen](docs/workshop-functions.md) dokumentieren die Auswahl.

Die Meister-Navigation enthält **Terminbuch**, **Leistungskatalog** und **Preisrechner**.
295 unterschiedliche Leistungen sind ohne Import vorhanden. Suche, Kategorien und
Favoriten helfen bei der Auswahl; die acht häufigsten Leistungen stehen zuerst.
Mengen und Zeiten werden addiert. Dieselbe Auswahl
steht bei „Neuer Auftrag“ zur Verfügung. Fehlende Zeitvorgaben selbst ergänzen.

Im Terminbuch: Fahrzeug wählen, Leistungen ergänzen, Ankunft/Arbeitsbeginn/Abholung
festlegen und mit „Termin speichern & bestätigen“ bestätigen. Ein Wochenkalender
im Formular zeigt bestehende Termine, Wochentage und freie Stunden.
Entwürfe belegen keine Kapazität. Tagesbudget und einzelne
Schließtage werden im Terminbuch eingestellt. Überbuchungen verlangen eine Begründung.
„Arbeitsauftrag erstellen“ erzeugt einen verknüpften Entwurf, den der Meister ausdrücklich
freigibt. Terminänderungen überschreiben den Auftrag nicht automatisch.

Der Preisrechner verwendet eigene jährliche Betriebskosten und abrechenbare Stunden.
Material und Fremdleistungen werden gesondert berechnet. Eigene Nettopreise erst mit
„Preis in Katalog übernehmen“ speichern. Regionale Vergleichsgruppen können nach Prüfung
von Umfang, Einheit und Material ausdrücklich als Zielpreis übernommen werden. Die Ansicht
zeigt die Umrechnung veröffentlichter Bruttopreise nach netto; eigene Kosten bleiben daneben.
Katalogupdates erhalten bearbeitete Felder, eigene Preise, Favoriten und eigene Zeiten.

334 Preisangaben von 32 Preisgebern liegen bei, davon 248 mit vergleichbarem Umfang
und bestätigter Steuerangabe. 57 Leistungen haben mindestens eine veröffentlichte Angabe;
238 haben noch keine geeignete Quelle. Eine Angabe bedeutet noch keinen Durchschnitt.
[Quellen und Datenlücken](docs/service-prices.md) erklären die Aussagekraft.

Kalenderübertragung und Kundennachrichten benötigen eine einmalige Einrichtung durch
Administratoren: eigenes Integrationskonto, Werkstattkalender mit Lesefreigaben,
HTTPS-CalDAV-Adresse/App-Passwort und Nextcloud-SMTP. Die öffentliche Adresse für
Cron (`overwrite.cli.url`) muss ebenfalls korrekt auf HTTPS zeigen.
[Einrichtung und Fehlerbehandlung](docs/operations.md#kalender-und-kundennachrichten-ab-020).
Optional lässt sich ein ausdrücklich ausgewähltes eigenes Konto aus Nextcloud Mail
als gemeinsame Werkstattadresse verwenden; die Zugangsdaten verbleiben dort.
Firmenname und Logo werden in den Stammdaten gepflegt und erscheinen auch im
öffentlichen Fahrerformular.

## Umsatz und Werkstattauswertung

Meister öffnen **Auswertung** in der Seitenleiste. Zeitraum auswählen und unter
**Beträge erfassen** den geplanten Netto-Gesamtwert beziehungsweise nach Abnahme den
abgerechneten Istbetrag mit Umsatzdatum eintragen. Fehlende Werte werden angezeigt.
Monatsumsätze, Tagesauslastung, Mitarbeiterzeiten und Soll-/Ist-Abweichungen helfen bei
der Planung; eigene Flottenarbeit ist getrennt. Die Auswertung ersetzt keine Buchhaltung
und verwendet keine fremden Vergleichspreise als eigene Umsätze.

## Kundennachrichten und Fahrzeugfristen

Unter **Einstellungen → Kundennachrichten und Fristenerinnerungen** bearbeiten Meister
sieben fertige Textvorlagen: Bestätigung, Änderung, Absage, aufgehobene Abholzeit,
Terminerinnerung, Abholbereit und Fristenerinnerung. Platzhalter setzen Kundenname,
Fahrzeugkennung, Termin, Abholung, Leistung, Frist und Werkstatt ein. Nachrichten
sind reine Texte. Vorlagen gelten für noch nicht versendete Nachrichten;
Versionsschutz verhindert das Überschreiben fremder Änderungen.

Im Kundentermin die Option **Kunden per E-Mail benachrichtigen …** auswählen und
eine Kundenadresse hinterlegen. Wenn der Meister den Termin auf **Abholbereit**
setzt und speichert, wird die Fertigmeldung vorgemerkt. Pro Termin und Adresse
wird sie einmal versendet. Auftrag fertigmelden oder abnehmen sendet allein keine
Abholnachricht. Eigene Flottenfahrzeuge bekommen keine Kunden-Fertigmeldung.

In **Stammdaten → Kunden / Firmen → Bearbeiten** lässt sich die automatische
Fristenerinnerung aktivieren. Neue Kunden sind vorausgewählt; bestehende Kunden
bleiben beim Upgrade deaktiviert. Erforderlich sind eine gültige Kundenadresse,
ein zugeordnetes Kundenfahrzeug und eine offene Frist. In der Fahrzeugakte eine
Frist mit **Zuletzt erledigt am** und Wiederholung anlegen; die nächste Fälligkeit
wird berechnet, wenn „Fällig am“ leer bleibt. HU/24 Monate und Inspektion/12 Monate
sind bearbeitbare Schnellvorgaben, keine verbindlichen Intervalle für jedes Fahrzeug.

Standardmäßig wird 30 Tage vor der nächsten Fälligkeit erinnert: ungefähr elf
Monate nach einer jährlichen Inspektion beziehungsweise 23 Monate nach einer
zweijährlichen HU. Der Vorlauf ist in den Einstellungen von 1 bis 365 Tagen
anpassbar. Kilometerfristen werden bei erreichtem bestätigtem Stand erinnert;
Fahrleistung wird nicht geschätzt. Nach Aktivierung werden auch überfällige offene
Fristen einmal gemeldet. Nach bestätigter Erledigung entsteht die nächste Frist
mit neuem Erinnerungszyklus. Es wird kein Kundentermin automatisch gebucht.

Der bestehende Nextcloud-Mailversand beziehungsweise das konfigurierte Werkstatt-
Mailkonto und Hintergrundjobs sind erforderlich. Wunsch, Empfänger, Fahrzeugbindung
und aktuelle Fälligkeit werden vor Versand erneut geprüft. Fertig- und Fristenmails
enthalten keine zusätzliche Kalenderdatei. Versandfehler sind im Terminbuch sichtbar;
unklare SMTP-Ausgänge werden nicht automatisch erneut gesendet. Ein Versandnachweis
ist keine Bestätigung, dass die E-Mail beim Empfänger gelesen wurde.

## Projekt unterstützen

In der Meisteransicht steht unten links **Projekt unterstützen**. Ein Hinweis erklärt
vor dem Wechsel zu PayPal: freiwilliger Beitrag zu Projektkosten und Weiterentwicklung,
keine Zusatzfunktionen oder individuellen Leistungen und keine Spendenbescheinigung.
Der Button öffnet [paypal.me/Werkstattmanager](https://www.paypal.me/Werkstattmanager).
Der Betrag wird auf PayPal frei gewählt; die App richtet keine wiederkehrenden Zahlungen ein.

Auch diese Projektseite enthält einen direkten Unterstützungslink.
`.github/FUNDING.yml` hinterlegt denselben PayPal.Me-Link für GitHubs
Sponsor-Funktion. Es wird kein GitHub-Sponsors-Konto vorausgesetzt und kein
Zugriff auf das private Repository durch eine Zahlung freigeschaltet.

Der Empfänger ist fest im ausgelieferten Code hinterlegt und kann nicht über
App-Einstellungen, Firmenprofil, API oder eingehende URL-Parameter ersetzt werden.
Serverbetreiber können den quelloffenen Code verändern; absolute Unveränderbarkeit
wird deshalb nicht behauptet. Der öffentliche PayPal.Me-Profilname ist im Zahlungslink
und Quellcode lesbar; eine Empfangs-E-Mail-Adresse wird nicht im Link mitgeliefert.
Es sind keine PayPal-Zugangsdaten enthalten.

PayPal wird erst nach dem ausdrücklichen Weiterklick in einem neuen Tab geöffnet.
Keine eingebetteten PayPal-Skripte, Cookies, Vorabverbindungen oder Übermittlung von
Werkstattdaten; die App verarbeitet oder speichert keine Zahlungen. Tablet, Fahrer
und Terminannahme zeigen keinen Unterstützungsaufruf. Die freiwillige Zahlung ändert
weder die Lizenz noch die steuerlichen Pflichten des Empfängers.

## Voraussetzungen und Installation auf einer Testinstanz

Ziel: Nextcloud 35, PHP 8.3–8.5 mit GD und Fileinfo. Reale Integration geprüft mit
Version 0.4.0 auf Nextcloud 35.0.0/35.0.1 mit PHP 8.5.11, PostgreSQL 17 und
MariaDB 11.8 in allen vier Kombinationen. PHP-Fachtests bestehen unter 8.3/8.4/8.5.
Vor einem Pilotbetrieb müssen die tatsächliche Serverumgebung und ihre Integrationen
separat geprüft werden.
MySQL wurde noch nicht separat getestet; MariaDB-Ergebnisse sind kein MySQL-Nachweis.
Upgrade von 0.3.0 und Datenerhalt wurden zusätzlich auf 35.0.0/MariaDB und
35.0.1/PostgreSQL geprüft. Maßgeblich ist der aktuelle Prüfbericht mit dem
zugehörigen Paketstand; ältere Nachweise werden dort getrennt aufgeführt.
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
   zusätzliche Meister können auch einzeln durch einen Administrator freigegeben werden.
   Benachrichtigungen berücksichtigen diese direkten Meisterfreigaben.
7. Als Administrator App öffnen und bei Bedarf unter Einstellungen andere existierende
   Gruppen wählen oder einzelne Nutzer für Meisterzugang beziehungsweise Terminannahme
   freigeben. Mitarbeiterdatensätze, Kunden und Fahrzeuge anlegen.
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
Browser-Bundle ohne Node-Globals in vier Rollen; separat mit `npm run test:bundle`.
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

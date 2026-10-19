# Prüfbericht – Werkstattmanager

Stand 2026-10-17. **Interner Prüfkandidat, keine vollständige Produktionsfreigabe.**
Dieser Bericht beschreibt die Reichweite der Prüfungen ohne standortspezifische
Betriebsdaten. Automatische Prüfungen sind über die
[GitHub-Pipeline](https://github.com/LtRock84/werkstattmanager/actions/workflows/quality.yml)
nachvollziehbar; bei privatem Repository benötigen sie entsprechende Zugriffsrechte.
Ein neues Paket muss die Pipeline erneut bestehen.

## Private GitHub-Aktualisierung 0.4.0 → 0.4.1 (11.10.2026)

Enthalten sind der Meisterbutton mit vorgeschaltetem Unterstützungshinweis,
das feste PayPal.Me-Ziel, der direkte README-Link, GitHubs Funding-Konfiguration
und die Veröffentlichungsvorbereitung unter Thomas Keilhaupt. Keine neuen
Backend-Endpunkte, Migrationen oder Abhängigkeiten.

**Erfolgreich ausgeführt:** Typecheck, ESLint, alle 98 Frontendtests, Produktionsbuild
mit vier Bundle-Rollenstarts und sechs Paketgrenztests erneut bestanden.
Ein gleichzeitig zum Build gestarteter Diff-Check traf kurzfristig auf dessen
neu aufgebauten Ausgabeordner; nach Buildabschluss erneut erfolgreich geprüft.
Die Funding-Konfiguration wird außerhalb des Nextcloud-Installationspakets gehalten.

Die GitHub-Pipeline prüft den hochgeladenen Commit einschließlich seines eigenen
Kandidatenarchivs und der vier Nextcloud-/SQL-Installationen. Ihr Ergebnis ist
beim [konkreten Actions-Lauf](https://github.com/LtRock84/werkstattmanager/actions)
zu kontrollieren; die vorherige 0.4.0-Pipeline bestätigt den neuen Commit nicht.
Die lokale Demo bleibt 0.4.0. Öffentliche Sichtbarkeit, Store-Einreichung,
Zahlung und physische Geräte-/Betriebsabnahme sind damit nicht erledigt.

## Store-Vorbereitung unter Thomas Keilhaupt (11.10.2026)

**Erfolgreich ausgeführt:** Herausgeber ohne öffentliches Mailattribut ergänzt;
Metadaten gegen das erneut abgerufene offizielle Store-XSD validiert und
Versionsgleichheit der App-/npm-Metadaten beim Paketbau geprüft;
sechs Paketgrenztests erneut bestanden. Gezielte Prüfung von 170 aktuellen
Projektdateien und 250 erreichbaren historischen Git-Blobs ohne Treffer für die
geprüften privaten Rechnerpfade, Kontaktdaten, Netzadressen, Archivverweise und
Schlüssel-/Tokenmuster. Die Prüfung ist keine Garantie gegen jedes mögliche
private Datum und keine rechtliche Wiederverwendungsprüfung.
Das offizielle aktuelle Store-Schema wurde erneut abgerufen; es entspricht dem
bisher für die Metadatenprüfung verwendeten Schema.

**Noch offen / nicht ausgeführt:** Zuordnung des vorhandenen App-Store-Kontos zur
gewünschten Konto-Adresse, öffentliche Repositoryfreigabe, Zertifikatsantrag,
ausgestelltes Zertifikat, Signierung, aktuelle Store-Bilder und Einreichung.
Eine erneute SQL-/HTTPS-Matrix, Installation des neuen Kandidaten und Prüfung
eines signierten Endpakets wurden in dieser Vorbereitung nicht durchgeführt.
Die nachfolgenden 0.4.1-Nachweise betreffen die dort genannten Funktionstests;
die ältere 0.4.0-Pipeline ist keine Prüfung des jetzigen Endpakets.

## Neue Prüfung 0.4.1 – freiwillige Projektunterstützung (11.10.2026)

**Erfolgreich ausgeführt:** Typecheck, ESLint, 98 Frontendtests, Produktionsbuild,
vier Rollenstarts des tatsächlich gebauten Browser-Bundles, offizielles
Metadaten-XSD und sechs Paketgrenztests. Das Zahlungsziel im Bundle und in den
App-Metadaten stimmt überein. Der Empfänger lässt sich weder durch die
Werkstatteinstellungen noch durch eingehende URL-Parameter ersetzen.

Eine isolierte Browser-Prüfseite mit dem Produktionsbundle und fiktiven, leeren
API-Antworten bestätigt: Unterstützung nur im Meisterbereich, Dialog öffnet und
schließt, Escape und Zurück stellen den Fokus auf den Ausgangsbutton zurück.
Bei 768×1024 und 1024×768 ist der Dialog vollständig lesbar; die Seitenleiste
bleibt erreichbar. Bei schmaler Ansicht ist der Dialog scrollbar. Der externe
Link besitzt `target="_blank"`, `noopener noreferrer` und `no-referrer`, auch
bei manipulierten Empfängerparametern der eingehenden URL.
Diese Prüfseite ersetzt keine Nextcloud- oder physische iPad-Prüfung und nutzt
nicht das vollständige Nextcloud-Theming.

Nach der Aktivierung durch den Eigentümer zeigt dessen Kontoprofil den Link
`paypal.me/Werkstattmanager`; die öffentliche Seite zeigt denselben Profilnamen
und den zugehörigen Kontoinhaber. Das frühere Donate-Ziel mit Empfangs-E-Mail
wurde durch dieses PayPal.Me-Ziel ersetzt und das Produktionsbundle neu gebaut.
Die Browserfixture-Prüfungen oben betreffen den vorangehenden Dialogstand;
der Dialog ist unverändert, der neue Link zusätzlich in Bundle/Metadaten geprüft.
**Nicht ausgeführt:** Zahlung, Zahlungsannahme-/Kontoeignungsbestätigung, neue Backend-/SQL-/HTTPS-Matrix,
Installation von 0.4.1 in der lokalen Demo sowie öffentliche GitHub-/Store-Veröffentlichung.
Backend und Migrationen sind unverändert; ältere Integrationsnachweise gelten
für ihre dort genannten Pakete. Steuerliche Einordnung und die bisherigen
Geräte-/Betriebsabnahmen bleiben offen. Die lokale Demo und GitHub stehen noch
auf 0.4.0 in diesen ursprünglichen Funktionsnachweisen. Die anschließende private
GitHub-Aktualisierung auf 0.4.1 ist ein eigener Prüflauf; ihr Commit und ihre Pipeline
müssen für deren konkreten Paketstand ausgewertet werden.

## GitHub-Übernahme 0.2.4 → 0.4.0 (11.10.2026)

Die Aktualisierung umfasst Auswertung, Kundennachrichten und Fristenerinnerungen
sowie überarbeitete öffentliche Dokumentation und App-Beschreibung. Die folgenden
Nachweise gehören zu ihren jeweiligen lokalen Prüfpaketen. Texte und Metadaten
der GitHub-Fassung wurden anschließend aktualisiert; ihre eigene Pipeline prüft
das daraus neu gebaute Installationsartefakt in allen vier SQL-/Nextcloud-Fällen.
Maßgeblich für den GitHub-Stand ist der zugehörige Commit mit Actions-Ergebnis.
Ein grüner Lauf ersetzt die unten benannten Geräte- und Betriebsabnahmen nicht.

## Neue Prüfung 0.4.0 – Kundenbenachrichtigungen (11.10.2026)

**Erfolgreich ausgeführt:** 70 native Fachszenarien jeweils unter PHP 8.3/8.4/8.5,
98 Frontendtests, Typecheck, ESLint, Nextcloud Coding Standard, Psalm ohne Fehler
(189 Informationsmeldungen), Produktionsbuild und vier Bundle-Rollenprüfungen,
offizielles Metadaten-XSD und sechs Paketgrenztests.

Der interne Kandidat 0.4.0 mit SHA-256
`fbde00ab0d086cbcc8b26c4021f6f35469fb4dcade1982ddb31fb7776f39dba6`
besteht PostgreSQL-/MariaDB-Integration jeweils mit Nextcloud 35.0.0 und 35.0.1.
Upgrade von 0.3.0 geprüft auf 35.0.0/MariaDB und 35.0.1/PostgreSQL:
vorhandene Kundenfelder erhalten, neue Kunden-Erinnerungsoption deaktiviert.
Deaktivierung/Aktivierung und Neustart erhalten Daten.

Auf 35.0.1/PostgreSQL wurden echtes isoliertes HTTPS, SMTP, CalDAV und Cron
geprüft: editierte personalisierte Vorlage, Name/Fahrzeug/Termin im Mailtext,
Abholbereit einmalig, Widerruf vor Zustellung, Fristenerinnerung einmalig,
keine zweite Kalenderdatei für Fertig-/Fristennachrichten, 24-Stunden-Erinnerung,
Änderung/Absage, SMTP-Ausfall und ausdrückliche Wiederholung bei unklarem Ausgang.
Alle Empfänger fiktiv und der Versandserver abgeschottet; keine echten Kundenmails.
Auch die bestehenden Werkstatt-, Fahrer-, QR-Zugangs-, Rollen-, Auswertungs- und
Kapazitätsabläufe wurden in dieser Integrationsschleife ausgeführt.

Chrome und WebKit: bearbeitete Vorlagen bleiben beim Menüwechsel erhalten,
Verwerfen stellt den gespeicherten Text wieder her, neue Kundenoption vorausgewählt,
letzte Erledigung berechnet Folgefrist, Termin-Entwürfe speichern beide Mailwünsche,
Tablet ohne Einstellungsmenü, keine JavaScript-Fehler und kein Seitenüberlauf bei
768×1024, 1024×768 und 1280×900. Fiktive Fahrzeuge, Fristen und Entwürfe entfernt.
Lokale Demo nach SQL-/Konfigurations-/Appsicherung aktualisiert; alle ursprünglichen
Fachfelder gegen die Sicherung geprüft. Neue Optionen sind für Bestandskunden aus.

**Frühe fehlgeschlagene Prüfansätze behoben:** temporäre npm-/Browserwerkzeuge fehlten;
gleiche Versionen wiederhergestellt. PHP-Testfixture hatte eine falsche Argumentzahl,
Browserselektor erwartete einen zu engen Labeltext, Sicherungs-Prüfcontainer benötigte
die originale Tabellenbesitzerrolle. Korrigierte Wiederholungen bestanden.
Ein API-Baselineversuch überschritt das zulässige Termin-Suchintervall;
Datenerhalt stattdessen gegen die echte unmittelbar vorherige SQL-Sicherung geprüft.
Keine Prüfregel abgeschwächt und kein früherer Fehlversuch als Erfolg gewertet.

**Nicht ausgeführt / weiter offen:** physisches iPad mit Touch/Kamerascan,
reale NAS-/Office-/Werkstattmailbox-Abnahme, vollständige WCAG-/Screenreader- und
Dauerlastprüfung sowie rechtliche Wiederverwendungsprüfung der Preisquellen.
Die bekannte Kernschemawarnung zur Tabelle `oc_federated_invites` bleibt bestehen;
die neue App-Migration erzeugt sie nicht. Dieser lokale Prüflauf veröffentlichte
nichts auf GitHub; die anschließende Übernahme ist oben getrennt beschrieben.
Kein öffentlicher Release, Store-Einreichung oder vollständige Produktionsfreigabe.

Nachfolgende Nachweise älterer Versionen gelten jeweils für den dort genannten Stand.

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

## Nachprüfung 0.1.7 – Fahrzeughistorie und Navigation (2026-10-05)

Version 0.1.7 verbessert die lesbare Fahrzeughistorie, die feste Bereichsnavigation
und die Rückkehr aus Detaildialogen. Die Verbindung wird durch tatsächliche
Serverantworten geprüft. Keine neue Migration und keine Abhängigkeitsaktualisierung.

**Erfolgreich ausgeführt:**

- Node 24.18.0/npm 11.16.0: Typecheck, ESLint ohne Warnungen, Produktionsbuild
  und drei Starttests des fertigen Browserbundles (Meister, Tablet, Fahrer).
- 25 Frontendtests: vorhandene Auftrags-/Tabletworkflows, lesbare Audittexte,
  fehlende Referenzen, Rückkehr mit Bereich und Fokus, bearbeitbare Termin-Daten,
  erreichbarer Server trotz Offline-Hinweis, tatsächlicher Verbindungsfehler und
  Wiederholung ohne neue Buchungskennung. Dialogmethoden sind im Komponententest ersetzt.
- Native PHP 8.3.35: 27 Fachtests, Coding Standard für 23 Dateien und Psalm 6.5
  ohne Befunde. Zuvor 27 Fachtests und Syntaxprüfungen mit PHP-WASM 8.5.10 bestanden.
  In-Memory-Tests ersetzen keine SQL-Integration.
- Reale Nextcloud 35.0.1/PHP 8.5.11 mit PostgreSQL 17 und MariaDB 11.8:
  Paketinstallation, konkurrierende Starts, parallele Aufträge, korrekte Zeitintervalle,
  idempotente Pause, Übernahme, Fertigmeldung, Meisterabnahme, Rollen und Termine.
  Zusätzlich tatsächliche Auditkontexte, Notizen/Schritte, Teileanforderungen mit und
  ohne Pause und gesperrter Tablet-Zugriff auf das Änderungsprotokoll. Datenerhalt nach
  Deaktivieren/Aktivieren und Containerneustart.
- Lokales Upgrade von 0.1.5 auf 0.1.7 mit vorheriger Datenbank-/App-/Konfigurationssicherung.
  Alle Datensätze aus 14 Werkstattbereichen unverändert: unter anderem drei Fahrzeuge,
  sieben Aufträge, neun Zeitintervalle und 51 Auditereignisse. Neuer Anzeigekontext
  wird beim Vergleich getrennt vom unveränderten gespeicherten Ereignis betrachtet.
- Chrome mit realer Test-Nextcloud: lange Historie, feste Navigation beim Scrollen,
  Auftrags-/Mängelrückkehr, Auftragserstellung mit vorbelegtem Fahrzeug und Rückkehr,
  tatsächlicher Tastaturfokus sowie bearbeitbare Termin-Erledigungsdaten.
  768×1024, 1024×768 und 390×844 ohne horizontalen Dialogüberlauf; lange
  Historienbeschriftungen überlappen auch mit den Nextcloud-Standardstilen nicht.
  Kein physischer iPad-/Safari-Test. Keine erfassten Browserwarnungen/-fehler.
- Metadaten gegen frisch heruntergeladenes offizielles App-Store-XSD validiert,
  sechs Paketgrenztests bestanden. Archiv mit 43 regulären Dateien auf sichere
  Grenzen geprüft; interne Unterlagen und Entwicklungslaufzeiten ausgeschlossen.

**Behobene Prüfprobleme:** Dateisystem-Begleitdateien sind vom Quelltextscan
ausgeschlossen. Eine zunächst unvollständige Fokusrückgabe, lange nicht umbrechende
Beschriftungen und eine irreführende Offline-Sperre wurden im echten Browser
erkannt und korrigiert. Die zwischenzeitliche Browserblockade wurde bei der
Fortsetzung nicht mehr gemeldet; die letzte Terminänderung ist nun visuell geprüft.

**Nicht ausgeführt für 0.1.7:** Installation auf einer produktiven Zielinstallation,
vollständige PHP-/DB-Versionsmatrix, neue vollständige HTTPS-/Office-Abnahme,
physischer iPad-/Safari-Test und vollständige Accessibility-/Screenreader-/Dark-Mode-Abnahme.
Frühere HTTPS-/Office-Prüfungen anderer Versionen ersetzen diese Nachweise nicht.
GitHub-CI wurde während dieser damaligen lokalen Prüfung nicht ausgeführt.

**Grenzen der Historie:** Referenztexte spiegeln den aktuellen Datensatz wider.
Neue Notizen/Arbeitsschritte und das Pausenflag werden ab 0.1.7 gespeichert;
fehlende Angaben alter Ereignisse werden nicht nachträglich erfunden.
Das interne Paket bleibt unsigniert und ohne vollständige Produktionsfreigabe.

## Nachprüfung 0.2.0 – Katalog, Terminbuch und Kalkulation (2026-10-05)

Der folgende Stand betrifft den damaligen lokalen 0.2.0-Code. Frühere 0.1.x-Nachweise
werden nicht als vollständige Prüfung der neuen Funktionen gewertet.

### Erfolgreich ausgeführt

- Node 24.18.0/npm 11.16.0: Typecheck, ESLint ohne Warnungen, **41 Frontendtests**, neuer
  Produktionsbuild und drei Bundle-Startprüfungen (Meister, Tablet, Fahrer).
  Zehn neue Regressionen sichern automatische/manuelle Titel, Mengen, Vorlagenzeit,
  Entfernung, unbekannte Zeiten sowie ausdrücklich bestätigte Vergleichspreisübernahme.
- Native PHP 8.3.35: **38 Fachszenarien**, darunter Drei-Wege-Katalogupdate mit erhaltenen
  Eigenanpassungen, einzelne Kunden-Abholabsage und sichere Versandlistenantwort.
- Nextcloud Coding Standard auf 36 PHP-Dateien; Psalm 6.5/errorLevel 4 ohne Fehler,
  **111 Informationsbefunde**. Dies ist keine vollständige Befundfreiheit.
- App-Metadaten gegen vorhandenes offizielles App-Store-XSD, sechs Archivgrenztests
  und Paketgrenzen erfolgreich. Interne Pakete enthalten 60 reguläre Dateien,
  einschließlich zusätzlicher additiver Migration; keine privaten Deploymentprotokolle.
- Datenabdeckung unabhängig geprüft: 295 Leistungen / 15 Kategorien, 334 Beobachtungen
  von 32 Preisgebern,248 zulässig/86 ausgeschlossen. 57 Leistungen mit Beobachtungen,
  238 ohne; nur fünf recherchierte Zeitangaben. Keine erfundenen Ersatzwerte.

### Integrations- und Browserstatus

**Erfolgreich ausgeführt:** acht isolierte Installationen: Nextcloud 35.0.0 und
35.0.1 mit PostgreSQL 17 bzw. MariaDB 11.8, jeweils frisch und Upgrade von 0.1.7.
Alle prüfen reale SQL-/HTTP-Abläufe, parallele Buchungen, Rollen, 295 Katalogeinträge,
unveränderliche Arbeitskopien, Kapazitätszählung und Datenerhalt nach Neustart.

Der echte Cron-Scheduler versendet eine fällige Erinnerung genau einmal; Widerruf
verhindert den Versand. CalDAV-503, Rückstellung, Wiederanlauf und entfernte/wieder
hergestellte Zugangsdaten wurden mit isoliertem HTTPS geprüft. Ein unklarer SMTP-
Ausgang wird sichtbar und ohne automatische Doppelzustellung behandelt. Entfallende
Abholzeit erzeugt genau eine Kundenabsage für deren UID. Keine echten Kundenmails.

Der zuvor offene MariaDB-Upgrade-Lauf ist erfolgreich abgeschlossen. Anfangs
scheiterten Starts vor dem Upgrade an HTTP-Bereitschaft (400 bzw. transient 404).
Der Harness wartet jetzt begrenzt auf tatsächliche HTTP-/Appbereitschaft.

Chrome mit realer lokaler Demo: Termin speichern und ändern, Mehrtagesaufteilung,
korrekt abgelehnte inkonsistente Zeitverteilung, manuell erhaltener Titel, genau ein
verknüpfter Auftrag, ausdrückliche Freigabe, Arbeitsstart/Fertigmeldung und Abnahme.
Mechanikeraktionen wurden in Meisteransicht mit Mitarbeiterwahl geprüft; ein eigener
Tablet-Browserlogin wurde nicht geprüft. Die Rollenbeschränkungen bestehen die API-Matrix.

Preisrechner unabhängig bestätigt: 80 €/h Kosten, 96 €/h Verkauf, 219 € netto,
41,61 € Umsatzsteuer und 260,61 € brutto. Regionale Preisübernahme erst nach
Umfangsbestätigung; 40 € brutto werden zu 33,61 € netto je Einheit. Eigene Kosten
bleiben erhalten; ausdrücklich gespeicherter Zielpreis wurde unabhängig aus der API
gelesen und der ursprüngliche Demopreis danach wiederhergestellt.

Dark-Mode-Katalog und Leistungsdialog visuell geprüft; 390×844, 768×1024 und
1024×768 ohne horizontalen Seitenüberlauf. Tab/Shift-Tab erreichen beschriftete
Dialogfelder; ursprüngliches Systemdesign und Bildschirmgröße werden wiederhergestellt.
Dies ersetzt keine vollständige Accessibility-Abnahme.

Die Matrix prüfte den Laufzeitkandidaten mit SHA-256
`6642f82581ea4027a1d0ba1d11c5eafe27e0e43cb34f14ffdbb0bc4bc094b8e0`.
Der abschließende Frontendfix verhindert, dass ein geleertes optionales Preisfeld
als 0 € gespeichert wird; eine weitere Regression sichert `null` ab.
Finales Archiv, bytegleicher Laufzeitabgleich und lokale Demo werden im internen
Übergabeprotokoll mit Prüfsummen und tatsächlichen Abschlussresultaten erfasst.

### Verbleibende Abnahmen

Physisches iPad/Safari, vollständige WCAG-AA-/Screenreader-Abnahme, externe Kunden-
Kalenderprogramme und Dauerlast werden ohne tatsächliche Prüfung nicht freigegeben.
Die App ist intern und unsigniert; kein Store-Release und kein Produktionsdeployment.
GitHub-CI wurde für den uncommittierten lokalen Stand nicht ausgeführt.

## UX-Verbesserung 0.2.0 (2026-10-05)

Erfolgreich: Typecheck, ESLint ohne Warnungen, **50 Frontendtests** und Produktionsbuild mit drei Browserbundle-Starttests. Neun gezielte Zusatzregressionen pruefen Monats-/Jahreswechsel, Berliner Tagesanteile, Dirty-Revert/Unload-Cleanup, ausdrueckliche Absage mit Originalpayload, Kundenkontext/Schliessschutz, freie Arbeit ohne Katalogmutation, Tagesstatus mit Originalversion und veraltete Preisantwort sowie freie Paketarbeit ohne leere Katalogreferenz.

Nur Frontend und Dokumentation geaendert; PHP, Migrationen und Rechte unveraendert. Die vorangehenden SQL-/Cron-/Versandnachweise gelten fuer diesen identischen Backendstand. Neuer Paket-/Demostand und reale Browserabnahme werden separat im laufenden lokalen Protokoll mit SHA erfasst. Die fruehere UX-Pruefung beschreibt Ausgangsfehler; Umsetzung und Nachpruefung unterscheiden. Physische Geraete-/Nutzer-/Screenreader-Abnahme weiterhin offen.

Reale Chrome-Nachpruefung: Kalkulation bleibt nach Bereichswechsel erhalten; 31.10. navigiert nach 01.11.; Mehrtagestermine zeigen Tagesanteil und Auftragsstatus. Freie 35-Minuten-Arbeit wurde in einem fiktiven Termin gespeichert. Direkte Ankunft und Abholbereit wurden serverseitig bestaetigt. Absage zeigt den Originaltermin, Zurueck bewahrt den Entwurf, Bestaetigung speichert die Absage ohne den ungespeicherten Titel. Keine echten Kundenmails. Native Verwerfen-Rueckfrage erscheint; Browsersteuerung beim Dialoghandling eingeschraenkt. Automatische Regression prueft Aenderung/Revert und Schliessschutz. Isoliertes Upgrade von 0.1.7 unter NC35.0.1/PostgreSQL17 mit realem Cron/SMTP/CalDAV erneut erfolgreich am UX-Kandidaten; Archividentitaet und abschliessender Installationslauf im internen Protokoll.

## Ausbau 0.2.1 – Annahme und Terminübersicht

Prüfung am 05.10.2026 mit Node 24.18.0/npm 11.16.0, nativer PHP 8.3.35-Analyse und realen Nextcloud-35.0.0-/35.0.1-Instanzen (PHP 8.5.11) gegen PostgreSQL 17/MariaDB 11.8. Die vorhandenen unveröffentlichten Vorarbeiten wurden erhalten.

| Prüfung | Ergebnis |
| --- | --- |
| Frontend | 59 Tests bestanden, einschließlich der letzten Navigationskorrektur |
| Backend | 40 native PHP-Szenarien bestanden |
| Coding Standard / Psalm | 38 Dateien bestanden; keine Psalm-Fehler bei errorLevel 4, weiterhin 111 Informationsmeldungen |
| Typecheck / ESLint / Build | Bestanden; Bundle-Start für Meister, Tablet und Fahrer bestanden |
| Metadaten / Paketgrenzen | XSD und sechs Paketgrenzentests bestanden |
| Installation / Upgrade | Acht SQL/API-Matrixfälle mit dem ersten Ausbauarchiv bestanden: beide Nextcloud-Versionen, beide Datenbanken, Neuinstallation und Upgrade von 0.1.7 |
| Upgrade von 0.2.0 | PostgreSQL und MariaDB mit erhaltenem altem Termin bestanden |
| HTTPS / CalDAV / SMTP / Cron | Echter PostgreSQL-Zusatzlauf mit TLS, SMTP-Stub, CalDAV und Nextcloud-Cron bestanden, einschließlich Erinnerungen und Ausfällen |
| Lokaler Datenerhalt | Reguläres Demo-Upgrade bewahrt alle ursprünglichen Felder und Versionen in 16 Datenbereichen |

Die letzte Navigationskorrektur betrifft nur das Frontend; PHP, Migrationen und APIs entsprechen dem vollständig geprüften Ausbauarchiv. Die Identität und zusätzlichen Installationsprüfungen des abschließenden Archivs werden im lokalen Freigabeprotokoll getrennt dokumentiert.

Die neuen Prüfungen decken Checklistenvalidierung, Rechte, Versionskonflikte, Wiederholbarkeit, Bewahrung bei Statusänderungen, interne Datenabgrenzung, Vorschläge ohne Reservierung, Arbeitskopien und Filter ohne veränderte Kapazität ab. Checklisten erscheinen weder in Tabletaufträgen noch in SMTP oder CalDAV. Im Browser wurden Suche, Folgetermin, Hinweise auf bestehende Aufträge, freie Werktage und das Speichern/Wiederöffnen der Checkliste bestätigt. Ein fiktiver Annahmeentwurf mit 35 Minuten wurde ohne Kunden-E-Mail gespeichert; er bucht keine Kapazität und versendet nichts.

**Nachprüfung am 06.10.2026:** Der native Chrome-Änderungsdialog ist vollständig geprüft: Abbrechen bewahrt Eingaben; bestätigtes Verwerfen schließt ohne Speicherung. Physisches iPad/Safari, vollständige WCAG-/Screenreaderprüfung, externe Kundenkalenderprogramme, Dauerlast und vollständige PHP-Versionenmatrix bleiben offen. Die bekannte lokale Nextcloud-Kernschemawarnung zu `oc_federated_invites` wurde nicht durch Änderungen an fremden Tabellen bearbeitet.

Keine Produktionsfreigabe, GitHub-Veröffentlichung, Store-Einreichung oder echten Kundenmails. [Recherchierte Werkstattfunktionen und Grenzen](workshop-functions.md).

## Browserfortsetzung und Datumskorrektur am 06.10.2026

- Erfolgreich: echte Chrome-Rückfrage mit Bewahrung/Verwerfen, freies 35-Minuten-Katalogpaket speichern, beim direkten Auftrag auswählen und anschließend das Testpaket reversibel archivieren. Keine leere Katalogreferenz und keine zusätzliche Auftragsfreigabe.
- Erfolgreich: separate reguläre Tablet-Anmeldung, ausschließlich Werkstattaufträge, Fortsetzen erst nach Namenswahl. Desktop-Browseransicht bei 768×1024 und 1024×768 ohne horizontalen Überlauf. Dies ersetzt keinen physischen iPad-/Safari-Test.
- Gefunden und korrigiert: Kundentermin-Ankunft im Fahrzeugjournal wurde fälschlich durch einen reinen Datumsformatter verarbeitet. Jetzt bestehender Berliner Zeitformatter und verständlicher Fallback für ungültige Altdaten; zwei neue Regressionen einschließlich Zeitumstellung/Jahreswechsel. **61 Frontendtests**, Typecheck und ESLint bestanden. Finaler Build, Paketidentität, Installation und Browsernachprüfung werden im lokalen Übergabeprotokoll gesondert belegt.
- Reine Frontendkorrektur; keine PHP-, API-, Schema-, Gruppen- oder Integrationsänderung. Vorangegangene Backendmatrix bleibt ein Nachweis des identischen Backendstands und wird nicht als neuer Lauf ausgegeben. Alte Demo-Geschäftsdaten bewahrt, kein Kunden- oder Zeittimerdatensatz durch diese Browserfortsetzung erzeugt.

### Erneute Fahrer-/Gesamtprüfung am 06.10.2026

Erneute reale SQL-/HTTP-Prüfungen mit PostgreSQL17 und MariaDB11.8 einschließlich anonymem Mangel/km/Fotoupload, Fahrzeugbindung, Widerruf, Meisterbestätigung, Reparatur, Teile, Nacharbeit, Fristen, Zeitkorrektur und zehn Exporten bestanden. PostgreSQL zusätzlich mit verifiziertem HTTPS, SMTP, CalDAV und echtem Cron. Im Browser erzeugter QR unabhängig lokal aus dem PNG decodiert; anonym Meldung/km/Foto abgesendet und geprüft. Dabei verbleibenden Dateinamen nach Erfolg korrigiert; Dateiauswahl wird erst nach vollständig bestätigten Uploads geleert. Der physische iPad-/Kamera-/Papierdrucktest bleibt nicht ausgeführt. Keine Produktionsänderung. Nach der Korrektur bestanden 69 Frontendtests, Typecheck, ESLint und Produktionsbuild mit drei Rollenstarts. Die SQL-/HTTPS-Nachweise beziehen sich auf den identischen Backendstand; der zusätzliche finale Paket-/Browsernachweis wird getrennt in der lokalen Deployment-Dokumentation geführt.

## Erweiterung0.2.2 – Prüfung am06.10.2026

Geprüft im bewahrten lokalen Entwicklungsstand auf Grundlage0.2.1, Gitbasis ce0d2d1. Node24.18/npm11.16, nativePHP8.3.35 und echte NC35.0.1-Container mit PHP8.5/PostgreSQL17/MariaDB11.8. Neue Prüfungen: zusätzliche Meister-/Annahmebenutzer, Gruppenzugänge/Entzug, reduzierte Datensicht und gesperrte Werkstattaktionen; bestätigtes Standardspeichern; Rangliste ohne Wiederholungs-/Mengen-/Termin-Auftrag-Doppelzählung; Kalenderkarten/Wochentage/Uhrzeiten/Kapazität/Navigation/staleAntworten.

Erste0.2.2-Paketprüfung (92e22d…): **bestanden** –78Frontendtests,45nativeSzenarien,Typecheck/ESLint/Build/vierBrowser-Bundlerollen,PHP-CS43Dateien,Psalm0Fehler(121Informationshinweise),XSD/sechsPaketgrenztests; echte Neuinstallation PostgreSQL mit vollständigemHTTPS/SMTP/CalDAV/Cron und MariaDB-Upgrade0.2.1 mit Datenerhalt plus vollständigen Werkstatt/Fahrer/Rollen/Ranglistenabläufen. Frühere Lint-/Psalm-/Testfixturefehler wurden behoben und nicht als Erfolge gewertet.

Abschließende Ergänzungen: Löschen direkt freigegebener Nutzer entfernt Rechte; begrenzte201Datensätze-Backfillprüfung, Erhalt unveränderter Meisterüberbuchung bei Assistenz-Kontaktänderung und echte Übernahme historischer verknüpfter Vorgänge. Diese Nachprüfungen/abschließendePaketidentität/Demoupgrade werden im lokalen Fortsetzungsprotokoll mit eindeutigenHashes geführt; Vorgängerergebnisse allein bestätigen keine danach veränderten Backenddateien.

**Nicht ausgeführt für0.2.2:** physisches iPad/Touch/Kamera, echte NAS-/Office-Zielintegration, vollständigerScreenreader/WCAG-/Dauerlast-/Betriebnachweis, neueNC35.0.0-/PHP8.4-Matrix und GitHubCI. GitHub bleibt privat, keine Store-/Produktionsfreigabe. Preisrecherche-Datenlücken unverändert. Interne Testarchive und Nachweise enthalten keine Produktionsfreigabe.

## Firmenprofil und Mail-Absender 0.2.3

85 Frontendtests,53nativePHP-Szenarien, Typecheck/ESLint/Produktionsbuild/vierRollenstarts bestanden; CodingStandard48Dateien, Psalm0Fehler/141Informationshinweise. GezielteRegressionsfälle: Textescaping/FirmenlogoURL undsofortigeServerübernahme, Fehlererhalt, verzögertesAdminflag/Kontoauswahl, getrennteÜbergabeanzeige, Konto-/Benutzer-/Adresswiderruf ohneFallback, privatePayloadentfernung.

EchteHTTP-Prüfung: Bildverarbeitung, ungültigesSVG/MIME-Fälschung/Entfernung/Rechte; offiziellerMail5.12.3-Provider zeigt ausschließlich eigeneKonten undverweigertfremde. Vorarchiv8c4b3e bestandPostgreSQLNeuinstallation undMariaDBUpgrade0.2.2 inklusiveSystemSMTP/HTTPS/CalDAV/Cron/Ausfall/Wiederholung undbestehenderWerkstatt-/Fahrerabläufe. DanachÜbergabeanzeige ergänzt; exakterfinalerArchivlauf undlokaleDemo/Browserergebnisse werdenmitSHAiminternenProtokoll finalisiert. KeineproduktivenMailbox-/Geräte-/Betriebsnachweise ableiten.

ÖffentlicheOCP IManager/IMessageSend; keineprivatenMailklassen/Passwörter. Mail-Ergebnis „übergeben“ istkeinZustellbeleg. OptionaleIntegrationundEmpfängerdaten/Speicherzweck inProdukt-/Betriebsdoku dokumentiert.


## Lokale Tablet-Demo 0.2.4 (06.10.2026)

Reine Frontendkorrektur für lokale HTTP-Adressen: kryptografische Request-UUIDs mit getRandomValues, wenn randomUUID nicht verfügbar ist; Wiederholungskennung bleibt bei verloren gegangenen Antworten erhalten. Backend, Rechte, CSRF und Datenmodell unverändert; keine neue Migration und keine Abhängigkeitsänderung. Produktivbetrieb weiterhin über HTTPS.

**Erfolgreich ausgeführt:** 90 Frontendtests (fünf zusätzliche HTTP-/UUID-/Fahrerregressionen), Typecheck, ESLint, Produktionsbuild und vier Bundle-Rollenstarts; offizielles XSD und Archivgrenzen. Chrome und WebKit26.6 mit aktuellem Tabletprofil über tatsächliches HTTP-Origin mit Bonjourname: Anmeldung als tablet, Auftragskarten, Hoch-/Querformat ohne horizontalen Überlauf, kryptografische UUID im ausgelieferten API-Schreibpfad bei tatsächlich fehlendem randomUUID; bewusst ungültiger Mitarbeiter vom realen Server mit404 abgewiesen und verständlich angezeigt, ohne Demotimer zu starten. Screenshots visuell geprüft. Separat reale Meister-/Tablet-Anmeldung über Mac-WLAN-IPv4 erfolgreich. 17 bestehende Fachsammlungen nach Upgrade unverändert.

Der separate Mac-Demostarter verwaltet nur Demo-Port8787, konkrete vertrauenswürdige Bonjour-/IPv4-Adressen und einen während der Demo geladenen Benutzer-LaunchAgent zur Ruhezustandsvermeidung. Alte WLAN-Adresse wird beim nächsten Start entfernt. Vollständiger Stop/Start und finale Paket-/Demoidentität werden mit konkreten Hashes im internen laufenden Protokoll dokumentiert; kein GitHub-Upload.

**Nicht ausgeführt:** physischer iPad-/Touch-/Kamera-/WLAN-Fremdgerätetest, tatsächlicher Router-/Gäste-WLAN-Wechsel, Produktiv-HTTPS-/NAS-/Mail-Abnahme. Keine neue PHP-/SQL-Fachmatrix für diesen unveränderten Backendstand behauptet. Nextcloud35 fordert laut tatsächlicher Browserprüfung Safari auf iPadOS26.1 oder neuer. Die Demoinstallation meldet eine fehlende NC-Kerntabelle oc_federated_invites; bereits im Vorbackup vor0.2.4 nicht vorhanden, keine Appmigration betroffen, Kernreparatur separat offen. Frühe Browserprofil-/Prüfselektor-/Ladezeitfehler korrigiert; diese ersten Versuche waren keine erfolgreichen Prüfungen.

**Zusätzliche Demo-Laufzeitkorrektur:** Die Abschlussprüfung entdeckte nach VM-Neustart sporadische HTTP500 im unveränderten Nextcloud-Themingmodul bei aktivem PHP-JIT (1255). Ausschließlich die separate Demo-Konfiguration wurde geändert: JIT deaktiviert, regulärer OPcache erhalten; Redis-Healthcheck und gesicherte App-Abhängigkeit für Start-/Stopreihenfolge. Danach100 aufeinanderfolgende angemeldete Appaufrufe (50 je Meister/Tablet), Datenvergleich/Rechte sowie Chrome-/WebKit-Nachprüfung erfolgreich. Vollständiger erneuter VM-Neustart wird getrennt im laufenden Protokoll belegt. Kein Nextcloud-Corepatch und keine PHP-Appänderung.

## Erweiterung 0.3.0 – Auswertung (09.10.2026)

**Erfolgreich ausgeführt:** 64 native PHP-Szenarien jeweils unter 8.3, 8.4 und
8.5, einschließlich elf neuer Umsatz-/Zeitregressionen; PHP-CS auf 52 Dateien,
Psalm ohne Fehler (171 Informationsbefunde). 95 Frontendtests, Typecheck, ESLint,
Produktionsbuild und vier Rollen-Bundlestarts; offizielles Metadaten-XSD und sechs
Paketgrenztests bestanden.

Vier isolierte echte SQL-/HTTP-Läufe mit Nextcloud 35.0.0 und 35.0.1, jeweils
PostgreSQL 17 oder MariaDB 11.8, bestanden. Beide 35.0.0-Läufe prüfen auch das Upgrade
von 0.2.4 mit bestehenden Daten. Alle vier prüfen verknüpfte Planwerte, konkurrierende
Betragsänderungen (genau ein Gewinner), Umsatzdatum, Flottenausschluss, finanzielle
Rollengrenzen und die bisherigen Werkstatt-/Fahrer-/Termin-/Rankingabläufe.
Nextcloud 35.0.1/MariaDB zusätzlich mit isoliertem vertrauenswürdigem HTTPS,
realem SMTP/CalDAV/Cron, Ausfällen, Wiederholung und Kalenderänderungen bestanden.
Installation, Deaktivierung/Aktivierung und Neustart erhalten die Testdaten.

Chrome und WebKit prüfen echte Anmeldung, Auswertungsmenü, Speichern von 250,01 Euro
netto, Verknüpfung zum Auftrag/Terminbuch und Tablet-Menüsperre. Desktop sowie
768×1024 und 1024×768 ohne Seitenüberlauf und ohne JavaScript-Seitenfehler bestanden.
Helle/dunkle Bildschirmdarstellung stichprobenartig visuell geprüft. Lokale Demo auf
0.3.0 aktualisiert; Originaldatensätze in 16 geprüften Fachsammlungen erhalten.
Fiktive Browserdaten werden administrativ gelöscht; dieser Löschvorgang bleibt im
Änderungsprotokoll. Kein physischer iPad-Nachweis aus Browserprofilen ableiten.

Die SQL- und Browserprüfungen verwenden das Archiv mit SHA-256
`66800c1221b73f3331d74b1b24e694b6dcc00e64731acd12e29869204e6bbe26`.
Eine nachfolgende Druckkorrektur ändert ausschließlich Frontend/Styles: alte globale
Modal-Druckregeln versteckten die Seitenauswertung. Gezielte Report-Druckregeln
wurden ergänzt; Frontendtests/Typecheck/Lint/Build erneut bestanden. Browser-/PDF-
Nachprüfung bestanden: vollständiger zweitseitiger Bericht einschließlich des letzten
Tages, stichprobenartig visuell geprüft. Auch feste Nextcloud-Seitenhöhen wurden für
die Auswertung im Druck gezielt aufgehoben. Das Archiv mit Druckkorrektur
`26440c14dcf23c026f2970ac7b609b997786c24175efaa34123e6b58f0cdde91`
besteht zusätzlich den vollständigen PostgreSQL-/HTTPS-/SMTP-/CalDAV-Prüflauf
auf Nextcloud 35.0.1 sowie die erneut ausgeführten Chrome-/WebKit-Browserprüfungen.
Das Abschlussarchiv aktualisiert gegenüber diesem Archiv ausschließlich
öffentliche Dokumentation; alle Laufzeitdateien bleiben identisch. Backenddateien bleiben identisch zum
vollständig geprüften Matrixstand. Abschlusshashes und Quell-/Archiv-/Demoidentität
werden im privaten Übergabeprotokoll geprüft. Frühe fehlende Test-Kundenbezüge, Browserselektoren
und eine fehlende temporäre WebKit-Laufzeit wurden korrigiert, nicht als Erfolg gewertet.

**Nicht ausgeführt/offen:** physisches iPad, tatsächliche NAS-/Office-/Mailboxabnahme,
vollständige Barrierefreiheit, Dauerlast, GitHub-CI dieser lokalen Änderungen,
Signierung, Store- und Produktionsfreigabe. Vorbestehende Demo-Kerntabellenwarnung
`oc_federated_invites` separat offen, kein Eingriff in Nextcloud-Core.
Die Auswertung ist auf 20.000 Datensätze je Fachbereich begrenzt und zeigt bei
Überschreitung einen Fehler statt einer unvollständigen Summe. Sie verwendet eigene
Nettobeträge und ist keine Buchhaltung oder Zahlungskontrolle.


## Mitarbeiter-Vorplanung und Store-Vorbereitung 0.5.0 (16.10.2026)

Lokal erfolgreich: 105 Frontendtests, Typecheck, ESLint, Produktionsbuild/vier Rollenstarts; 74 PHP-WASM-Fachszenarien und PHP-Syntaxprüfungen. Zusätzlich dieselben74Szenarien nativ auf PHP8.3.35/8.4/8.5, Nextcloud-Coding-Standard für57Dateien und Psalm ohneFehler bestanden; der separate CLI-Analysecontainer installiert Werkzeuge mit Ausnahme für ext-gd, die echten Nextcloud-/CI-Installationen behalten die GD-Anforderung. Sieben neue Frontend- und vier Backendfälle prüfen sichtbare/unverbindliche Planung, Erstellung/Ändern/Aufheben, fehlende/archivierte Referenzen, Rollen, Versionskonflikte, Wiederholungen, lesbare Historie und Start durch einen anderen Mitarbeiter. Zehn Paket-/Store-Inventartests einschließlich Ablehnung privaten Schlüsselmaterials und fehlender Vertrauensableitung aus einer bloß vorhandenen Signaturdatei.

Die erweiterte CI prüft den hochgeladenen Stand zusätzlich nativ auf PHP8.3/8.4/8.5 und in vier frischen NC35.0.0/35.0.1×PostgreSQL17/MariaDB11.8-Instanzen sowie Upgrades von exakt 0.4.1 auf PostgreSQL/35.0.1 und MariaDB/35.0.0. Der neue SQL-Fall kontrolliert Planung/Filter, Entwurfsprivatsphäre, Rechte einschließlich Terminannahme, konkurrierende Versionen, Wiederholungen, Übernahme, Archivierung und Journal. Nachweis/Ergebnis des jeweiligen Commit-Laufs ist über GitHub Actions zu prüfen; aus der Definition allein folgt kein bestandener Lauf.

Browsernachweise und aktuelle Screenshots werden mit dem ausgelieferten Bundle in einer klar gekennzeichneten lokalen UI-Fixture mit fiktiven Daten erstellt. Sie ersetzen keine echte Nextcloud-/physische iPad-/Touch-/Kamera-Abnahme. Signiertes Endpaket, Store-Konto, öffentlicher Download, Rechteprüfung der Preisquellen und bestehende Geräte-/Betriebsabnahmen bleiben getrennt offen. Keine erneute native PHP-/SQL-Matrix als lokalen WASM-Erfolg ausgeben.


### Sicherheitsnachprüfung der Entwicklungswerkzeuge (16.10.2026)

Vitest 3.2.7 wurde wegen GHSA-82fw-gwwq-j7x9 auf die kompatible reparierte Version 4.1.11 aktualisiert. Die vollständige npm-Prüfung meldet anschließend keine bekannten Schwachstellen (einschließlich Entwicklungsabhängigkeiten). 105 Frontendtests, Typecheck, ESLint und Produktionsbuild mit vier Rollenstarts erneut bestanden. Die CI prüft künftig sämtliche npm-Abhängigkeiten. Die Laufzeit bleibt auf Vite 6.4.3. Der erste 0.5.0-CI-Lauf fand einen unvollständig typisierten neuen Testdatensatz; alle Pflichtfelder wurden ergänzt, ohne Prüfregeln abzuschwächen. Ein neuer CI-Lauf muss den korrigierten Commit prüfen.


## Erste öffentliche Test-Beta0.5.1-beta.1 (16.10.2026)

Vergleichspreise behalten:334 Beobachtungen/32 unabhängige Preisgeber, Beträge/Steuer/Seed-Schlüssel unverändert. Namen/Anbieterlinks in Paket und API neutralisiert, auch für Altdaten ohne Datenbankschreibvorgang. Drei neue PHP-Fälle prüfen Statistikgleichheit, wiederholbare Ausgabe und unveränderten Datenbestand; zwei neue Frontendfälle prüfen fehlende Namens-/Linkanzeige sowie weiterhin ausdrückliche Preisübernahme. Eigene Werkstattpreise/Zeiten/Favoriten werden nicht ersetzt.

Erfolgreich lokal ausgeführt:107 Frontendtests, Typecheck, ESLint, Produktionsbuild mit vier Rollenstarts;77 native PHP8.3.35-Fachszenarien, CodingStandard57Dateien, Psalm ohne Fehler. XSD/Paketinventar und weitere PHP-/SQL-Prüfungen des neuen Commits sind über dessen Actions-Lauf zu verifizieren. Eine Workflowdefinition gilt nicht als bestandene Prüfung. Der Release-Beleg für das exakte signierte Archiv wird getrennt bei den GitHub-Release-Assets geführt.

Nextcloud hat Zertifikat zum bestehenden Schlüssel ausgestellt; Überprüfung gegen die mitNC35.0.1 ausgelieferte Vertrauensdatei bestanden. Der Schlüssel bleibt außerhalb von Git/Paket/CI. Signiertes Endpaket benötigt separate Integritäts-, Neuinstallations-, Upgrade- und Downloadprüfung. Physisches iPad, reale NAS-/Office-/Mailboxabnahme, volle A11y-/Lastprüfung und offene Preisquellenrechte bleiben ausdrücklich getrennt. Vorhandene Unternehmensangaben in historischen Git-Commits werden durch diesen Release nicht gelöscht.

## Kalenderkorrektur 0.5.1-beta.2 (17.10.2026)

Drei PHP-Services ändern ausschließlich das Verhalten der optionalen CalDAV-Queue. Zwei neue Unit-Szenarien und ein realer SQL-/TLS-Zustellablauf prüfen ausgeschaltete Integration, unveränderte Kundenmail, Passwortentzug, Abbruch alter wartender Jobs ohne weitere Versuche/Logwarnung sowie erfolgreiche Wiederaktivierung.

Erfolgreich ausgeführt:79 Unit-Szenarien jeweils nativ auf PHP8.3/8.4/8.5; Codingstandard57Dateien, Psalm ohne Errors; vollständige PostgreSQL17/Nextcloud35.0.1-Installation mit echtem TLS/SMTP/CalDAV/Cron, Ausfällen, Wiederholungsschutz, Rechten und Neustart. Auch der vollständige MariaDB11.8/Nextcloud35.0.1-Lauf ist bestanden. Das getrennte unsigned Prüfpaket hat SHA256 f9be9d0a6ea032325548bdae1c455d115c0b7d486746538d1621d43d9652bd8b und unveränderte Metadatenversion0.5.1-beta.1; es ist kein nachträglich ersetztes Store-Release. Betriebsdokumentation und dieser Nachtrag entstanden anschließend.

Zusätzlich lokal Mail5.12.3/EuroOffice11.0.5 geprüft: Doppelbelegung application/pdf durch Office und eingebauten PDF-Viewer. Nur Office-PDF-Standardzuordnung in isolierter Backup-Kopie deaktiviert; frischer Mailtab ohne bisherigen Registrierungsfehler. PDF tatsächlich im eingebauten Viewer geöffnet, DOCX weiterhin in Office bearbeitet und per WebDAV/XML mit altem und neuem Testtext zurückgelesen. Werkstatt-Store-App bleibt signaturgültig. Fremde Programmdateien unverändert.

Im vorangegangenen reinen Fix-Lauf wurden Frontendtests/Build und die GitHub-CI nicht erneut ausgeführt. Für die versionierte Veröffentlichung wurden anschließend Typecheck, ESLint,107 Frontendtests und Produktionsbuild mit vier Rollenstarts erneut erfolgreich geprüft; die erzeugten Frontenddateien sind unverändert. Die endgültigen CI-, Signatur- und Storeergebnisse stehen beim konkreten Release. NAS, produktive Mailbox, physisches iPad sowie allgemeine Geräte-/Barrierefreiheitsabnahmen bleiben offen.

Für die versionierte Beta0.5.1-beta.2 sind das aktuelle offizielle App-Store-XSD, Versionsgleichheit,82Dateien-Paketgrenzen und npm-Audit ohne bekannte Schwachstellen geprüft. Vor Veröffentlichung müssen zusätzlich die vorgesehene CI-Matrix und die signierten Installations-/Upgradeprüfungen abgeschlossen sein. Die drei PHP-Dateien enthalten denselben bereits auf PostgreSQL/MariaDB geprüften Fix. Exakte Paketidentität, Signatur-, Neuinstallations- und Upgrade-Ergebnisse sind den Release-Nachweisen zuzuordnen; frühere Testarchive werden nicht als das neue Release ausgegeben.

## Stabile Store-Version 0.5.1 (19.10.2026)

0.5.1 übernimmt den Laufzeitstand der vollständig geprüften0.5.1-beta.2 ohne Datenbank- oder Funktionsänderung. Versionsmetadaten und öffentliche Dokumentation wurden für den stabilen App-Store-Kanal angepasst.

Erfolgreich ausgeführt:107 Frontendtests, Typecheck, ESLint, Produktionsbuild mit vier Rollenstarts, zehn Pakettests, aktuelles App-Store-XSD, reproduzierbares82-Dateien-Paket und npm-Audit ohne Meldung. GitHub Actions [Lauf39202411955](https://github.com/LtRock84/werkstattmanager/actions/runs/39202411955) für Commit`056d748` bestand alle elf Jobs: Node24.18/npm11.16, PHP8.3/8.4/8.5, Codingstandard/Psalm, vier frische Installationen auf Nextcloud35.0.0/35.0.1 mit PostgreSQL17/MariaDB11.8 und zwei Upgrades. Das CI-Artefakt war bytegleich zum lokal reproduzierten Kandidaten.

Ein daraus signierter Prüfstand bestand die native Nextcloud-Integrität und RSA/SHA-512-Archivsignatur. Das signierte Archiv wurde anschließend frisch auf MariaDB/Nextcloud35.0.0 installiert sowie von der veröffentlichten signierten0.5.1-beta.2 auf PostgreSQL/Nextcloud35.0.1 aktualisiert. Beide vollständigen21-Gruppen-Läufe bestanden einschließlich TLS, SMTP, CalDAV, Cron, Rollen, konkurrierenden Aufträgen, Tablet, Fahrer-QR/Fotos, Teile, Termine, Fristen, Auswertung, Deaktivieren/Aktivieren und Neustart mit Datenerhalt. Die abschließende Dokumentationsaktualisierung verändert keine Laufzeitdatei; ihr eigener CI- und Paketstand wird vor Veröffentlichung nochmals geprüft.

Installationsabhängig offen bleiben physisches iPad/Touch/Kamera, reale NAS-/Werkstattmailbox-/Office-Abnahme, vollständige WCAG-/Screenreader-Prüfung und längerer Dauerbetrieb.

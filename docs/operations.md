# Betrieb und Datenschutz

Stand: 2026-10-11, Version 0.4.0. Gilt für den internen Prüfkandidaten, nicht als Betriebsfreigabe.

## Einrichtung und laufender Betrieb

Eine Werkstattorganisation je Installation. Rollen serverseitig über Nextcloud-Gruppen
oder direkte Benutzerfreigaben; Administratoren konfigurieren Meister-/Terminannahme-
und Tabletzugänge. Meister sollten eigene Konten nutzen.
Tablet-Konten nur der Tabletgruppe zuordnen und nicht mit Kundendateien oder anderen
vertraulichen Nextcloud-Freigaben ausstatten. Mitarbeiter-Auswahl ist kein Login.
HTTPS verwenden. Fahrerlinks erlauben jedem Besitzer den eingeschränkten Fahrzeugzugang;
bei Verlust neu erzeugen oder widerrufen. Alte Sitzungen werden bei jedem Zugriff geprüft.

Das Dashboard lädt bei sichtbarer Seite alle zehn Sekunden nach. Geöffnete Dialoge und
Einstellungseingaben werden nicht durch Polling ersetzt. Bei verlorener Verbindung
keine neue Zeitbuchung vornehmen. Bereits serverseitig gestartete Zeiten laufen weiter.
Nach unklarer Antwort aktualisieren und den Zustand prüfen, bevor eine neue Aktion erfolgt.

System-Cron muss Nextcloud-Hintergrundjobs ausführen. Maintenance arbeitet in Batches
von 100 und frühestens alle fünf Minuten. Zustellversuche verwenden Backoff und enden
nach zehn Fehlschlägen. Dashboard-Einträge bleiben unabhängig davon verfügbar.
Fehlgeschlagene Zustellungen werden im Meisterdashboard angezeigt. Unter Einstellungen
kann der Meister sie seitenweise prüfen und einzeln erneut vormerken. Die App prüft die
Empfängerrechte erneut und verwirft Zustellungen an ehemalige Meister. Wiederholungsversuche
und die vorherige Versuchszahl werden im Änderungsprotokoll festgehalten. Bei wiederholtem
Fehlschlag Nextcloud-Logs prüfen und die Ursache beheben. Die Zustellung erfolgt weiterhin
durch Cron; Vormerken bedeutet noch nicht erfolgreiche Zustellung.
Keine Zugangsdaten oder kompletten Nutzdaten in Fehlerprotokolle übernehmen.

## Sicherung, Aktualisierung und Rückkehr

Datenbank und Nextcloud-AppData bilden zusammen einen Sicherungsstand; zusätzlich
Nextcloud-Konfiguration und exakten App-Quell-/Buildstand aufbewahren. Bei Object Storage
auch dessen Sicherungsverfahren berücksichtigen. Dateizugriffe erfolgen über OCP-APIs.
Wiederherstellung nur als zusammenpassendes Paar aus Datenbank und Dateien testen.

Vor jeder Aktualisierung Testkopie erstellen, Migrationen ausführen und Zahl sowie Inhalte
von Fahrzeugen, Aufträgen, Intervallen und Anhängen vergleichen. Keine Migrationen
rückwärts ausführen. Bei Fehlern vollständigen Backupstand wiederherstellen. Die erste
Version hat keinen veröffentlichten Upgradevorgänger; das ist kein bestandener Upgradetest.
Deaktivieren/Entfernen der App ist keine fachliche Datenlöschung. Es existiert bewusst
keine automatische Deinstallationsroutine zum Löschen von Werkstattakten.

## Daten und Aufbewahrung

Kundenkontakte, Mitarbeiterzuordnungen, Fahrername, Kilometerstände, Mängel, Fotos,
Auftragsprotokolle und Zeitintervalle werden lokal in Nextcloud gespeichert. Keine
Lieferantenanbindung oder Telemetrie. Ab 0.2.0 werden bestätigte Kundentermine bei
eingerichteter Integration an den eigenen Kalender und die angegebene Kundenadresse gesendet. Browsercode enthält
keine Secrets. Die Entwicklung lädt Bibliotheken; das ist kein Datenfluss aus Fahrzeugakten.

Fachobjekte werden standardmäßig archiviert. Vor Produktivbetrieb muss der Betreiber
Aufbewahrungsdauer, zulässige Zeitkorrekturen und Zuständigkeiten bestimmen. CSV-Exporte
enthalten interne Informationen; exportierte Dateien ebenfalls schützen und fristgerecht
löschen. CSV-Export ist auf 100.000 Datensätze begrenzt und schützt gegen Formelauswertung.

Administrative Anonymisierung leert definierte strukturierte Felder. Freitext, Fotos,
Kennzeichen, FIN und exportierte Kopien werden dadurch nicht automatisch anonymisiert.
Die kontrollierte Fahrzeuglöschung erfordert Archivierung, keine aktive Belegung und eine
explizite Bestätigung der Fahrzeug-ID. Zugehörige Buchungen und Verknüpfungen werden
bereinigt; Dateilöschung erfolgt nachgelagert. Vorher Export/Backup und Löschumfang prüfen.
Nextcloud-Benutzerlöschung entfernt historische Mitarbeiter-/Auditdaten nicht automatisch.

## Leistungsgrenzen

Hauptlisten sind paginiert, das Dashboard zeigt begrenzte Ausschnitte und weist darauf hin.
Bei mehr als 100 offenen Fristen ist zusätzlich die vollständige Fristenliste zu prüfen;
die Übersicht garantiert derzeit keine globale Dringlichkeitssortierung. Detailhistorien
und Auswahlbibliotheken werden seitenweise vollständig nachgeladen. Große Bestände müssen
im Pilot vermessen werden. Keine automatische Einsatzplanung oder Offline-Zeitbuchung.

## Kalender und Kundennachrichten ab 0.2.0

1. In Nextcloud ein dediziertes Integrationskonto ohne allgemeine Werkstattrechte anlegen.
2. Mit diesem Konto einen Kalender „Werkstatt“ erstellen und mit der Meistergruppe nur
   zum Lesen teilen. Meister bearbeiten Termine ausschließlich im Werkstattmanager.
3. Die CalDAV-Adresse des einzelnen Kalenders kopieren, nicht die Adresse der gesamten
   Kalenderliste. Sie endet beispielsweise auf `/remote.php/dav/calendars/integration/werkstatt/`.
4. Ein App-Passwort des Integrationskontos erzeugen; unter Werkstatt-Einstellungen als
   Administrator Benutzer, HTTPS-Adresse und Passwort speichern. Es wird verschlüsselt
   mit Nextcloud `ICrypto` gespeichert und nicht an den Browser zurückgegeben.
5. Öffentliche HTTPS-Adresse und `overwrite.cli.url` prüfen, insbesondere bei Reverseproxy.
   Der Hintergrundjob muss dieselbe eigene Instanz erreichen. Ein öffentlich gültiges oder
   systemseitig vertrauenswürdiges Zertifikat verwenden; TLS-Prüfung bleibt eingeschaltet.
6. Nextcloud-System-Cron und SMTP konfigurieren. Ausschließlich mit eigener Testadresse
   Bestätigung, Terminänderung, Absage und ICS-Datei prüfen; erst danach Kundennachrichten nutzen.

Cron führt den Zustelljob frühestens nach 60 Sekunden aus. Kalenderressourcen enthalten
Ankunft, gegebenenfalls Abholung und Arbeitsplanung. Ohne feste Arbeitsuhrzeit entsteht
für den Arbeitstag ein ausdrücklich gekennzeichnetes ganztägiges Planungselement.
Interne Ressourcen enthalten keine Teilnehmer und lösen keine zweite Einladung aus.

Bei Zustellfehlern den Bereich im Terminbuch prüfen. Kalenderfehler werden mit Backoff
wiederholt. SMTP-Ausgänge können unklar sein; deshalb vor „Erneut zustellen“ kontrollieren,
ob die erste Nachricht bereits angekommen ist. Ein erfolgreiches Vormerken ist noch kein
Versandnachweis. Kunden erhalten eine Kalenderdatei; deren Import/Absagenbehandlung hängt
von ihrem Kalenderprogramm ab. Ein Kalendereintrag ist keine automatische Zeitbuchung.

Das Integrationskonto kontrolliert seinen eigenen Kalender. Lesefreigaben bleiben eine
Nextcloud-Einstellung, die die App nicht automatisch erzeugt. Konto/Passwort/Widerruf
administrativ pflegen. Fehlende Kalenderkonfiguration verhindert nicht das Terminbuch;
ausstehende Kalenderaufträge erscheinen als Fehler, bis sie eingerichtet ist.

Termine und Zustellkopien enthalten Fahrzeugkennung, Titel, Kundenadresse und Zeiten.
Interne Notizen gehören nicht in Kundennachrichten. Kundendaten-Anonymisierung ändert
historische Termin-/Versandkopien und Freitexte nicht automatisch; diese beim Löschvorgang
gezielt prüfen. Fahrzeuglöschung setzt abgesagte Termine und abgeschlossene/verworfene
Zustellungen voraus. CSV-Export enthält auch Termine. Backups umfassen neue Tabellen
und verschlüsselte Integrationszugänge samt Nextcloud-Konfiguration.

Vor Upgrade sichern und Migration auf einer separaten Kopie testen. Zurück auf 0.1.7
nur durch Wiederherstellung des zusammengehörigen Sicherungsstands; kein Schema-Downgrade.

### Vergleichszielpreise und Katalogupdates

Im Preisrechner die passende Vergleichsgruppe öffnen, Umfang/Einheit/Material und
Fahrzeugklasse prüfen und bestätigen. Der Zielpreis wird mit Quellen-Umsatzsteuer
nach netto umgerechnet und neben der eigenen Kalkulation angezeigt. Öffentliche
Einstiegspreise bleiben „ab“-Preise. Die zweite Aktion zum Katalogspeichern übernimmt
bewusst den Nettopreis je bestätigter Einheit; die Leistungsmengen teilen diesen
Zielpreis nicht nochmals. Eigene Kosten oder bereits angelegte Aufträge ändern sich nicht.

Updates aktualisieren nur unveränderte Seed-Felder. Eigene Preise, Favoriten und Zeiten
bleiben bestehen. Bereits bearbeitete frühere Einträge ohne Baseline werden zunächst
vollständig erhalten. Fehlende recherchierte Zeiten werden nicht nachträglich erfunden.

## Annahme und Tagesgeschäft (0.2.1)

Im Terminformular „Fahrzeugannahme, Rückfrage und Abholung“ öffnen. Nur durchgeführte Schritte abhaken, bei offenen Kundenfragen den entsprechenden Terminstand wählen. Mit „Terminänderungen speichern“ werden interne Angaben übernommen; Statusänderungen allein bewahren sie. Der Annahmekilometerstand verändert nicht automatisch den bestätigten Fahrzeugstand.

„Passende freie Tage finden“ ist bei neuen eintägigen Terminen verfügbar, sobald eine Dauer feststeht. Vorschläge verwenden den aktuellen Tagesstand, buchen nichts und berücksichtigen keine individuellen Mechaniker oder Bühnen. Mehrtägige Arbeiten bitte wie bisher aufteilen. „Folgetermin vorbereiten“ speichert nichts; neue Zusagen und Empfänger prüfen. Terminfilter betreffen den gewählten Zeitraum, nicht die errechnete Auslastung.

Upgrade von 0.2.0 über das vollständige Paket und reguläres `occ upgrade`: nullable Checklistenspalte wird durch eine neue Migration ergänzt. Keine älteren Migrationen zurücksetzen oder App-Tabellen manuell verändern. Interne Checklisten sind in Datenschutzexport/-löschung enthalten, nicht in Kunden-ICS oder Tabletaufträgen.

## Zusätzliche Benutzer und Terminassistenz ab0.2.2

Als Nextcloud-Administrator Werkstattmanager → Einstellungen → „Zugänge für Meister und Terminannahme“ öffnen. Rolle wählen, vorhandenen Benutzer suchen, hinzufügen, **Zugänge speichern** drücken. Mehrere Personen möglich; „Entfernen“ wird ebenfalls erst durch Speichern wirksam. Vollständiger Meisterzugang gibt dieselben Fachrechte wie dem Meister. Für Assistenz **Nur Terminannahme** wählen; diese öffnet dieselbe Appadresse und landet im Terminbuch. Assistenz benutzt vorhandene Kunden/Fahrzeuge; neue Stammdaten legt ein Meister an. Optional vorhandene Nextcloud-Gruppe als Terminannahmegruppe eintragen. Nextcloud-Administratoren und Mitglieder der Meistergruppe bleiben Meister, auch bei zusätzlicher eingeschränkter Zuordnung. Entzug erfolgt sofort bei Folgeanfragen; zum Wechsel der sichtbaren Ansicht Seite neu laden.

Ranking wird ohne Datenaustausch automatisch aktualisiert. Bei mehr als100Altdatensätzen je Vorgangsart übernimmt Cron den verbleibenden Bestand; vor Abschluss können alte Vorgänge noch fehlen. Entwürfe zählen nicht. Kein Reset oder manuelles Hochzählen nötig. Beim Upgrade Datenbank, Konfiguration, Appcode und AppData gemeinsam sichern; neues Schema benötigt Wiederherstellung des vollständigen Backups für ein Downgrade. Produktive Einführung weiterhin erst nach Zielinstallations-/Geräte-/Betriebsabnahme.

## Firmenprofil / Werkstattadresse

Unter Stammdaten Firmenname/Logo pflegen; beides ist im öffentlichen Fahrerformular sichtbar. Alte Logos nach24h durch Maintenancebereinigung, aktuellesLogo im AppData-Sicherungsbestand enthalten.

Administrator richtet Werkstattkonto zuerst in Nextcloud Mail ein und wählt es unter Einstellungen → Werkstatt-E-Mailadresse ausdrücklich aus. Erlaubt damit gemeinsame Nutzung für Kundentermine und Cron-Erinnerungen, unabhängig vom angemeldeten Bearbeiter. Zugangsdaten bleiben inMail; ein aktiver, dauerhaft berechtigter Kontoinhaber erforderlich. DessenLöschung sperrt Versand biserneuter Auswahl. Keine heimlicheSMTP-Alternative. „An Mail übergeben“ ist keinZustellbeleg: Postausgang/Senden/Sentstatus imMailclient prüfen. Mailkonto muss IMAP undSMTP zuverlässig erreichen undgesendeteNachrichten verwalten können. UnklarerVersandausgang nur nachPrüfung wiederholen. Einstellungen/Sicherungen enthaltenKontobezug/Adresse, keineMailpasswörter; Mailclient eigenerDatenspeicher/Sicherung/Löschregeln.

Geprüfte öffentliche APIs: [Mailprovider NC35](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/groupware/mail_provider.html), [Mail5.12.3 Provider](https://github.com/nextcloud/mail/tree/v5.12.3/lib/Provider). Prüfung06.10.2026 gegen installierteNC35.0.1-Interfaces undveröffentlichtenMailcode. Mail ist optionaleIntegration, keineApppflicht.

## Umsatz- und Zeitauswertung ab 0.3.0

Unter **Auswertung** wählen Meister den Zeitraum und Kunden-/Flottenfilter.
Bei **Beträge erfassen** wird der vereinbarte Planwert bzw. nach Meisterabnahme
der abgerechnete Istwert einschließlich Material und Fremdleistungen in Euro netto
eingetragen. Das Umsatzdatum bestimmt den Zeitraum des Istwerts; eine Notiz begründet
jede Erfassung/Korrektur. Leer bedeutet unbekannt, ausdrücklich 0 bedeutet ohne
Umsatz. Die App leitet keine Erlöse aus Katalogpreisen oder gebuchten Stunden ab.

Ungeplante offene Aufträge werden separat kenntlich gemacht. Erst ein Planungstag
ordnet ihren Planumsatz einem Zeitraum zu. Entwürfe und Stornierungen zählen nicht;
verknüpfte Termine/Aufträge zählen einmal. Bereits abgerechnete Nacharbeiten erzeugen
keinen zusätzlichen Planumsatz. Ein erneut zur Nacharbeit geöffneter Auftrag zählt
erst nach erneuter Abnahme wieder als abgeschlossener Ist-Umsatz. Diese Übersicht
ersetzt keine Buchhaltung, unveränderliche Rechnungen oder Zahlungskontrolle.

Die Tagesauslastung verwendet die Stundenbudgets aus dem Terminbuch und umfasst auch
die eigene Flotte. Mitarbeiterzeiten sind gebuchte Auftragszeiten, keine Anwesenheit
oder Leistungsbewertung; Pausen sind ausgeschlossen. **Auswertung anzeigen** lädt
aktuelle Daten nach. Das Druckbild enthält die gewählte Auswertung. Der geschützte
CSV-Link exportiert alle erfassten Betragsdatensätze, unabhängig vom Zeitraum.

Finanzdaten bleiben auf dem Nextcloud-Server und sind nur für Meister zugänglich.
Berechtigungen für Meisterzugänge daher entsprechend vergeben. Datenschutz-Export,
Sicherung und administrative Fahrzeuglöschung schließen die neuen Betragsdaten ein.
Die Obergrenze von 20.000 Datensätzen je Auswertungsbereich ist vor einem größeren
Pilotbestand zu vermessen; bei Überschreitung erscheint ein Fehler statt einer
unvollständigen Summe. Abrechnung, Korrekturgründe und Exporte brauchen die gleichen
betrieblichen Aufbewahrungs- und Zugriffsvorgaben wie die übrigen Werkstattakten.

## Kundennachrichten und Fahrzeugfristen (0.4.0)

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

## Projektunterstützung ab 0.4.1

Der Unterstützungslink ist ein fester Bestandteil der App und keine Werkstatt-
Absenderadresse. Er wird nicht aus Stammdaten übernommen und besitzt keine
Administratoreinstellung. Änderungen des veröffentlichten Zielkontos erfolgen
bewusst im Quellcode und durch ein neues geprüftes Paket; auch die donation-
Metadaten müssen dasselbe Ziel enthalten. Änderungen durch Betreiber sind bei
quelloffener Software technisch möglich. Es gibt keine automatische Zahlung oder
Rückmeldung an die App; Zahlungen, Gebühren und Buchführung werden außerhalb geführt.

Der eigene Hinweis bezeichnet die Zahlung als freiwillige Unterstützung der
Projektkosten ohne individuelle Gegenleistung und ohne Spendenbescheinigung.
Dies entscheidet nicht über Gewerbe, Einkommen- oder Umsatzsteuer. Der Empfänger
muss seine tatsächliche Tätigkeit und Zahlungseingänge steuerlich einordnen lassen.
Der Eigentümer hat das PayPal.Me-Profil am 11.10.2026 aktiviert; Zuordnung im
angemeldeten Konto und öffentliche Profilseite wurden geprüft. Die Eignung für
die konkrete Nutzung und der Zahlungsempfang sind damit noch nicht geprüft;
das bloße Laden einer PayPal-Seite ist kein Zahlungsempfangsnachweis.

Die App lädt weder PayPal-SDK noch Zahlungsbilder oder Drittanbieter-Cookies.
Nur nach ausdrücklichem Weiterklick wird im neuen Tab das feste öffentliche
PayPal.Me-Profil `Werkstattmanager` aufgerufen; keine Empfangs-E-Mail,
Betragsvorgabe oder Kunden-/Fahrzeug-/Auftragsdaten im Link. Die App legt keine
Zahlungsart fest und richtet keine wiederkehrenden Zahlungen ein.
Neue Tabs erhalten noopener/noreferrer. Von Nextcloud selbst dargestellte
Unterstützungslinks in App-Verwaltung/Store führen direkt zum externen Ziel und
tragen den Hinweis auf die fehlende Spendenbescheinigung im Linktitel.

Quellen geprüft 11.10.2026: [PayPal.Me](https://www.paypal.com/de/cshelp/article/was-ist-paypalme-help432),
[Nextcloud-Unterstützungslinks](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/monetizing.html).

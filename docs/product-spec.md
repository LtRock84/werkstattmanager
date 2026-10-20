# Werkstattmanager – verbindliche Produktspezifikation

Stand 2026-10-11. Eine Werkstattorganisation pro Installation,
beliebig viele Meister und Tablets.

Automatische Qualitätsprüfungen umfassen echte PostgreSQL-/MariaDB-Installationen.
Interne Prüfkandidaten bleiben unsigniert. Öffentlicher Quellcode, Support,
Zertifikat und signiertes Paket sind Voraussetzungen für eine Store-Veröffentlichung.
Ein Pilot benötigt Geräteabnahme und dokumentiertes Sicherungs-/Rückkehrverfahren.
Veröffentlichungsunterlagen enthalten keine standortspezifischen Betriebsdaten,
persönlichen Arbeitsplatzpfade oder Zugangsdaten. Private Prüfunterlagen und
Signierschlüssel gehören nicht in Quellrepository oder Installationspaket.

## Rollen
Meister: persönlicher NC-Account, konfigurierbare Gruppe oder direkte Administratorfreigabe.
Terminannahme: begrenzte Gruppe oder direkte Benutzerfreigabe, ohne Finanz- und
Werkstattverwaltungsrechte. Administratoren richten Zugänge und Integrationen ein.
Tablet: dedizierter NC-Account,
eigene App-Rolle, Mitarbeiterwahl ohne PIN. Diese Wahl ist keine Identitätsbestätigung.
Fahrer: Fahrzeuglink ohne NC-Account, nur Meldungen und freigegebene offene Mängel.

## Schneller Auftrag
Aus Fahrzeugakte: Neuer Auftrag → Vorlage → Freigeben (höchstens drei Aktionen).
Fahrzeugsuche nach Kennzeichen, Nummer, FIN, Kunde; zuletzt verwendete Fahrzeuge.
Vorlagen kopieren Titel, Beschreibung, Schritte und optionale Sollminuten.
Mehrere Textbausteine ergänzen Schritte ohne automatische Zeitänderung; freie Eingabe.
Vorlagen und Bausteine sind editierbar, sortierbar und favorisiert. Auftrag als Vorlage speichern.
Nur Fahrzeug und Titel sind Pflicht. Sollminuten, Beginn, Fertig-bis sind unabhängige
optionale Felder; Ende darf nicht vor Beginn liegen. Schnellzeiten 15/30/45/60/90/120.
Entwurf ist nicht am Tablet sichtbar. Geplanter Beginn startet keine Buchung.

## Auftragsablauf
Entwurf → Offen → In Arbeit → Zur Abnahme → Abgeschlossen; zusätzlich pausiert und storniert.
Genau ein laufender Auftrag je Mitarbeiter und genau ein aktiver Mitarbeiter je Auftrag.
Start, Pause, Übernahme, Fertigmeldung sind serverseitig atomar und wiederholbar.
Pause beendet das Intervall. Kollegen dürfen pausierte Aufträge übernehmen.
Browserende beendet keine Zeit. Mitarbeiterwahl wird nach erfolgreicher Buchung zurückgesetzt.
Fertigmeldung erfasst Arbeitsschritte/Notizen; Abnahme durch Meister, Nacharbeit möglich.
Zeit je Auftrag/Mitarbeiter, keine Schritt-Timer, Pausen zählen nicht. Korrekturen mit
Grund und erhaltenem Original. Lange Buchungen ab 10 Stunden sichtbar. Soll/Ist/Vergleich.

## Fahrzeuge, Termine, Mängel
Kunden/Firmen, Kundenfahrzeuge/eigene Flotte, Stammdaten, Historie und Anhänge.
Datums- und Kilometerfristen, ODER-Verknüpfung, Wiederholung ab bestätigter Erledigung.
Warnungen standardmäßig 30/7 Tage, konfigurierbar. Kilometerhinweise nennen Datenalter.
Externe Kilometerstände erst nach Meisterbestätigung übernehmen.
QR-Code für Flottenfahrzeuge; widerrufbarer Token. Meldung: Kategorie, Beschreibung,
Dringlichkeit, optional Name/Kilometer und bis fünf Fotos. Keine automatische Veröffentlichung
oder Auftragserstellung. Meister veröffentlicht eine bereinigte Kurzinfo; keine Fotos,
Kontaktdaten, internen Texte, Mitarbeiter oder Zeiten im öffentlichen Formular.
Dubletten zuordnen; Mängel beim Abschluss einzeln bestätigen. Keine Fahrfreigabe durch App.

## Teile und Ausgaben
Teil/Menge/optionale Nummer/Dringlichkeit/Notiz/Foto; Auftrag, Fahrzeug, Mitarbeiter automatisch.
Anfordern oder Anfordern und pausieren. Angefordert → Bestellt → Eingetroffen,
zusätzlich abgelehnt/storniert. Bestellungen außerhalb der App. Offene Anforderungen
verhindern Abnahme. Dauerhafter Eingang plus Nextcloud-Benachrichtigung.
CSV-Export für Meister, druckbarer Auftragsbericht, QR-Druck.

## Sicherheit und Betrieb
OCP-APIs, objektbezogene Autorisierung, CSRF, begrenzte validierte Uploads, Rate Limits,
gehashte öffentliche Tokens im URL-Fragment; Sitzung prüft Widerruf bei jedem Zugriff.
AppData für Anhänge. DB-Migrationen und eindeutige aktive Belegungen. Keine Secrets in Logs.
UTC intern, Europe/Berlin standardmäßig, Tagesfristen ohne UTC-Verschiebung.
Polling alle zehn Sekunden nur sichtbar; keine Offline-Buchung. Übersetzbar, Dark Mode,
Tastatur und Screenreader/WCAG AA als Prüfziel. Wiederholbare Jobs und Benachrichtigungen.
Archivieren statt automatische Löschung. Personenbezogene Daten gezielt anonymisieren;
Export vor endgültiger administrativer Löschung; Aufbewahrungsfristen vor Produktivstart festlegen.
Keine fachliche Löschung bei Deaktivierung oder Entfernung der App.

## Grenzen
Keine Abrechnung, Lagerbuchhaltung, Lieferanten-API, Telematik, automatische Personalplanung,
zweiseitige Kalender-Synchronisation oder Offline-Zeitbuchung. Seit 0.5.2 stellt die App
bestätigte Termine ohne Zugangsdaten als nativen schreibgeschützten Nextcloud-Kalender bereit.
Produktionsfreigabe erst nach Installation und vollständiger Testmatrix auf
der vorgesehenen Betriebsumgebung.

## Umsetzungsstand 2026-10-02

Kernfunktionen als interner Prüfkandidat implementiert. Erstinstallation erhält fünf
editierbare Auftragsvorlagen und fünf Textbausteine; Sollzeiten sind Beispielwerte.
Fachtests, statische Prüfung und Frontendbuild liegen vor. Maßgeblich für tatsächliche
Abnahme und verbleibende Funktionsgrenzen ist [docs/test-report.md](test-report.md).
Die dort genannten offenen Punkte sind keine implizite Änderung der Anforderungen.

## Bedienbarkeit

Die Oberfläche richtet sich ausdrücklich an Personen ohne Computererfahrung.
Jede Hauptansicht erklärt den nächsten Schritt in kurzer Alltagssprache. Wichtige
Hinweise sind sichtbar; Zusatzhilfen lassen sich per Antippen und Tastatur aufklappen.
Auftragserstellung: drei klar benannte Schritte, optionale Details einklappbar,
Freigabe und Entwurf erklärt. Der schnelle Vorlagenablauf bleibt erhalten.
Tablet: Namensauswahl und gesperrte Aktionen erklären; Start/Pause/Fertigmeldung
mit konkreter Bestätigung der Zeitbuchung. Pflichtfelder kennzeichnen, fehlgeschlagene
Buchungen nicht als Erfolg anzeigen, Eingaben beim Fehler erhalten. Fahrer können
versehentlich ausgewählte Fotos vor dem Senden entfernen. Keine Datenmodell- oder
Berechtigungsänderung durch diese Überarbeitung. Visuelle Abnahme und Tests mit
ungeübten Anwendern bleiben Voraussetzung für eine belegte Usabilitybewertung.

## Fahrzeugakte: Historie und Navigation (2026-10-05)

Die Bereichsleiste steht oberhalb des scrollenden Inhalts und bleibt sichtbar.
Fahrzeugdaten, Historie, Aufträge, Mängel, Termine, Dokumente und Fahrer-QR sind
direkt erreichbar. Beim Öffnen eines Details oder Bearbeitungsdialogs bleibt die
Fahrzeugakte erhalten. „Zurück zur Fahrzeugakte“ kehrt zum gewählten Bereich zurück
und stellt den Tastaturfokus wieder her; die Daten werden neu geladen.

Das Änderungsprotokoll zeigt übersetzbare Ereignisnamen und fachliche Angaben
statt interner Ereigniscodes und JSON: Auftrag, Mitarbeiter, Teilemenge/Nummer,
Dringlichkeit, Pausenhinweis, Arbeitsnotiz, erledigte Arbeiten und Zeitkorrekturen.
Die Anzeige nennt Nextcloud-Konto und ausgewählten Mitarbeiter getrennt.
Mitarbeiternamen werden beim Lesen aufgelöst, damit Anonymisierung auch für die
Historie gilt. Fehlende Referenzen werden verständlich gekennzeichnet.

Bestandsereignisse erhalten die neue Darstellung ohne Migration. Historische
Notizen oder Pausenflags, die bisher nicht gespeichert wurden, lassen sich nicht
nachträglich rekonstruieren. Referenzangaben wie Auftragstitel und Teiletexte
zeigen den derzeit gespeicherten Stand; sie sind keine historischen Schnappschüsse.

Die Verbindungsanzeige bezieht sich auf die Erreichbarkeit dieser Nextcloud.
Ein Offline-Hinweis des Browsers darf einen erreichbaren lokalen Server nicht
sperren. Ohne Serverbestätigung wird keine Buchung als erfolgreich angezeigt;
nach Verbindungsfehlern ist eine erneute Prüfung möglich. Offene Eingaben bleiben
erhalten, automatische Hintergrundaktualisierungen setzen sie nicht zurück.

## Erweiterung 0.2.0 – verbindlicher Umfang

Ein gemeinsamer Katalog mit mindestens 250 unterschiedlichen Leistungen (geliefert: 295)
für PKW und leichte Transporter. Varianten zählen nicht als eigenständige Leistungen.
Suchbegriffe/Synonyme, Kategorien, Favoriten, kurze Erklärungen, bearbeitbare Schritte,
Einheiten und Fahrzeugklassen. Eigene Leistungen und Pakete bleiben möglich. Mengen und
Zeiten werden summiert, enthaltene Arbeiten warnen vor Doppelansätzen. Termine und
Aufträge speichern Arbeitskopien ohne interne Preise. Archivierte Katalogeinträge
verändern vorhandene Vorgänge nicht. Ein Fahrzeugklassenwechsel verlangt neue Zeiten.

Kostenlose öffentliche Angebote tatsächlicher Betriebe werden mit Quelle, Ort, Datum,
Preisart, Steuer, Material, Umfang, Einheit und Fahrzeugklasse geliefert. Gleiche zentrale
Preislisten zählen als ein Preisgeber. Berlin/Brandenburg zuerst, bei weniger als fünf
vergleichbaren Anbietern ausdrücklich Ostdeutschland. Ab fünf Anbietern Durchschnitt,
Median und Spanne; drei/vier eingeschränkter Vergleich; ein/zwei nur Einzelangaben.
Ab- und Festpreise, Klassen, Materialumfänge und Betriebstypen bleiben getrennt.
Historische/abgelaufene, widersprüchliche oder steuerlich unklare Preise gehen nicht in
Durchschnitte ein. Keine repräsentative Rechnungsstatistik. Fehlende Preise oder Zeiten
bleiben als Lücke sichtbar. Eigene Änderungen bleiben bei erneuter Initialisierung erhalten.

Terminbuch mit Tag/Woche/Monat, Fahrzeug/Kunde, mehreren Leistungen und Freitext.
Ankunft, Arbeitsbeginn und Abholung getrennt; UTC intern, Europe/Berlin in der Anzeige.
Bestätigung benötigt eine vollständige Arbeitsdauer. Wochenbudgets, Schließtage,
einzelne Ausnahmen und optionale Fahrzeuggrenze. Mehrtagesstunden müssen die Gesamtdauer
exakt ergeben und innerhalb Ankunft/Abholung liegen. Kunden, Flotte und direkte geplante
Aufträge zählen gemeinsam; verknüpfte Termine/Aufträge nur einmal. Konkurrenz wird
transaktional geprüft, Überbuchung nur mit dokumentiertem Grund. Entwürfe belegen keine
Kapazität und senden keine Nachricht. Markierungen: eingetroffen, Teile fehlen,
Kundenrückfrage, abholbereit, abgeholt, nicht erschienen. Druckansicht und Soll/Ist
bleiben erreichbar. Ungeplante direkte Aufträge haben weiterhin optionale Zeiten.

Aus bestätigtem Termin genau ein verknüpfter Auftragsentwurf mit Arbeitsschritten,
Sollzeit und Planung. Tabletfreigabe ausdrücklich; spätere Änderungen überschreiben
keinen Auftrag. Mechaniker erhalten Arbeitsinformationen, keine Kosten oder Marktpreise.

Bestätigte Termine erscheinen über einen nativen, schreibgeschützten Nextcloud-Kalenderprovider
automatisch bei Meistern und Administratoren. Ein Integrationskonto, Lesefreigaben und
App-Passwort sind nicht erforderlich. Stabile Ereignis-IDs sowie getrennte
Annahme-/Abholung-/Arbeitseinträge werden direkt aus den Termindaten erzeugt.
Kundennachrichten bei Bestätigung, Änderung und Absage über Nextcloud-Mail mit ICS;
optionale Erinnerung 24 Stunden vorher, keine sofortige nachträgliche Erinnerung.
Keine internen Notizen, Mitarbeiterzeiten oder Kalkulationsdaten. Kein ATTENDEE im
internen Kalender, deshalb keine zweite Kalendereinladung. Versandfehler sind sichtbar;
bei unklarem SMTP-Ausgang nur bewusste Wiederholung. ICS-Dateien müssen je nach
Kunden-Kalenderprogramm importiert werden; automatische Annahme ist nicht zugesagt.

Preisrechner: jährliche Arbeitgeberkosten, Unternehmerlohn, Miete, Energie, Versicherungen,
Werkzeuge, Software und weitere Kosten / tatsächlich abrechenbare Jahresstunden.
Kosten- und Verkaufssatz netto, ausdrücklicher Gewinnaufschlag auf Kosten, Material mit
eigenem Aufschlag, Fremdleistungen, Netto/Umsatzsteuer/Brutto. Regionale Angaben ändern
keinen eigenen Preis automatisch; kalkulierte Nettopreise werden ausdrücklich übernommen.
Keine Doppelzählung direkten Materials unter allgemeinen Betriebskosten.

Rechercheziele 30 Preisgeber/300 Angaben sind Datenumfangsziele, keine Garantie für
250 belastbare Vergleichspreise. Maßgeblich sind die Abdeckung je Leistung und
[Preisquellen](service-prices.md). Aktueller Implementierungs-/Prüfstand siehe
[Prüfbericht](test-report.md). Einseitige Integration ersetzt weder Einsatzplanung
noch Rechnungsstellung. GitHub bleibt privat; Veröffentlichung und produktive Installation
sind nicht Teil dieser Erweiterung. Physischer iPad-Test bleibt offen.

### Präzisierungen der Fortsetzung (2026-10-05)

- Automatisch erzeugte Titel folgen der Vorlagen-/Leistungsauswahl; manuell geänderte
  Titel bleiben auch bei Vorlagenwechsel erhalten. Zusätzliche Leistungen addieren
  ihre mengenabhängige Zeit zur Vorlagenzeit; unbekannte Teilzeiten lassen die Summe offen.
  Die Oberfläche warnt davor, bereits enthaltene Arbeit doppelt auszuwählen.
- Regionale Vergleichsgruppen mit mindestens fünf unabhängigen Anbietern können nach
  Bestätigung von Umfang, Einheit, Material und Fahrzeugklasse als Zielpreis übernommen
  werden. Netto wird nur bei einheitlichem, bekanntem Quellen-Steuersatz berechnet.
  Eigene Kosten bleiben unverändert; Katalogspeicherung ist eine zweite ausdrückliche Aktion.
- Katalogupdates vergleichen Felder mit der zuletzt gespeicherten Seed-Baseline. Eigene
  Preise, Favoriten, Archivierung, Sortierung und eigene Zeitangaben bleiben erhalten.
  Bereits bearbeitete Alteinträge ohne Baseline werden beim ersten Übergang konservativ erhalten.
- Entfällt eine zugesagte Abholung, erhält der Kunde zusätzlich eine ICS-Absage nur der
  Abhol-UID. Versandfehler zeigen Termin, Fahrzeug und Ankunft ohne interne Notizen.

## UX-Verbesserung nach Ablaufpruefung (2026-10-05)

Kalkulation bleibt beim internen Bereichswechsel erhalten; keine automatische Profil-/Preis-Speicherung. Tatsaechtliche ungespeicherte Formulareingaben sind beim Schliessen und Neuladen geschuetzt. Unveraenderte Dialoge benoetigen keine Verwerf-Rueckfrage. Netzwerkaktionen sperren das Schliessen.

Monatsnavigation waehlt den ersten Zielmonatstag und ueberspringt keine kurzen Monate. Terminkarten nennen Ankunft, an diesem Tag geplante Minuten, Gesamtdauer und verknuepften Auftragsstatus. Ankunft/Abholbereitschaft/Abholung sind versionierte explizite Aktionen. Abnahme setzt keine tatsaechliche Abholung automatisch.

Absagen benoetigen eine eigene Bestaetigung und verwenden den gespeicherten Termin; ungespeicherte Nebenangaben werden dabei nicht uebernommen. Neue Kunden sind im folgenden Fahrzeugdialog als Kundenfahrzeug vorausgewaehlt. Eigene Arbeiten koennen ohne globale Katalogaenderung direkt ergaenzt werden. Preisantworten und Uebernahmen gelten nur fuer den unveraenderten Eingabestand.

## Ausbau 0.2.1: Annahme und Tagesgeschäft

- Interne Checkliste je Termin: Schlüsselannahme, Unterlagen, vorhandene Schäden, Arbeitsumfang abgesprochen, Abschlusskontrolle, Kundeninformation und Schlüsselrückgabe. Optional erfasster Kilometerstand sowie Zustands- und Rückrufnotiz. Haken bedeuten „durchgeführt“, nicht „nicht vorhanden“. Keine Unterschrift, technische Fahrzeugfreigabe oder automatische Kilometerbestätigung. Meister speichern ausdrücklich; bestehende Rollen, Versionsprüfung und Änderungsprotokoll bleiben erhalten. Interne Angaben gelangen weder ans Tablet noch in Kundenmail/CalDAV. Datenschutzexport enthält sie; kontrollierte Fahrzeuglöschung entfernt sie mit dem Termin.
- Im gewählten Kalenderzeitraum nach Bezeichnung, Fahrzeugkennung und E-Mail suchen; Entwürfe, bestätigte Termine, Teile-/Kundenrückfragen und Abholbereitschaft filtern. Tagesbudgets bleiben bei Filterung vollständig, damit versteckte Termine nicht als freie Zeit erscheinen.
- Bei neuer Terminannahme freie Tage für die eingeplante Dauer suchen (14 Kalendertage, offene Tage mit ausreichender Arbeits- und Fahrzeugkapazität). Es werden Tagesbudgets vorgeschlagen, keine Uhrzeit-/Mechanikerreservierungen. Erst die serverseitig geprüfte Bestätigung bucht. Mehrtagesplanung weiterhin ausdrücklich.
- „Folgetermin vorbereiten“ kopiert nur Fahrzeug, Arbeitstitel, Leistungen und Dauer in einen neuen ungespeicherten Termin. Keine alte Bindung, Checkliste, Notizen, Erinnerung, Überbuchungsbegründung oder zugesagte Uhrzeiten. Aktuelle Fahrzeug-/Kundenzuordnung bestimmt die vorausgefüllte E-Mail. Entwurf versendet nichts.
- Alte bereits veröffentlichte Migrationen bleiben unverändert; neue nullable JSON-Spalte via Version000201Date20261005230000. Kein neuer externer Dienst oder neue Abhängigkeit.

- Vorhandene unerledigte Fahrzeugaufträge werden bei direkter Auftragserstellung und Terminannahme als Hinweis angezeigt; die vorhandene Terminverknüpfung wird ausgenommen. Kein automatisches Zusammenführen. Nach dem Speichern zeigt das Terminbuch den Ankunftstag und hebt versteckende Filter auf.

### Nachprüfung der Fahrzeughistorie am 06.10.2026

Ankunftszeitpunkte aus Kundenterminen erscheinen auch im Änderungsprotokoll als Datum und Uhrzeit in Europe/Berlin. Sommer-/Winterzeit und Tageswechsel dürfen keine technischen ISO-Fragmente oder falschen Kalendertage anzeigen. Ungültige Zeitangaben in Alteinträgen verhindern nicht das Lesen der übrigen Historie.

### Fahrerformular: bestätigter Versand

Nach vollständig bestätigter Meldung und allen Foto-Uploads werden Eingaben, Fotoliste und native Dateiauswahl zurückgesetzt. Bei Uploadfehlern bleiben noch nicht bestätigte Fotos ausgewählt; erneutes Senden legt keinen zweiten Mangel an.

## Erweiterung 0.2.2: Zugänge, Leistungsrangliste und Kalender bei der Annahme

Ein Nextcloud-Administrator vergibt unter **Einstellungen → Zugänge für Meister und Terminannahme** zusätzliche Einzelzugänge per Benutzersuche. Geschäftsleitung kann vollständigen Meisterzugang erhalten; dies umfasst auch sensible Kosten, Kundenakten und Exporte. Kein neu eingeführter Lesemodus. Eine Assistenz erhält ausschließlich Terminannahme (bestehende Kunden/Fahrzeuge auswählen, Termine einschließlich Leistungen vereinbaren, ändern, Entwürfe speichern und absagen). Stammdatenanlage, Arbeitsaufträge/Zeiten, Teile, Mängel, Dateien/QR, Kosten/Vergleichspreise, Exporte, Kapazitätsänderungen und Überbuchung bleiben gesperrt. Interne Fahrzeug-/Kundennotizen und Annahme-/Abholchecklisten werden nicht ausgegeben und bei Terminänderungen durch Assistenz erhalten. Eine bereits begründete Überbuchung kann in Kontaktdaten verändert werden, wenn Fahrzeug/Ankunftstag/Arbeitstage unverändert bleiben; neue Überbuchung erfordert Meister. Eine Nextcloud-Benutzerlöschung entfernt beide zusätzlichen Einzelzuordnungen über den öffentlichen UserDeletedEvent (getUid seitNC31); eine spätere Wiederverwendung der Benutzer-ID erbt keine direkte Freigabe. Jede Operation prüft den aktuellen Zugang, auch in vorhandenen Sitzungen. Administrator/Meister hat Vorrang, danach Terminannahme, dann Tablet. Assistenz darf deshalb nicht parallel Administrator/Meister sein. Einzelzugänge und optionale Gruppen gelten appweit, keine öffentlichen Freigabelinks.

Das gemeinsame Leistungsfeld zeigt acht Vorschläge nach Nutzungshäufigkeit absteigend; bei Gleichstand Favorit, eigene Position, Bezeichnung und ID. Suche/Kategorie/gesamter Katalog bleiben erreichbar. Gezählt wird jede echte Katalogleistung höchstens einmal je bestätigtem Termin oder freigegebenem Auftrag, unabhängig von Menge und mehrfacher Auswahl. Ein verknüpfter Termin/Auftrag zählt zusammen einmal: sobald Auftrag freigegeben, gelten dessen unveränderliche Leistungen; andernfalls bestätigter Termin. Entwürfe, frei eingegebene Arbeiten, reine Kalkulation und abgesagte/stornierte Vorgänge zählen nicht. Ein abgesagter Termin mit weiterhin freigegebenem Auftrag zählt weiterhin dessen Arbeiten. Ranking bezeichnet Nutzung zur Planung, keinen Nachweis der tatsächlich abgerechneten Arbeit. Mengen, Zeiten und bestehende Momentaufnahmen bleiben unverändert. `wm_service_usage` enthält lediglich Vorgangsreferenz und Leistungs-ID; nur aggregierte Zahlen gelangen zum Browser. Uniqueindex, bestehender Planungslock und gemeinsame Transaktionen verhindern Doppelzählung. Beim Upgrade erste100Datensätze je Art, Rest per begrenztem Cronjob mit stabilem ID-Cursor; wiederholbar ohne Änderung der alten Termine/Aufträge. Kontrollierte Fahrzeuglöschung entfernt auch die Nutzungsreferenzen.

Im Terminformular ist eine Wochenansicht sofort sichtbar. Tageskarten nennen Wochentag/Datum, freie/verfügbare Stunden, prozentuale Auslastung, Fahrzeugzahl/-limit und vorhandene Ankunfts-/Abholtermine samt Arbeitstitel. Abgesagte Termine sind ausgeblendet, Entwürfe ausdrücklich ohne Reservierung gekennzeichnet. Wochennavigation und Datumssprung verändern keine Formulareingaben. „Diesen Tag wählen“ übernimmt nur den Ankunftstag und erhält die Ankunftsuhrzeit; beim Wechsel Arbeitsbeginn/Abholung leeren und ausdrücklich neu festlegen. Mehrtagesverteilung wird nicht unbemerkt verschoben. Geschlossene Tage sind nicht per Vorschlag auswählbar. Kapazität zählt auch direkte Kunden-/Flottenaufträge und wird erst nach bestätigter Speicherung reserviert, transaktional nochmals geprüft. Die Übersicht zeigt Tagesbudgets, keine freien Mechanikerzeitfenster. Bestehende Fristen/Termine/Kalenderintegration bleiben unabhängig.

Neue Termine werden ohne expliziten Entwurfstatus serverseitig bestätigt. Hauptaktion „Termin speichern & bestätigen“, Rückmeldung „gespeichert und bestätigt“. Der bisherige interne stage-Wert planned bleibt aus Kompatibilitätsgründen bestehen, wird für bestätigte Termine verständlich als „Bestätigt“ angezeigt. Entwurf bleibt Entwurf, ohne Kapazitätsbuchung/E-Mail. Hintergrundversand/Kalender bleiben getrennt von erfolgreicher Datenspeicherung.

API-Ergänzung: administratorgeschützte GET/PUT `/api/v1/access-settings`, GET `/api/v1/access-users?q=…` (20 Ergebnisse, mindestens2Zeichen, keine E-Mail/Extras). CSRF bleibt unverändert. Bestehende Planungs-GET/POST erlauben zusätzlich Rolle reception mit reduzierten Antworten. `usage_count` ist berechnetes, ausschließlich lesbares Feld im Leistungskatalog. Neue Migration Version000202Date20261006090000; keine historische Migration geändert. Keine neue Bibliothek/ExApp/externen Datenflüsse.

Öffentliche APIs am06.10.2026 geprüft: [IGroupManager v35.0.1](https://github.com/nextcloud/server/blob/v35.0.1/lib/public/IGroupManager.php); IUserManager get/searchDisplayName und QueryBuilder count/selectAlias zusätzlich anhand installierter Release-Interfaces/gebundener stable35-OCP-Stubs verifiziert. Browserprüfungen ersetzen keinen physischen iPad-Test.

## Firmenprofil und gemeinsame Werkstattadresse (0.2.3)

Meister pflegen Firmenname und Logo unter Stammdaten & Vorlagen. Öffentlich sichtbare Identität in allen Appkopfbereichen einschließlich Fahrerformular; keine vertraulichen Inhalte. PNG/JPEG/WebP maximal2MB/4MP, serverseitig als PNG bis1024px ohne Metadaten gespeichert. Austausch/Entfernung sperrt alten öffentlichen Zugriff, ungenutzte Dateien nach24h im begrenzten Maintenancejob löschen.

Ein Administrator delegiert ausdrücklich eines seiner eigenen Nextcloud-Mailkonten als gemeinsame Werkstattadresse für alle Termine/Erinnerungen. Konfiguration global, unabhängig vom bearbeitenden Meister oder Assistenznutzer. Geheimnisse bleiben bei Mail. Öffentliche OCP-Mail-Provider-API seitNC30; geprüft gegen NC35.0.1 undMail5.12.3. Besitzer Enabled/Meisterberechtigung/Konto/primäreAdresse werden beim Job erneut geprüft; Löschung widerruft Delegation. Kein automatischer Systemfallback. Systemversand ist bewusste Alternative und bisheriger Default. Providererfolg bedeutet „an Mail übergeben“, kein Zustellbeleg; Status im Mailclient prüfen. Unklare Versandversuche weiterhin nur explizit wiederholen. Keine Testnachricht an echte Empfänger.

## Lokale Vorführdemo mit iPad (06.10.2026)

Der bestehende Mac-Starter bereitet den Zugang im gleichen lokalen WLAN bei jedem Start vor: Bonjourname mit .local und Port8787, konkret erlaubte Nextcloud-Domänen und aktuelle private IPv4-Adressen, automatischer Ruhezustand während der Demo verhindert. Anmeldung mit getrenntem Tabletkonto und unveränderten serverseitigen Rollen. Mac-Name bleibt bei WLAN-IP-Wechsel gleich; Umbenennung und isoliertes Gäste-WLAN sind Grenzen. Deckel geöffnet lassen. Die lokale HTTP-Vorführung verwendet fiktive Daten; Produktivbetrieb verwendet HTTPS. Request-IDs müssen auch auf einem lokalen HTTP-Origin kryptografisch zufällig und bei Wiederholung gleich bleiben; kein Ersatz durch schwache Zufallswerte. Physische Geräteabnahme gesondert.

## Auswertung (Erweiterung vom 09.10.2026)

Meister-Menü „Auswertung“: Zeitraum bis ein Jahr, Kunden-/Flottenfilter, Monatsumsatz,
Kapazität nach Tag, Arbeitszeiten je Mitarbeiter und Soll/Ist abgeschlossener Aufträge.
Alle Finanzwerte in Euro netto, manuell bestätigte Plan- und Istbeträge, keine automatische
Ableitung tatsächlicher Umsätze aus Vergleichspreisen oder geleisteten Stunden. Fehlend
ist nicht null Euro. Istbeträge nur für abgeschlossene Kundenaufträge, mit Umsatzdatum
und Änderungsgrund; keine Rechnungsstellung, Zahlungsüberwachung oder Buchhaltung.
Termine und verknüpfte Aufträge zählen einmal; bestätigte Termine mit Auftragsentwurf
bleiben in der Vorschau. Abgeschlossene/stornierte Aufträge, abgesagte Termine und
Entwürfe ohne bestätigten Termin erzeugen keine Umsatzprognose. Mehrtages-Planumsatz
wird centgenau proportional auf Arbeitsplanung verteilt; ohne Planungstag gesondert
sichtbar, nicht einem beliebigen Tag zugerechnet. Eigene Flotte erzeugt keinen Kundenumsatz.
Zeitintervalle an Berliner Periodengrenzen beschneiden; Pausen ausschließen, laufende
Zeiten bis Serverzeit, Soll/Ist abgeschlossener Aufträge über deren gesamten Lebenszyklus.
Finanzdaten bleiben ausschließlich beim Meister, auch gegenüber Terminannahme/Tablet/Fahrer.
Optimistische Versionierung, transaktionale Speicherung, Grund und Vorher/Nachher im
Änderungsprotokoll. Neue additive Umsatz-Tabelle, Export und Fahrzeuglöschung ergänzen.

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

## Freiwillige Projektunterstützung (0.4.1)

Meister können über den unteren Navigationsbereich freiwillig die Projektkosten
und Weiterentwicklung unterstützen. Vor PayPal erscheint ein zugänglicher Hinweis:
kostenlose Nutzung bleibt erhalten, keine zusätzlichen Funktionen/individuellen
Leistungen, keine Spendenbescheinigung oder behauptete steuerliche Begünstigung.
Öffentliches PayPal.Me-Profil `Werkstattmanager`, ohne Betrag oder Zahlungsart im
Link. Keine wiederkehrende Zahlung durch die App. Keine Kopplung an Aufträge,
Kunden, Personal, Nutzungsumfang oder Kalender; keine Zahlungsdaten in der App.

Ziel fest im Releasecode und den Nextcloud-Unterstützungsmetadaten, kein bearbeitbares
Einstellungsfeld und keine Übernahme aus Firmenprofil/API/Initial State/URL-Parametern.
Quelloffene Änderungen durch Serverbetreiber bleiben möglich. Profilname bewusst
öffentlich lesbar, keine Empfangs-E-Mail im Link und keine PayPal-Zugangsdaten. Externer Link erst nach ausdrücklicher
Aktion, neuer Tab ohne opener/Referrer; keine Fremdskripte oder Vorabverbindungen.
Tablet, Fahrer und Terminannahme zeigen den Projektaufruf nicht. Steuerliche
Einordnung und PayPal-Kontoberechtigung verantwortet der Projektinhaber.

## Store-Veröffentlichung unter persönlichem Herausgebernamen (11.10.2026)

Die Veröffentlichung wird unter **Thomas Keilhaupt** vorbereitet. Der öffentliche
App-Autor ist vom Kontobenutzernamen und der privaten Konto-E-Mail getrennt.
Die Konto-E-Mail wird nicht in `info.xml`, Quellcode, Screenshots oder öffentliche
Unterlagen aufgenommen. Ein bereits angemeldetes Store-Konto wird vor einer
Neuanlage dem gewünschten Eigentümer zugeordnet; keine ungefragte Doppelregistrierung.

Vorbereitete Beschreibung und Zertifikatsanfrage sind keine Einreichung.
GitHub bleibt bis zur ausdrücklichen Sichtbarkeitsfreigabe privat; private Archive
bleiben getrennt. Öffentlicher Quellcode/Support, extern ausgestelltes Zertifikat,
finale Dateisignatur, Archivsignatur und ohne Anmeldung erreichbarer Download
sind Voraussetzungen der späteren Store-Einreichung. Signiert wird erst nach
Finalisierung und Prüfung des tatsächlich angebotenen Pakets. Geräte-, Betriebs-
und Datenrechteabnahmen bleiben sichtbar offen; keine automatische Produktionsfreigabe.


## Mitarbeiter-Vorplanung (0.5.0, Entscheidung 16.10.2026)

Der Meister kann einen aktiven Mitarbeiter optional beim Erstellen eines Auftrags vorsehen und die Zuordnung bei Entwürfen, offenen oder pausierten Aufträgen ändern oder aufheben. Die Zuordnung ist ausdrücklich ein Hinweis; Kollegen dürfen übernehmen. Es entstehen keine Arbeitszeit, aktive Belegung oder Statusänderung. Vorplanung bleibt bis zur bewussten Änderung erhalten, tatsächliche Arbeitsintervalle und letzter Bearbeiter sind davon getrennt. Bei laufenden oder zur Abnahme gemeldeten/abgeschlossenen/stornierten Aufträgen lässt sie sich nicht ändern.

Tablet und Meister sehen in offenen/pausierten Karten und Details „Vorgeplant: Name“ mit auffälligem rotem Rahmen sowie erklärendem Text; Entwürfe bleiben ausschließlich beim Meister. Der Name wird nicht automatisch als tatsächlicher Bearbeiter gewählt. Archivierte oder fehlende Mitarbeiter werden verständlich als nicht mehr verfügbar gekennzeichnet; sie können nicht neu zugeordnet werden. Meister können die alte Zuordnung aufheben. Namensfilter berücksichtigen Vorplanung und letzten Bearbeiter bereits serverseitig vor der Pagination.

Nur Meister ändern die Vorplanung; Tablet und Terminannahme sind serverseitig ausgeschlossen. Optimistische Version, Transaktion und Wiederholungskennung schützen vor verlorenen Antworten und parallelen Änderungen. Das Fahrzeugjournal zeigt Änderungen/Aufhebungen verständlich; gespeichert werden Referenzen statt dauerhafter Namenskopien. Vorherige Namen werden beim Lesen aus den aktuellen Mitarbeiterdaten aufgelöst und folgen deren Anonymisierung. Additive nullable-Spalte erhält vorhandene Auftragsfelder, Versionen und Zeitintervalle. Keine neuen externen Datenflüsse.


## Öffentliche Test-Beta0.5.1-beta.1 (16.10.2026)

Nutzer beauftragt Store-Veröffentlichung mit erhaltenen Vergleichspreisen, aber ohne Nennung der Preisgeber. 334 Beobachtungen und32 unabhängige Anbieter bleiben numerisch unverändert; statische Preisdaten, API-Ausgaben einschließlich Altbeständen und Preisansicht veröffentlichen neutrale Anbieterkennungen und keine Anbieterlinks. Eigene Werkstattpreise, Preisrechner, Übernahmebestätigung und bestehende Berechtigungen bleiben erhalten. Keine Schemaänderung und keine automatische Löschung bestehender Preiszeilen. Originalquellen werden getrennt intern archiviert; diese Änderung behauptet keine abgeschlossene Rechteprüfung und entfernt keine historische Git-Information. Software-/Bibliothekslizenzen bleiben vollständig erhalten.

## Korrektur 0.5.1-beta.2: optionaler Kalender

Eine leere Kalenderadresse bedeutet, dass die einseitige CalDAV-Integration ausgeschaltet ist. Terminbestätigungen und Kunden-E-Mails funktionieren unabhängig davon. Ohne Adresse werden keine neuen Kalenderzustellungen angelegt; beim nächsten fälligen Zustelllauf werden alte wartende Kalenderzustellungen ohne weiteren Fehlversuch als abgebrochen markiert und ihre überholte Fehlermeldung geleert. Es erfolgt keine rückwirkende Übertragung aller Termine bei späterer Einrichtung; eine folgende relevante Terminänderung überträgt den aktuellen Stand. Bei weiterhin hinterlegter Adresse bleiben entzogene Zugangsdaten und HTTP-Ausfälle sichtbar und werden mit bestehender begrenzter Wiederholung behandelt. Bereits versandte Kalenderereignisse werden beim Abschalten nicht automatisch aus dem entfernten Kalender gelöscht.

## Stabile Store-Version 0.5.1 (19.10.2026)

Der fachlich und technisch geprüfte Laufzeitstand von0.5.1-beta.2 wird ohne Vorabversionssuffix im stabilen Nextcloud-App-Kanal angeboten. Es gibt keine neue Migration oder Funktionsänderung. Die stabile Kanalzuordnung setzt weiterhin eine betriebliche Pilotabnahme der konkreten Installation, Geräte, Sicherungen und Integrationen voraus.

## Automatischer Werkstattkalender 0.5.2 (20.10.2026)

Die App registriert über `OCP\Calendar\ICalendarProvider` für jedes aktuell als Meister oder Administrator berechtigte Nextcloud-Konto einen virtuellen Kalender „Werkstatt“. Er enthält nur bestätigte Termine und ist ausschließlich lesbar. Rechte werden beim Laden und bei jeder Suche erneut anhand der aktuellen Werkstattrolle geprüft. Tablet- und Terminannahme-Konten erhalten keinen Kalender. Ausgegeben werden Fahrzeugkennung, Arbeitstitel, Ankunft, Abholung und Arbeitsplanung; Kundenadresse, interne Notiz, Checkliste, Mitarbeiterzeit und Kalkulation bleiben intern.

Die Kalenderansicht liest bestehende Termindaten unmittelbar, speichert keine Kopien und benötigt keine Zugangsdaten. Eine additive Migration ergänzt ausschließlich Abfrageindizes. Bereits hinterlegte externe CalDAV-Konfigurationen bleiben zur Upgrade-Kompatibilität aktiv und unterdrücken den nativen Kalender, damit keine Duplikate entstehen. Ein Administrator kann Zugang und verschlüsseltes Altpasswort entfernen und damit auf den nativen Kalender wechseln.

Bei einem Wechsel bleiben bereits in den externen Kalender kopierte Ereignisse dort bestehen. Die Einstellungsansicht weist vor der Aktion auf die einmalige manuelle Bereinigung hin. Ereignisse, die über einen Tageswechsel reichen, müssen in jedem tatsächlich überlappten Abfragefenster erscheinen; dies gilt auch für die 15-Minuten-Marker von Annahme und Abholung.

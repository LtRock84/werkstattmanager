# Preisquellen und Aussagekraft – Datenlieferung 2026-10-05.1

Prüfdatum 2026-10-05. 295 unterschiedliche Leistungen, 334 nachvollziehbare Preisangaben
von 32 Preisgebern. 248 Angaben erfüllen die Regeln für vergleichbare Gruppen; 86 bleiben
wegen unklarem Umfang/Steuer/Material oder widersprüchlicher Angaben außerhalb der Statistik.
57 Leistungen haben mindestens eine öffentliche Angabe, 238 noch keine passende Quelle.
Diese Zahl ist **keine Zahl belastbarer Durchschnittspreise**. Varianten je Radgröße,
Fahrzeug oder Material zählen als Preisbeobachtungen, nicht als zusätzliche Leistungen.

Die Rechercheziele 30 Preisgeber/300 Angaben werden beim Rohdatenumfang erreicht.
Der Schwerpunkt liegt bei öffentlich gut dokumentierten Reifen-/Räderleistungen. Für
fahrzeugabhängige Reparaturen fehlen häufig vergleichbare kostenlose Listen. Es werden
keine Ersatzpreise erfunden. Eine große Bibliothek bedeutet nicht, dass jede Leistung
bereits einen belastbaren Marktpreis oder eine Herstellerzeit hat.

Nur gleicher Umfang/Material/Einheit/Klasse/Betriebstyp/Preisart werden zusammengeführt.
Ab-Preise heißen „Durchschnitt veröffentlichter Einstiegspreise“, Festpreise bleiben getrennt.
Fünf unabhängige Anbieter: Mittelwert, Median, Spanne; drei/vier: eingeschränkter Vergleich;
ein/zwei: Einzelangebote. Berlin/Brandenburg mit weniger als fünf passenden Anbietern wird
sichtbar nach Ostdeutschland erweitert. Zentrale Preislisten zählen nur einmal; eine Betriebskette ist kein eigener Anbieter je Standort.

Veröffentlichte Bruttopreise werden nur bei eindeutiger Steuerangabe in Gruppen übernommen.
Undatierte Seiten heißen „öffentlich angezeigt am Prüfdatum; Preisgültigkeit nicht bestätigt“.
Historische/ausgelaufene Aktionen sind nicht übernommen. Quarantänisierte Angaben bleiben
mit Begründung sichtbar. Die Erhebung ist keine repräsentative Statistik bezahlter Rechnungen.

## Anbieterkennzeichnung in der öffentlichen Beta

Seit Datenlieferung 2026-10-16.1 enthalten Paket und Preis-API neutrale, stabile Preisgeberkennungen. Unternehmensnamen und Anbieterlinks werden nicht veröffentlicht. Die 334 Preisbeobachtungen von 32 unabhängigen Preisgebern, Beträge, Steuerkennzeichnung, Prüfdatum, Regionen, Leistungsumfang und Ausschlussgründe bleiben erhalten. Bestehende Datenbankzeilen werden bei der API-Ausgabe ebenfalls neutralisiert; Datenbestände werden nicht gelöscht oder umgeschrieben. Ein Anbieter zählt weiterhin nur einmal je Vergleichsgruppe.

Die Namen und Originalbelege sind getrennt von der Veröffentlichung intern archiviert. Die Anonymisierung ist kein Nachweis einer Nutzungserlaubnis; die zuvor offene Prüfung der Wiederverwendungsbedingungen bleibt dokumentiert. Historische GitHub-Commits können ältere Quellenangaben enthalten; die öffentliche Historie wurde nicht umgeschrieben.

Die öffentliche Bibliothek behauptet keine individuelle Empfehlung und keine Preiszusage eines genannten Betriebs. Sie enthält keine aktuellen Angebote bestimmter Anbieter. Preise sind Orientierungswerte aus der dokumentierten Erhebung, keine repräsentative Statistik tatsächlich bezahlter Rechnungen.

## Aktualisierung

`scripts/build-price-catalog.py` bereitet die ausgelieferten neutralisierten Beobachtungen reproduzierbar auf. Optional kann ein getrennt aufbewahrter Recherchebestand über `--input` verarbeitet werden. Dieser Originalbestand gehört nicht ins Git oder ins App-Paket. Stabile Seed-Schlüssel erhalten vorhandene Datensätze und vermeiden doppelte Preisbeobachtungen.

## Zeitvorschläge

Die Richtwerte für saisonalen Räderwechsel (25Minuten) und Achsvermessung mit Einstellung (75Minuten) wurden in derselben Erhebung recherchiert. Der veröffentlichte Herkunftshinweis verweist auf diese Methodennotiz; der originale Anbieterbeleg bleibt intern archiviert. Die Minutenwerte bleiben unverändert und sind keine fahrzeugspezifischen Herstellervorgaben. Andere unabhängige Hersteller-/Fachquellen für Zeitvorschläge bleiben separat gekennzeichnet.

#!/usr/bin/env python3
"""Manually verified factual public offers, never inferred or fabricated market prices."""
import json, hashlib
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
services=json.loads((ROOT/'lib/Data/services.json').read_text())['services']; keys={s['title']:s['key'] for s in services}
observations=[]
def provider(name,url,city,region,vat=19,segment='freie Werkstatt',valid=None):
 return dict(provider=name,source=url,city=city,region=region,vat=vat,segment=segment,valid_from=valid,valid_until=None,checked_date='2026-10-05',undated=valid is None)
def add(p,title,price,scope,unit='Leistung',material='Arbeitsleistung, Material zusätzlich',kind='fixed',vehicle='car',net=False,eligible=True,exclusion='',valid_until=None):
 assert title in keys,title
 # No VAT inference from a consumer-looking page. An unconfirmed price is shown separately.
 amount=round(price*100*(1+p['vat']/100 if net and p['vat'] is not None else 1))
 row=dict(p,service_key=keys[title],gross_cents=amount if p['vat'] is not None else None,scope=scope,unit=unit,material=material,price_kind=kind,vehicle_class=vehicle,eligible=eligible and p['vat'] is not None,exclusion=exclusion or ('Umsatzsteuer nicht eindeutig angegeben; veröffentlichter Betrag: '+str(price)+' Euro' if p['vat'] is None else ''),valid_until=valid_until)
 row['seed_key']=hashlib.sha256(json.dumps(row,sort_keys=True).encode()).hexdigest()[:32]
 observations.append(row)
p=provider('Reifen Tobi','https://www.reifen-tobi.de/preise.htm','Königs Wusterhausen','Brandenburg',segment='Reifendienst')
for vc,price in [('car',30),('van',35)]:add(p,'Saisonaler Räderwechsel',price,'Vier Kompletträder wechseln','Satz',vehicle=vc)
for vc in ['car']:
 add(p,'Räder demontieren und auswuchten',45,'Vier Räder abnehmen, wuchten und anbauen','Satz',vehicle=vc)
 add(p,'Lose Räder auswuchten',25,'Vier lose Räder auswuchten','Satz',vehicle=vc)
 for price,scope in [(65,'Vier lose Stahlfelgen, Montage, Ventile und Wuchten'),(75,'Vier Stahlfelgen, Montage, Ventile, Wuchten und Fahrzeugmontage'),(75,'Vier lose Alufelgen, Montage, Ventile und Wuchten'),(85,'Vier Alufelgen, Montage, Ventile, Wuchten und Fahrzeugmontage')]:add(p,'Reifen montieren und auswuchten',price,scope,'Satz',material='Ventile und Wuchtmaterial enthalten')
add(p,'Räderwechsel mit Einlagerung',60,'Vier Räder wechseln und einlagern','Saison',material='Einlagerung enthalten')
add(p,'Reifen reparieren',40,'Innenreparatur mit Demontage, Montage, Ventil und Wuchten','Rad',material='Reparaturmaterial enthalten')
p=provider('BHS Hartmann','https://reifen-kfz-hartmann.de/preise/','Berlin-Spandau','Berlin')
for title,price,scope,unit in [('Saisonaler Räderwechsel',25,'Vier Kompletträder wechseln','Satz'),('Lose Räder auswuchten',5,'Loses Rad auswuchten','Rad'),('Reifen einlagern',40,'Vier Räder einlagern','Saison'),('Öl- und Filterwechsel',49,'Motoröl und Ölfilter wechseln','Leistung'),('Fehlerspeicher auslesen',39,'Fehlerspeicher auslesen / Diagnoseumfang unklar','Leistung'),('Starterbatterie erneuern und anlernen',15,'Batterie prüfen und wechseln, Anlernen unbestätigt','Leistung'),('Bremsen prüfen',19,'Sicht- und Messprüfung der Bremsen','Leistung'),('Bremsflüssigkeit wechseln',59,'Bremsflüssigkeit wechseln und entlüften','Leistung'),('Fahrwerk prüfen',29,'Fahrwerk prüfen','Leistung')]:add(p,title,price,scope,unit,kind='from',eligible=title not in ['Fehlerspeicher auslesen','Starterbatterie erneuern und anlernen'],exclusion='Leistungsumfang nicht eindeutig.' if title in ['Fehlerspeicher auslesen','Starterbatterie erneuern und anlernen'] else '')
add(p,'Lichtanlage prüfen',0,'Lichtprüfung mit kleinen Einstellungen',material='Material zusätzlich',eligible=False,exclusion='Kostenloses Check-Angebot, getrennt von Reparaturleistungen.')
p=provider('Autoservice Köhler','https://www.autoservicekoehler.de/aktionen/','Chemnitz','Sachsen',valid='2026-07-01',segment='Reifendienst')
add(p,'Saisonaler Räderwechsel',38,'Vier Kompletträder wechseln','Satz')
for price,scope in [(67,'Räderwechsel, Einlagerung und Reinigung unter 650 mm'),(71,'Räderwechsel, Einlagerung und Reinigung über 650 mm')]:add(p,'Räderwechsel mit Einlagerung',price,scope,'Saison',material='Einlagerung und Reinigung enthalten')
add(p,'Achsvermessung mit Einstellung',120,'Achsvermessung mit Spur-/Sturzeinstellung',material='Kein Material erforderlich')
for title,price in [('Klimaservice R134a',75),('Klimaservice R1234yf',90)]:add(p,title,price,title+' ohne Kältemittel',material='Kältemittel zusätzlich')
p=provider('Lipsia Automobile','https://www.lipsia-automobile.de/preise/','Leipzig','Sachsen')
add(p,'Saisonaler Räderwechsel',35,'Vier Kompletträder wechseln','Satz')
add(p,'Räderwechsel mit Einlagerung',60,'Vier Räder wechseln und einlagern','Saison',material='Einlagerung enthalten')
add(p,'Räder demontieren und auswuchten',60,'Vier Räder abnehmen, wuchten und anbauen','Satz')
add(p,'Räderwechsel mit Einlagerung',79,'Vier Räder wechseln, auswuchten und einlagern','Saison',material='Einlagerung enthalten')
for price,scope in [(11.9,'Loses Stahlrad auswuchten'),(16.9,'Loses Alurad auswuchten')]:add(p,'Lose Räder auswuchten',price,scope,'Rad')
for price,scope in [(23,'Reifenmontage mit Wuchten bis 17 Zoll'),(27.9,'Reifenmontage mit Wuchten ab 18 Zoll')]:add(p,'Reifen montieren und auswuchten',price,scope,'Rad')
add(p,'HU-Vorbereitung',35,'Technischer Vorabcheck')
add(p,'Fehlerspeicher auslesen',35,'Fehlerspeicher auslesen',material='Kein Material erforderlich')
for title in ['Wintercheck','Urlaubscheck']:add(p,title,35,title)
add(p,'Achsvermessung ohne Einstellung',45,'Achsvermessung ohne Einstellung',material='Kein Material erforderlich')
for price,scope in [(79,'Achsvermessung mit Einstellung einer Achse'),(89,'Achsvermessung mit Einstellung beider Achsen')]:add(p,'Achsvermessung mit Einstellung',price,scope,material='Kein Material erforderlich')
add(p,'Achsvermessung mit Einstellung',95,'Transportervermessung, Einstellung unklar',kind='from',vehicle='van',eligible=False,exclusion='Umfang der Einstellung unklar.')
add(p,'Klimaservice R134a',59.9,'Klimaservice, Kältemitteltyp nicht genannt',kind='from',eligible=False,exclusion='Kältemitteltyp unklar.')
add(p,'Klimaanlage desinfizieren',34.99,'Klimaanlage desinfizieren',kind='from',material='Material enthalten')
add(p,'Einspritzanlage reinigen',250,'Benzin-Ansaug-/Kraftstoffsystem reinigen',kind='from',material='Umfang nicht spezifiziert',eligible=False,exclusion='Materialumfang nicht eindeutig.')
add(p,'Steinschlag reparieren',70,'Steinschlag reparieren',kind='from',material='Umfang nicht spezifiziert',eligible=False,exclusion='Materialumfang nicht eindeutig.')
add(p,'HU und AU organisieren',150,'HU und AU ohne zusätzliche Reparaturen',material='Prüfgebühr enthalten')
p=provider('KFZ Meisterbetrieb Südwest','https://www.kfzmeisterbetrieb-suedwest.de/','Berlin-Tempelhof','Berlin')
add(p,'Saisonaler Räderwechsel',60,'Vier Kompletträder wechseln','Satz')
add(p,'Reifen montieren und auswuchten',40,'Reifenummontage mit Wuchten','Rad')
for title,price in [('Klimaservice R134a',100),('Klimaservice R1234yf',150)]:add(p,title,price,title+' ohne Kältemittel',material='Kältemittel zusätzlich')
add(p,'Klimaanlage auf Dichtheit prüfen',200,'Formiergasprüfung',material='Materialumfang nicht spezifiziert',eligible=False,exclusion='Materialumfang nicht eindeutig.')
add(p,'Fehlerspeicher auslesen und löschen',50,'Fehlerspeicher auslesen, löschen und kurz beurteilen',material='Kein Material erforderlich')
for title,price,scope in [('Bremsflüssigkeit wechseln',100,'Bremsflüssigkeit inklusive ATE'),('Bremsbeläge erneuern',150,'Bremsenwechsel pro Achse, genauer Umfang unklar'),('Elektrische Feststellbremse warten',200,'Elektrische Feststellbremse, genauer Umfang unklar'),('Bremstrommeln und Bremsbacken erneuern',300,'Trommelbremse, genauer Umfang unklar'),('Automatikgetriebeöl und Filter wechseln',300,'Getriebeölservice mit Filter und Dichtungen'),('Zahnriemen erneuern',600,'Zahnriemenwechsel mit Rollen'),('Kupplung erneuern',600,'Kupplungswechsel'),('Steuerkette erneuern',800,'Steuerkettenwechsel')]:add(p,title,price,scope,kind='from' if title not in ['Bremsflüssigkeit wechseln','Automatikgetriebeöl und Filter wechseln'] else 'fixed',material='Umfang widersprüchlich / unklar',eligible=False,exclusion='Allgemeiner Materialausschluss widerspricht Detailangebot oder genauer Umfang fehlt.')
p=provider('Autoservice A.P.E.','https://www.autoserviceape.de/','Berlin','Berlin')
add(p,'Saisonaler Räderwechsel',40,'Vier Kompletträder wechseln','Satz')
add(p,'Lose Räder auswuchten',20,'Vier Räder auswuchten, Montageumfang unklar','Satz',eligible=False,exclusion='Fahrzeugmontage nicht eindeutig angegeben.')
add(p,'Öl- und Filterwechsel',50,'Ölwechsel bei Kauf des Öls im Betrieb, Filterumfang unklar',eligible=False,exclusion='Filterumfang unklar; Bedingung Kauf des Öls.')
for title,price in [('Traggelenk erneuern',25),('Querlenker erneuern',60),('Spurstange erneuern',40),('Spurstangenkopf erneuern',25)]:add(p,title,price,title,kind='from',material='Materialumfang nicht spezifiziert',eligible=False,exclusion='Materialumfang und Stückzahl nicht ausdrücklich bestätigt.')
add(p,'Bremsbeläge erneuern',60,'Bremsbeläge vorne wechseln','Achse',material='Materialumfang nicht spezifiziert',eligible=False,exclusion='Materialumfang nicht ausdrücklich bestätigt.')
for price,scope in [(80,'Achsvermessung vorne'),(100,'Achsvermessung vorne und hinten')]:add(p,'Achsvermessung mit Einstellung',price,scope,eligible=False,exclusion='Einstellarbeiten nicht eindeutig enthalten.')
add(p,'Gezielte Fehlerdiagnose',35,'Computerdiagnostik, genauer Umfang unklar',eligible=False,exclusion='Diagnoseumfang unklar.')
add(p,'Fahrwerk prüfen',35,'Fahrwerkdiagnose ohne Reparatur',material='Kein Material erforderlich')
p=provider('B-S-F Fahrzeugservice','https://www.philipgrabow.de/preisliste.php','Rostock','Mecklenburg-Vorpommern')
for title,price,scope,unit in [('Kleine Inspektion',89,'Kleine Inspektion','Leistung'),('Öl- und Filterwechsel',79,'Ölwechsel, Filterumfang unklar','Leistung'),('Saisonaler Räderwechsel',45,'Vier Kompletträder wechseln','Satz'),('Reifen montieren und auswuchten',24.99,'Reifenmontage mit Wuchten und Ventilen','Rad'),('Reifen einlagern',26,'Vier Räder einlagern','Saison'),('RDKS anlernen',10,'Vier RDKS-Sensoren anlernen','Satz'),('Fehlerspeicher auslesen',39,'Fehlerspeicher auslesen','Leistung')]:add(p,title,price,scope,unit,kind='from',material='Ventile und Wuchtmaterial enthalten' if title=='Reifen montieren und auswuchten' else 'Arbeitsleistung, Material zusätzlich',eligible=title!='Öl- und Filterwechsel',exclusion='Ölfilterumfang nicht ausdrücklich bestätigt.' if title=='Öl- und Filterwechsel' else '')
p=provider('Autohaus zur Schwellenburg','https://autohaus-schwellenburg.de/','Gamstädt','Thüringen')
add(p,'HU und AU organisieren',157,'HU und AU ohne zusätzliche Reparaturen',material='Prüfgebühr enthalten')
for price,vc in [(50,'car'),(65,'van')]:add(p,'Saisonaler Räderwechsel',price,'Vier Räder wechseln mit Wuchtkontrolle','Satz',vehicle=vc)
for title,price,scope in [('Klimaservice R134a',90,'Klimaservice R134a mit Dichtprüfung, keine Leeranlage'),('Klimaservice R1234yf',120,'Klimaservice R1234yf mit Dichtprüfung, keine Leeranlage')]:add(p,title,price,scope,material='Kältemittel zusätzlich')
add(p,'Achsvermessung mit Einstellung',120,'Achsvermessung mit digitaler Einstellung',material='Kein Material erforderlich')
add(p,'Fehlerspeicher auslesen und löschen',20,'Fehlerspeicher auslesen und löschen',material='Kein Material erforderlich')
add(p,'Sicherheitscheck',30,'Fahrzeugcheck, genaue Prüfliste unklar',eligible=False,exclusion='Prüfumfang nicht eindeutig angegeben.')
p=provider('Autohaus Goldbach','https://www.goldbach24.de/rostock/werkstattangebote.php','Rostock','Mecklenburg-Vorpommern',segment='Markenbetrieb')
for vc,price in [('car',29),('van',36)]:add(p,'Sicherheitscheck',price,'15-Punkte-Fahrzeugcheck',vehicle=vc,material='Kein Material erforderlich')
add(p,'Achsvermessung ohne Einstellung',120,'Achsvermessung, Einstellumfang unklar',eligible=False,exclusion='Einstellumfang nicht eindeutig angegeben.')
for vc,price in [('car',79),('van',89)]:add(p,'Klimaservice R134a',price,'Klimawartung, Kältemitteltyp unklar',vehicle=vc,material='Kältemittel zusätzlich',eligible=False,exclusion='Kältemitteltyp nicht eindeutig angegeben.')
for vc,price in [('car',69),('van',79)]:add(p,'Klimaanlage desinfizieren',price,'Klimaanlage desinfizieren',vehicle=vc,material='Reinigungsmittel enthalten')
p=provider('KFZ Fahrzeugtechnik Matthias Maj','https://www.kfz-ftm.de/service','Blankenfelde-Mahlow','Brandenburg')
for title,price,scope in [('Kleine Inspektion',90,'Inspektion, genaue Prüfliste unklar'),('Öl- und Filterwechsel',30,'Ölwechsel, Filterumfang unklar'),('Fehlerspeicher auslesen',35,'Fehlerspeicher auslesen'),('HU-Vorbereitung',40,'HU-Vorabcheck'),('Saisonaler Räderwechsel',30,'Vier Kompletträder wechseln'),('Reifen einlagern',30,'Räder einlagern, Saisonumfang unklar'),('Klimaservice R134a',90,'Klimabefüllung, Kältemitteltyp unklar')]:add(p,title,price,scope,'Satz' if title=='Saisonaler Räderwechsel' else 'Leistung',net=True,kind='from' if title in ['Kleine Inspektion','Öl- und Filterwechsel','Klimaservice R134a'] else 'fixed',material='Umfang nicht spezifiziert',eligible=False,exclusion='Materialumfang, Saisonbezug oder genaue Leistungsabgrenzung nicht bestätigt.')
p=provider('Auto Service Fiebig','https://www.auto-service-fiebig.de/leistungen','Berlin-Marzahn','Berlin')
for title,price,scope in [('Urlaubscheck',25,'20-Punkte-Urlaubscheck'),('Kleine Inspektion',145,'Jahresinspektion PKW'),('Zahnriemen erneuern',165,'Zahnriemen erneuern, fahrzeugabhängig'),('Bremsflüssigkeit wechseln',57,'Bremsflüssigkeit wechseln')]:add(p,title,price,scope,net=True,kind='from' if title in ['Kleine Inspektion','Zahnriemen erneuern'] else 'fixed',eligible=title not in ['Kleine Inspektion','Zahnriemen erneuern'],exclusion='Fahrzeug-/Prüfumfang nicht eindeutig.' if title in ['Kleine Inspektion','Zahnriemen erneuern'] else '')
add(p,'Räderwechsel mit Einlagerung',105,'Räder wechseln und sechs Monate einlagern','Saison',net=True,material='Einlagerung enthalten')
p=provider('Autoservice Pilgrim','https://www.autoservice-pilgrim.de/preise-berlin.html','Berlin','Berlin',vat=None,segment='Mobiler Service')
for title,price,scope in [('Saisonaler Räderwechsel',59,'Vier Räder ohne Wuchten'),('Öl- und Filterwechsel',45,'Ölwechsel ohne Öl'),('Öl- und Filterwechsel',109,'Ölwechsel inklusive Öl'),('Flüssigkeitsstände prüfen',29,'Flüssigkeiten prüfen und nachfüllen'),('Fehlerspeicher auslesen',39,'OBD-Fehlerspeicher auslesen'),('Starterbatterie erneuern und anlernen',59,'Batteriewechsel, Anlernen unklar'),('Fahrzeugaußenwäsche',69,'Außenwäsche'),('Innenraum reinigen',69,'Innenreinigung'),('Räder reinigen',29,'Felgenreinigung'),('Lack aufbereiten',149,'Einstufige Politur')]:add(p,title,price,scope,kind='from' if title!='Fehlerspeicher auslesen' else 'fixed')
p=provider('Kfz-Service Hahnl','https://www.kfz-service-hahnl.de/startseite','Erfurt','Thüringen')
for title,price,scope in [('Saisonaler Räderwechsel',24,'Reifenservice, Satz-/Montageumfang unklar'),('DPF ausbauen und reinigen lassen',205,'DPF-Reinigung, Aus-/Einbau unklar'),('Achsvermessung mit Einstellung',95,'Achsvermessung, Einstellumfang unklar')]:add(p,title,price,scope,kind='from',eligible=False,exclusion='Genauer Umfang nicht eindeutig angegeben.')
p=provider('Schmidt Motors','https://schmidt-motors.com/','Berlin','Berlin',segment='US-/Oldtimer-Spezialist')
for title,price,scope in [('Bremsflüssigkeit wechseln',55,'Bremsflüssigkeit wechseln'),('Klimaservice R134a',180,'Klimaservice, Kältemitteltyp unklar'),('Achsvermessung mit Einstellung',95,'Achsvermessung, Einstellumfang unklar'),('Urlaubscheck',35,'Urlaubscheck'),('Saisonaler Räderwechsel',70,'Räderwechsel, Satzumfang unklar'),('Fehlerspeicher auslesen',45,'Fehlerspeicher auslesen')]:add(p,title,price,scope,net=True,material='Materialumfang nicht spezifiziert',eligible=False,exclusion='Spezialist; Leistungs-/Materialumfang nicht eindeutig.')

# Partner pages: prices are supplied and maintained by the named independent businesses.
# Rim/vehicle variants are observations of one operation, never extra catalogue services.
def partner(name,slug,city,region,mounts,extras=(),segment='freie Werkstatt'):
 p=provider(name,'https://www.reifen.com/de-de/storeselect/storedetail/'+slug,city,region,segment=segment)
 for vehicle,rim,price in mounts:
  add(p,'Reifen montieren und auswuchten',price,rim+'; Montage, Wuchten, Ventil, Radaus-/einbau','Rad',material='Ventil enthalten; Reifen zusätzlich',vehicle=vehicle)
 for title,price,scope,unit,material,eligible in extras:
  add(p,title,price,scope,unit,material,eligible=eligible,exclusion='' if eligible else 'Unklarer oder widersprüchlicher Leistungsumfang.')
 return p
wheel=lambda price:('Saisonaler Räderwechsel',price,'Vier Kompletträder wechseln','Satz','Kein Material erforderlich',True)
rad=lambda price:('Saisonaler Räderwechsel',price,'Ein Komplettrad wechseln','Rad','Kein Material erforderlich',True)
bal=lambda price:('Lose Räder auswuchten',price,'Ein loses Rad auswuchten','Rad','Kein Material erforderlich',True)
axis=lambda price:('Achsvermessung ohne Einstellung',price,'Achsvermessung, Einstellumfang unklar','Leistung','Kein Material erforderlich',False)
partner('Hoffmann Automobile','12434_hoffmann-automobile-autopro-mehrmarkenwerkstatt','Dresden','Sachsen',[
 ('car','Alu 13–15 Zoll',20),('car','Alu 16–17 Zoll',25),('car','Alu 18–23 Zoll',28),('car','Stahl 13–17 Zoll',20),('car','SUV Alu 15–18 Zoll',30),('car','SUV Stahl 15–18 Zoll',28),('van','Alu 14–17 Zoll',30),('van','Stahl 14–17 Zoll',28)], [bal(15),axis(150),('Saisonaler Räderwechsel',39,'Satzpreis 39 Euro; zugleich als Radpreis bezeichnet','Satz','Kein Material erforderlich',False)])
partner('Kfz Meisterbetrieb 360° & GA','12277_kfz-meisterbetrieb-360-ga','Berlin','Berlin',[
 ('car','Alu 13–17 Zoll',25),('car','Stahl 13–18 Zoll',25),('van','Alu 14–17 Zoll',30),('van','Stahl 14–17 Zoll',30)],[bal(10),rad(10),axis(150)])
partner('Autopoint Connewitz','12338_autopoint-connewitz','Leipzig','Sachsen',[
 ('car','Alu 14–17 Zoll',28),('car','Stahl 14–17 Zoll',28),('car','SUV Alu 15–18 Zoll',35),('car','SUV Alu 19 Zoll',40),('car','SUV Stahl 15–18 Zoll',35),('car','SUV Stahl 19 Zoll',40),('van','Alu 14–17 Zoll',35),('van','Alu 18–19 Zoll',40),('van','Stahl 14–17 Zoll',35),('van','Stahl 18–19 Zoll',40)],[rad(11.5)])
partner('Reifen Drümecker','12679_reifen-druemecker','Magdeburg','Sachsen-Anhalt',[
 ('car','Alu 13–17 Zoll',28),('car','Alu 18–23 Zoll',30),('car','Stahl 13–18 Zoll',25),('car','SUV Alu 15–18 Zoll',25),('car','SUV Alu 19–30 Zoll',45),('car','SUV Stahl 15–18 Zoll',25),('van','Alu 14–17 Zoll',35),('van','Stahl 14–17 Zoll',30)],[rad(6),axis(80)])
rc=[('Alu 13–15 Zoll',18),('Alu 16 Zoll',19.5),('Alu 17 Zoll',21),('Alu 18 Zoll',23),('Alu 19–20 Zoll',26.5),('Alu 21–22 Zoll',30),('Stahl 13–20 Zoll',15)]
partner('RC Kfz-Service GmbH','12257_rc-kfz-service-gmbh','Berlin','Berlin',[(vc,('SUV ' if suv else '')+rim,price) for vc,suv in [('car',False),('car',True),('van',False)] for rim,price in rc],[bal(10),wheel(45),axis(120)])
partner('Reifen Onimax','15580_reifen-onimax','Berlin','Berlin',[
 ('car','Alu 13–16 Zoll',17.5),('car','Alu 17–18 Zoll',20),('car','Alu 19–20 Zoll',25),('car','Alu 21–22 Zoll',35),('car','Stahl 13–16 Zoll',17.5),('car','Stahl 17–18 Zoll',20),('car','Stahl 19–20 Zoll',25),
 ('car','SUV Alu 13–16 Zoll',25),('car','SUV Alu 17–18 Zoll',30),('car','SUV Alu 19–20 Zoll',35),('car','SUV Alu 21–22 Zoll',42.5),('car','SUV Stahl 13–16 Zoll',25),('car','SUV Stahl 17–18 Zoll',27.5),('car','SUV Stahl 19–20 Zoll',32.5),('car','SUV Stahl 21–22 Zoll',42.5),
 ('van','Alu 15–16 Zoll',25),('van','Alu 17–18 Zoll',30),('van','Alu 19–20 Zoll',35),('van','Stahl 15–16 Zoll',25),('van','Stahl 17–18 Zoll',30),('van','Stahl 19–20 Zoll',35)],[bal(7.5),axis(100),('Saisonaler Räderwechsel',40,'40 Euro je Satz widerspricht 7,50 Euro je Rad','Satz','Kein Material erforderlich',False)])
partner('Autoteile Leukersdorf','12596_autoteile-leukersdorf','Jahnsdorf','Sachsen',[
 ('car','Alu 13–15 Zoll',25),('car','Alu 16–19 Zoll',27),('car','Stahl 13–15 Zoll',20),('car','Stahl 16–19 Zoll',23),('car','SUV Alu 15–19 Zoll',27),('car','SUV Stahl 15–19 Zoll',24),('van','Alu 14–18 Zoll',38),('van','Stahl 14–18 Zoll',35)],[bal(5),wheel(32),axis(90)])
partner('Auto Glas Zentrum Brandenburg','12321_auto-glas-zentrum-brandenburg','Brandenburg an der Havel','Brandenburg',[
 ('car','Alu 13–20 Zoll',24),('car','Stahl 13–20 Zoll',19),('car','SUV Alu 15–20 Zoll',24),('car','SUV Stahl 14–20 Zoll',19),('van','Alu 14–20 Zoll',24),('van','Stahl 14–20 Zoll',19)],[rad(10.9)])
partner('KFZ Meisterbetrieb Sven Amelang','12298_kfz-meisterbetrieb-s-amelang','Potsdam','Brandenburg',[
 ('car','Alu 13–15 Zoll',40),('car','Alu 16–17 Zoll',45),('car','Alu 18–22 Zoll',60),('car','Stahl 13–15 Zoll',40),('car','Stahl 16–17 Zoll',45),('car','Stahl 18–21 Zoll',55)],[rad(10)])
def premium_mounts(steel,alu,suvsteel,suvalu,van):
 return [('car','Stahl '+rim,price) for rim,price in zip(['bis 13 Zoll','14 Zoll','15 Zoll','16 Zoll','17 Zoll','ab 18 Zoll'],steel)]+[('car','Alu '+rim,price) for rim,price in zip(['bis 15 Zoll','16–17 Zoll','18 Zoll','19–20 Zoll','21–22 Zoll','ab 23 Zoll'],alu)]+[('car','SUV Stahl',suvsteel),('car','SUV Alu',suvalu),('van','Alu/Stahl',van)]
p=partner('Krüll Motor Company','15188_kruell-motor-company-gmbh-co-kg','Rostock','Mecklenburg-Vorpommern',premium_mounts([51.2]*6,[51.2]*4+[53.2]*2,51.2,53.2,53.2),[rad(7.5)],segment='Markenbetrieb')
add(p,'Reifen einlagern',54,'Vier Räder pro Saison lagern','Saison',material='Einlagerung enthalten')
add(p,'Räder reinigen',6,'Ein Komplettrad waschen','Rad',material='Waschmittel enthalten',kind='from')
add(p,'RDKS-Sensor erneuern',25,'Sensor programmieren und einbauen','Sensor',material='Sensor zusätzlich')
add(p,'Öl- und Filterwechsel',149,'Öl, Filter, Altölentsorgung und Arbeit',material='Öl und Filter enthalten',kind='from')
p=partner('Autoservice Schröder GmbH','15649_autoservice-schroeder-gmbh','Werder','Brandenburg',premium_mounts([21.42]*5+[29.75],[21.42]*2+[29.75]*4,29.75,29.75,29.75),[rad(10)],segment='Markenbetrieb')
add(p,'Reifen einlagern',45,'Vier Räder pro Saison lagern','Saison',material='Einlagerung enthalten')
add(p,'Räder reinigen',5,'Ein Komplettrad waschen','Rad',material='Waschmittel enthalten',kind='from')
add(p,'RDKS-Sensor erneuern',4,'Sensor programmieren und einbauen','Sensor',material='Sensor zusätzlich')
p=partner('Reifen Müller GmbH & Co. KG','8037_reifen-mueller-filiale-erfurt','Erfurt','Thüringen',premium_mounts([19.64]*3+[22.02]*3,[19.64,22.02,24.4,24.4,26.8,26.8],22.02,22.02,22.02),[rad(8.5)])
add(p,'Reifen einlagern',40,'Vier Räder pro Saison lagern','Saison',material='Einlagerung enthalten')
add(p,'Räder reinigen',3,'Ein Komplettrad waschen','Rad',material='Waschmittel enthalten',kind='from')
add(p,'RDKS-Sensor erneuern',8.75,'Sensor programmieren und einbauen','Sensor',material='Sensor zusätzlich')
add(p,'Öl- und Filterwechsel',49,'Öl, Filter, Altölentsorgung und Arbeit',material='Öl und Filter enthalten',kind='from')
partner('Reifen- und Kfz-Service Kirschbaum','12523_reifen-kfz-service-kirschbaum','Erfurt','Thüringen',[
 ('car','Alu 13–16 Zoll',17),('car','Alu 17–18 Zoll',33),('car','Alu 19 Zoll',35.9),('car','Alu 20–23 Zoll',40.4),('car','Stahl 13–16 Zoll',14.38),('car','Stahl 17–18 Zoll',17.83),('car','Stahl 19 Zoll',20.59),('car','Stahl 20–23 Zoll',23.58),('car','SUV Alu 15–18 Zoll',45.5),('car','SUV Stahl 15–18 Zoll',34),('van','Alu 14–17 Zoll',39),('van','Stahl 14–17 Zoll',34)],[bal(6.44),wheel(40),axis(90)])
p=provider('FSP / Kfz-Prüfstelle Biesdorf','https://www.kfz-pruefstelle-berlin.de/preise/','Berlin','Berlin',segment='Prüforganisation',valid='2026-01-01')
for vc in ['car','van']:add(p,'HU und AU organisieren',165.90,'HU mit UMA, Gesamtgewicht bis 3500 kg','Prüfung',material='Prüfgebühr enthalten',vehicle=vc)
for price,scope in [(17.5,'Nachprüfung nach eigener FSP-HU'),(31,'Nachprüfung nach externer HU')]:add(p,'Reparatur nachprüfen',price,scope,'Prüfung',material='Prüfgebühr enthalten')
p=provider('Drive In Sachsenring','https://www.drivein-sachsenring.de/werkstatt','Hohenstein-Ernstthal','Sachsen',vat=None)
add(p,'Öl- und Filterwechsel',59,'Ölwechsel, Filterumfang unklar',kind='from')
add(p,'Klimaservice R134a',69,'Klimaservice, Kältemitteltyp unklar',kind='from')
add(p,'HU und AU organisieren',168,'HU und AU')
p=provider('ADAC Berlin-Brandenburg','https://www.adac.de/der-adac/regionalclubs/berlin-brandenburg/rund-ums-fahrzeug/kfz-pruefstellen/','Berlin/Brandenburg','Berlin',vat=None,segment='Prüfnetzwerk')
for title,price,scope in [('Lichtanlage prüfen',12.3,'Beleuchtung: Funktion und Einstellung'),('Bremsenprüfstandmessung',12.3,'Bremsenprüfung'),('Stoßdämpfer prüfen',12.3,'Stoßdämpferprüfung'),('Sicherheitscheck',99,'Gebrauchtwagen-Untersuchung'),('Fahrwerk prüfen',45.7,'Fahrwerkscheck'),('Korrosion prüfen',71.7,'Korrosionscheck'),('Urlaubscheck',50.1,'Urlaubscheck ohne Motortest'),('Wintercheck',50.1,'Wintercheck ohne Motortest'),('Bremsflüssigkeit prüfen',6.7,'Bremsflüssigkeitstest'),('Ladesystem prüfen',13.3,'Ladesystemtest'),('Kühlsystem auf Dichtheit prüfen',26.7,'Kühlsystemtest')]:add(p,title,price,scope+'; Nichtmitgliedertarif')
p=provider('ATU','https://autowerkstatt.atu.de/sachsen-anhalt/magdeburg/leipziger-chaussee-127','Magdeburg','Sachsen-Anhalt',vat=None,segment='Werkstattkette')
add(p,'Öl- und Filterwechsel',79.99,'Ölwechsel-Komplettpreis; Filterumfang nicht bestätigt',kind='from')
add(p,'HU und AU organisieren',149.99,'HU und AU; Angebotsgültigkeit nicht bestätigt')
add(p,'LED-Scheinwerferlampen nachrüsten',129.99,'LED-Nachrüstung inklusive Einbau')
p=provider('Motor Gruppe Sachsen','https://www.mgs-sachsen.de/aktion/mgs5-service/','Dresden/Radebeul','Sachsen',vat=None,segment='Markenbetrieb')
add(p,'Kleine Inspektion',249,'MGS5+: privater PKW älter als fünf Jahre, keine SUV/Elektro/Gasfahrzeuge',material='Öl, Filter, Dichtring, Scheibenklar und Lohn enthalten')
# Same central price lists always retain one provider identity. Do not manufacture a missing VAT statement.
for o in observations:
 if o['segment']=='Reifendienst':o['segment']='freie Werkstatt'
 if o['scope']=='Vier Kompletträder wechseln':o['material']='Kein Material erforderlich'
 o['seed_key']=hashlib.sha256(json.dumps({k:v for k,v in o.items() if k!='seed_key'},sort_keys=True).encode()).hexdigest()[:32]
(ROOT/'lib/Data/prices.json').write_text(json.dumps(dict(version='2026-10-05.1',method='Public offers, qualified scope groups; not representative paid-invoice statistics',observations=observations),ensure_ascii=False,indent=2)+'\n')
coverage=[dict(key=s['key'],title=s['title'],observations=sum(o['service_key']==s['key'] for o in observations),status='source_found' if any(o['service_key']==s['key'] for o in observations) else 'no_qualified_public_offer_yet') for s in services]
(ROOT/'lib/Data/price-coverage.json').write_text(json.dumps(dict(checked='2026-10-05',providers=len({o['provider'] for o in observations}),observations=len(observations),targets=dict(providers=30,observations=300),services=coverage),ensure_ascii=False,indent=2)+'\n')
print(len(observations),'observations from',len({o['provider'] for o in observations}),'providers')

# Release und Store-Veröffentlichung

Stand 2026-10-16, früher Prüfkandidat0.5.1-beta.1, öffentliches Quellprojekt;
lokale Demo bleibt auf dem separat geprüften Stand0.5.0. Das Projekt befindet sich in der Veröffentlichungsvorbereitung.
Ein internes Kandidatenpaket ist keine Produktions- oder App-Store-Freigabe.

## Automatische Prüfungen

`Quality and installation` prüft bei Push auf `main`, Pull Requests und manuellem Start:

- PHP 8.3/8.4/8.5: Lockfile-Installation, Plattform, Syntax und Fachtests;
  Coding Standard und Psalm unter PHP 8.3. Psalm 6 wird wegen des bekannten
  Werkzeugfehlers unter PHP 8.5 nicht dort ausgeführt.
- Node 24.18.0/npm 11.16.0: Typecheck, ESLint, Frontendtests, vollständiger npm-Audit und Build.
- Offizielles aktuelles App-Store-XSD, Versionsgleichheit, Paketgrenzen und identische
  Prüfsummen zweier aufeinanderfolgender Paketbauten.
- Installation exakt dieses Artefakts in vier wegwerfbaren Instanzen: Nextcloud
  35.0.0 und 35.0.1 jeweils mit PostgreSQL 17 und MariaDB 11.8. Echte konkurrierende
  Starts, parallele Aufträge, Pause/Übernahme, Abnahme, Rechte, Fahrerzugänge,
  Terminplanung, Auswertung, Kundennachrichten und Datenerhalt nach Neustart.
- Isoliertes HTTPS/SMTP/CalDAV/Cron einschließlich Kalenderänderung/-absage,
  Abholbereit, Fahrzeugfristen, Versandfehlern und kontrollierter Wiederholung.

Die CI nutzt synthetische Daten, gepinnte Actions und Container, interne Docker-Netze
mit einem isolierten HTTPS-Testproxy und abgeschottetem SMTP-Testserver.
Sie versendet keine echten Kundenmails und beweist weder die reale NAS-/Office-/
Mailbox-Integration noch Touch-Bedienung am iPad. Keine produktiven Zugänge oder
Signing-Schlüssel in der CI.
Das geprüfte Kandidatenarchiv wird 14 Tage als internes Actions-Artefakt aufbewahrt.
Es findet keine automatische Store-Veröffentlichung statt.

## Installierbarer Kandidat

Nach grüner Pipeline das Artefakt dieses Commits herunterladen, Prüfsumme kontrollieren
und Installation/Upgrade in der Testkopie ausführen. Die generischen GitHub-Quellarchive
sind nicht das freigegebene Installationspaket. Das interne Archiv bleibt unsigniert.
`python3 scripts/package.py --candidate --schema /pfad/info.xsd` erstellt es lokal.
`python3 scripts/check-package.py /pfad/paket.tar.gz` prüft die Archivgrenzen.

## Öffentliche Freigabe und Zertifikat

Öffentlicher Herausgeber ist **Thomas Keilhaupt**. Das Kontoprofil im App Store
und das `author`-Element der App werden getrennt geprüft. Die für das Konto
verwendete private E-Mail wird nicht in Quellcode oder Release-Unterlagen veröffentlicht.

Der GitHub-Issue-Tracker und das Quellprojekt sind seit der ausdrücklichen Eigentümerfreigabe vom16.10.2026 öffentlich. Alle drei Metadaten-Bildadressen wurden anonym per HTTPS geprüft.

Der private RSA-4096-Schlüssel liegt außerhalb dieses Repositorys. Der öffentliche CSR mit CN `werkstattmanager` wurde als einziger Dateiinhalt in [Zertifikatsantrag#1345](https://github.com/nextcloud/app-certificate-requests/pull/1345) eingereicht und bytegleich zum lokalen CSR bestätigt. Maintainer erhalten keine Schreibrechte auf den persönlichen Antragsbranch. Nextcloud hat das Zertifikat am16.10.2026 ausgestellt. Zertifikat/öffentlicher CSR/privater Schlüssel passen zueinander; Prüfung gegen die mitNC35.0.1 ausgelieferte Vertrauensdatei bestanden.
Es wird weder ein Store-Zertifikat selbst ausgestellt noch eine Signierung vorgetäuscht.

Der Nutzer hat die Veröffentlichung mit beibehaltenen Vergleichspreisen und ohne Nennung der Unternehmen beauftragt. Stabile neutrale Kennungen erhalten die Anbieterunabhängigkeit; Originalquellen sind getrennt intern archiviert. Die zuvor offene Rechteprüfung bleibt dokumentiert und wird durch diese Kennzeichnung nicht als abgeschlossen ausgegeben.

Nach bestandener Abnahme und Zertifikatsausstellung:

1. Release-Dateien vollständig finalisieren und den exakten Stand festhalten.
2. Sauberes App-Staging mit `occ integrity:sign-app --privateKey=... --certificate=... --path=...`
   signieren. Schlüsselpfade nicht in Logs und Schlüssel nie in das Paket aufnehmen.
3. Das signierte Staging reproduzierbar archivieren und die Integrität prüfen.
4. Archivbytes mit RSA/SHA-512 signieren; App-ID separat zur Registrierung signieren.
5. Mit dem Eigentümerkonto die App im Store registrieren, öffentlich erreichbare
   Archiv-URL und Archivsignatur einreichen und Installation dieses Artefakts prüfen.

Zur Registrierung wird ausschließlich die App-ID ohne Zeilenumbruch signiert
(`printf '%s' werkstattmanager`, SHA-512). Für das Release werden die vollständigen
finalen `tar.gz`-Bytes separat signiert. Beides ersetzt nicht die Datei-Signatur in
`appinfo/signature.json`. Das Archiv wird nach dem Signieren nicht neu gebaut.
Download und Bilder müssen auch ohne GitHub- oder Store-Anmeldung erreichbar sein;
ein privates GitHub-Actions-Artefakt erfüllt diese Voraussetzung nicht.

Die erste Store-Testversion ist0.5.1-beta.1 und liegt damit über der installierten Demo0.5.0. Der Store ordnet Vorabkennungen als Beta ein. Nach Änderungen an Version, Metadaten oder Dateien sind das konkrete Endpaket und die Signaturen erneut zu prüfen.

Ein später signiertes Archiv unterscheidet sich vom internen Kandidaten und braucht
eigene Integritäts-, Installations- und Prüfsummenprüfung. Metadatenfreigabe bleibt
auf tatsächlich getestete Nextcloud-Hauptversionen begrenzt.

Offizielle Quellen, erneut geprüft 2026-10-16:
[Nextcloud-Signierung](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html),
[Store-Entwicklerverfahren](https://nextcloudappstore.readthedocs.io/en/latest/developer.html),
[App-Store-Schema](https://apps.nextcloud.com/schema/apps/info.xsd).


## Wiederholbare Store-Vorbereitung 0.5.0

`python3 scripts/store-preflight.py dist/werkstattmanager-0.5.1-beta.1-candidate.tar.gz --output dist/store-preflight.json` prüft offline das konkrete Kandidatenarchiv und schreibt ein SHA-256-Dateiinventar mit Versions-/Herausgeberangaben, Bild-URLs und getrennten Veröffentlichungsvoraussetzungen. Zehn Paket-/Inventartests prüfen auch, dass privates Schlüsselmaterial in erlaubten Textpfaden abgewiesen wird und die bloße Anwesenheit von signature.json keine gültige Signatur behauptet. Der CI-Artefakt enthält das Inventar; weder Werkzeug noch CI veröffentlicht oder signiert automatisch.

Die SQL-Matrix enthält zusätzlich zwei echte Upgrades von Commit06df957 (0.4.1): PostgreSQL/NC35.0.1 und MariaDB/NC35.0.0. Die alte Version wird aus dem gepinnten Quellstand separat gebaut. Vorhandene Auftragsfelder und Versionen müssen erhalten bleiben; die neue Vorplanung ist bei Altaufträgen NULL. Öffentliche Bilder zeigen die gekennzeichnete isolierte UI-Fixture, keine behauptete physische Geräteabnahme.

Der Kontozugang wurde am16.10.2026 im vom Nutzer geöffneten Chrome-Tab bestätigt. Das ausgestellte Zertifikat liegt vor; die Registrierung verwendet dessen App-ID-Signatur. Das sichtbare Releaseformular verlangt öffentliche Archiv-URL und Archivsignatur; Beschreibungen und Bilder werden aus dem Paket gelesen. Der ausdrückliche Hinweis auf eine sehr frühe Testversion steht jetzt am Anfang der deutschen und englischen Metadatenbeschreibung. Öffentliche Sichtbarkeit und CSR-Pull-Request wurden ausdrücklich freigegeben und umgesetzt. Signaturen und die Einreichung des exakten geprüften Releasearchivs werden im aktuellen Veröffentlichungsablauf ausgeführt.

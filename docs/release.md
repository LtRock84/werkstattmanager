# Release und Store-Veröffentlichung

Stand 2026-10-05. Das Projekt befindet sich in der Veröffentlichungsvorbereitung.
Ein internes Kandidatenpaket ist keine Produktions- oder App-Store-Freigabe.

## Automatische Prüfungen

`Quality and installation` prüft bei Push auf `main`, Pull Requests und manuellem Start:

- PHP 8.3/8.4/8.5: Lockfile-Installation, Plattform, Syntax und Fachtests;
  Coding Standard und Psalm unter PHP 8.3. Psalm 6 wird wegen des bekannten
  Werkzeugfehlers unter PHP 8.5 nicht dort ausgeführt.
- Node 24.18.0/npm 11.16.0: Typecheck, ESLint, Frontendtests, Runtime-Audit und Build.
- Offizielles aktuelles App-Store-XSD, Versionsgleichheit, Paketgrenzen und identische
  Prüfsummen zweier aufeinanderfolgender Paketbauten.
- Installation exakt dieses Artefakts in zwei wegwerfbaren Nextcloud-35.0.1-Instanzen
  mit PostgreSQL 17 und MariaDB 11.8: echte konkurrierende Starts, parallele Aufträge,
  Pause/Übernahme, Abnahme, Rechte, Reaktivierung und Datenerhalt nach Neustart.

Die CI nutzt synthetische Daten, gepinnte Actions und Container, interne Docker-Netze
und ausschließlich internes HTTP ohne veröffentlichte Ports. Sie beweist weder HTTPS-Proxyintegration noch
Touch-Bedienung am iPad. Keine produktiven Zugänge oder Signing-Schlüssel in der CI.
Das geprüfte Kandidatenarchiv wird 14 Tage als internes Actions-Artefakt aufbewahrt.
Es findet keine automatische Store-Veröffentlichung statt.

## Installierbarer Kandidat

Nach grüner Pipeline das Artefakt dieses Commits herunterladen, Prüfsumme kontrollieren
und Installation/Upgrade in der Testkopie ausführen. Die generischen GitHub-Quellarchive
sind nicht das freigegebene Installationspaket. Das interne Archiv bleibt unsigniert.
`python3 scripts/package.py --candidate --schema /pfad/info.xsd` erstellt es lokal.
`python3 scripts/check-package.py /pfad/paket.tar.gz` prüft die Archivgrenzen.

## Öffentliche Freigabe und Zertifikat

Die Supportadresse ist der echte GitHub-Issue-Tracker. Solange das Repository privat
ist, ist er nur für berechtigte Mitglieder erreichbar und kein öffentlicher Support.
Vor Veröffentlichung muss der Eigentümer die öffentliche Sichtbarkeit ausdrücklich
freigeben oder eine andere öffentlich erreichbare Supportseite bereitstellen.

Der private RSA-4096-Schlüssel liegt außerhalb dieses Repositorys. Nur der CSR
mit CN `werkstattmanager` darf veröffentlicht werden. Die offizielle Zertifikatsanfrage
verlangt einen öffentlich erreichbaren Quellcode-Nachweis. Deshalb bleibt sie bis zur
Freigabe vorbereitet; das Zertifikat wird anschließend von Nextcloud ausgestellt.
Es wird weder ein Store-Zertifikat selbst ausgestellt noch eine Signierung vorgetäuscht.

Nach bestandener Abnahme und Zertifikatsausstellung:

1. Release-Dateien vollständig finalisieren und den exakten Stand festhalten.
2. Sauberes App-Staging mit `occ integrity:sign-app --privateKey=... --certificate=... --path=...`
   signieren. Schlüsselpfade nicht in Logs und Schlüssel nie in das Paket aufnehmen.
3. Das signierte Staging reproduzierbar archivieren und die Integrität prüfen.
4. Archivbytes mit RSA/SHA-512 signieren; App-ID separat zur Registrierung signieren.
5. Mit dem Eigentümerkonto die App im Store registrieren, öffentlich erreichbare
   Archiv-URL und Archivsignatur einreichen und Installation dieses Artefakts prüfen.

Ein später signiertes Archiv unterscheidet sich vom internen Kandidaten und braucht
eigene Integritäts-, Installations- und Prüfsummenprüfung. Metadatenfreigabe bleibt
auf tatsächlich getestete Nextcloud-Hauptversionen begrenzt.

Offizielle Quellen, geprüft 2026-10-05:
[Nextcloud-Signierung](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html),
[Store-Entwicklerverfahren](https://nextcloudappstore.readthedocs.io/en/latest/developer.html),
[App-Store-Schema](https://apps.nextcloud.com/schema/apps/info.xsd).

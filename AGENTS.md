# Übergabe und laufende Dokumentation

Die übergeordneten Nextcloud-Regeln dieses Arbeitsbereichs gelten weiterhin.

Der Nutzer verlangt ein fortlaufend aktuelles Übernahmeprotokoll. Vor einer
Fortsetzung `docs/deployment/fortsetzungsprotokoll-2026-10-05.md` lesen. Bei jedem
wesentlichen Fortschritt, Fehler, Code-/Build-/Demoänderung und Prüfresultat diese
Datei aktualisieren; aktuelle Zusammenfassung und offene Punkte ebenfalls pflegen.
Erfolgreiche, fehlgeschlagene und nicht ausgeführte Prüfungen klar unterscheiden.

Eigene Änderungen von übernommenen, bereits uncommittierten Änderungen trennen.
Paket- und Demoergebnisse mit den tatsächlich geprüften SHA-256-Werten belegen.
Interne Ergebnisdateien liegen in `docs/deployment/fortsetzung-2026-10-05/`.
Diese lokalen Unterlagen sind ignoriert und werden von einem Git-Klon allein
nicht übernommen; für einen anderen Rechner gesondert übertragen.
Falls das Protokoll bei einem frischen Git-Klon fehlt, zuerst README.md,
docs/product-spec.md und docs/test-report.md lesen und ein neues lokales
Übernahmeprotokoll im ignorierten Deployment-Verzeichnis beginnen. Vorhandene
Prüfergebnisse ihrem tatsächlichen Appstand zuordnen und fehlende interne
Nachweise ausdrücklich als nicht verfügbar kennzeichnen.

Keine Zugangsdaten in Protokolle, Ergebnisdateien oder Git aufnehmen. Offene
Produktions-/Geräteabnahmen nicht aus einem erfolgreichen lokalen Build ableiten.

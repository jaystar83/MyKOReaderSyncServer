# MyKOReaderSyncServer

Ein minimalistischer, selbstgehosteter Synchronisationsserver für den KOReader E-Reader. Das Skript ist komplett in PHP geschrieben und nutzt eine lokale SQLite-Datenbank, weshalb es sich perfekt für kostenlose Hosting-Anbieter wie InfinityFree oder FreeWHA eignet.

Es bietet ein integriertes Web-Dashboard, um deine aktuellen Lesestände bequem im Browser zu verfolgen.

## Features
- Leichtgewichtig und Schnell: Nur eine einzige PHP-Datei benötigt.
- Keine MySQL-Datenbank nötig: Verwendet SQLite (kosync.db), welche automatisch erstellt wird.
- Integriertes Dashboard: Visuelle Übersicht über gelesene Bücher, Seitenzahlen und Fortschrittsbalken.
- Volle KOReader-Kompatibilität: Unterstützt Benutzerregistrierung, Authentifizierung sowie Fortschritts-Push und -Pull.

## Installation und Einrichtung

### 1. Dateien hochladen
1. Lade den Inhalt dieses Repositories herunter.
2. Nenne die Hauptdatei in index.php um (falls nicht bereits geschehen).
3. Verbinde dich per FTP mit deinem Webspace (z. B. InfinityFree) und navigiere in das Verzeichnis /htdocs.
4. Lösche die Standard-Willkommensseite (index2.html).
5. Lade die Dateien index.php und .htaccess direkt in den /htdocs-Ordner hoch.

### 2. URL-Routing aktivieren (.htaccess)
Stelle sicher, dass die .htaccess auf deinem Server aktiv ist, damit alle API-Anfragen an die index.php weitergeleitet werden:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)\$ index.php [QSA,L]
```

## KOReader konfigurieren

1. Öffne den KOReader auf deinem E-Reader.
2. Navigiere im Menü zu Fortschritt-Synchronisation -> Kosync-Server einrichten.
3. Trage deine eigene Server-URL ein, zum Beispiel:
   http://infinityfreeapp.com
4. Erstelle im KOReader ein neues Benutzerkonto. 
5. Fertig! Dein Lesestatus wird ab jetzt automatisch synchronisiert.

## Sicherheitshinweise
- Dashboard-Sichtbarkeit: Das Web-Dashboard ist standardmäßig öffentlich einsehbar. 
- Registrierung: Die Registrierung neuer Benutzer ist offen. Es wird empfohlen, den Registrierungs-Codeblock in der index.php zu deaktivieren, sobald du dein eigenes Konto erstellt hast.
- HTTPS: Nutze nach Möglichkeit ein kostenloses SSL-Zertifikat deines Hosters, um die Datenübertragung zu verschlüsseln.

## Lizenz
Dieses Projekt steht unter der MIT-Lizenz. Siehe die LICENSE-Datei für weitere Details.

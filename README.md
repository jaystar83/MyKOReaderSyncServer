# MyKOReaderSyncServer

Ein minimalistischer, selbstgehosteter Synchronisationsserver für den KOReader E-Reader. Das Skript ist komplett in PHP geschrieben und nutzt eine lokale SQLite-Datenbank, weshalb es sich für kostenlose Hosting-Anbieter wie InfinityFree oder FreeWHA eignet.

Es bietet ein integriertes Web-Dashboard, um die aktuellen Lesestände bequem im Browser zu verfolgen.

## Features
- Leichtgewichtig und schnell: Nur eine einzige PHP-Datei wird benötigt.
- Keine MySQL-Datenbank nötig: Verwendet SQLite (kosync.db), welche automatisch erstellt wird.
- Integriertes Dashboard: Visuelle Übersicht über gelesene Bücher, Seitenzahlen und Fortschrittsbalken.
- Volle KOReader-Kompatibilität: Unterstützt Benutzerregistrierung, Authentifizierung sowie Fortschritts-Push und -Pull.

## Installation und Einrichtung

### 1. Dateien hochladen
1. Lade den Inhalt dieses Repositories herunter.
2. Verbinde dich per FTP mit deinem Webspace (z. B. InfinityFree) und navigiere in das Verzeichnis `/htdocs`.
3. Lösche die Standard-Willkommensseite (z. B. `index2.html`).
4. Lade `index.php` und `.htaccess` direkt in den `/htdocs`-Ordner hoch.

### 2. Konfiguration anpassen
Öffne `index.php` und passe die Konstanten am Dateianfang an:

```php
define('ALLOWED_USER', 'IhrWunschNutzername');
define('DASHBOARD_USER', 'admin');
define('DASHBOARD_PASS', 'IhrGeheimesPasswort');
```

`ALLOWED_USER` ist der einzige Benutzername, der sich über die API registrieren darf. `DASHBOARD_USER` und `DASHBOARD_PASS` schützen das Browser-Dashboard per HTTP Basic Auth.

### 3. URL-Routing und Datenbankschutz aktivieren (.htaccess)
Die `.htaccess` leitet alle API-Anfragen an `index.php` weiter und blockiert den direkten Download der SQLite-Datei sowie ihrer Journal- und WAL-Dateien:

```apache
RewriteEngine On

# HTTPS erzwingen (optional, falls kein SSL verfügbar ist)
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Direktzugriff auf Datenbankdateien blockieren
<FilesMatch "(?i)\.(db|sqlite|sqlite3)(-journal|-wal|-shm)?$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

# Alle nicht existierenden Pfade an index.php weiterleiten
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

Wichtig: Die Datenbank liegt aus Hosting-Gründen im öffentlichen Verzeichnis. Der `FilesMatch`-Block ist daher zwingend erforderlich.

## KOReader konfigurieren

1. Öffne den KOReader auf deinem E-Reader.
2. Navigiere im Menü zu Fortschritt-Synchronisation -> Kosync-Server einrichten.
3. Trage deine eigene Server-URL ein, zum Beispiel `https://deine-domain.example`.
4. Erstelle im KOReader ein neues Benutzerkonto. Der Benutzername muss mit `ALLOWED_USER` übereinstimmen.
5. Fertig. Der Lesestatus wird ab jetzt automatisch synchronisiert.

## Sicherheitshinweise
- Datenbank: `kosync.db` liegt im Web-Root und wird über die `.htaccess` für HTTP-Zugriffe gesperrt. Prüfe nach dem Upload, dass ein direkter Aufruf von `/kosync.db` mit HTTP 403 endet.
- Dashboard: Das Dashboard ist durch HTTP Basic Auth geschützt. Verwende ein starkes Passwort.
- Registrierung: Neue Konten sind auf `ALLOWED_USER` beschränkt.
- Passwörter: KOReader überträgt das Passwort bei der Registrierung im Klartext und authentifiziert sich anschließend mit einem MD5-Hash im Header `X-Auth-Key`. Der Server speichert deshalb nicht das Klartextpasswort, sondern einen mit `password_hash()` gesicherten Hash des MD5-Auth-Keys. MD5 ist protokollbedingt nicht als starker Passwort-Hash geeignet; die Übertragung muss daher zwingend über HTTPS erfolgen.
- HTTPS: Nutze ein SSL-Zertifikat deines Hosters, um die Datenübertragung zu verschlüsseln.

## Lizenz
Dieses Projekt steht unter der MIT-Lizenz. Siehe die `LICENSE`-Datei für weitere Details.

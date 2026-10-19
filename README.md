# MyKOReaderSyncServer

Ein minimalistischer, selbstgehosteter Synchronisationsserver für den KOReader. Die Anwendung besteht aus `index.php`, einer lokalen `config.php` und einer SQLite-Datenbank.

## Hosting-Voraussetzungen

Erforderlich:

- PHP 8.x mit `pdo_sqlite`
- Apache mit `.htaccess` und `mod_rewrite` (oder gleichwertiges Routing)
- HTTPS für den Betrieb

Nicht geeignet sind Hoster, die eine globale JavaScript-/Bot-Sperre wie `aes.js` einsetzen (z. B. InfinityFree). KOReader kann diese Challenge nicht lösen; die API-Anfragen erreichen die Anwendung nie. Ein Hoster mit direktem PHP-Zugriff (z. B. Lima-City Free) oder ein eigener Server ist erforderlich.

## Features

- Kompakt: `index.php` plus lokale `config.php`.
- SQLite statt MySQL; `kosync.db` wird automatisch erstellt.
- Dashboard mit Session-Anmeldung, Fortschrittsbalken und Geräteübersicht.
- KOReader-Kompatibilität: Registrierung, Login sowie Fortschritts-Push und -Pull.

## Installation und Einrichtung

### 1. Konfiguration vorbereiten

1. Lade den Inhalt dieses Repositories herunter.
2. Kopiere `config.example.php` zu `config.php`.
3. Trage in `config.php` den KOReader-Benutzernamen und den Dashboard-Benutzernamen ein.
4. Erzeuge den Hash für das Dashboard-Passwort wie im nächsten Abschnitt beschrieben.
5. Trage die Ausgabe als `dashboard_password_hash` in `config.php` ein.

`config.php` ist über `.gitignore` ausgeschlossen und darf nicht committet oder öffentlich bereitgestellt werden. Für den Betrieb muss die Datei per FTP auf den Server hochgeladen werden.

### 2. Dashboard-Passwort-Hash erzeugen

#### Variante A: PHP lokal installiert

```sh
php -r "echo password_hash('IhrGeheimesPasswort', PASSWORD_DEFAULT), PHP_EOL;"
```

Die Ausgabe beginnt mit `$2y$` und wird vollständig in `dashboard_password_hash` eingetragen.

#### Variante B: Python unter Windows

PowerShell allein enthält keine bcrypt-Implementierung. Python ist der einfachste lokale Weg, wenn kein PHP installiert ist.

1. Python von `https://www.python.org/downloads/` installieren. Beim Setup `Add python.exe to PATH` aktivieren.
2. Das Paket `bcrypt` installieren:

```powershell
py -m pip install --user bcrypt
```

Falls der `py`-Launcher nicht vorhanden ist:

```powershell
python -m pip install --user bcrypt
```

3. Eine temporäre Datei `hash.py` anlegen:

```python
import bcrypt
import getpass

password = getpass.getpass("Dashboard-Passwort: ")
hashed = bcrypt.hashpw(password.encode("utf-8"), bcrypt.gensalt(rounds=12)).decode("ascii")

# PHP password_verify() erwartet bei bcrypt den Präfix $2y$.
print(hashed.replace("$2b$", "$2y$", 1))
```

4. Das Skript ausführen:

```powershell
py hash.py
```

5. Den ausgegebenen Hash in `config.php` als `dashboard_password_hash` eintragen und `hash.py` anschließend löschen.

Hinweis: `bcrypt` erzeugt standardmäßig den Präfix `$2b$`. Der Austausch gegen `$2y$` ist bei bcrypt zulässig und stellt die Kompatibilität mit PHP `password_verify()` sicher.

#### Variante C: Temporäres PHP-Skript auf dem Webspace

Wenn lokal weder PHP noch Python verfügbar ist, kann der Hash einmalig auf dem Webspace erzeugt werden:

```php
<?php
header('Content-Type: text/plain; charset=utf-8');
echo password_hash('IhrGeheimesPasswort', PASSWORD_DEFAULT), PHP_EOL;
```

Die Datei mit einem zufälligen Namen hochladen, einmal im Browser aufrufen, den Hash kopieren und die Datei sofort wieder löschen. Das Klartextpasswort liegt währenddessen auf dem Server. Verwende niemals einen öffentlichen Online-Hash-Generator für ein echtes Passwort.

### 3. Dateien hochladen

1. Verbinde dich per FTP mit deinem Webspace und navigiere in das Webroot-Verzeichnis.
   - Lima-City Free: `default-website`
   - Viele andere Hoster: `/htdocs` oder `public_html`
2. Lade `index.php`, `config.php` und `.htaccess` in dieses Verzeichnis hoch.
3. `config.example.php` wird auf dem Server nicht benötigt.

### 4. Aufbau von config.php

```php
<?php
return [
    'allowed_user' => 'IhrWunschNutzername',
    'dashboard_user' => 'admin',
    'dashboard_password_hash' => '$2y$10$...',
];
```

`allowed_user` ist der einzige Benutzername, der sich über die API registrieren darf. `dashboard_user` und `dashboard_password_hash` schützen das Dashboard per Session-Anmeldung mit Formular. Das Passwort selbst steht nicht in der Datei, sondern nur der bcrypt-Hash.

### 5. URL-Routing und Dateischutz aktivieren (.htaccess)

Die `.htaccess` leitet alle API-Anfragen an `index.php` weiter und blockiert den direkten HTTP-Zugriff auf `config.php`, SQLite-Dateien sowie Backup- und Editor-Dateien:

```apache
RewriteEngine On

# 1. HTTPS erzwingen
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# 2. Direktzugriff auf Konfiguration, Datenbanken und Backup-Dateien blockieren
<FilesMatch "(?i)(^config\.php$|^config\.example\.php$|\.(db|sqlite|sqlite3)(-journal|-wal|-shm)?$|~$|\.(bak|old|orig|save|swp|swo)$)">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

# 3. API-Pfade immer an die Root-index.php leiten
RewriteRule ^(syncs|users)(/|$) index.php [QSA,L]

# 4. Alle nicht existierenden Pfade an index.php weiterleiten
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

Wichtig: Die Datenbank und `config.php` liegen aus Hosting-Gründen im öffentlichen Verzeichnis. Der `FilesMatch`-Block ist daher zwingend erforderlich. Das Blockieren von `config.php` betrifft nur HTTP-Anfragen; das PHP-`require` aus `index.php` funktioniert weiterhin.

## KOReader konfigurieren

1. Öffne den KOReader auf deinem E-Reader.
2. Navigiere im Menü zu Fortschritt-Synchronisation -> Kosync-Server einrichten.
3. Trage unter `Custom sync server` deine Server-URL ein, zum Beispiel:

   ```text
   https://deine-domain.example
   ```

4. Speichere die Einstellung und melde dich ab.
5. Registriere dich mit dem Benutzernamen, der exakt `allowed_user` entspricht.
6. Nach der Registrierung einloggen und den Fortschritt pushen.

Wichtig: Die URL muss exakt mit der Domain übereinstimmen, unter der das Dashboard erreichbar ist. Ein zusätzlicher Pfad wie `/syncs` oder `/kosync` gehört nicht in die Server-URL.

## Sicherheitshinweise

- Konfiguration: `config.php` enthält Zugangsdaten, ist in `.gitignore` eingetragen und wird per `.htaccess` für HTTP gesperrt. Nach dem Anlegen des Kontos sollte `allowed_user` auf einen zufälligen Wert gesetzt werden, um weitere Registrierungen zu verhindern.
- Dashboard: Das Dashboard ist durch eine Session-Anmeldung mit Formular geschützt. Das Passwort wird mit `password_verify()` gegen einen bcrypt-Hash geprüft. Das Session-Cookie ist `HttpOnly`, `Secure` und `SameSite=Lax`; nach dem Login wird die Session-ID erneuert. Ein CSRF-Token schützt das Formular. Verwende ein langes, zufälliges und ausschließlich hier verwendetes Passwort.
- Datenbank: `kosync.db` liegt im Web-Root und wird über die `.htaccess` für HTTP-Zugriffe gesperrt. Prüfe nach dem Upload, dass ein direkter Aufruf von `/kosync.db` mit HTTP 403 endet. Dasselbe gilt für `/config.php`.
- Registrierung: Neue Konten sind auf `allowed_user` beschränkt. Bei einer frischen Installation sollte das Konto zeitnah angelegt werden, damit der bekannte Benutzername nicht von Dritten zuerst registriert wird.
- Passwörter: KOReader sendet bei der Registrierung und beim Login den MD5-Hash des Passworts (`md5(password)`) als Auth-Key; beim Login im Header `X-Auth-Key`. Der Server akzeptiert diesen 32-stelligen Hex-Wert unverändert und speichert ihn nicht im Klartext, sondern als mit `password_hash()` gesicherten Hash. Aus Kompatibilitätsgründen wird bei der Registrierung zusätzlich ein Klartextpasswort akzeptiert und auf den MD5-Auth-Key normalisiert. MD5 ist protokollbedingt nicht als starker Passwort-Hash geeignet; die Übertragung muss daher zwingend über HTTPS erfolgen.
- Altbestände: Zeilen mit rohem MD5-Key oder Klartextpasswort werden beim nächsten erfolgreichen Login automatisch auf `password_hash()` migriert.
- Diagnose: `auth_check.php`, `test_api.py` und `hash.py` sind temporäre lokale Hilfsmittel. Sie dürfen nicht auf den Server hochgeladen oder committet werden. Nach dem Test löschen.
- Brute-Force: Es gibt keine automatische Sperre für fehlerhafte Dashboard- oder API-Logins. Verwende starke Passwörter; bcrypt verlangsamt Angriffe.
- HTTPS: Nutze ein SSL-Zertifikat deines Hosters. Die `.htaccess` erzwingt HTTPS. Session-Cookies und KOReader-Auth-Keys dürfen nur über HTTPS übertragen werden.

## Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Siehe die `LICENSE`-Datei für weitere Details.

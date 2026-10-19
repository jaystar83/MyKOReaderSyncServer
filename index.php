<?php
// =========================================================================
// CONFIGURATION - Wird aus config.php geladen (nicht versionieren!)
// =========================================================================
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
     header("Content-Type: application/json");
     http_response_code(500);
     echo json_encode(["error" => "Konfiguration fehlt"]);
     exit;
}

$config = require $configFile;
if (!is_array($config)) {
     header("Content-Type: application/json");
     http_response_code(500);
     echo json_encode(["error" => "Konfiguration ungültig"]);
     exit;
}

define('ALLOWED_USER', (string)($config['allowed_user'] ?? ''));
define('DASHBOARD_USER', (string)($config['dashboard_user'] ?? ''));
define('DASHBOARD_PASSWORD_HASH', (string)($config['dashboard_password_hash'] ?? ''));

// 1. Datenbank-Initialisierung (SQLite)
try {
     $db = new PDO("sqlite:" . __DIR__ . "/kosync.db");
     $db->exec("PRAGMA busy_timeout = 5000");
     $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
     $db->exec("CREATE TABLE IF NOT EXISTS users (username TEXT PRIMARY KEY, password_hash TEXT)");
     $db->exec("CREATE TABLE IF NOT EXISTS progress (username TEXT, document TEXT, percentage REAL, progress TEXT, timestamp INTEGER, device TEXT, PRIMARY KEY (username, document))");
} catch (Exception $e) {
     header("Content-Type: application/json");
     http_response_code(500);
     echo json_encode(["error" => "Datenbankfehler"]);
     exit;
}

// 2. Robustes Routing für Freehoster
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// FIX: Falls der Hoster den Pfad durch die .htaccess-Umleitung verschluckt
if ((preg_match('/index\.php$/', $requestUri) || $requestUri == '') && isset($_SERVER['REDIRECT_URL'])) {
     $requestUri = $_SERVER['REDIRECT_URL'];
}

// Hilfsfunktion: Passwort in den KOReader-Auth-Key (MD5) normalisieren.
// KOReader sendet bei Registrierung und Login md5(password) als 32-stelligen
// Hex-String. Klartext wird nur als zusätzlicher Kompatibilitätspfad akzeptiert.
// Bereits gehashte 32-stellige Hex-Werte werden unverändert übernommen.
function normalize_auth_key($password) {
     $password = (string)$password;
     if (preg_match('/^[a-f0-9]{32}$/i', $password)) {
          return strtolower($password);
     }
     return md5($password);
}

// Hilfsfunktion: KOReader Authentifizierung prüfen via HTTP-Header
function authenticate($db) {
     $user = $_SERVER['HTTP_X_AUTH_USER'] ?? '';
     $key  = $_SERVER['HTTP_X_AUTH_KEY'] ?? '';

     if ($user === '' || $key === '') return false;

     $key = normalize_auth_key($key);

     $stmt = $db->prepare("SELECT password_hash FROM users WHERE username = ?");
     $stmt->execute([$user]);
     $u = $stmt->fetch(PDO::FETCH_ASSOC);

     if (!$u) return false;

     $stored = (string)$u['password_hash'];
     $valid = false;
     $upgrade = false;

     if (password_verify($key, $stored)) {
          $valid = true;
     } elseif (hash_equals(strtolower($stored), $key)) {
          // Altbestand: ungesicherter MD5-String
          $valid = true;
          $upgrade = true;
     } elseif (hash_equals(md5($stored), $key)) {
          // Altbestand: Klartextpasswort
          $valid = true;
          $upgrade = true;
     }

     if (!$valid) return false;

     // Erfolgreiche Altbestands-Anmeldung direkt auf password_hash() migrieren.
     if ($upgrade) {
          $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
          $stmt->execute([password_hash($key, PASSWORD_DEFAULT), $user]);
     }

     return $user;
}

// 3. DASHBOARD: Session-basierte Anmeldung für den Browser
$isDashboardPath = preg_match('/\/$/', $requestUri)
    || preg_match('/\/index\.php$/', $requestUri)
    || preg_match('/\/kosync\/?$/', $requestUri);

function render_dashboard_login($error = '') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>KoSync Dashboard Login</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
            .login { max-width: 360px; margin: 80px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            h1 { font-size: 1.4em; margin-top: 0; color: #2c3e50; }
            label { display: block; margin: 12px 0 4px; font-weight: 600; }
            input { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1em; }
            button { margin-top: 18px; width: 100%; padding: 10px; border: 0; border-radius: 6px; background: #3498db; color: #fff; font-size: 1em; font-weight: 600; cursor: pointer; }
            .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 6px; margin-bottom: 12px; }
        </style>
    </head>
    <body>
        <div class="login">
            <h1>KoSync Dashboard</h1>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" action="">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf'] ?? ''); ?>">
                <label for="username">Benutzer</label>
                <input id="username" name="username" autocomplete="username" required>
                <label for="password">Passwort</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
                <button type="submit" name="login" value="1">Anmelden</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($isDashboardPath && !isset($_SERVER['HTTP_X_AUTH_USER'])) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (isset($_GET['logout'])) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
        header('Location: /');
        exit;
    }

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    if ($method === 'POST' && isset($_POST['login'])) {
        $loginUser = (string)($_POST['username'] ?? '');
        $loginPass = (string)($_POST['password'] ?? '');
        $loginCsrf = (string)($_POST['csrf'] ?? '');

        if (!hash_equals($_SESSION['csrf'], $loginCsrf)) {
            render_dashboard_login('Sitzung abgelaufen. Bitte erneut versuchen.');
        }

        if (hash_equals(DASHBOARD_USER, $loginUser) && password_verify($loginPass, DASHBOARD_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['dashboard_authenticated'] = true;
            header('Location: ' . $requestUri);
            exit;
        }

        render_dashboard_login('Ungültige Zugangsdaten.');
    }

    if ($method === 'GET' && empty($_SESSION['dashboard_authenticated'])) {
        render_dashboard_login();
    }
}

if ($isDashboardPath && $method === 'GET' && !isset($_SERVER['HTTP_X_AUTH_USER'])) {
     // Hole alle Lesefortschritte aus der Datenbank
     $stmt = $db->query("SELECT * FROM progress ORDER BY timestamp DESC");
     $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
     ?>
     <!DOCTYPE html>
     <html lang="de">
     <head>
         <meta charset="UTF-8">
         <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <title>Sicheres KoSync Dashboard</title>
         <style>
             body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
             .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
             h1 { color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; margin-top: 0; }
             table { width: 100%; border-collapse: collapse; margin-top: 20px; }
             th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
             th { background-color: #f8fafc; color: #64748b; font-weight: 600; }
             tr:hover { background-color: #f8fafc; }
             .badge { background: #3498db; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; }
             .progress-bar-bg { background: #edf2f7; border-radius: 4px; height: 8px; width: 100px; display: inline-block; vertical-align: middle; margin-right: 8px; }
             .progress-bar-fill { background: #2ecc71; height: 100%; border-radius: 4px; }
             .timestamp { color: #94a3b8; font-size: 0.9em; }
         </style>
     </head>
     <body>
         <div class="container">
             <h1>Mein geschütztes KoSync Dashboard</h1>
             <p style="text-align: right; margin-top: -10px;"><a href="?logout=1">Abmelden</a></p>
             <?php if (empty($books)): ?>
                 <p style="color: #64748b;">Noch keine Daten vorhanden. Registriere dein Konto im KOReader!</p>
             <?php else: ?>
                 <table>
                     <thead>
                         <tr>
                             <th>Nutzer</th>
                             <th>Dokument (MD5)</th>
                             <th>Fortschritt</th>
                             <th>Seite</th>
                             <th>Gerät</th>
                             <th>Letzter Sync</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php foreach ($books as $book):
                             $progressData = json_decode($book['progress'], true);
                             $pageInfo = "-";
                             if ($progressData && isset($progressData['page']) && isset($progressData['max_page'])) {
                                 $pageInfo = "S. " . $progressData['page'] . " / " . $progressData['max_page'];
                             }
                             $pct = round($book['percentage'] * 100, 1);
                         ?>
                             <tr>
                                 <td><strong><?php echo htmlspecialchars($book['username']); ?></strong></td>
                                 <td style="font-family: monospace; font-size: 0.85em; color: #475569;"><?php echo htmlspecialchars($book['document']); ?></td>
                                 <td>
                                     <div class="progress-bar-bg"><div class="progress-bar-fill" style="width: <?php echo min($pct, 100); ?>%"></div></div>
                                     <span class="badge"><?php echo $pct; ?>%</span>
                                 </td>
                                 <td><?php echo htmlspecialchars($pageInfo); ?></td>
                                 <td><small><?php echo htmlspecialchars($book['device']); ?></small></td>
                                 <td class="timestamp"><?php echo date("d.m.Y H:i", $book['timestamp']); ?> Uhr</td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             <?php endif; ?>
         </div>
     </body>
     </html>
     <?php
     exit;
}

// 4. API-ROUTEN (Für den KOReader)
header("Content-Type: application/json");

// REGISTRIERUNG: POST /users/create
if (preg_match('/\/users\/create$/', $requestUri) && $method === 'POST') {
     $input = json_decode(file_get_contents('php://input'), true);
     if (!is_array($input) || !isset($input['username'], $input['password'])
         || !is_string($input['username']) || !is_string($input['password'])
         || $input['username'] === '' || $input['password'] === '') {
         http_response_code(400);
         echo json_encode(["error" => "Ungültige Eingabe"]);
         exit;
     }

     // Sicherheitsprüfung auf den erlaubten Namen
     if ($input['username'] !== ALLOWED_USER) {
         http_response_code(403);
         echo json_encode(["error" => "Registrierung verboten."]);
         exit;
     }

     $stmt = $db->prepare("SELECT username FROM users WHERE username = ?");
     $stmt->execute([$input['username']]);
     if ($stmt->fetch()) {
         http_response_code(409);
         echo json_encode(["error" => "Benutzer existiert bereits"]);
         exit;
     }

     // KOReader sendet md5(password); der Wert wird nur als password_hash() gespeichert.
     $authKey = normalize_auth_key($input['password']);
     $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
     $stmt->execute([$input['username'], password_hash($authKey, PASSWORD_DEFAULT)]);

     http_response_code(201);
     echo json_encode(["username" => $input['username']]);
     exit;
}

// AUTH-CHECK: GET /users/auth
if (preg_match('/\/users\/auth$/', $requestUri) && $method === 'GET') {
     $user = authenticate($db);
     if (!$user) {
         http_response_code(401);
         echo json_encode(["error" => "Nicht autorisiert"]);
         exit;
     }
     echo json_encode(["authorized" => true]);
     exit;
}

// PROGRESS PUSH & PULL: /syncs/progress
if (preg_match('/\/syncs\/progress/', $requestUri)) {
     $user = authenticate($db);
     if (!$user) {
         http_response_code(401);
         echo json_encode(["error" => "Nicht autorisiert"]);
         exit;
     }

     // PULL: GET /syncs/progress/:document
     if ($method === 'GET') {
         $parts = explode('/', rtrim($requestUri, '/'));
         $document = end($parts);

         $stmt = $db->prepare("SELECT percentage, progress, timestamp, device FROM progress WHERE username = ? AND document = ?");
         $stmt->execute([$user, $document]);
         $row = $stmt->fetch(PDO::FETCH_ASSOC);

         if ($row) {
             echo json_encode([
                 "document"   => $document,
                 "percentage" => (float)$row['percentage'],
                 "progress"   => $row['progress'],
                 "timestamp"  => (int)$row['timestamp'],
                 "device"     => $row['device']
             ]);
         } else {
             http_response_code(404);
             echo json_encode(["error" => "Kein Fortschritt vorhanden"]);
         }
         exit;
     }

     // PUSH: PUT /syncs/progress
     if ($method === 'PUT') {
         $input = json_decode(file_get_contents('php://input'), true);
         if (!is_array($input) || !isset($input['document'], $input['progress'])
             || !is_string($input['document']) || $input['document'] === '') {
             http_response_code(400);
             echo json_encode(["error" => "Daten unvollständig"]);
             exit;
         }

         $document   = $input['document'];
         $percentage = isset($input['percentage']) ? (float)$input['percentage'] : 0.0;
         $progress   = (string)$input['progress'];
         $timestamp  = isset($input['timestamp']) ? (int)$input['timestamp'] : time();
         $device     = isset($input['device']) ? (string)$input['device'] : 'Unknown';

         // Abwärtskompatibel ohne ON CONFLICT: REPLACE INTO ist als einzelnes
         // Statement atomar; eine explizite Transaktion ist nicht erforderlich.
         try {
             $stmt = $db->prepare("REPLACE INTO progress (username, document, percentage, progress, timestamp, device) VALUES (?, ?, ?, ?, ?, ?)");
             $stmt->execute([$user, $document, $percentage, $progress, $timestamp, $device]);
         } catch (Throwable $e) {
             http_response_code(500);
             echo json_encode(["error" => "Datenbankfehler"]);
             exit;
         }

         echo json_encode(["status" => "success"]);
         exit;
     }
}
// Fallback für nicht gefundene Routen
http_response_code(404);
echo json_encode(["error" => "Route nicht gefunden"]);

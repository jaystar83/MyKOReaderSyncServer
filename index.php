<?php
// =========================================================================
// CONFIGURATION - BITTE HIER ANPASSEN!
// =========================================================================
define('ALLOWED_USER', 'IhrWunschNutzername'); // Der Username für Ihren KOReader
define('DASHBOARD_USER', 'admin');           // Login-Name für das Browser-Dashboard
define('DASHBOARD_PASS', 'IhrGeheimesPasswort'); // Passwort für das Browser-Dashboard

// 1. Datenbank-Initialisierung (SQLite)
try {
     $db = new PDO("sqlite:./kosync.db");
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

// Hilfsfunktion: KOReader Authentifizierung prüfen via HTTP-Header
function authenticate($db) {
     $user = $_SERVER['HTTP_X_AUTH_USER'] ?? '';
     $key  = $_SERVER['HTTP_X_AUTH_KEY'] ?? '';

     if (empty($user) || empty($key)) return false;

     $stmt = $db->prepare("SELECT password_hash FROM users WHERE username = ?");
     $stmt->execute([$user]);
     $u = $stmt->fetch(PDO::FETCH_ASSOC);

     if ($u && hash_equals($u['password_hash'], $key)) {
         return $user;
     }
     return false;
}

// 3. DASHBOARD: Wenn die Haupt-URL im Browser aufgerufen wird
if ((preg_match('/\/$/', $requestUri) || preg_match('/\/index\.php$/', $requestUri) || preg_match('/\/kosync\/?$/', $requestUri)) && $method === 'GET' && !isset($_SERVER['HTTP_X_AUTH_USER'])) {

     // HTTP Basic Auth Passwortschutz für den Browser
     if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== DASHBOARD_USER || $_SERVER['PHP_AUTH_PW'] !== DASHBOARD_PASS) {
         header('WWW-Authenticate: Basic realm="KoSync Dashboard"');
         header('HTTP/1.0 401 Unauthorized');
         echo 'Zugriff verweigert. Bitte korrekte Zugangsdaten eingeben.';
         exit;
     }

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
             <h1>🔒 Mein geschütztes KoSync Dashboard</h1>
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
     if (!isset($input['username']) || !isset($input['password'])) {
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

     $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
     $stmt->execute([$input['username'], $input['password']]);

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
         if (!isset($input['document']) || !isset($input['progress'])) {
             http_response_code(400);
             echo json_encode(["error" => "Daten unvollständig"]);
             exit;
         }

         $timestamp = $input['timestamp'] ?? time();

         $stmt = $db->prepare("INSERT INTO progress (username, document, percentage, progress, timestamp, device)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT(username, document) DO UPDATE SET
             percentage=excluded.percentage, progress=excluded.progress, timestamp=excluded.timestamp, device=excluded.device");


        $stmt->execute([
        $user,
        $input['document'],
        $input['percentage'] ?? 0,
        $input['progress'],
        $timestamp,
        $input['device'] ?? 'Unknown'
        ]);
        echo json_encode(["status" => "success"]);
        exit;
    }
}
// Fallback für nicht gefundene Routen
http_response_code(404);
echo json_encode(["error" => "Route nicht gefunden"]);

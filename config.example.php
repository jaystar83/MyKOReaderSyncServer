<?php
// Vorlage für config.php. Diese Datei enthält keine echten Geheimnisse.
//
// 1. Kopie als config.php anlegen.
// 2. Werte eintragen.
// 3. Dashboard-Hash erzeugen und in dashboard_password_hash eintragen:
//    php -r "echo password_hash('IhrGeheimesPasswort', PASSWORD_DEFAULT), PHP_EOL;"
//
// config.php niemals committen und nicht in ein öffentliches Repository hochladen.

return [
    // Einziger Benutzername, der sich über die KOReader-API registrieren darf.
    'allowed_user' => 'IhrWunschNutzername',

    // Login für das Browser-Dashboard (Session-Anmeldung mit Formular).
    'dashboard_user' => 'admin',

    // Mit password_hash() erzeugter bcrypt-Hash des Dashboard-Passworts.
    'dashboard_password_hash' => '',
];

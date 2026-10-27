<?php
// config/db.php - Connessione centralizzata al database MySQL tramite PDO
require_once __DIR__ . '/../dati_generali.php';

// Estrazione flessibile delle credenziali da dati_generali.php
$host = $db_host ?? 'localhost';
$db   = $db_name ?? 'palestra648';
$user = $db_user ??  'root';
$pass = $db_pass ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (\PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage() .
        "<br><small>Verifica i parametri nel file <code>dati_generali.php</code> ed esegui prima lo script <code>install.php</code>.</small>");
}

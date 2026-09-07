<?php

$host = '127.0.0.1';
$db   = 'palestra648';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Solleva eccezioni in caso di errore
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Restituisce array associativi
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa vere query preparate
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

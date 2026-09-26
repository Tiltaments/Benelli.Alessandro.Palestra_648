<?php
// logout.php - Pagina di Logout per la Palestra 648 con gestione della sessione e reindirizzamento al login
require_once __DIR__ . '/includes/auth.php';

// 1. Svuotiamo l'array di sessione: rimuove tutti i dati (es. user_id, ruolo)
//    senza ancora distruggere la sessione lato server
$_SESSION = [];

// 2. Se il browser usa i cookie di sessione, lo invalidiamo subito
//    impostando una data di scadenza nel passato (-42000 secondi = già scaduto)
//    In questo modo il browser eliminerà il cookie alla risposta
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), // Nome del cookie di sessione (es. PHPSESSID)
        '',             // Valore vuoto
        time() - 42000, // Data nel passato → il browser lo cancella
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Distruggiamo definitivamente la sessione lato server
session_destroy();

header("Location: login.php");
exit();

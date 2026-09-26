<?php
// includes/auth.php - Gestione dell'autenticazione e dei ruoli utente
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restituisce true se l'utente è autenticato
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Restituisce il ruolo corrente: 'visitatore', 'cliente', 'gestore', 'admin'
function getRuolo()
{
    return $_SESSION['ruolo'] ?? 'visitatore';
}

// Blocca l'accesso se l'utente non ha uno dei ruoli consentiti
function richiediRuolo($ruoliConsentiti = [])
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    if (!in_array(getRuolo(), $ruoliConsentiti)) {
        header("Location: index.php?errore=accesso_negato");
        exit();
    }
}

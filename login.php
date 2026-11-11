<?php
// login.php - Pagina di Login per la Palestra 648 con gestione sessione e autenticazione
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$errore = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errore = "Inserisci sia username che password.";
    } else {
        // Query preparata con parametro "?" per prevenire SQL Injection:
        // il valore di $username non viene mai concatenato direttamente nella query
        $stmt = $pdo->prepare("SELECT id, username, password, nome, cognome, ruolo, stato FROM utenti WHERE username = ?");
        $stmt->execute([$username]);
        $utente = $stmt->fetch(); // Restituisce un array associativo o false se non trovato

        // password_verify() confronta la password in chiaro inserita dall'utente
        // con l'hash BCrypt salvato nel database (non si confrontano mai le password in chiaro)
        if ($utente && password_verify($password, $utente['password'])) {
            if ($utente['stato'] === 'bannato') {
                $errore = "Il tuo account è stato sospeso dall'amministratore.";
            } else {
                // Salviamo i dati dell'utente in sessione: da questo momento è "loggato"
                // Questi valori sono accessibili in tutte le pagine tramite $_SESSION
                $_SESSION['user_id'] = $utente['id'];
                $_SESSION['username'] = $utente['username'];
                $_SESSION['nome_completo'] = $utente['nome'] . ' ' . $utente['cognome'];
                $_SESSION['ruolo'] = $utente['ruolo']; // es. 'cliente', 'gestore', 'admin'

                header("Location: index.php");
                exit();
            }
        } else {
            // Messaggio generico: non specifichiamo se è lo username o la password
            // ad essere errata (per non dare informazioni utili a un intruso)
            $errore = "Credenziali non valide.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Palestra 648 - Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            padding: 40px;
        }

        .form-container {
            max-width: 380px;
            margin: auto;
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .btn {
            width: 100%;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn:hover {
            background: #0056b3;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <div class="form-container">
        <h1>Accesso Palestra 648</h1>
        <?php if ($errore): ?><div class="alert-danger"><?= htmlspecialchars($errore) ?></div><?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="btn">Accedi</button>
        </form>
        <p style="text-align: center; margin-top: 15px;">Non hai un account? <a href="register.php">Registrati</a></p>
    </div>
</body>

</html>
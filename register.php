<?php
// register.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$errore = '';
$successo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $codice_fiscale = strtoupper(trim($_POST['codice_fiscale'] ?? ''));
    $telefono = trim($_POST['telefono'] ?? '');
    $indirizzo = trim($_POST['indirizzo'] ?? '');

    if (empty($username) || empty($password) || empty($nome) || empty($cognome) || empty($email) || empty($codice_fiscale)) {
        $errore = "Tutti i campi contrassegnati da * sono obbligatori.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errore = "Inserisci un indirizzo email valido.";
    } elseif (strlen($codice_fiscale) !== 16) {
        $errore = "Il codice fiscale deve essere di 16 caratteri.";
    } else {
        // Verifica unicità di username ed email
        $stmt = $pdo->prepare("SELECT id FROM utenti WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errore = "Username o Email già registrati nel sistema.";
        } else {
            // Hash sicuro della password
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $insert = $pdo->prepare("INSERT INTO utenti (username, password, nome, cognome, email, codice_fiscale, telefono, indirizzo, ruolo, stato) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cliente', 'attivo')");
            $insert->execute([$username, $passwordHash, $nome, $cognome, $email, $codice_fiscale, $telefono, $indirizzo]);

            // Inizializzazione automatica del conto crediti nel file transazioni.xml
            $nuovoId = $pdo->lastInsertId();
            inizializzaContoXML($nuovoId);

            $successo = "Registrazione completata con successo! Ora puoi effettuare il login.";
        }
    }
}

// Funzione ausiliaria per creare il nodo conto in transazioni.xml
function inizializzaContoXML($userId)
{
    $xmlPath = __DIR__ . '/data/transazioni.xml';
    if (file_exists($xmlPath)) {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->load($xmlPath);

        $xpath = new DOMXPath($dom);
        $contoEsistente = $xpath->query("//conto[@id_cliente='$userId']");

        if ($contoEsistente->length === 0) {
            $contiClienti = $dom->getElementsByTagName('conti_clienti')->item(0);
            if ($contiClienti) {
                $conto = $dom->createElement('conto');
                $conto->setAttribute('id_cliente', $userId);

                $saldo = $dom->createElement('saldo_crediti', '0');
                $speso = $dom->createElement('totale_speso', '0.00');

                $conto->appendChild($saldo);
                $conto->appendChild($speso);
                $contiClienti->appendChild($conto);

                $dom->save($xmlPath);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Palestra 648 - Registrazione</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            padding: 20px;
        }

        .form-container {
            max-width: 450px;
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
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .btn {
            width: 100%;
            background: #28a745;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn:hover {
            background: #218838;
        }

        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>

<body>
    <div class="form-container">
        <h1>Registrazione Cliente</h1>
        <?php if ($errore): ?><div class="alert alert-danger"><?= htmlspecialchars($errore) ?></div><?php endif; ?>
        <?php if ($successo): ?><div class="alert alert-success"><?= htmlspecialchars($successo) ?> <a href="login.php">Accedi qui</a></div><?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group"><label>Nome *</label><input type="text" name="nome" required></div>
            <div class="form-group"><label>Cognome *</label><input type="text" name="cognome" required></div>
            <div class="form-group"><label>Codice Fiscale *</label><input type="text" name="codice_fiscale" maxlength="16" required></div>
            <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Telefono</label><input type="text" name="telefono"></div>
            <div class="form-group"><label>Indirizzo di Fatturazione</label><input type="text" name="indirizzo"></div>
            <div class="form-group"><label>Username *</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password *</label><input type="password" name="password" required></div>
            <button type="submit" class="btn">Crea Account</button>
        </form>
        <p style="text-align: center; margin-top: 15px;">Hai già un account? <a href="login.php">Accedi</a></p>
    </div>
</body>

</html>
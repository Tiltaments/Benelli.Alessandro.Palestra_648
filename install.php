<?php
// install.php - Script di installazione e popolamento iniziale del database

// =========================================================================
// CONFIGURAZIONE DATABASE (Modificare questi parametri se necessario)
// =========================================================================
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'palestra648';
// =========================================================================

$messaggi = [];

try {
    // 1. Connessione iniziale senza specificare il DB (per crearlo se non esiste)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Creazione del Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $messaggi[] = "Database '$dbname' verificato/creato con successo.";

    // 3. Connessione al nuovo Database
    $pdo->exec("USE `$dbname`");

    // 4. Creazione della Tabella 'utenti'
    $queryTabella = "
        CREATE TABLE IF NOT EXISTS `utenti` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `nome` VARCHAR(50) NOT NULL,
            `cognome` VARCHAR(50) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `codice_fiscale` VARCHAR(16) NOT NULL,
            `telefono` VARCHAR(20) DEFAULT NULL,
            `indirizzo` VARCHAR(150) DEFAULT NULL,
            `ruolo` ENUM('cliente', 'gestore', 'admin') NOT NULL DEFAULT 'cliente',
            `stato` ENUM('attivo', 'bannato') NOT NULL DEFAULT 'attivo',
            `data_registrazione` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($queryTabella);
    $messaggi[] = "Tabella 'utenti' creata con successo.";

    // 5. Svuotamento della tabella (per evitare duplicati se lo script viene lanciato più volte)
    $pdo->exec("TRUNCATE TABLE `utenti`");

    // 6. Popolamento iniziale con utenti di test (Password per tutti: Password123!)
    $passwordInChiaro = 'Password123!';
    $hashPassword = password_hash($passwordInChiaro, PASSWORD_BCRYPT);

    $queryInsert = "INSERT INTO `utenti` (`username`, `password`, `nome`, `cognome`, `email`, `codice_fiscale`, `telefono`, `indirizzo`, `ruolo`, `stato`) VALUES 
        ('admin', :pass_admin, 'Mario', 'Rossi', 'admin@palestra648.it', 'RSSMRA80A01H501U', '3331122334', 'Via Palestro 1, Roma', 'admin', 'attivo'),
        ('gestore', :pass_gest, 'Luigi', 'Verdi', 'gestore@palestra648.it', 'VRDLGU85B02H501O', '3335566778', 'Corso Italia 45, Roma', 'gestore', 'attivo'),
        ('cliente1', :pass_cliente, 'Alessandro', 'Benelli', 'cliente@palestra648.it', 'BNLLSN99C15H501Z', '3339988776', 'Via Appia 12, Priverno', 'cliente', 'attivo')
    ";

    $stmt = $pdo->prepare($queryInsert);
    $stmt->execute([
        ':pass_admin' => $hashPassword,
        ':pass_gest' => $hashPassword,
        ':pass_cliente' => $hashPassword
    ]);
    $messaggi[] = "Account di test inseriti correttamente.";
    $successo = true;
} catch (PDOException $e) {
    $messaggi[] = "ERRORE CRITICO: " . $e->getMessage();
    $successo = false;
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <title>Installazione Palestra 648</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            padding: 40px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .log {
            background: #1e1e1e;
            color: #00ff00;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            margin: 20px 0;
        }

        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }

        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>⚙️ Setup Sistema: Palestra 648</h1>
        <p>Inizializzazione del database e popolamento dei dati di test.</p>

        <div class="log">
            <?php foreach ($messaggi as $msg): ?>
                &gt; <?= htmlspecialchars($msg) ?><br>
            <?php endforeach; ?>
        </div>

        <?php if ($successo): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong>✅ Installazione completata con successo!</strong><br><br>
                Utenze create (Password per tutti: <code>Password123!</code>):
                <ul style="margin-top: 10px;">
                    <li><strong>Admin:</strong> username <code>admin</code></li>
                    <li><strong>Gestore:</strong> username <code>gestore</code></li>
                    <li><strong>Cliente:</strong> username <code>cliente1</code></li>
                </ul>
            </div>
            <a href="index.php" class="btn">Vai alla Homepage &rarr;</a>
        <?php else: ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;">
                <strong>❌ Installazione fallita.</strong> Controlla le credenziali all'interno del file <code>install.php</code>.
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
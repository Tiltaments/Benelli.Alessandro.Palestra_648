<?php
// install.php - Script di installazione e popolamento iniziale del database

require_once __DIR__ . '/dati_generali.php';
$dirInit = __DIR__ . '/data-init';
$dirData = __DIR__ . '/data';

// Estrazione parametri da dati_generali.php
$host     = $db_host ?? $host ?? 'localhost';
$username = $db_user ?? $user ?? 'root';
$password = $db_pass ?? $password ?? '';
$dbname   = $db_name ?? $dbname ?? 'palestra648';

$messaggi = [];
$successo = true;
$forzaResetXml = isset($_GET['reset']) && $_GET['reset'] === '1';


// 1. INIZIALIZZAZIONE DATABASE MYSQL
try {
    $pdo = null;
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("USE `$dbname`");
        $messaggi[] = "Connessione a MySQL stabilita. Database '$dbname' verificato/creato.";
    } catch (PDOException $eDb) {
        // Fallback connessione diretta
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $messaggi[] = "Connessione diretta al database '$dbname' stabilita.";
    }

    // Creazione della Tabella 'utenti'
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
    $messaggi[] = "Tabella 'utenti' verificata/creata con successo.";

    // Svuotamento e popolamento se non ci sono utenti o se richiesto reset
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM `utenti`");
    $numUtenti = (int)$stmtCount->fetchColumn();

    if ($numUtenti === 0 || $forzaResetXml) {
        $pdo->exec("TRUNCATE TABLE `utenti`");
        $passwordComune = 'Password123!';
        $hashPassword = password_hash($passwordComune, PASSWORD_BCRYPT);

        // Popolamento congruo e differenziato:
        // - admin: Admin Supremo
        // - admin2: Admin ordinario (per testare la gerarchia ruoli)
        // - gestore: Gestore principale
        // - gestore2: Secondo gestore
        // - cliente1: Cliente attivo con acquisti recenti
        // - cliente2: Cliente storico (data_registrazione antecedente di 6 mesi per testare lo sconto anzianità)
        // - cliente3: Cliente attivo nella community
        // - cliente4: Cliente con stato='bannato' per verificare il blocco dell'accesso
        $queryInsert = "INSERT INTO `utenti` 
            (`id`, `username`, `password`, `nome`, `cognome`, `email`, `codice_fiscale`, `telefono`, `indirizzo`, `ruolo`, `stato`, `data_registrazione`) 
            VALUES 
            (1, 'admin', :p, 'Mario', 'Rossi', 'admin@palestra648.it', 'RSSMRA80A01H501U', '3331122334', 'Via Palestro 1, Roma', 'admin', 'attivo', NOW()),
            (2, 'admin2', :p, 'Paolo', 'Neri', 'admin2@palestra648.it', 'NREPLA82B02H501P', '3332233445', 'Via Veneto 10, Roma', 'admin', 'attivo', NOW()),
            (3, 'gestore', :p, 'Luigi', 'Verdi', 'gestore@palestra648.it', 'VRDLGU85B02H501O', '3335566778', 'Corso Italia 45, Roma', 'gestore', 'attivo', NOW()),
            (4, 'gestore2', :p, 'Anna', 'Bianchi', 'gestore2@palestra648.it', 'BNCNNA88C43H501T', '3336677889', 'Via Po 8, Roma', 'gestore', 'attivo', NOW()),
            (5, 'cliente1', :p, 'Alessandro', 'Benelli', 'cliente@palestra648.it', 'BNLLSN02T04D101Z', '3339988776', 'Via Appia 12, Priverno', 'cliente', 'attivo', NOW()),
            (6, 'cliente2', :p, 'Giulia', 'De Santis', 'giulia@palestra648.it', 'DSNGLI95D55H501K', '3334455667', 'Via Cavour 22, Latina', 'cliente', 'attivo', DATE_SUB(NOW(), INTERVAL 6 MONTH)),
            (7, 'cliente3', :p, 'Marco', 'Ferri', 'marco@palestra648.it', 'FRRMRC98E12H501Y', '3338899001', 'Via Garibaldi 3, Latina', 'cliente', 'attivo', NOW()),
            (8, 'cliente4', :p, 'Roberto', 'Gialli', 'roberto@palestra648.it', 'BLCRRT90F14H501W', '3330011223', 'Via Po 99, Roma', 'cliente', 'bannato', NOW())
        ";

        $stmt = $pdo->prepare($queryInsert);
        $stmt->execute([':p' => $hashPassword]);
        $messaggi[] = "Popolamento congruo eseguito: 8 account creati (Password unica per tutti: $passwordComune).";
    } else {
        $messaggi[] = "Tabella 'utenti' già popolata ($numUtenti account presenti). Dati preservati.";
    }
} catch (PDOException $e) {
    $messaggi[] = "ERRORE DATABASE: " . $e->getMessage();
    $successo = false;
}


// 2. GESTIONE ARCHIVIO XML E DTD (PRESERVAZIONE O COPIA DA data-init/)
if (!is_dir($dirInit)) {
    $messaggi[] = "ERRORE: Cartella sorgente template 'data-init/' non trovata.";
    $successo = false;
} else {
    if (!is_dir($dirData)) {
        mkdir($dirData, 0777, true);
        $messaggi[] = "Cartella di runtime 'data/' creata.";
    }

    $elementi = scandir($dirInit);
    $copiati = 0;
    $preservati = 0;

    foreach ($elementi as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $sorgente     = $dirInit . '/' . $file;
        $destinazione = $dirData . '/' . $file;

        if (is_file($sorgente)) {
            if ($forzaResetXml || !file_exists($destinazione)) {
                if (copy($sorgente, $destinazione)) {
                    $copiati++;
                }
            } else {
                $preservati++;
            }
        }
    }

    if ($forzaResetXml) {
        $messaggi[] = "RESET FORZATO: $copiati file ripristinati da 'data-init/' a 'data/'.";
    } elseif ($copiati > 0) {
        $messaggi[] = "Inizializzazione completata: $copiati file copiati da 'data-init/' ($preservati già presenti).";
    } else {
        $messaggi[] = "Archivio 'data/' già esistente ($preservati file presenti). Dati preservati intatti.";
    }

    // Validazione formale DOM dei file XML attivi in data/
    $xmlFiles = ['catalogo.xml', 'community.xml', 'faq.xml', 'promozioni.xml', 'transazioni.xml'];
    $tuttiValidi = true;

    foreach ($xmlFiles as $xmlFile) {
        $percorsoXml = $dirData . '/' . $xmlFile;
        if (file_exists($percorsoXml)) {
            $dom = new DOMDocument();
            if (@$dom->load($percorsoXml)) {
                if (@$dom->validate()) {
                    $messaggi[] = "Validazione XML: '$xmlFile' conforme alla relativa DTD [OK].";
                } else {
                    $messaggi[] = "ERRORE VALIDAZIONE: '$xmlFile' non rispetta la relativa DTD.";
                    $tuttiValidi = false;
                }
            } else {
                $messaggi[] = "ERRORE SINTASSI: Impossibile caricare '$xmlFile'.";
                $tuttiValidi = false;
            }
        } else {
            $messaggi[] = "ERRORE: File '$xmlFile' mancante in 'data/'.";
            $tuttiValidi = false;
        }
    }

    if (!$tuttiValidi) {
        $successo = false;
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Sistema - <?= htmlspecialchars($nome_applicazione ?? 'Palestra 648') ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            padding: 30px 15px;
            margin: 0;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        }

        h1 {
            color: #1a1a2e;
            border-bottom: 3px solid #e94560;
            padding-bottom: 10px;
            font-size: 24px;
            margin-top: 0;
        }

        .subtitle {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .log {
            background: #1e1e2f;
            color: #4ade80;
            padding: 16px;
            border-radius: 6px;
            font-family: Consolas, "Courier New", monospace;
            font-size: 13px;
            line-height: 1.6;
            margin: 20px 0;
            max-height: 250px;
            overflow-y: auto;
        }

        .log-error {
            color: #f87171;
            font-weight: bold;
        }

        .alert-box {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            background: #fff;
            border-radius: 4px;
            overflow: hidden;
            font-size: 13px;
        }

        .user-table th,
        .user-table td {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            text-align: left;
        }

        .user-table th {
            background: #f3f4f6;
            color: #374151;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-admin {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-gestore {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-cliente {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-bannato {
            background: #4b5563;
            color: #ffffff;
        }

        .btn {
            display: inline-block;
            background: #e94560;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .btn:hover {
            background: #c82333;
        }

        .btn-reset {
            background: #6c757d;
            font-size: 12px;
            padding: 6px 12px;
            margin-left: 10px;
        }

        .btn-reset:hover {
            background: #495057;
        }

        code {
            background: rgba(0, 0, 0, 0.06);
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Installazione &amp; Setup Sistema</h1>
        <p class="subtitle">Applicazione: <strong><?= htmlspecialchars($nome_applicazione ?? 'Palestra 648') ?></strong> &bull; Gestione Database e Archivio Dati XML</p>

        <div class="log">
            <?php foreach ($messaggi as $msg): ?>
                <?php if (strpos($msg, 'ERRORE') !== false): ?>
                    <span class="log-error">&gt; <?= htmlspecialchars($msg) ?></span><br>
                <?php else: ?>
                    &gt; <?= htmlspecialchars($msg) ?><br>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($successo): ?>
            <div class="alert-box alert-success">
                <strong>Sistema operativo e verificato!</strong><br>
                Tutti i file XML di <code>data/</code> sono conformi alle rispettive DTD.<br><br>
                <strong>Credenziali di accesso predefinite</strong> (Password unica per tutti: <code>Password123!</code>):
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ruolo</th>
                            <th>Username</th>
                            <th>Nome &amp; Cognome</th>
                            <th>Caratteristica di Collaudo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><span class="badge badge-admin">Admin</span></td>
                            <td><code>admin</code></td>
                            <td>Mario Rossi</td>
                            <td><strong>Admin Supremo</strong> intoccabile</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td><span class="badge badge-admin">Admin</span></td>
                            <td><code>admin2</code></td>
                            <td>Paolo Neri</td>
                            <td>Admin ordinario (modificabile solo dal Supremo)</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td><span class="badge badge-gestore">Gestore</span></td>
                            <td><code>gestore</code></td>
                            <td>Luigi Verdi</td>
                            <td>Gestore corsi, listini e sconti</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><span class="badge badge-gestore">Gestore</span></td>
                            <td><code>gestore2</code></td>
                            <td>Anna Bianchi</td>
                            <td>Secondo gestore di turno</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td><span class="badge badge-cliente">Cliente</span></td>
                            <td><code>cliente1</code></td>
                            <td>Alessandro Benelli</td>
                            <td>Cliente con conto crediti e acquisti attivi</td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td><span class="badge badge-cliente">Cliente</span></td>
                            <td><code>cliente2</code></td>
                            <td>Giulia De Santis</td>
                            <td>Cliente storico (iscritto da 6 mesi &rarr; sconto anzianit&agrave;)</td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td><span class="badge badge-cliente">Cliente</span></td>
                            <td><code>cliente3</code></td>
                            <td>Marco Ferri</td>
                            <td>Cliente con ricarica in attesa di approvazione</td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td><span class="badge badge-cliente">Cliente</span></td>
                            <td><code>cliente4</code></td>
                            <td>Roberto Gialli</td>
                            <td>Account sospeso (accesso respinto al login)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="text-align: center; margin-top: 25px;">
                <a href="index.php" class="btn">Vai alla Homepage &rarr;</a>
                <a href="install.php?reset=1" class="btn btn-reset" onclick="return confirm('Sei sicuro? Questo ripristinerà tutti i file XML di data/ e ricreerà gli utenti di test da data-init/.');">Ripristina Dati Iniziali (Reset)</a>
            </p>
        <?php else: ?>
            <div class="alert-box alert-danger">
                <strong>Installazione non riuscita o incompleta.</strong><br>
                Verifica i parametri di configurazione MySQL all'interno del file <code>dati_generali.php</code> (host, utente, password, nome database).
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
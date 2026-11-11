<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

richiediRuolo(['admin']);

$xmlPath = __DIR__ . '/data/transazioni.xml';
$messaggioSuccesso = '';
$messaggioErrore = '';

// 1. GESTIONE DELLE AZIONI (APPROVA/RESPINGI)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_richiesta'], $_POST['azione'])) {
    // Sanitizza l'ID richiesta per evitare problemi di sicurezza
    $idReq = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_POST['id_richiesta']);
    // Determina l'azione da eseguire (approva o respingi)
    $azione = $_POST['azione'] === 'respinta' ? 'respinta' : ($_POST['azione'] === 'approvata' ? 'approvata' : null);

    // Procedi solo se l'azione è valida e l'ID richiesta non è vuoto
    if ($azione && !empty($idReq)) {
        // Carica il file XML delle transazioni
        $xml = simplexml_load_file($xmlPath);

        // Se il file XML è stato caricato correttamente
        if ($xml !== false) {
            // Trova la richiesta specifica nell'XML
            $risultato = $xml->xpath("//richiesta[@id_richiesta='$idReq']");

            // Se la richiesta esiste, procedi con l'azione
            if (!empty($risultato)) {
                $richiesta = $risultato[0];

                // Procedi solo se è ancora in attesa
                if ((string)$richiesta['stato'] === 'in_attesa') {
                    $richiesta['stato'] = $azione;
                    if (!isset($richiesta->data_gestione)) {
                        $richiesta->addChild('data_gestione', date('Y-m-d\TH:i:s'));
                    } else {
                        $richiesta->data_gestione = date('Y-m-d\TH:i:s');
                    }

                    $idCliente = (string)$richiesta['id_cliente'];
                    $creditiDaAggiungere = (int)$richiesta->crediti_richiesti;

                    if ($azione === 'approvata') {
                        // Trova il conto del cliente
                        $idClienteSanitizzato = preg_replace('/[^a-zA-Z0-9_-]/', '', $idCliente);
                        $conti = $xml->xpath("//conto[@id_cliente='$idClienteSanitizzato']");
                        if (!empty($conti)) {
                            $conto = $conti[0];
                            $conto->saldo_crediti = (int)$conto->saldo_crediti + $creditiDaAggiungere;
                            $messaggioSuccesso = "✅ Richiesta $idReq approvata. Aggiunti $creditiDaAggiungere crediti all'utente $idCliente.";
                        } else {
                            // Se il conto non esiste, lo creiamo
                            $nuovoConto = $xml->conti_clienti->addChild('conto');
                            $nuovoConto->addAttribute('id_cliente', $idCliente);
                            $nuovoConto->addChild('saldo_crediti', (string)$creditiDaAggiungere);
                            $nuovoConto->addChild('totale_speso', '0');
                            $messaggioSuccesso = "✅ Richiesta approvata e nuovo conto creato per l'utente $idCliente.";
                        }
                    } else {
                        $messaggioSuccesso = "❌ Richiesta $idReq respinta.";
                    }

                    $xml->asXML($xmlPath);
                } else {
                    $messaggioErrore = "La richiesta $idReq è già stata gestita.";
                }
            } else {
                $messaggioErrore = "Richiesta non trovata.";
            }
        }
    }
}

// 2. CARICAMENTO DATI PER LA TABELLA (Solo quelle 'in_attesa')
$xml = simplexml_load_file($xmlPath);
// Recupera tutte le richieste in attesa di approvazione
$richiesteInAttesa = ($xml !== false) ? ($xml->xpath("//richiesta[@stato='in_attesa']") ?: []) : [];
?>

<div class="card">
    <h1>🛡️ Gestione Ricariche Crediti</h1>
    <p>Qui puoi approvare o rifiutare le richieste di ricarica effettuate dai clienti.</p>
</div>

<?php if ($messaggioSuccesso): ?>
    <div class="alert alert-success"><?= htmlspecialchars($messaggioSuccesso) ?></div>
<?php endif; ?>

<?php if ($messaggioErrore): ?>
    <div class="alert alert-wip"><?= htmlspecialchars($messaggioErrore) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Richieste da Gestire</h2>
    <?php if (empty($richiesteInAttesa)): ?>
        <p style="color: #28a745; margin-top: 15px;">✅ Non ci sono richieste in attesa di approvazione.</p>
    <?php else: ?>
        <table class="tabella-admin">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Data</th>
                    <th>Crediti</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($richiesteInAttesa as $req): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($req['id_richiesta']) ?></code></td>
                        <td>Utente #<?= htmlspecialchars($req['id_cliente']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($req->data_richiesta))) ?></td>
                        <td><strong><?= htmlspecialchars($req->crediti_richiesti) ?></strong></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id_richiesta" value="<?= htmlspecialchars($req['id_richiesta']) ?>">
                                <input type="hidden" name="azione" value="approvata">
                                <button type="submit" class="btn-approva">Approva</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id_richiesta" value="<?= htmlspecialchars($req['id_richiesta']) ?>">
                                <input type="hidden" name="azione" value="respinta">
                                <button type="submit" class="btn-rifiuta" onclick="return confirm('Respingere la richiesta?')">Respingi</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>

</html>
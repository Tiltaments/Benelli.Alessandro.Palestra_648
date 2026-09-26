<?php
// admin_crediti.php - Pannello Amministratore per l'approvazione delle richieste di ricarica crediti
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

// 1. BLOCCO DI SICUREZZA: Solo l'Amministratore può vedere questa pagina 
richiediRuolo(['admin']);

$xmlPath = __DIR__ . '/data/transazioni.xml';
$messaggioSuccesso = '';
$messaggioErrore = '';

// BLOCCATO: Non modifichiamo il file transazioni.xml
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messaggioSuccesso = "<div class='alert-wip'><strong>🚧 WORK IN PROGRESS:</strong> Connessione al file transazioni.xml per l'approvazione disattivata in questa versione.</div>";
}

// Dati fittizi statici per simulare richieste in attesa di approvazione (test)
$richiesteInAttesa = [
    ['id' => 'RIC12345', 'id_cliente' => '4', 'crediti' => '50', 'data_richiesta' => '2026-08-24T10:00:00'],
    ['id' => 'RIC67890', 'id_cliente' => '5', 'crediti' => '150', 'data_richiesta' => '2026-08-25T14:30:00']
];
?>
<div class="card">
    <h1>🛡️ Pannello Amministratore: Approvazione Crediti</h1>
    <p style="color: #666;">Qui puoi verificare e gestire i pagamenti ricevuti dai clienti. Ricorda che l'approvazione caricherà i crediti sul profilo dell'utente.</p>
</div>

<?php if ($messaggioSuccesso): ?>
    <div class="alert-wip"><?= $messaggioSuccesso ?></div>
<?php endif; ?>
<?php if ($messaggioErrore): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($messaggioErrore) ?></div>
<?php endif; ?>

<div class="card">
    <h2>Richieste in attesa di revisione</h2>
    <?php if (empty($richiesteInAttesa)): ?>
        <p style="color: #28a745; font-weight: bold; margin-top: 15px;">🎉 Non ci sono richieste in attesa. Ottimo lavoro!</p>
    <?php else: ?>
        <table class="tabella-admin">
            <thead>
                <tr>
                    <th>ID Richiesta</th>
                    <th>ID Cliente</th>
                    <th>Data Richiesta</th>
                    <th>Importo (Crediti)</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($richiesteInAttesa as $req): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($req['id']) ?></code></td>
                        <td>Utente #<?= htmlspecialchars($req['id_cliente']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($req['data_richiesta']))) ?></td>
                        <td style="font-size: 18px;"><strong><?= htmlspecialchars($req['crediti']) ?></strong></td>
                        <td>
                            <!-- Modulo per APPROVARE -->
                            <form method="POST" action="admin_crediti.php" class="form-inline">
                                <input type="hidden" name="id_richiesta" value="<?= htmlspecialchars($req['id']) ?>">
                                <input type="hidden" name="azione" value="approva">
                                <button type="submit" class="btn-approva">✔️ Approva</button>
                            </form>
                            <!-- Modulo per RIFIUTARE -->
                            <form method="POST" action="admin_crediti.php" class="form-inline">
                                <input type="hidden" name="id_richiesta" value="<?= htmlspecialchars($req['id']) ?>">
                                <input type="hidden" name="azione" value="rifiuta">
                                <button type="submit" class="btn-rifiuta" onclick="return confirm('Sei sicuro di voler rifiutare questa richiesta?');">❌ Rifiuta</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</div>
</body>

</html>
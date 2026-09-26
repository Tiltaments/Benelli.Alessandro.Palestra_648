<?php
// richiesta_crediti.php - Pagina di Richiesta Crediti per la Palestra 648 con gestione della richiesta e visualizzazione dello storico
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

richiediRuolo(['cliente']);
$messaggioSuccesso = '';
$messaggioErrore = '';

// BLOCCATO: Non scriviamo in transazioni.xml
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messaggioErrore = "🚧 WORK IN PROGRESS: Il modulo di richiesta è completo, ma l'invio all'amministratore (XML) è disattivato.";
}

$storicoRichieste = [];
?>

<div class="card">
    <h1>💳 Ricarica Crediti Palestra</h1>
    <p style="color: #666; margin-top: 5px;">
        I Crediti Palestra ti permettono di acquistare corsi, abbonamenti e lezioni private (1 Credito = 1 Euro convenzionale).
    </p>
</div>

<?php if ($messaggioSuccesso): ?>
    <div class="alert alert-success"><?= htmlspecialchars($messaggioSuccesso) ?></div>
<?php endif; ?>

<?php if ($messaggioErrore): ?>
    <div class="alert-wip"><?= htmlspecialchars($messaggioErrore) ?></div>
<?php endif; ?>

<div class="grid-layout">
    <div class="card">
        <h2>Nuova Richiesta</h2>
        <form method="POST" action="richiesta_crediti.php" style="margin-top: 15px;">
            <div class="form-group">
                <label for="crediti">Quantità di Crediti:</label>
                <input type="number" id="crediti" name="crediti" min="1" step="1" placeholder="Es. 50" required>
            </div>
            <button type="submit" class="btn-submit">Invia Richiesta all'Admin</button>
        </form>
    </div>

    <div class="card">
        <h2>Le Tue Richieste di Ricarica</h2>
        <?php if (empty($storicoRichieste)): ?>
            <p style="color: #888; margin-top: 15px;">Non hai ancora inviato richieste di ricarica.</p>
        <?php else: ?>
            <table class="tabella-storico">
                <thead>
                    <tr>
                        <th>ID Richiesta</th>
                        <th>Crediti</th>
                        <th>Data Richiesta</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($storicoRichieste as $richiesta): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($richiesta['id']) ?></code></td>
                            <td><strong><?= htmlspecialchars($richiesta['crediti']) ?></strong></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($richiesta['data_richiesta']))) ?></td>
                            <td>
                                <span class="stato-<?= htmlspecialchars($richiesta['stato']) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $richiesta['stato'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</div>
</body>

</html>
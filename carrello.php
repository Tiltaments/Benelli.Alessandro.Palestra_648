<?php
// carrello.php - Pannello Carrello Cliente
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';

richiediRuolo(['cliente']);
$messaggio = '';
$errore = '';

if (!isset($_SESSION['carrello'])) {
    $_SESSION['carrello'] = [];
}

// BLOCCATO: Non facciamo aggiungere al carrello né fare checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errore = "🚧 WORK IN PROGRESS: Le funzioni di acquisto e decremento saldo XML saranno rilasciate nello Stato Finale.";
}
if (isset($_GET['rimuovi'])) {
    $errore = "🚧 WORK IN PROGRESS: Rimozione temporaneamente disabilitata.";
}

$corsiNelCarrello = [];
$totaleCrediti = 0;
?>



<div class="card">
    <h1>🛒 Il Tuo Carrello</h1>
    <p style="color: #666;">Verifica i corsi che hai selezionato prima di procedere al pagamento tramite i tuoi Crediti Palestra.</p>
</div>

<?php if ($messaggio): ?>
    <div class="alert alert-success"><?= htmlspecialchars($messaggio) ?></div>
<?php endif; ?>

<?php if ($errore): ?>
    <div class="alert-wip"><?= htmlspecialchars($errore) ?></div>
<?php endif; ?>

<div class="cart-container">
    <!-- Colonna sinistra: Elenco dei corsi nel carrello -->
    <div class="card" style="padding: 0;">
        <?php if (empty($corsiNelCarrello)): ?>
            <div style="padding: 30px; text-align: center; color: #888;">
                <h2>Il carrello è vuoto.</h2>
                <p style="margin-top: 10px;">Vai al <a href="index.php">Catalogo Corsi</a> per aggiungere qualcosa.</p>
            </div>
        <?php else: ?>
            <table class="tabella-carrello">
                <thead>
                    <tr>
                        <th>Corso / Abbonamento</th>
                        <th>Costo</th>
                        <th>Azione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($corsiNelCarrello as $c): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($c['titolo']) ?></strong><br>
                                <span style="font-size: 12px; color: #666;"><?= htmlspecialchars(str_replace('_', ' ', $c['categoria'])) ?></span>
                            </td>
                            <td style="font-weight: bold; font-size: 18px;"><?= $c['prezzo_finale'] ?> crediti</td>
                            <td>
                                <a href="carrello.php?rimuovi=<?= urlencode($c['id']) ?>" class="btn-rimuovi">Rimuovi 🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Colonna destra: Riepilogo e Pagamento -->
    <div class="riepilogo-box">
        <h2 style="font-size: 18px; margin-bottom: 15px;">Riepilogo Ordine</h2>
        <ul style="list-style: none; padding: 0; color: #555; line-height: 1.8;">
            <li>Articoli nel carrello: <strong><?= count($corsiNelCarrello) ?></strong></li>
        </ul>

        <div class="riepilogo-totale">
            Totale: <?= $totaleCrediti ?> crediti
        </div>

        <?php if (!empty($corsiNelCarrello)): ?>
            <form action="carrello.php" method="POST">
                <input type="hidden" name="checkout" value="1">
                <button type="submit" class="btn-checkout">Conferma Ordine e Paga 💳</button>
            </form>
        <?php else: ?>
            <button class="btn-checkout" style="background-color: #ccc; cursor: not-allowed;" disabled>Carrello Vuoto</button>
        <?php endif; ?>
    </div>
</div>

</div>
</body>

</html>
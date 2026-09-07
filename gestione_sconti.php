<?php
// gestione_sconti.php 
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';

richiediRuolo(['gestore', 'admin']);
$messaggio = '';

// BLOCCATO: Intercettiamo il POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messaggio = "<div class='alert-danger'><strong>🚧 WORK IN PROGRESS:</strong> La logica XPath per agganciare gli sconti ai corsi sarà implementata a breve.</div>";
}

$offerteAttuali = caricaOfferteCatalogo('default');
?>



<div class="card">
    <h1>🎁 Gestione Sconti e Bonus</h1>
    <p style="color: #666;">Applica regole promozionali ai corsi a catalogo definendo criteri specifici.</p>
</div>

<?= $messaggio ?>

<div class="card">
    <form method="POST" action="gestione_sconti.php">
        <input type="hidden" name="azione" value="aggiungi_promo">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Seleziona Offerta dal Catalogo</label>
                <select name="id_offerta" required>
                    <option value="" disabled selected>-- Seleziona offerta --</option>
                    <?php foreach ($offerteAttuali as $corso): ?>
                        <option value="<?= htmlspecialchars($corso['id']) ?>">
                            <?= htmlspecialchars($corso['titolo']) ?> (Base: <?= $corso['prezzo_base'] ?> cr.)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tipo Promozione</label>
                <select name="tipo" required>
                    <option value="" disabled selected>-- Seleziona tipo --</option>
                    <option value="sconto">Sconto percentuale (%)</option>
                    <option value="bonus">Bonus (Crediti in omaggio al cliente)</option>
                </select>
            </div>

            <div class="form-group">
                <label>Valore (es. 20 per 20% o 20 crediti)</label>
                <input type="number" step="0.1" name="valore" min="1" required>
            </div>

            <div class="form-group">
                <label>Criterio di Applicazione</label>
                <select name="tipo_criterio" required>
                    <option value="" disabled selected>-- Seleziona criterio --</option>
                    <option value="indiscriminato">Indiscriminato (Per tutti)</option>
                    <option value="anzianita">Anzianità minima (Mesi)</option>
                    <option value="spesa_pregressa">Spesa Pregressa minima (Crediti)</option>
                    <option value="reputazione">Reputazione Minima</option>
                </select>
            </div>

            <div class="form-group">
                <label>Valore Soglia del Criterio</label>
                <input type="text" name="valore_criterio" value="tutti" placeholder="Es. 12 per 12 mesi, 200 per crediti" required>
            </div>

            <div class="form-group">
                <label>Data Inizio</label>
                <input type="date" name="data_inizio" required>
            </div>

            <div class="form-group">
                <label>Data Fine</label>
                <input type="date" name="data_fine" required>
            </div>
        </div>

        <button type="submit" class="btn-submit" style="margin-top: 15px;">💾 Associa Promozione al Corso</button>
    </form>
</div>
</div>
</body>

</html>
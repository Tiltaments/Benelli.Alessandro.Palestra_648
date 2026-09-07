<?php
// index.php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';

// Lettura del filtro di ordinamento dalla query string GET
$ordinamento = $_GET['ordine'] ?? 'default';
$offerte = caricaOfferteCatalogo($ordinamento);
?>



<div class="card">
    <div class="catalogo-header">
        <div>
            <h1>🏋️ Catalogo Offerte &amp; Corsi</h1>
            <p style="color: #666; margin-top: 5px;">Scegli il tuo percorso di allenamento e consulta le promozioni attive.</p>
        </div>

        <!-- Form Ordinamento (CU1) -->
        <form method="GET" action="index.php" class="filtri-form">
            <label for="ordine"><strong>Ordina per:</strong></label>
            <select name="ordine" id="ordine" onchange="this.form.submit()">
                <option value="default" <?= $ordinamento === 'default' ? 'selected' : '' ?>>Predefinito</option>
                <option value="prezzo_crescente" <?= $ordinamento === 'prezzo_crescente' ? 'selected' : '' ?>>Prezzo: dal più economico</option>
                <option value="prezzo_decrescente" <?= $ordinamento === 'prezzo_decrescente' ? 'selected' : '' ?>>Prezzo: dal più caro</option>
                <option value="nome_az" <?= $ordinamento === 'nome_az' ? 'selected' : '' ?>>Nome (A - Z)</option>
                <option value="intensita" <?= $ordinamento === 'intensita' ? 'selected' : '' ?>>Intensità (Bassa -> Alta)</option>
            </select>
        </form>
    </div>

    <!-- Griglia dei Corsi -->
    <div class="grid-offerte">
        <?php foreach ($offerte as $corso): ?>
            <div class="offerta-card">
                <div>
                    <span class="badge-categoria"><?= htmlspecialchars(str_replace('_', ' ', $corso['categoria'])) ?></span>

                    <?php if ($corso['sconto_percentuale'] > 0): ?>
                        <span class="badge-promo">-<?= $corso['sconto_percentuale'] ?>% SCONTO</span>
                    <?php endif; ?>

                    <?php if ($corso['bonus_crediti'] > 0): ?>
                        <span class="badge-bonus">+<?= $corso['bonus_crediti'] ?> CREDITI BONUS</span>
                    <?php endif; ?>

                    <h2 style="margin-top: 5px;"><?= htmlspecialchars($corso['titolo']) ?></h2>
                    <p style="font-size: 13px; color: #555; margin: 8px 0;"><?= htmlspecialchars($corso['descrizione']) ?></p>

                    <p style="font-size: 12px; color: #777;">
                        <strong>Trainer:</strong> <?= htmlspecialchars($corso['trainer']) ?><br>
                        <strong>Intensità:</strong> <span style="text-transform: capitalize;"><?= htmlspecialchars($corso['intensita']) ?></span>
                    </p>
                </div>

                <div>
                    <div class="prezzo-container">
                        <?php if ($corso['sconto_percentuale'] > 0): ?>
                            <span class="prezzo-vecchio"><?= $corso['prezzo_base'] ?> crediti</span>
                            <span class="prezzo-scontato"><?= $corso['prezzo_finale'] ?> crediti</span>
                        <?php else: ?>
                            <span class="prezzo-normale"><?= $corso['prezzo_base'] ?> crediti</span>
                        <?php endif; ?>
                    </div>

                    <?php if (getRuolo() === 'cliente'): ?>
                        <a href="dettaglio_corso.php?id=<?= urlencode($corso['id']) ?>" class="btn-dettaglio">Vedi &amp; Acquista</a>
                    <?php elseif (getRuolo() === 'visitatore'): ?>
                        <a href="login.php" class="btn-dettaglio" style="background: #6c757d;">Accedi per Iscriverti</a>
                    <?php else: ?>
                        <a href="dettaglio_corso.php?id=<?= urlencode($corso['id']) ?>" class="btn-dettaglio" style="background: #17a2b8;">Dettaglio &amp; Scheda</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</div>
</body>

</html>
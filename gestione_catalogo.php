<?php
// gestione_catalogo.php 
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';

richiediRuolo(['gestore', 'admin']);
$messaggio = '';

// BLOCCATO: Intercettiamo il POST e blocchiamo la scrittura XML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messaggio = "<div class='alert-danger'><strong>🚧 WORK IN PROGRESS:</strong> L'inserimento e la modifica dei corsi tramite DOMDocument non sono ancora attivi.</div>";
}

$datiModifica = null;
$offerteAttuali = caricaOfferteCatalogo('default');
?>

<div class="card">
    <h1>🏋️ Gestione Catalogo Corsi</h1>
    <p style="color: #666;">Aggiungi, modifica o elimina offerte dal listino della palestra.</p>
</div>

<?= $messaggio ?>

<div class="grid-catalogo">
    <!-- Colonna Sinistra: Form Inserimento/Modifica -->
    <div class="card" <?= $datiModifica ? 'style="border: 2px solid #ffc107;"' : '' ?>>
        <h2 style="margin-bottom: 15px; font-size: 18px;">
            <?= $datiModifica ? '✏️ Modifica Servizio' : '➕ Nuovo Servizio' ?>
        </h2>

        <form method="POST" action="gestione_catalogo.php">
            <input type="hidden" name="azione" value="<?= $datiModifica ? 'salva_modifica' : 'nuova_offerta' ?>">
            <?php if ($datiModifica): ?>
                <input type="hidden" name="id_offerta_modifica" value="<?= $datiModifica['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Categoria</label>
                <select name="categoria" id="categoria" required>
                    <option value="" disabled selected>-- Seleziona una categoria --</option>
                    <option value="corso">Corso</option>
                    <option value="abbonamento">Abbonamento</option>
                    <option value="lezione_privata">Lezione Privata</option>
                </select>
            </div>

            <div class="form-group">
                <label>Titolo Offerta</label>
                <input type="text" name="titolo" value="<?= htmlspecialchars($datiModifica['titolo'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Descrizione</label>
                <textarea name="descrizione" rows="3" required><?= htmlspecialchars($datiModifica['descrizione'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Trainer di Riferimento</label>
                <input type="text" name="trainer" value="<?= htmlspecialchars($datiModifica['trainer'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Intensità</label>
                <select name="intensita" id="intensita" required>
                    <option value="" disabled selected>-- Seleziona intensità --</option>
                    <option value="bassa">Bassa</option>
                    <option value="media">Media</option>
                    <option value="alta">Alta</option>
                </select>
            </div>

            <div class="form-group">
                <label>Prezzo (in Crediti)</label>
                <input type="number" name="prezzo_crediti" min="1" value="<?= htmlspecialchars($datiModifica['prezzo_crediti'] ?? '') ?>" required>
            </div>

            <button type="submit" class="btn-submit" <?= $datiModifica ? 'style="background-color: #ffc107; color: black;"' : '' ?>>
                <?= $datiModifica ? 'Salva Modifiche' : 'Salva nel Catalogo' ?>
            </button>
            <?php if ($datiModifica): ?>
                <a href="gestione_catalogo.php" style="display:block; text-align:center; margin-top:10px; color:#666;">Annulla</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Colonna Destra: Elenco Corsi -->
    <div class="card">
        <h2 style="margin-bottom: 15px; font-size: 18px;">📋 Corsi Attualmente a Sistema</h2>
        <?php if (empty($offerteAttuali)): ?>
            <p>Nessuna offerta presente nel catalogo.</p>
        <?php else: ?>
            <table class="tabella-offerte">
                <thead>
                    <tr>
                        <th>Categoria & Titolo</th>
                        <th>Prezzo</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offerteAttuali as $corso): ?>
                        <tr>
                            <td>
                                <span class="badge-cat"><?= htmlspecialchars(str_replace('_', ' ', $corso['categoria'])) ?></span><br>
                                <strong><?= htmlspecialchars($corso['titolo']) ?></strong>
                            </td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($corso['prezzo_base']) ?> cr.</td>
                            <td>
                                <a href="gestione_catalogo.php?edit=<?= htmlspecialchars($corso['id']) ?>" class="btn-azione btn-modifica">Modifica</a>
                                <form method="POST" action="gestione_catalogo.php" class="form-inline" onsubmit="return confirm('Vuoi davvero eliminare questo corso?');">
                                    <input type="hidden" name="azione" value="elimina">
                                    <input type="hidden" name="id_offerta" value="<?= htmlspecialchars($corso['id']) ?>">
                                    <button type="submit" class="btn-azione btn-elimina">Elimina</button>
                                </form>
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
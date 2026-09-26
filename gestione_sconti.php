<?php
// gestione_sconti.php - Pannello di Gestione Sconti e Bonus per Gestori e Admin
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';

richiediRuolo(['gestore', 'admin']);
$messaggio = '';

// BLOCCATO: Intercettiamo il POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messaggio = "<div class='alert-wip'><strong>🚧 WORK IN PROGRESS:</strong> L'aggiornamento dinamico del file promozioni.xml sarà implementato nel prossimo step.</div>";
}

$offerteAttuali = caricaOfferteCatalogo('default');
?>

<div class="card">
    <h1>🏷️ Gestione Sconti e Bonus</h1>
    <p style="color: #666;">Crea regole promozionali dinamiche e associale a uno o più corsi del catalogo.</p>
</div>

<?= $messaggio ?>

<div class="card">
    <form method="POST" action="gestione_sconti.php" id="formPromo">
        <input type="hidden" name="azione" value="aggiungi_promo">

        <div class="form-group">
            <label>Titolo Promozione (Es. "Promo Estiva", "Sconto Fedeltà")</label>
            <input type="text" name="titolo_promo" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Colonna Sinistra -->
            <div>
                <div class="form-group">
                    <label>Tipo Promozione</label>
                    <select name="tipo" id="tipo" required>
                        <option value="" disabled selected>-- Seleziona tipo --</option>
                        <option value="sconto">Sconto percentuale (%)</option>
                        <option value="bonus">Bonus (Crediti in omaggio al cliente)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Valore</label>
                    <input type="number" step="1" name="valore" id="valore" min="1" placeholder="Es. 20" required>
                </div>

                <!-- Lista a Checkbox Scrollabile -->
                <div class="form-group">
                    <label>Corsi a cui applicare l'offerta (Seleziona almeno uno):</label>
                    <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; border-radius: 4px; background: #fff;">
                        <?php foreach ($offerteAttuali as $corso): ?>
                            <label style="display: block; font-weight: normal; margin-bottom: 8px; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#f1f3f5'" onmouseout="this.style.background='transparent'">
                                <input type="checkbox" name="id_corsi[]" value="<?= htmlspecialchars($corso['id']) ?>" class="corso-checkbox" style="width: auto; margin-right: 8px;">
                                <strong><?= htmlspecialchars($corso['titolo']) ?></strong>
                                <span style="color: #888; font-size: 12px;">(Base: <?= $corso['prezzo_base'] ?> cr.)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div id="errore_corsi" style="color: #dc3545; font-size: 13px; font-weight: bold; margin-top: 5px; display: none;">Devi selezionare almeno un corso!</div>
                </div>
            </div>

            <!-- Colonna Destra -->
            <div>
                <div class="form-group" style="margin-bottom: 5px;">
                    <label>Criterio di Applicazione</label>
                    <select name="tipo_criterio" id="tipo_criterio" required>
                        <option value="" disabled selected>-- Seleziona criterio --</option>
                        <option value="indiscriminato">Indiscriminato (Per tutti)</option>
                        <option value="anzianita">Anzianità minima in palestra</option>
                        <option value="spesa_pregressa">Spesa Pregressa minima</option>
                        <option value="reputazione">Reputazione Minima</option>
                    </select>
                </div>

                <!-- Box Soglia: Appare dinamicamente sotto al criterio -->
                <div class="form-group" id="container_soglia" style="display: none; background: #f8f9fa; border-left: 4px solid #17a2b8; padding: 15px; border-radius: 4px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <label id="label_soglia" style="color: #17a2b8; font-size: 14px; margin-bottom: 8px;">Valore Soglia</label>
                    <input type="number" name="valore_criterio" id="valore_criterio" min="1" placeholder="Inserisci il valore numerico" style="border: 1px solid #17a2b8;">
                </div>
            </div>
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">

        <!-- Date affiancate in fondo -->
        <h3 style="font-size: 16px; margin-bottom: 15px; color: #444;">Validità Promozione</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Data Inizio</label>
                <input type="date" name="data_inizio" required>
            </div>
            <div class="form-group">
                <label>Data Fine</label>
                <input type="date" name="data_fine" required>
            </div>
        </div>

        <button type="submit" class="btn-submit" style="margin-top: 15px; font-size: 18px; padding: 15px;">💾 Salva e Associa Promozione</button>
    </form>
</div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectCriterio = document.getElementById('tipo_criterio');
        const containerSoglia = document.getElementById('container_soglia');
        const labelSoglia = document.getElementById('label_soglia');
        const inputSoglia = document.getElementById('valore_criterio');
        const selectTipo = document.getElementById('tipo');
        const inputValore = document.getElementById('valore');
        const formPromo = document.getElementById('formPromo');
        const checkboxes = document.querySelectorAll('.corso-checkbox');
        const erroreCorsi = document.getElementById('errore_corsi');

        // Mostra/Nascondi il campo "Valore Soglia" in base al criterio scelto:
        // - "indiscriminato" → la soglia non serve (nascosta)
        // - Qualsiasi altro valore → la mostriamo con etichetta e placeholder contestuali
        selectCriterio.addEventListener('change', function() {
            if (this.value === 'indiscriminato' || this.value === '') {
                containerSoglia.style.display = 'none';
                inputSoglia.removeAttribute('required'); // Non obbligatorio se nascosto
                inputSoglia.value = '';
            } else {
                containerSoglia.style.display = 'block';
                inputSoglia.setAttribute('required', 'required'); // Obbligatorio se visibile

                // Adattiamo l'etichetta in base al tipo di criterio selezionato
                if (this.value === 'anzianita') {
                    labelSoglia.innerHTML = '⏱️ Valore Soglia <br><small style="color:#666;">Inserisci il numero di Mesi di iscrizione richiesti</small>';
                    inputSoglia.placeholder = "Es. 12";
                } else if (this.value === 'spesa_pregressa') {
                    labelSoglia.innerHTML = '💳 Valore Soglia <br><small style="color:#666;">Inserisci l\'importo minimo in Crediti già speso</small>';
                    inputSoglia.placeholder = "Es. 200";
                } else if (this.value === 'reputazione') {
                    labelSoglia.innerHTML = '⭐ Valore Soglia <br><small style="color:#666;">Inserisci il Punteggio Minimo di reputazione richiesto</small>';
                    inputSoglia.placeholder = "Es. 15";
                }
            }
        });

        // Aggiorniamo il placeholder del campo "Valore" quando cambia il tipo di promozione
        // (sconto percentuale o bonus crediti in omaggio)
        selectTipo.addEventListener('change', function() {
            if (this.value === 'bonus') {
                inputValore.setAttribute('step', '1');
                inputValore.placeholder = "Es. 20 (Solo crediti interi)";
            } else {
                inputValore.setAttribute('step', '1');
                inputValore.placeholder = "Es. 20 (Percentuale di sconto)";
            }
        });

        // Almeno un checkbox (corso) deve essere selezionato
        // Se nessuno è selezionato, blocchiamo l'invio e mostriamo il messaggio di errore
        formPromo.addEventListener('submit', function(e) {
            let isChecked = false;
            checkboxes.forEach(function(chk) {
                if (chk.checked) isChecked = true;
            });

            if (!isChecked) {
                e.preventDefault(); // Blocca l'invio del form
                erroreCorsi.style.display = 'block';
            } else {
                erroreCorsi.style.display = 'none';
            }
        });
    });
</script>

</body>

</html>
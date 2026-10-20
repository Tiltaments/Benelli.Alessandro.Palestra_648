<?php
// gestione_sconti.php - Pannello di Gestione Sconti e Bonus per Gestori e Admin
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';

richiediRuolo(['gestore', 'admin']);
$messaggio = '';

// --- GESTIONE AZIONI POST ---
$xmlPromozioni = __DIR__ . '/data/promozioni.xml';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['azione']) && $_POST['azione'] === 'aggiungi_promo') {

        $titoloPromo  = trim($_POST['titolo_promo'] ?? '');
        $tipo         = $_POST['tipo'] ?? ''; // 'sconto' o 'bonus'
        $valore       = (int)($_POST['valore'] ?? 0);
        $tipoCriterio = $_POST['tipo_criterio'] ?? 'indiscriminato';
        $valoreSoglia = (int)($_POST['valore_criterio'] ?? 0);
        $dataInizio   = $_POST['data_inizio'] ?? '';
        $dataFine     = $_POST['data_fine'] ?? '';

        $corsiSelezionati = $_POST['id_corsi'] ?? []; // Array degli id_corso scelti con i checkbox

        // Controllo validità minima dei dati
        if (!empty($titoloPromo) && !empty($tipo) && $valore > 0 && !empty($dataInizio) && !empty($dataFine) && !empty($corsiSelezionati)) {

            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            // Carichiamo l'XML o lo creiamo con radice <elenco_promozioni> e DOCTYPE se non esiste
            if (file_exists($xmlPromozioni) && filesize($xmlPromozioni) > 0) {
                $dom->load($xmlPromozioni);
                $root = $dom->documentElement;
            } else {
                $implementation = new DOMImplementation();
                $dtd = $implementation->createDocumentType('elenco_promozioni', '', 'promozioni.dtd');
                $dom = $implementation->createDocument(null, 'elenco_promozioni', $dtd);
                $dom->encoding = 'UTF-8';
                $dom->formatOutput = true;
                $root = $dom->documentElement;
            }

            // 1. Creazione del nodo <promozione> con attributo ID
            $promozione = $dom->createElement('promozione');
            $idPromo = 'PR_' . time() . mt_rand(10, 99);
            $promozione->setAttribute('id_promo', $idPromo);

            // 2. Aggiunta dei nodi figli ordinati secondo la DTD:
            // (titolo, tipo, valore, criterio_applicazione, data_inizio, data_fine, corsi_applicati)
            $promozione->appendChild($dom->createElement('titolo', htmlspecialchars($titoloPromo)));
            $promozione->appendChild($dom->createElement('tipo', htmlspecialchars($tipo)));
            $promozione->appendChild($dom->createElement('valore', (string)$valore));

            // 3. Creazione del nodo <criterio_applicazione> (PCDATA con attributi tipo_criterio e soglia facoltativo)
            $descrizioneCriterio = 'Tutti i clienti';
            if ($tipoCriterio === 'anzianita') {
                $descrizioneCriterio = "Clienti con almeno $valoreSoglia mesi di iscrizione";
            } elseif ($tipoCriterio === 'spesa_pregressa') {
                $descrizioneCriterio = "Clienti con spesa minima di $valoreSoglia crediti";
            } elseif ($tipoCriterio === 'reputazione') {
                $descrizioneCriterio = "Clienti con reputazione minima di $valoreSoglia punti";
            }

            $criterioNode = $dom->createElement('criterio_applicazione', htmlspecialchars($descrizioneCriterio));
            $criterioNode->setAttribute('tipo_criterio', htmlspecialchars($tipoCriterio));
            if ($tipoCriterio !== 'indiscriminato' && $valoreSoglia > 0) {
                $criterioNode->setAttribute('soglia', (string)$valoreSoglia);
            }
            $promozione->appendChild($criterioNode);

            // 4. Aggiunta delle date di validità
            $promozione->appendChild($dom->createElement('data_inizio', htmlspecialchars($dataInizio)));
            $promozione->appendChild($dom->createElement('data_fine', htmlspecialchars($dataFine)));

            // 5. Creazione del nodo contenitore <corsi_applicati> con i singoli <id_corso>
            $corsiApplicatiNode = $dom->createElement('corsi_applicati');
            foreach ($corsiSelezionati as $idCorsoScelto) {
                $corsiApplicatiNode->appendChild($dom->createElement('id_corso', htmlspecialchars($idCorsoScelto)));
            }
            $promozione->appendChild($corsiApplicatiNode);

            // 6. Validazione rispetto alla DTD e salvataggio
            $root->appendChild($promozione);
            
            // Validazione XML rispetto alla DTD
            if (@$dom->validate()) {
                if ($dom->save($xmlPromozioni)) {
                    $messaggio = "<div class='alert alert-success' style='background:#d4edda; color:#155724; padding:12px; border-radius:4px; margin-bottom:20px;'>Promozione \"<strong>" . htmlspecialchars($titoloPromo) . "</strong>\" creata e validata con successo rispetto alla DTD!</div>";
                } else {
                    $messaggio = "<div class='alert alert-danger' style='background:#f8d7da; color:#721c24; padding:12px; border-radius:4px; margin-bottom:20px;'>Errore durante il salvataggio del file XML.</div>";
                }
            } else {
                $messaggio = "<div class='alert alert-danger' style='background:#f8d7da; color:#721c24; padding:12px; border-radius:4px; margin-bottom:20px;'>Errore di validazione DTD: la struttura generata non rispetta promozioni.dtd.</div>";
            }
        } else {
            $messaggio = "<div class='alert-danger'>Errore: Compila tutti i campi richiesti e seleziona almeno un corso.</div>";
        }
    } elseif (isset($_POST['azione']) && $_POST['azione'] === 'elimina_promo') {
        $idPromoDaEliminare = $_POST['id_promo'] ?? '';

        if (!empty($idPromoDaEliminare) && file_exists($xmlPromozioni)) {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if ($dom->load($xmlPromozioni)) {
                $xpath = new DOMXPath($dom);
                $promoNodi = $xpath->query("//promozione[@id_promo='$idPromoDaEliminare']");

                if ($promoNodi && $promoNodi->length > 0) {
                    $nodoTrovato = $promoNodi->item(0);
                    $nodoTrovato->parentNode->removeChild($nodoTrovato);

                    if ($dom->save($xmlPromozioni)) {
                        $messaggio = "<div class='alert alert-success' style='background:#d4edda; color:#155724; padding:12px; border-radius:4px; margin-bottom:20px;'>Promozione eliminata con successo!</div>";
                    } else {
                        $messaggio = "<div class='alert alert-danger' style='background:#f8d7da; color:#721c24; padding:12px; border-radius:4px; margin-bottom:20px;'>Errore durante il salvataggio delle modifiche XML.</div>";
                    }
                } else {
                    $messaggio = "<div class='alert alert-danger' style='background:#f8d7da; color:#721c24; padding:12px; border-radius:4px; margin-bottom:20px;'>Promozione non trovata.</div>";
                }
            }
        }
    }
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

<!-- TABELLA PROMOZIONI PRESENTI -->
<?php
$elencoPromoPresenti = [];
if (file_exists($xmlPromozioni) && filesize($xmlPromozioni) > 0) {
    $domP = new DOMDocument();
    $domP->preserveWhiteSpace = false;
    if ($domP->load($xmlPromozioni)) {
        $nodiPr = $domP->getElementsByTagName('promozione');
        foreach ($nodiPr as $p) {
            $idPr = $p->getAttribute('id_promo');
            $titPr = $p->getElementsByTagName('titolo')->item(0)->nodeValue ?? '';
            $tipPr = $p->getElementsByTagName('tipo')->item(0)->nodeValue ?? '';
            $valPr = $p->getElementsByTagName('valore')->item(0)->nodeValue ?? '';
            $critPrElem = $p->getElementsByTagName('criterio_applicazione')->item(0);
            $critPrTipo = $critPrElem ? $critPrElem->getAttribute('tipo_criterio') : '';
            $critPrSoglia = $critPrElem ? $critPrElem->getAttribute('soglia') : '';
            $critPrTesto = $critPrElem ? $critPrElem->nodeValue : '';
            $dInizioPr = $p->getElementsByTagName('data_inizio')->item(0)->nodeValue ?? '';
            $dFinePr = $p->getElementsByTagName('data_fine')->item(0)->nodeValue ?? '';
            
            $corsiPr = [];
            $nodiC = $p->getElementsByTagName('id_corso');
            foreach ($nodiC as $c) {
                $corsiPr[] = $c->nodeValue;
            }

            $elencoPromoPresenti[] = [
                'id' => $idPr,
                'titolo' => $titPr,
                'tipo' => $tipPr,
                'valore' => $valPr,
                'tipo_criterio' => $critPrTipo,
                'soglia' => $critPrSoglia,
                'criterio_testo' => $critPrTesto,
                'data_inizio' => $dInizioPr,
                'data_fine' => $dFinePr,
                'corsi' => $corsiPr
            ];
        }
    }
}
?>

<div class="card" style="margin-top: 30px;">
    <h2>📋 Promozioni Configurate nel Sistema</h2>
    <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Consulta e gestisci tutte le offerte attive o programmate registrate in <code>promozioni.xml</code>.</p>

    <?php if (empty($elencoPromoPresenti)): ?>
        <p style="color: #888; font-style: italic;">Nessuna promozione configurata al momento.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
            <thead>
                <tr style="background: #f1f3f5; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 10px;">Titolo / ID</th>
                    <th style="padding: 10px;">Tipologia</th>
                    <th style="padding: 10px;">Criterio di Accesso</th>
                    <th style="padding: 10px;">Periodo Validità</th>
                    <th style="padding: 10px;">Corsi Target</th>
                    <th style="padding: 10px; text-align: center;">Azione</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $oggi = date('Y-m-d');
                foreach ($elencoPromoPresenti as $pItem): 
                    $inCorso = ($oggi >= $pItem['data_inizio'] && $oggi <= $pItem['data_fine']);
                ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px;">
                            <strong><?= htmlspecialchars($pItem['titolo']) ?></strong><br>
                            <small style="color: #777;">ID: <?= htmlspecialchars($pItem['id']) ?></small>
                        </td>
                        <td style="padding: 10px;">
                            <?php if ($pItem['tipo'] === 'sconto'): ?>
                                <span style="background: #e3f2fd; color: #0d47a1; padding: 3px 8px; border-radius: 4px; font-weight: bold;">
                                    Sconto -<?= $pItem['valore'] ?>%
                                </span>
                            <?php else: ?>
                                <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-weight: bold;">
                                    Bonus +<?= $pItem['valore'] ?> crediti
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px;">
                            <span style="text-transform: capitalize; font-weight: bold;"><?= htmlspecialchars(str_replace('_', ' ', $pItem['tipo_criterio'])) ?></span>
                            <?php if (!empty($pItem['soglia'])): ?>
                                <br><small style="color: #555;">Soglia min: <strong><?= htmlspecialchars($pItem['soglia']) ?></strong></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?= htmlspecialchars($pItem['data_inizio']) ?> &rarr; <?= htmlspecialchars($pItem['data_fine']) ?><br>
                            <?php if ($inCorso): ?>
                                <span style="color: #28a745; font-weight: bold; font-size: 12px;">● In corso adesso</span>
                            <?php elseif ($oggi < $pItem['data_inizio']): ?>
                                <span style="color: #17a2b8; font-size: 12px;">Programmata</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-size: 12px;">Scaduta</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?= implode(', ', array_map('htmlspecialchars', $pItem['corsi'])) ?>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <form method="POST" action="gestione_sconti.php" onsubmit="return confirm('Sei sicuro di voler eliminare la promozione <?= addslashes(htmlspecialchars($pItem['titolo'])) ?>?');">
                                <input type="hidden" name="azione" value="elimina_promo">
                                <input type="hidden" name="id_promo" value="<?= htmlspecialchars($pItem['id']) ?>">
                                <button type="submit" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">Elimina 🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
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
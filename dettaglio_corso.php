<?php
// dettaglio_corso.php - Pagina di dettaglio corso con gestione promozioni e community
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

$idOfferta = $_GET['id'] ?? '';
$xmlCatalogo = __DIR__ . '/data/catalogo.xml';
$xmlPromozioni = __DIR__ . '/data/promozioni.xml';
$corso = null;
$errore = '';
$messaggioCommunity = '';

// BLOCCATO: Non salviamo i post né i voti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && getRuolo() === 'cliente') {
    $messaggioCommunity = "<div class='alert-wip'><strong>🚧 Lavori in Corso:</strong> L'algoritmo di salvataggio recensioni e calcolo reputazione sarà rilasciato nel prossimo step.</div>";
}

// LETTURA DEL CORSO
if (!empty($idOfferta) && file_exists($xmlCatalogo)) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    if ($dom->load($xmlCatalogo)) {
        $xpath = new DOMXPath($dom);

        // FIX 1: Ricerca del tag <corso> 
        $nodi = $xpath->query("//corso[@id='$idOfferta']");

        if ($nodi !== false && $nodi->length > 0) {
            $nodo = $nodi->item(0);


            if ($nodo instanceof DOMElement) {

                // FIX 2: Controllo del Soft Delete (Corsi archiviati)
                if ($nodo->getElementsByTagName('disponibile')->item(0)->nodeValue === 'true') {

                    $prezzoBase = (int)($nodo->getElementsByTagName('prezzo_crediti')->item(0)->nodeValue ?? 0);

                    // Estrazione dei trainer multipli dalle <edizioni>
                    $trainers = [];
                    $edizioni = $nodo->getElementsByTagName('edizione');
                    foreach ($edizioni as $ed) {
                        if ($ed instanceof DOMElement) {
                            $trainers[] = $ed->getAttribute('trainer');
                        }
                    }

                    $corso = [
                        'id' => $idOfferta,
                        'categoria' => $nodo->getAttribute('categoria'),
                        'titolo' => $nodo->getElementsByTagName('titolo')->item(0)->nodeValue ?? 'Senza Titolo',
                        'descrizione' => $nodo->getElementsByTagName('descrizione')->item(0)->nodeValue ?? '',
                        'immagine' => $nodo->getElementsByTagName('immagine')->item(0)->nodeValue ?? '',
                        'trainers' => implode(", ", $trainers),
                        'intensita' => $nodo->getElementsByTagName('intensita')->item(0)->nodeValue ?? 'N/D',
                        'prezzo_base' => $prezzoBase,
                        'prezzo_finale' => $prezzoBase,
                        'sconto' => 0
                    ];

                    // Ricerca sconti dal file promozioni.xml
                    $dataOggi = date('Y-m-d');
                    if (file_exists($xmlPromozioni)) {
                        $domPromo = new DOMDocument();
                        $domPromo->preserveWhiteSpace = false;
                        $domPromo->load($xmlPromozioni);
                        $promoNodi = $domPromo->getElementsByTagName('promozione');

                        foreach ($promoNodi as $pNodo) {
                            $dataInizio = $pNodo->getElementsByTagName('data_inizio')->item(0)->nodeValue;
                            $dataFine = $pNodo->getElementsByTagName('data_fine')->item(0)->nodeValue;

                            if ($dataOggi >= $dataInizio && $dataOggi <= $dataFine) {
                                $applicato = false;
                                $nodiCorsi = $pNodo->getElementsByTagName('id_corso');
                                foreach ($nodiCorsi as $nc) {
                                    if ($nc->nodeValue === $idOfferta) {
                                        $applicato = true;
                                        break;
                                    }
                                }

                                if ($applicato) {
                                    $tipo = $pNodo->getElementsByTagName('tipo')->item(0)->nodeValue;
                                    $valore = (int)$pNodo->getElementsByTagName('valore')->item(0)->nodeValue;
                                    if ($tipo === 'sconto') {
                                        $corso['sconto'] = $valore;
                                        $corso['prezzo_finale'] = round($prezzoBase - ($prezzoBase * ($valore / 100)));
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $errore = "Il corso richiesto non è più disponibile a catalogo (Archiviato).";
                }
            } else {
                $errore = "Il corso richiesto non è presente a catalogo.";
            }
        }
    }
}

$contributiCorso = []; // Disattiviamo la lettura dei commenti
?>

<div class="card">
    <?php if ($errore): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;">
            <strong>Errore:</strong> <?= htmlspecialchars($errore) ?><br><br>
            <a href="index.php" style="color: #721c24; text-decoration: underline;">&larr; Torna al Catalogo</a>
        </div>
    <?php elseif ($corso): ?>
        <div class="corso-dettaglio">
            <!-- Colonna Sinistra: Informazioni -->
            <div class="corso-info">
                <span class="badge badge-cat"><?= htmlspecialchars(str_replace('_', ' ', $corso['categoria'])) ?></span>
                <?php if ($corso['sconto'] > 0): ?>
                    <span class="badge badge-sconto">-<?= $corso['sconto'] ?>% PROMO ATTIVA</span>
                <?php endif; ?>

                <h1 style="font-size: 36px; margin-bottom: 15px;"><?= htmlspecialchars($corso['titolo']) ?></h1>

                <!-- Box Immagine -->
                <div style="width: 100%; height: 250px; background-color: #eef2f7; background-image: url('<?= htmlspecialchars($corso['immagine']) ?>'); background-size: cover; background-position: center; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd;"></div>

                <p style="font-size: 16px; line-height: 1.8; color: #444; margin-bottom: 25px;">
                    <?= htmlspecialchars($corso['descrizione']) ?>
                </p>
                <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                <h2>Specifiche Tecniche</h2>
                <ul style="list-style-type: none; padding: 0; margin-top: 10px;">
                    <li style="margin-bottom: 8px;"><strong>Trainer (Edizioni):</strong> <?= htmlspecialchars($corso['trainers']) ?></li>
                    <li style="margin-bottom: 8px;"><strong>Intensità:</strong> <span style="text-transform: capitalize;"><?= htmlspecialchars($corso['intensita']) ?></span></li>
                </ul>
            </div>

            <!-- Colonna Destra: Prezzo e Acquisto -->
            <div class="corso-sidebar">
                <h2 style="font-size: 20px; color: #555; margin-bottom: 15px;">Riepilogo Costi</h2>
                <div style="margin-bottom: 20px;">
                    <?php if ($corso['sconto'] > 0): ?>
                        <span class="prezzo-vecchio"><?= $corso['prezzo_base'] ?> crediti</span><br>
                        <span class="prezzo-scontato"><?= $corso['prezzo_finale'] ?> crediti</span>
                    <?php else: ?>
                        <span class="prezzo-nuovo"><?= $corso['prezzo_base'] ?> crediti</span>
                    <?php endif; ?>
                </div>
                <?php if (getRuolo() === 'cliente'): ?>
                    <!-- Indicatore crediti disponibili -->
                    <?php
                    // Leggiamo il saldo direttamente da transazioni.xml per mostrarlo in tempo reale
                    // nella sidebar, senza doverlo memorizzare in sessione (che potrebbe essere obsoleta)
                    $saldoAttuale = 0;
                    $xmlTransazioni = __DIR__ . '/data/transazioni.xml';
                    if (file_exists($xmlTransazioni)) {
                        $domT = new DOMDocument();
                        $domT->preserveWhiteSpace = false;
                        if ($domT->load($xmlTransazioni)) {
                            $xpT = new DOMXPath($domT);
                            $uid = $_SESSION['user_id'];
                            // Percorso XPath: naviga fino al nodo <saldo_crediti> del conto di questo utente
                            $nodoConto = $xpT->query("//conto[@id_cliente='$uid']/saldo_crediti")->item(0);
                            if ($nodoConto) $saldoAttuale = (int)$nodoConto->nodeValue;
                        }
                    }
                    ?>
                    <p style="font-size: 14px; margin-bottom: 10px; color: #28a745; font-weight: bold;">
                        I tuoi crediti disponibili: <?= $saldoAttuale ?>
                    </p>
                    <form action="carrello.php" method="POST">
                        <input type="hidden" name="id_corso" value="<?= htmlspecialchars($corso['id']) ?>">
                        <button type="submit" class="btn-azione btn-acquista">Aggiungi al Carrello 🛒</button>
                    </form>
                <?php elseif (getRuolo() === 'visitatore'): ?>
                    <a href="login.php" class="btn-azione btn-login">Accedi per Acquistare 🔒</a>
                <?php else: ?>
                    <p style="color: #666; font-size: 14px; margin-top: 15px;">Solo i clienti possono acquistare questo corso.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- SEZIONE COMMUNITY & FEEDBACK -->
<div class="community-box">
    <h2>💬 Community & Recensioni sul Corso</h2>
    <p style="color: #666; margin-bottom: 20px;">Leggi le recensioni degli altri iscritti o poni una domanda tecnica.</p>

    <?= $messaggioCommunity ?>

    <!-- Form per scrivere un post (Solo per clienti) -->
    <?php if (isLoggedIn() && getRuolo() === 'cliente'): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px;">
            <h3 style="font-size: 16px; margin-bottom: 10px;">✍️ Lascia una recensione o fai una domanda</h3>
            <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>">
                <input type="hidden" name="azione_community" value="nuovo_contributo">
                <div class="form-group">
                    <label>Tipologia Contributo:</label>
                    <select name="tipo" required>
                        <option value="" disabled selected>-- Seleziona tipologia --</option>
                        <option value="recensione">Recensione</option>
                        <option value="domanda">Domanda</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Testo:</label>
                    <textarea name="testo" rows="3" placeholder="Scrivi qui il tuo pensiero o il tuo dubbio..." required></textarea>
                </div>
                <button type="submit" style="background: #007bff; color: white; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer;">Pubblica Contributo</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Lista Post Esistenti -->
    <?php if (empty($contributiCorso)): ?>
        <p style="color: #888;">Non ci sono ancora contributi per questo corso. Sii il primo a scriverne uno!</p>
    <?php else: ?>
    <?php endif; ?>
</div>
</div>
</body>

</html>
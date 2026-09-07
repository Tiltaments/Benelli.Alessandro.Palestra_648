<?php
// profilo.php - Area Personale del Cliente (CU2)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

// Sicurezza: solo i clienti possono accedere a questa pagina
richiediRuolo(['cliente']);

$userId = (string)$_SESSION['user_id'];
$nomeCompleto = $_SESSION['nome_completo'] ?? 'Utente';
$xmlTransazioni = __DIR__ . '/data/transazioni.xml';

// Inizializziamo le variabili per l'interfaccia
$saldoAttuale = 0;
$totaleSpeso = 0.00;
$storicoOrdini = [];
$messaggioErrore = '';

if (file_exists($xmlTransazioni)) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;

    if ($dom->load($xmlTransazioni)) {
        $xpath = new DOMXPath($dom);

        // 1. Lettura Saldo e Totale Speso
        $nodiConto = $xpath->query("//conto[@id_cliente='$userId']");
        if ($nodiConto !== false && $nodiConto->length > 0) {
            $nodoConto = $nodiConto->item(0);

            if ($nodoConto instanceof DOMElement) {
                $nodoSaldo = $nodoConto->getElementsByTagName('saldo_crediti')->item(0);
                if ($nodoSaldo) {
                    $saldoAttuale = (int) $nodoSaldo->nodeValue;
                }

                $nodoSpeso = $nodoConto->getElementsByTagName('totale_speso')->item(0);
                if ($nodoSpeso) {
                    $totaleSpeso = (float) $nodoSpeso->nodeValue;
                }
            }
        }

        // 2. Lettura Storico Ordini
        $nodiOrdini = $xpath->query("//ordine[@id_cliente='$userId']");
        if ($nodiOrdini !== false) {
            foreach ($nodiOrdini as $nodoOrdine) {
                if ($nodoOrdine instanceof DOMElement) {
                    $idOffertaNodo = $nodoOrdine->getElementsByTagName('id_offerta')->item(0);
                    $creditiNodo = $nodoOrdine->getElementsByTagName('crediti_pagati')->item(0);
                    $dataNodo = $nodoOrdine->getElementsByTagName('data_ordine')->item(0);

                    $storicoOrdini[] = [
                        'id_ordine' => $nodoOrdine->getAttribute('id_ordine'),
                        'id_offerta' => $idOffertaNodo ? $idOffertaNodo->nodeValue : 'N/D',
                        'crediti_pagati' => $creditiNodo ? $creditiNodo->nodeValue : '0',
                        'data_ordine' => $dataNodo ? $dataNodo->nodeValue : '-'
                    ];
                }
            }
            // Ordiniamo lo storico dal più recente al più vecchio
            usort($storicoOrdini, function ($a, $b) {
                return strtotime($b['data_ordine']) - strtotime($a['data_ordine']);
            });
        }
    } else {
        $messaggioErrore = "Errore nel caricamento del file delle transazioni.";
    }
} else {
    $messaggioErrore = "Il file delle transazioni non esiste.";
}

// --- CALCOLO DELLA REPUTAZIONE ---
$reputazioneTotale = 0;
$xmlCommunity = __DIR__ . '/data/community.xml';

if (file_exists($xmlCommunity)) {
    $domComm = new DOMDocument();
    $domComm->preserveWhiteSpace = false;

    if ($domComm->load($xmlCommunity)) {
        $xpathComm = new DOMXPath($domComm);

        // 1. Calcoliamo la reputazione dai POST PRINCIPALI
        $mieiPost = $xpathComm->query("//contributo[id_autore='$userId']");
        if ($mieiPost !== false) {
            foreach ($mieiPost as $post) {
                if ($post instanceof DOMElement) {
                    $acquistoVerificato = $post->getAttribute('acquisto_verificato') === 'true';
                    $moltiplicatoreAcquisto = $acquistoVerificato ? 1.5 : 1.0;

                    $valutazioni = $post->getElementsByTagName('valutazione');
                    foreach ($valutazioni as $voto) {
                        if ($voto instanceof DOMElement) {
                            $supporto = (int) ($voto->getElementsByTagName('supporto')->item(0)->nodeValue ?? 0);
                            $utilita = (int) ($voto->getElementsByTagName('utilita')->item(0)->nodeValue ?? 0);
                            $ruoloVotante = $voto->getAttribute('ruolo_votante');

                            $moltiplicatoreRuolo = ($ruoloVotante === 'gestore') ? 3.0 : 1.0;
                            $reputazioneTotale += ($supporto + $utilita) * $moltiplicatoreAcquisto * $moltiplicatoreRuolo;
                        }
                    }
                }
            }
        }

        // 2. Calcoliamo la reputazione dalle RISPOSTE
        $mieRisposte = $xpathComm->query("//risposta[id_autore='$userId']");
        if ($mieRisposte !== false) {
            foreach ($mieRisposte as $risposta) {
                if ($risposta instanceof DOMElement) {
                    $nodoRisposte = $risposta->parentNode;
                    $nodoContributo = $nodoRisposte ? $nodoRisposte->parentNode : null;

                    $moltiplicatoreAcquisto = 1.0;
                    if ($nodoContributo instanceof DOMElement) {
                        $acquistoVerificato = $nodoContributo->getAttribute('acquisto_verificato') === 'true';
                        $moltiplicatoreAcquisto = $acquistoVerificato ? 1.5 : 1.0;
                    }

                    $valutazioni = $risposta->getElementsByTagName('valutazione');
                    foreach ($valutazioni as $voto) {
                        if ($voto instanceof DOMElement) {
                            $supporto = (int) ($voto->getElementsByTagName('supporto')->item(0)->nodeValue ?? 0);
                            $utilita = (int) ($voto->getElementsByTagName('utilita')->item(0)->nodeValue ?? 0);
                            $ruoloVotante = $voto->getAttribute('ruolo_votante');

                            $moltiplicatoreRuolo = ($ruoloVotante === 'gestore') ? 3.0 : 1.0;
                            $reputazioneTotale += ($supporto + $utilita) * $moltiplicatoreAcquisto * $moltiplicatoreRuolo;
                        }
                    }
                }
            }
        }
    }
}
?>



<div class="card">
    <h1>👤 Area Personale: <?= htmlspecialchars($nomeCompleto) ?></h1>
    <p style="color: #666;">Benvenuto nel tuo profilo. Qui puoi controllare il tuo saldo e lo storico degli acquisti.</p>
</div>

<?php if ($messaggioErrore): ?>
    <div class="alert-danger"><?= htmlspecialchars($messaggioErrore) ?></div>
<?php endif; ?>

<div class="profilo-grid">
    <!-- Colonna Sinistra -->
    <div>
        <div class="stat-box">
            <div class="stat-etichetta">Saldo Crediti Disponibile</div>
            <div class="stat-valore" style="color: #28a745;"><?= $saldoAttuale ?></div>
            <a href="richiesta_crediti.php" style="text-decoration: none; color: #007bff; font-weight: bold; font-size: 14px;">➕ Ricarica Crediti</a>
        </div>
        <div class="stat-box">
            <div class="stat-etichetta">Totale Speso Storico</div>
            <div class="stat-valore"><?= $totaleSpeso ?> <span style="font-size: 16px; color: #888;">crediti</span></div>
        </div>
        <div class="stat-box" style="border-color: #007bff; background-color: #e9f2ff;">
            <div class="stat-etichetta" style="color: #007bff;">Reputazione Community</div>
            <div class="stat-valore"><?= $reputazioneTotale ?></div>
            <p style="font-size: 12px; color: #666; margin-top: 5px;">Calcolata in base all'utilità e supporto dei tuoi contributi.</p>
        </div>
    </div>

    <!-- Colonna Destra -->
    <div class="card">
        <h2 style="margin-bottom: 15px;">📜 I Tuoi Acquisti Recenti</h2>
        <?php if (empty($storicoOrdini)): ?>
            <p style="color: #888; text-align: center; padding: 20px;">Non hai ancora effettuato nessun acquisto.</p>
        <?php else: ?>
            <table class="tabella-ordini">
                <thead>
                    <tr>
                        <th>ID Ordine</th>
                        <th>Corso/Abbonamento (ID)</th>
                        <th>Data</th>
                        <th>Crediti Pagati</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($storicoOrdini as $ordine): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($ordine['id_ordine']) ?></code></td>
                            <td><strong><?= htmlspecialchars($ordine['id_offerta']) ?></strong></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ordine['data_ordine']))) ?></td>
                            <td style="font-weight: bold;"><?= htmlspecialchars($ordine['crediti_pagati']) ?></td>
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
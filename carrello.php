<?php
// carrello.php - Pannello Carrello Cliente
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/catalogo_service.php';

richiediRuolo(['cliente']);
$messaggio = '';
$errore = '';

// Helper per aggiornare il cookie del carrello usando una stringa separata da virgole (no JSON)
function aggiornaCookieCarrello(array $carrello): void
{
    $stringaCarrello = implode(',', array_filter($carrello));
    setcookie('carrello_persistente', $stringaCarrello, [
        'expires' => time() + (86400 * 30),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// 0. RIPRISTINO DA COOKIE (Se la sessione è vuota ma esiste il cookie salvato)
if (empty($_SESSION['carrello']) && !empty($_COOKIE['carrello_persistente'])) {
    $ids = explode(',', $_COOKIE['carrello_persistente']);
    $carrelloSalvato = array_filter(array_map('trim', $ids));
    if (!empty($carrelloSalvato)) {
        $_SESSION['carrello'] = array_values($carrelloSalvato);
    }
}

if (!isset($_SESSION['carrello']) || !is_array($_SESSION['carrello'])) {
    $_SESSION['carrello'] = [];
}

// Recupera l'ID del cliente loggato
$idCliente = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? $_SESSION['utente_id'] ?? $_SESSION['id_cliente'] ?? null;

// 1. GESTIONE AGGIUNTA CORSO AL CARRELLO (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_corso_aggiungi'])) {
    $idCorso = $_POST['id_corso_aggiungi'];
    if (!in_array($idCorso, $_SESSION['carrello'])) {
        $_SESSION['carrello'][] = $idCorso;
        aggiornaCookieCarrello($_SESSION['carrello']);
        $messaggio = "Corso aggiunto al carrello con successo!";
    } else {
        $errore = "Il corso è già presente nel tuo carrello.";
    }
}

// 2. GESTIONE RIMOZIONE CORSO DAL CARRELLO (via GET)
if (isset($_GET['rimuovi'])) {
    $idCorso = htmlspecialchars($_GET['rimuovi']);
    $key = array_search($idCorso, $_SESSION['carrello']);
    if ($key !== false) {
        unset($_SESSION['carrello'][$key]);
        $_SESSION['carrello'] = array_values($_SESSION['carrello']);
        aggiornaCookieCarrello($_SESSION['carrello']);
        $messaggio = "Corso rimosso dal carrello.";
    }
}

// 3. POPOLAMENTO CORSI NEL CARRELLO E CALCOLO TOTALE
$corsiNelCarrello = [];
$totaleCrediti = 0;

foreach ($_SESSION['carrello'] as $idCorso) {
    $corso = caricaCorsoPerId($idCorso, $idCliente);
    if ($corso) {
        $corsiNelCarrello[] = $corso;
        $totaleCrediti += $corso['prezzo_finale'];
    }
}

// 4. GESTIONE CHECKOUT / ACQUISTO (via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (empty($corsiNelCarrello)) {
        $errore = "Il carrello è vuoto. Aggiungi almeno un corso prima di procedere al pagamento.";
    } elseif (!$idCliente) {
        $errore = "Utente non autenticato correttamente.";
    } else {
        $transazioniFile = __DIR__ . '/data/transazioni.xml';

        if (!file_exists($transazioniFile)) {
            $errore = "Impossibile accedere al file delle transazioni.";
        } else {
            $xmlTransazioni = simplexml_load_file($transazioniFile);

            // Trova il conto dell'utente
            $conti = $xmlTransazioni->xpath("//conto[@id_cliente='$idCliente']");

            if (empty($conti)) {
                $errore = "Nessun conto crediti trovato per il tuo utente. Effettua prima una ricarica.";
            } else {
                $conto = $conti[0];
                $saldoAttuale = (int)$conto->saldo_crediti;

                if ($saldoAttuale < $totaleCrediti) {
                    $errore = "Crediti insufficienti. Il totale dell'ordine è di $totaleCrediti crediti, ma il tuo saldo attuale è di $saldoAttuale crediti.";
                } else {
                    // Calcolo eventuale bonus crediti complessivo
                    $totaleBonusOttenuto = 0;
                    foreach ($corsiNelCarrello as $c) {
                        $totaleBonusOttenuto += (int)($c['bonus_crediti'] ?? 0);
                    }

                    // Scalo crediti per il pagamento e accredito bonus
                    $conto->saldo_crediti = $saldoAttuale - $totaleCrediti + $totaleBonusOttenuto;
                    $totaleSpesoAttuale = (float)$conto->totale_speso;
                    $conto->totale_speso = $totaleSpesoAttuale + $totaleCrediti;

                    // Registrazione ordini in transazioni.xml
                    foreach ($corsiNelCarrello as $c) {
                        $nuovoOrdine = $xmlTransazioni->ordini->addChild('ordine');
                        $nuovoOrdine->addAttribute('id_ordine', 'ORD' . round(microtime(true) * 1000) . mt_rand(10, 99));
                        $nuovoOrdine->addAttribute('id_cliente', (string)$idCliente);

                        $nuovoOrdine->addChild('id_corso', $c['id']);
                        $nuovoOrdine->addChild('crediti_pagati', (string)$c['prezzo_finale']);
                        $nuovoOrdine->addChild('bonus_ottenuto', (string)($c['bonus_crediti'] ?? '0'));
                        $nuovoOrdine->addChild('indirizzo_fatturazione', 'Sede Palestra');
                        $nuovoOrdine->addChild('data_ordine', date('Y-m-d\TH:i:s'));
                    }

                    // Salva sul file XML
                    $xmlTransazioni->asXML($transazioniFile);

                    // Svuota carrello in sessione e azzera il cookie
                    $_SESSION['carrello'] = [];
                    aggiornaCookieCarrello([]);
                    $corsiNelCarrello = [];
                    $totaleCrediti = 0;

                    if ($totaleBonusOttenuto > 0) {
                        $messaggio = "Pagamento completato con successo! Hai inoltre ricevuto <strong>+$totaleBonusOttenuto crediti bonus</strong> in omaggio accreditati direttamente sul tuo saldo!";
                    } else {
                        $messaggio = "Pagamento completato con successo! I corsi sono ora attivi nel tuo profilo.";
                    }
                }
            }
        }
    }
}

// INCLUSIONE HEADER DOPO LA LOGICA DEI COOKIE E DEI REINDIRIZZAMENTI
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h1>🛒 Il Tuo Carrello</h1>
    <p style="color: #666;">Verifica i corsi che hai selezionato prima di procedere al pagamento tramite i tuoi Crediti Palestra.</p>
</div>

<?php if ($messaggio): ?>
    <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
        <?= htmlspecialchars($messaggio) ?>
    </div>
<?php endif; ?>

<?php if ($errore): ?>
    <div class="alert-wip" style="background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
        <?= htmlspecialchars($errore) ?>
    </div>
<?php endif; ?>

<div class="cart-container" style="display: flex; gap: 20px; flex-wrap: wrap;">
    <!-- Colonna sinistra: Elenco dei corsi nel carrello -->
    <div class="card" style="flex: 2; padding: 0;">
        <?php if (empty($corsiNelCarrello)): ?>
            <div style="padding: 30px; text-align: center; color: #888;">
                <h2>Il carrello è vuoto.</h2>
                <p style="margin-top: 10px;">Vai al <a href="index.php">Catalogo Corsi</a> per aggiungere qualcosa.</p>
            </div>
        <?php else: ?>
            <table class="tabella-carrello" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #eee; text-align: left;">
                        <th style="padding: 12px;">Corso / Abbonamento</th>
                        <th style="padding: 12px;">Costo</th>
                        <th style="padding: 12px;">Azione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($corsiNelCarrello as $c): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 12px;">
                                <strong><?= htmlspecialchars($c['titolo']) ?></strong><br>
                                <span style="font-size: 12px; color: #666;"><?= htmlspecialchars(str_replace('_', ' ', $c['categoria'])) ?></span>
                                <?php if (!empty($c['bonus_crediti']) && $c['bonus_crediti'] > 0): ?>
                                    <br><span style="display: inline-block; margin-top: 4px; padding: 2px 6px; font-size: 11px; background: #28a745; color: white; border-radius: 3px;">🎁 In omaggio: +<?= $c['bonus_crediti'] ?> crediti bonus!</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px; font-weight: bold; font-size: 18px;"><?= $c['prezzo_finale'] ?> crediti</td>
                            <td style="padding: 12px;">
                                <a href="carrello.php?rimuovi=<?= urlencode($c['id']) ?>" class="btn-rimuovi" style="color: #dc3545; text-decoration: none;">Rimuovi 🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Colonna destra: Riepilogo e Pagamento -->
    <div class="riepilogo-box" style="flex: 1; background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #ddd; height: fit-content;">
        <h2 style="font-size: 18px; margin-bottom: 15px;">Riepilogo Ordine</h2>
        <ul style="list-style: none; padding: 0; color: #555; line-height: 1.8;">
            <li>Articoli nel carrello: <strong><?= count($corsiNelCarrello) ?></strong></li>
        </ul>

        <div class="riepilogo-totale" style="font-size: 20px; font-weight: bold; margin: 15px 0;">
            Totale: <?= $totaleCrediti ?> crediti
        </div>

        <?php if (!empty($corsiNelCarrello)): ?>
            <form action="carrello.php" method="POST">
                <input type="hidden" name="checkout" value="1">
                <button type="submit" class="btn-checkout" style="width: 100%; background-color: #28a745; color: white; border: none; padding: 12px; border-radius: 4px; font-weight: bold; font-size: 16px; cursor: pointer;">Conferma Ordine e Paga 💳</button>
            </form>
        <?php else: ?>
            <button class="btn-checkout" style="width: 100%; background-color: #ccc; color: white; border: none; padding: 12px; border-radius: 4px; font-weight: bold; font-size: 16px; cursor: not-allowed;" disabled>Carrello Vuoto</button>
        <?php endif; ?>
    </div>
</div>

</div>
</body>

</html>
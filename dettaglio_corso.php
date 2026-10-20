<?php
// dettaglio_corso.php - Pagina di dettaglio corso con gestione promozioni e community
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';

$idOfferta = $_GET['id'] ?? '';
$xmlCatalogo = __DIR__ . '/data/catalogo.xml';
$xmlPromozioni = __DIR__ . '/data/promozioni.xml';
$xmlCommunity = __DIR__ . '/data/community.xml';
$xmlFaq = __DIR__ . '/data/faq.xml';

$corso = null;
$errore = '';
$messaggioCommunity = '';

require_once __DIR__ . '/config/db.php';

// Dizionario utenti per visualizzare username al posto degli ID
$dizionarioUtenti = [];
try {
    $stmtU = $pdo->query("SELECT id, username FROM utenti");
    while ($rU = $stmtU->fetch(PDO::FETCH_ASSOC)) {
        $dizionarioUtenti[$rU['id']] = $rU['username'];
    }
} catch (Exception $e) {
    // Gestione silenziosa se DB non raggiungibile
}

// Recupero ID dell'utente connesso
$idAutoreConnesso = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? $_SESSION['id_cliente'] ?? null;

// 1. GESTIONE SALVATAGGIO NUOVO CONTRIBUTO O VOTO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $azioneComm = $_POST['azione_community'] ?? '';

    // A) PUBBLICAZIONE RECENSIONE O DOMANDA
    if ($azioneComm === 'nuovo_contributo' && getRuolo() === 'cliente') {
        $tipo = trim($_POST['tipo'] ?? '');
        $testo = trim($_POST['testo'] ?? '');

        if (in_array($tipo, ['recensione', 'domanda']) && !empty($testo) && !empty($idOfferta)) {

            // Verifica acquisto effettivo dal file transazioni.xml
            $haAcquistato = false;
            $xmlTrans = __DIR__ . '/data/transazioni.xml';
            if ($idAutoreConnesso && file_exists($xmlTrans)) {
                $domTr = new DOMDocument();
                if ($domTr->load($xmlTrans)) {
                    $xpTr = new DOMXPath($domTr);
                    $ordTrovati = $xpTr->query("//ordini/ordine[@id_cliente='$idAutoreConnesso' and id_corso='$idOfferta']");
                    if ($ordTrovati !== false && $ordTrovati->length > 0) {
                        $haAcquistato = true;
                    }
                }
            }

            // Blocco recensione se il corso non è stato acquistato
            if ($tipo === 'recensione' && !$haAcquistato) {
                $messaggioCommunity = "<div style='background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;'><strong>Attenzione:</strong> Puoi pubblicare una recensione solo se hai acquistato ed iscritto a questo corso.</div>";
            } else {
                $domComm = new DOMDocument();
                $domComm->preserveWhiteSpace = false;
                $domComm->formatOutput = true;

                if (file_exists($xmlCommunity) && $domComm->load($xmlCommunity)) {
                    $radice = $domComm->documentElement;

                    $nuovoContributo = $domComm->createElement('contributo');
                    $nuovoContributo->setAttribute('id_post', 'POST' . time() . '_' . mt_rand(100, 999));
                    $nuovoContributo->setAttribute('id_corso', htmlspecialchars($idOfferta));
                    $nuovoContributo->setAttribute('tipo', htmlspecialchars($tipo));
                    $nuovoContributo->setAttribute('acquisto_verificato', $haAcquistato ? 'true' : 'false');

                    // Figli nell'ordine prescritto da community.dtd: (id_autore, data_creazione, testo)
                    $nuovoContributo->appendChild($domComm->createElement('id_autore', htmlspecialchars($idAutoreConnesso)));
                    $nuovoContributo->appendChild($domComm->createElement('data_creazione', date('Y-m-d\TH:i:s')));
                    $nuovoContributo->appendChild($domComm->createElement('testo', htmlspecialchars($testo)));

                    $radice->appendChild($nuovoContributo);

                    if ($domComm->save($xmlCommunity)) {
                        $messaggioCommunity = "<div style='background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;'><strong>Successo:</strong> Il tuo contributo è stato pubblicato! " . ($haAcquistato ? "⭐ (Acquisto Verificato)" : "") . "</div>";
                    } else {
                        $messaggioCommunity = "<div style='background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;'><strong>Errore:</strong> Impossibile salvare il contributo sul file XML.</div>";
                    }
                }
            }
        } else {
            $messaggioCommunity = "<div style='background-color: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;'><strong>Attenzione:</strong> Seleziona una tipologia valida e compila il testo.</div>";
        }
    }

    // B) VOTAZIONE DI UN CONTRIBUTO (Supporto 1-3, Utilità 1-5)
    elseif ($azioneComm === 'vota_contributo' && in_array(getRuolo(), ['cliente', 'gestore'])) {
        $idPost = trim($_POST['id_post'] ?? '');
        $supporto = max(1, min(3, (int)($_POST['supporto'] ?? 1)));
        $utilita = max(1, min(5, (int)($_POST['utilita'] ?? 1)));
        $idVotante = (string)$idAutoreConnesso;
        $ruoloVotante = (getRuolo() === 'gestore') ? 'gestore' : 'cliente';

        if (!empty($idPost) && file_exists($xmlCommunity)) {
            $domComm = new DOMDocument();
            $domComm->preserveWhiteSpace = false;
            $domComm->formatOutput = true;

            if ($domComm->load($xmlCommunity)) {
                $xpC = new DOMXPath($domComm);
                $nodoPost = $xpC->query("//contributo[@id_post='$idPost' or @id='$idPost']")->item(0);

                if ($nodoPost instanceof DOMElement) {
                    $idAutorePost = $nodoPost->getElementsByTagName('id_autore')->item(0)->nodeValue ?? '';
                    if ($idAutorePost === $idVotante) {
                        $messaggioCommunity = "<div style='background-color: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin-bottom: 20px;'>Non puoi votare un tuo contributo.</div>";
                    } else {
                        // Cerchiamo o creiamo il nodo <valutazioni>
                        $nodoValutazioni = $nodoPost->getElementsByTagName('valutazioni')->item(0);
                        if (!$nodoValutazioni) {
                            $nodoValutazioni = $domComm->createElement('valutazioni');
                            $nodoPost->appendChild($nodoValutazioni);
                        }

                        // Verifichiamo se l'utente ha già votato questo post
                        $votoEsistente = $xpC->query(".//valutazione[@id_votante='$idVotante']", $nodoValutazioni)->item(0);
                        if ($votoEsistente instanceof DOMElement) {
                            $votoEsistente->getElementsByTagName('supporto')->item(0)->nodeValue = (string)$supporto;
                            $votoEsistente->getElementsByTagName('utilita')->item(0)->nodeValue = (string)$utilita;
                        } else {
                            $nuovoVoto = $domComm->createElement('valutazione');
                            $nuovoVoto->setAttribute('id_votante', $idVotante);
                            $nuovoVoto->setAttribute('ruolo_votante', $ruoloVotante);
                            $nuovoVoto->appendChild($domComm->createElement('supporto', (string)$supporto));
                            $nuovoVoto->appendChild($domComm->createElement('utilita', (string)$utilita));
                            $nodoValutazioni->appendChild($nuovoVoto);
                        }

                        $domComm->save($xmlCommunity);
                        $messaggioCommunity = "<div style='background-color: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;'>Voto registrato con successo! Grazie per il tuo feedback.</div>";
                    }
                }
            }
        }
    }
}

// 2. LETTURA DEI DATI DEL CORSO DAL CATALOGO (con calcolo promozioni e criteri)
if (!empty($idOfferta)) {
    $corso = caricaCorsoPerId($idOfferta, $idAutoreConnesso);
    if (!$corso) {
        $errore = "Il corso richiesto non è presente a catalogo oppure non è più disponibile.";
    } else {
        $corso['sconto'] = $corso['sconto_percentuale'];
    }
}

// 3. LETTURA CONTRIBUTI COMMUNITY PER QUESTO CORSO
$contributiCorso = [];
if (file_exists($xmlCommunity) && !empty($idOfferta)) {
    $domCommRead = new DOMDocument();
    $domCommRead->preserveWhiteSpace = false;
    if ($domCommRead->load($xmlCommunity)) {
        $xpComm = new DOMXPath($domCommRead);
        $nodiContributi = $xpComm->query("//contributo[@id_corso='$idOfferta'] | //contributo[id_corso='$idOfferta']");

        if ($nodiContributi !== false) {
            foreach ($nodiContributi as $nodoC) {
                if ($nodoC instanceof DOMElement) {
                    $idAutorePost = $nodoC->getElementsByTagName('id_autore')->item(0)->nodeValue
                        ?? ($nodoC->getElementsByTagName('autore')->item(0)->nodeValue ?? '0');
                    $tipoPost = $nodoC->getAttribute('tipo')
                        ?: ($nodoC->getElementsByTagName('tipo')->item(0)->nodeValue ?? 'recensione');
                    $acqVerif = ($nodoC->getAttribute('acquisto_verificato') === 'true');
                    $dataPost = $nodoC->getElementsByTagName('data_creazione')->item(0)->nodeValue
                        ?? ($nodoC->getElementsByTagName('data')->item(0)->nodeValue ?? '');
                    $testoPost = $nodoC->getElementsByTagName('testo')->item(0)->nodeValue ?? '';
                    $idPost = $nodoC->getAttribute('id_post') ?: $nodoC->getAttribute('id');

                    // Conteggio valutazioni
                    $votiNodi = $nodoC->getElementsByTagName('valutazione');
                    $numVoti = $votiNodi->length;
                    $sommaSupp = 0;
                    $sommaUtil = 0;
                    $haVotato = false;

                    foreach ($votiNodi as $vn) {
                        if ($vn instanceof DOMElement) {
                            $sommaSupp += (int)($vn->getElementsByTagName('supporto')->item(0)->nodeValue ?? 0);
                            $sommaUtil += (int)($vn->getElementsByTagName('utilita')->item(0)->nodeValue ?? 0);
                            if ($idAutoreConnesso && $vn->getAttribute('id_votante') == $idAutoreConnesso) {
                                $haVotato = true;
                            }
                        }
                    }

                    $mediaSupp = ($numVoti > 0) ? round($sommaSupp / $numVoti, 1) : 0;
                    $mediaUtil = ($numVoti > 0) ? round($sommaUtil / $numVoti, 1) : 0;

                    $contributiCorso[] = [
                        'id' => $idPost,
                        'id_autore' => $idAutorePost,
                        'autore' => $dizionarioUtenti[$idAutorePost] ?? "Utente #$idAutorePost",
                        'tipo' => $tipoPost,
                        'acquisto_verificato' => $acqVerif,
                        'testo' => $testoPost,
                        'data' => $dataPost,
                        'num_voti' => $numVoti,
                        'media_supporto' => $mediaSupp,
                        'media_utilita' => $mediaUtil,
                        'ha_votato' => $haVotato
                    ];
                }
            }
        }
    }
}
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
                    <span class="badge badge-sconto">-<?= $corso['sconto'] ?>% (<?= htmlspecialchars($corso['nome_promo']) ?>)</span>
                <?php endif; ?>
                <?php if ($corso['bonus_crediti'] > 0): ?>
                    <span class="badge" style="background-color: #28a745; color: white;">+<?= $corso['bonus_crediti'] ?> CR. BONUS (<?= htmlspecialchars($corso['nome_promo']) ?>)</span>
                <?php endif; ?>

                <h1 style="font-size: 36px; margin-bottom: 15px;"><?= htmlspecialchars($corso['titolo']) ?></h1>

                <div style="width: 100%; height: 250px; background-color: #eef2f7; background-image: url('<?= htmlspecialchars($corso['immagine']) ?>'); background-size: cover; background-position: center; border-radius: 8px; margin-bottom: 20px; border: 1px solid #ddd;"></div>

                <p style="font-size: 16px; line-height: 1.8; color: #444; margin-bottom: 25px;">
                    <?= htmlspecialchars($corso['descrizione']) ?>
                </p>
                <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                <h2>Specifiche Tecniche</h2>
                <ul style="list-style-type: none; padding: 0; margin-top: 10px; margin-bottom: 20px;">
                    <li style="margin-bottom: 8px;"><strong>Intensità:</strong> <span style="text-transform: capitalize;"><?= htmlspecialchars($corso['intensita']) ?></span></li>
                </ul>

                <h3 style="font-size: 18px; margin-bottom: 10px;">📅 Turni ed Edizioni Disponibili</h3>
                <?php if (!empty($corso['edizioni'])): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #17a2b8;">
                        <ul style="list-style-type: none; padding: 0; margin: 0;">
                            <?php foreach ($corso['edizioni'] as $ed): ?>
                                <li style="margin-bottom: 8px; font-size: 15px;">
                                    <strong>👤 <?= htmlspecialchars($ed['trainer']) ?></strong> &mdash;
                                    🗓️ <?= htmlspecialchars($ed['giorni']) ?>
                                    🕒 <em><?= htmlspecialchars($ed['orario']) ?></em>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <p style="color: #666; font-style: italic;">Nessun turno specificato al momento.</p>
                <?php endif; ?>
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
                    <?php
                    $saldoAttuale = 0;
                    $xmlTransazioni = __DIR__ . '/data/transazioni.xml';
                    if (file_exists($xmlTransazioni) && $idAutoreConnesso) {
                        $domT = new DOMDocument();
                        $domT->preserveWhiteSpace = false;
                        if ($domT->load($xmlTransazioni)) {
                            $xpT = new DOMXPath($domT);
                            $nodoConto = $xpT->query("//conto[@id_cliente='$idAutoreConnesso']/saldo_crediti")->item(0);
                            if ($nodoConto) $saldoAttuale = (int)$nodoConto->nodeValue;
                        }
                    }
                    ?>
                    <p style="font-size: 14px; margin-bottom: 10px; color: #28a745; font-weight: bold;">
                        I tuoi crediti disponibili: <?= $saldoAttuale ?>
                    </p>
                    <form action="carrello.php" method="POST">
                        <input type="hidden" name="id_corso_aggiungi" value="<?= htmlspecialchars($corso['id']) ?>">
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

<!-- SEZIONE FAQ UFFICIALI DEL CORSO -->
<?php
$faqCorso = [];
if (file_exists($xmlFaq) && !empty($corso['titolo'])) {
    $domFaqRead = new DOMDocument();
    $domFaqRead->preserveWhiteSpace = false;
    if ($domFaqRead->load($xmlFaq)) {
        $xpFaq = new DOMXPath($domFaqRead);
        // Cerchiamo le FAQ che corrispondono al titolo di questo specifico corso
        $titoloCorsoEsc = addslashes($corso['titolo']);
        $nodiFaq = $xpFaq->query("//faq[etichetta_corso='$titoloCorsoEsc']");

        if ($nodiFaq !== false) {
            foreach ($nodiFaq as $fNode) {
                if ($fNode instanceof DOMElement) {
                    $faqCorso[] = [
                        'domanda' => $fNode->getElementsByTagName('domanda')->item(0)->nodeValue ?? '',
                        'risposta' => $fNode->getElementsByTagName('risposta')->item(0)->nodeValue ?? '',
                        'provenienza' => $fNode->getAttribute('provenienza')
                    ];
                }
            }
        }
    }
}
?>

<?php if (!empty($faqCorso)): ?>
    <div class="faq-box" style="margin-top: 30px; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ffc107;">
        <h2>❓ Domande Frequenti su questo Corso</h2>
        <p style="color: #666; margin-bottom: 20px;">Risposte ufficiali dello staff alle domande più comuni su <strong><?= htmlspecialchars($corso['titolo']) ?></strong>.</p>

        <div class="lista-faq-corso">
            <?php foreach ($faqCorso as $f): ?>
                <div style="background: #fff9e6; border-left: 4px solid #ffc107; padding: 12px 15px; margin-bottom: 12px; border-radius: 0 4px 4px 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <strong style="color: #007bff; font-size: 15px;">Q: <?= htmlspecialchars($f['domanda']) ?></strong>
                        <?php if ($f['provenienza'] === 'elevata'): ?>
                            <span style="font-size: 10px; background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-weight: bold;">⭐ Dalla Community</span>
                        <?php endif; ?>
                    </div>
                    <p style="margin: 0; color: #444; font-size: 14px; line-height: 1.5;"><strong>A:</strong> <?= htmlspecialchars($f['risposta']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>


<!-- SEZIONE COMMUNITY & FEEDBACK -->
<div class="community-box" style="margin-top: 30px; background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ddd;">
    <h2>💬 Community & Recensioni sul Corso</h2>
    <p style="color: #666; margin-bottom: 20px;">Leggi le recensioni degli altri iscritti o poni una domanda tecnica.</p>

    <?= $messaggioCommunity ?>

    <!-- Form per scrivere un post (Solo per clienti) -->
    <?php if (isLoggedIn() && getRuolo() === 'cliente'): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #e9ecef;">
            <h3 style="font-size: 16px; margin-bottom: 10px;">✍️ Lascia una recensione o fai una domanda</h3>
            <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>">
                <input type="hidden" name="azione_community" value="nuovo_contributo">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Tipologia Contributo:</label>
                    <select name="tipo" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="" disabled selected>-- Seleziona tipologia --</option>
                        <option value="recensione">Recensione (Richiede corso acquistato)</option>
                        <option value="domanda">Domanda</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Testo:</label>
                    <textarea name="testo" rows="3" placeholder="Scrivi qui il tuo pensiero o il tuo dubbio..." required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;"></textarea>
                </div>
                <button type="submit" style="background: #007bff; color: white; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer;">Pubblica Contributo</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Lista Post Esistenti -->
    <?php if (empty($contributiCorso)): ?>
        <p style="color: #888;">Non ci sono ancora contributi per questo corso. Sii il primo a scriverne uno!</p>
    <?php else: ?>
        <div class="lista-contributi">
            <?php foreach ($contributiCorso as $c): ?>
                <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div>
                            <strong style="color: #333;">👤 <?= htmlspecialchars($c['autore']) ?></strong>
                            <?php if ($c['acquisto_verificato']): ?>
                                <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; margin-left: 6px;">✓ Acquisto Verificato</span>
                            <?php endif; ?>
                        </div>
                        <span style="font-size: 12px; color: #888;">🕒 <?= htmlspecialchars($c['data']) ?></span>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <span style="display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: bold; text-transform: uppercase; border-radius: 3px; background: <?= $c['tipo'] === 'recensione' ? '#28a745' : '#17a2b8' ?>; color: white;">
                            <?= htmlspecialchars($c['tipo']) ?>
                        </span>
                        <?php if ($c['num_voti'] > 0): ?>
                            <span style="font-size: 12px; color: #666; margin-left: 10px;">
                                ⭐ Supporto: <strong><?= $c['media_supporto'] ?>/3</strong> | Utilità: <strong><?= $c['media_utilita'] ?>/5</strong> (<?= $c['num_voti'] ?> voti)
                            </span>
                        <?php else: ?>
                            <span style="font-size: 12px; color: #999; margin-left: 10px;">Nessun voto ricevuto finora</span>
                        <?php endif; ?>
                    </div>

                    <p style="margin: 0 0 12px 0; color: #444; line-height: 1.5;"><?= nl2br(htmlspecialchars($c['testo'])) ?></p>

                    <!-- Modulo di Valutazione (Supporto 1-3, Utilità 1-5) -->
                    <?php if (isLoggedIn() && in_array(getRuolo(), ['cliente', 'gestore']) && (string)$idAutoreConnesso !== (string)$c['id_autore']): ?>
                        <div style="background: #f8f9fa; border-top: 1px dashed #eee; padding-top: 10px; margin-top: 10px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <span style="font-size: 13px; font-weight: bold; color: #555;">Valuta questo post:</span>
                            <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>" style="display: inline-flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <input type="hidden" name="azione_community" value="vota_contributo">
                                <input type="hidden" name="id_post" value="<?= htmlspecialchars($c['id']) ?>">

                                <label style="font-size: 12px; color: #666;">Supporto (1-3):
                                    <select name="supporto" style="padding: 2px 6px; border-radius: 3px; border: 1px solid #ccc; font-size: 12px;">
                                        <option value="1">1 (Basso)</option>
                                        <option value="2" selected>2 (Medio)</option>
                                        <option value="3">3 (Alto)</option>
                                    </select>
                                </label>

                                <label style="font-size: 12px; color: #666;">Utilità (1-5):
                                    <select name="utilita" style="padding: 2px 6px; border-radius: 3px; border: 1px solid #ccc; font-size: 12px;">
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4" selected>4</option>
                                        <option value="5">5</option>
                                    </select>
                                </label>

                                <button type="submit" style="background: #6c757d; color: white; border: none; padding: 4px 10px; border-radius: 3px; font-size: 12px; cursor: pointer;">
                                    <?= $c['ha_votato'] ? 'Aggiorna Voto' : 'Invia Voto' ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>

</html>
<?php
// dettaglio_corso.php - Pagina di dettaglio corso con gestione promozioni, community, risposte staff ed elevazione FAQ
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

// Dizionario anagrafico utenti: mappa ID -> "Nome Cognome (Ruolo)"
$dizionarioUtenti = [];
try {
    $stmtU = $pdo->query("SELECT id, username, nome, cognome, ruolo FROM utenti");
    while ($rU = $stmtU->fetch(PDO::FETCH_ASSOC)) {
        $etichettaRuolo = ucfirst($rU['ruolo']);
        $dizionarioUtenti[$rU['id']] = htmlspecialchars($rU['nome'] . ' ' . $rU['cognome']) . " <small style='color:#666;'>($etichettaRuolo)</small>";
    }
} catch (Exception $e) {
    // Fallback se DB non raggiungibile
}

// Recupero ID dell'utente connesso
$idAutoreConnesso = $_SESSION['user_id'] ?? null;


// 1. GESTIONE AZIONI COMMUNITY (POST)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $azioneComm = $_POST['azione_community'] ?? '';

    // A) PUBBLICAZIONE RECENSIONE O DOMANDA DA PARTE DI UN CLIENTE
    if ($azioneComm === 'nuovo_contributo' && getRuolo() === 'cliente') {
        $tipo = trim($_POST['tipo'] ?? '');
        $testo = trim($_POST['testo'] ?? '');

        if (in_array($tipo, ['recensione', 'domanda']) && !empty($testo) && !empty($idOfferta)) {
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

            if ($tipo === 'recensione' && !$haAcquistato) {
                $messaggioCommunity = "<div class='alert-danger' style='padding:12px; border-radius:4px;'><strong>Accesso Negato:</strong> Puoi recensire questo corso solo dopo averlo regolarmente acquistato. Per chiedere informazioni usa 'Domanda'.</div>";
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

                    $nuovoContributo->appendChild($domComm->createElement('id_autore', (string)$idAutoreConnesso));
                    $nuovoContributo->appendChild($domComm->createElement('data_creazione', date('Y-m-d\TH:i:s')));
                    $nuovoContributo->appendChild($domComm->createElement('testo', htmlspecialchars($testo)));

                    $radice->appendChild($nuovoContributo);
                    $domComm->save($xmlCommunity);
                    $messaggioCommunity = "<div class='alert-success' style='padding:12px; border-radius:4px;'>Il tuo contributo è stato pubblicato! " . ($haAcquistato ? "★ (Acquisto Verificato)" : "") . "</div>";
                }
            }
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
                $nodoPost = $xpC->query("//contributo[@id_post='$idPost']")->item(0);

                if ($nodoPost instanceof DOMElement) {
                    $idAutorePost = $nodoPost->getElementsByTagName('id_autore')->item(0)->nodeValue ?? '';
                    if ($idAutorePost === $idVotante) {
                        $messaggioCommunity = "<div class='alert-wip' style='padding:12px; border-radius:4px;'>Non puoi votare un tuo stesso contributo.</div>";
                    } else {
                        // Verifichiamo se l'utente ha già votato questo post
                        $votoEsistente = $xpC->query(".//valutazione[@id_votante='$idVotante']", $nodoPost)->item(0);
                        if ($votoEsistente instanceof DOMElement) {
                            $messaggioCommunity = "<div class='alert-wip' style='padding:12px; border-radius:4px;'>Hai già votato questo contributo. Non è consentito votare più volte.</div>";
                        } else {
                            // Ricerca o creazione del nodo <valutazioni> 
                            $nodoValutazioni = $nodoPost->getElementsByTagName('valutazioni')->item(0);
                            if (!$nodoValutazioni) {
                                $nodoValutazioni = $domComm->createElement('valutazioni');
                                // Inserimento del nodo risposte
                                $nodoRisposte = $nodoPost->getElementsByTagName('risposte')->item(0);
                                if ($nodoRisposte) {
                                    $nodoPost->insertBefore($nodoValutazioni, $nodoRisposte);
                                } else {
                                    $nodoPost->appendChild($nodoValutazioni);
                                }
                            }

                            $nuovoVoto = $domComm->createElement('valutazione');
                            $nuovoVoto->setAttribute('id_votante', $idVotante);
                            $nuovoVoto->setAttribute('ruolo_votante', $ruoloVotante);
                            $nuovoVoto->appendChild($domComm->createElement('supporto', (string)$supporto));
                            $nuovoVoto->appendChild($domComm->createElement('utilita', (string)$utilita));
                            $nodoValutazioni->appendChild($nuovoVoto);

                            $domComm->save($xmlCommunity);
                            $messaggioCommunity = "<div class='alert-success' style='padding:12px; border-radius:4px;'>Voto registrato con successo! Grazie per la valutazione.</div>";
                        }
                    }
                }
            }
        }
    }

    // C) RISPOSTA DELLO STAFF ALLA DOMANDA (Solo Admin e Gestore)
    elseif ($azioneComm === 'rispondi_domanda' && in_array(getRuolo(), ['admin', 'gestore'])) {
        $idPost = trim($_POST['id_post'] ?? '');
        $testoRisposta = trim($_POST['testo_risposta'] ?? '');

        if (!empty($idPost) && !empty($testoRisposta) && file_exists($xmlCommunity)) {
            $domComm = new DOMDocument();
            $domComm->preserveWhiteSpace = false;
            $domComm->formatOutput = true;

            if ($domComm->load($xmlCommunity)) {
                $xpC = new DOMXPath($domComm);
                $nodoPost = $xpC->query("//contributo[@id_post='$idPost']")->item(0);

                if ($nodoPost instanceof DOMElement) {
                    $nodoRisposte = $nodoPost->getElementsByTagName('risposte')->item(0);
                    if (!$nodoRisposte) {
                        $nodoRisposte = $domComm->createElement('risposte');
                        $nodoPost->appendChild($nodoRisposte);
                    }

                    $nuovaRisposta = $domComm->createElement('risposta');
                    $nuovaRisposta->setAttribute('id_risposta', 'R' . time() . '_' . mt_rand(10, 99));
                    $nuovaRisposta->appendChild($domComm->createElement('id_autore', (string)$idAutoreConnesso));
                    $nuovaRisposta->appendChild($domComm->createElement('data_creazione', date('Y-m-d\TH:i:s')));
                    $nuovaRisposta->appendChild($domComm->createElement('testo', htmlspecialchars($testoRisposta)));

                    $nodoRisposte->appendChild($nuovaRisposta);
                    $domComm->save($xmlCommunity);
                    $messaggioCommunity = "<div class='alert-success' style='padding:12px; border-radius:4px;'>Risposta dello staff pubblicata con successo!</div>";
                }
            }
        }
    }

    // D) ELEVAZIONE DI UNA DOMANDA RISPOSTA A FAQ UFFICIALE (Solo Admin)
    elseif ($azioneComm === 'eleva_a_faq' && in_array(getRuolo(), ['admin'])) {
        $idPost = trim($_POST['id_post'] ?? '');
        $titoloCorso = trim($_POST['titolo_corso'] ?? 'Generale');

        if (!empty($idPost) && file_exists($xmlCommunity) && file_exists($xmlFaq)) {
            $domComm = new DOMDocument();
            if ($domComm->load($xmlCommunity)) {
                $xpC = new DOMXPath($domComm);
                $nodoPost = $xpC->query("//contributo[@id_post='$idPost']")->item(0);

                if ($nodoPost instanceof DOMElement) {
                    $testoDomanda = $nodoPost->getElementsByTagName('testo')->item(0)->nodeValue ?? '';
                    $nodoRisposta = $xpC->query(".//risposta/testo", $nodoPost)->item(0);
                    $testoRisposta = $nodoRisposta ? $nodoRisposta->nodeValue : '';

                    if (!empty($testoDomanda) && !empty($testoRisposta)) {
                        $domFaq = new DOMDocument();
                        $domFaq->preserveWhiteSpace = false;
                        $domFaq->formatOutput = true;

                        if ($domFaq->load($xmlFaq)) {
                            $rootFaq = $domFaq->getElementsByTagName('archivio_faq')->item(0);

                            // Verifica se già elevata
                            $xpF = new DOMXPath($domFaq);
                            $giaElevata = $xpF->query("//faq[id_post_origine='$idPost']")->length > 0;

                            if ($giaElevata) {
                                $messaggioCommunity = "<div class='alert-wip' style='padding:12px; border-radius:4px;'>Questa domanda è già stata elevata a FAQ ufficiale.</div>";
                            } else {
                                $nuovaFaq = $domFaq->createElement('faq');
                                $nuovaFaq->setAttribute('id', 'FAQ' . time());
                                $nuovaFaq->setAttribute('provenienza', 'elevata');

                                // Sequenza secondo faq.dtd: (domanda, etichetta_corso?, risposta, id_post_origine?, autore_inserimento, data_pubblicazione)
                                $nuovaFaq->appendChild($domFaq->createElement('domanda', htmlspecialchars($testoDomanda)));
                                $nuovaFaq->appendChild($domFaq->createElement('etichetta_corso', htmlspecialchars($titoloCorso)));
                                $nuovaFaq->appendChild($domFaq->createElement('risposta', htmlspecialchars($testoRisposta)));
                                $nuovaFaq->appendChild($domFaq->createElement('id_post_origine', htmlspecialchars($idPost)));
                                $nuovaFaq->appendChild($domFaq->createElement('autore_inserimento', getRuolo()));
                                $nuovaFaq->appendChild($domFaq->createElement('data_pubblicazione', date('Y-m-d')));

                                $rootFaq->appendChild($nuovaFaq);
                                $domFaq->save($xmlFaq);
                                $messaggioCommunity = "<div class='alert-success' style='padding:12px; border-radius:4px;'>★ <strong>Domanda elevata a FAQ ufficiale con successo!</strong> Ora compare nella sezione FAQ pubblica.</div>";
                            }
                        }
                    } else {
                        $messaggioCommunity = "<div class='alert-danger' style='padding:12px; border-radius:4px;'>La domanda deve contenere una risposta prima di poter essere elevata.</div>";
                    }
                }
            }
        }
    }
}


// 2. LETTURA DEI DATI DEL CORSO DAL CATALOGO

if (!empty($idOfferta)) {
    $corso = caricaCorsoPerId($idOfferta, $idAutoreConnesso);
    if (!$corso) {
        $errore = "Il corso richiesto non è presente a catalogo o non è più disponibile.";
    } else {
        $corso['sconto'] = $corso['sconto_percentuale'];
    }
}


// 3. LETTURA CONTRIBUTI COMMUNITY, VOTI E RISPOSTE PER QUESTO CORSO

$contributiCorso = [];
if (file_exists($xmlCommunity) && !empty($idOfferta)) {
    $domCommRead = new DOMDocument();
    $domCommRead->preserveWhiteSpace = false;
    if ($domCommRead->load($xmlCommunity)) {
        $xpComm = new DOMXPath($domCommRead);
        $nodiContributi = $xpComm->query("//contributo[@id_corso='$idOfferta']");

        if ($nodiContributi !== false) {
            foreach ($nodiContributi as $nodoC) {
                if ($nodoC instanceof DOMElement) {
                    $idAutorePost = $nodoC->getElementsByTagName('id_autore')->item(0)->nodeValue ?? '0';
                    $tipoPost = $nodoC->getAttribute('tipo');
                    $acqVerif = ($nodoC->getAttribute('acquisto_verificato') === 'true');
                    $dataPost = $nodoC->getElementsByTagName('data_creazione')->item(0)->nodeValue ?? '';
                    $testoPost = $nodoC->getElementsByTagName('testo')->item(0)->nodeValue ?? '';
                    $idPost = $nodoC->getAttribute('id_post');

                    // Conteggio e calcolo media valutazioni
                    $votiNodi = $nodoC->getElementsByTagName('valutazione');
                    $numVoti = $votiNodi->length;
                    $sommaSupp = 0;
                    $sommaUtil = 0;
                    $haVotato = false;
                    $mioSupporto = 0;
                    $mioUtilita = 0;

                    foreach ($votiNodi as $vn) {
                        if ($vn instanceof DOMElement) {
                            $s = (int)($vn->getElementsByTagName('supporto')->item(0)->nodeValue ?? 0);
                            $u = (int)($vn->getElementsByTagName('utilita')->item(0)->nodeValue ?? 0);
                            $sommaSupp += $s;
                            $sommaUtil += $u;

                            if ($idAutoreConnesso && (string)$vn->getAttribute('id_votante') === (string)$idAutoreConnesso) {
                                $haVotato = true;
                                $mioSupporto = $s;
                                $mioUtilita = $u;
                            }
                        }
                    }

                    $mediaSupp = ($numVoti > 0) ? round($sommaSupp / $numVoti, 1) : 0;
                    $mediaUtil = ($numVoti > 0) ? round($sommaUtil / $numVoti, 1) : 0;

                    // Estrazione delle risposte collegate
                    $risposteElenco = [];
                    $nodiRisp = $nodoC->getElementsByTagName('risposta');
                    foreach ($nodiRisp as $nr) {
                        if ($nr instanceof DOMElement) {
                            $idAutoreRisp = $nr->getElementsByTagName('id_autore')->item(0)->nodeValue ?? '';
                            $risposteElenco[] = [
                                'id_risposta' => $nr->getAttribute('id_risposta'),
                                'id_autore'   => $idAutoreRisp,
                                'autore'      => $dizionarioUtenti[$idAutoreRisp] ?? "Staff Palestra",
                                'data'        => $nr->getElementsByTagName('data_creazione')->item(0)->nodeValue ?? '',
                                'testo'       => $nr->getElementsByTagName('testo')->item(0)->nodeValue ?? ''
                            ];
                        }
                    }

                    $contributiCorso[] = [
                        'id'                  => $idPost,
                        'id_autore'           => $idAutorePost,
                        'autore'              => $dizionarioUtenti[$idAutorePost] ?? "Utente #$idAutorePost",
                        'tipo'                => $tipoPost,
                        'acquisto_verificato' => $acqVerif,
                        'testo'               => $testoPost,
                        'data'                => $dataPost,
                        'num_voti'            => $numVoti,
                        'media_supporto'      => $mediaSupp,
                        'media_utilita'       => $mediaUtil,
                        'ha_votato'           => $haVotato,
                        'mio_supporto'        => $mioSupporto,
                        'mio_utilita'         => $mioUtilita,
                        'risposte'            => $risposteElenco
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
            <?= htmlspecialchars($errore) ?>
            <p style="margin-top: 10px;"><a href="index.php">&larr; Torna al catalogo</a></p>
        </div>
    <?php else: ?>
        <p style="margin-bottom: 15px;"><a href="index.php" style="color: #007bff; text-decoration: none;">&larr; Torna al Catalogo Corsi</a></p>

        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <!-- Immagine di copertina -->
            <div style="flex: 1; min-width: 300px; max-width: 480px;">
                <img src="<?= htmlspecialchars($corso['immagine']) ?>" alt="<?= htmlspecialchars($corso['titolo']) ?>" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); object-fit: cover;">
            </div>

            <!-- Informazioni Corso -->
            <div style="flex: 2; min-width: 320px;">
                <div style="margin-bottom: 10px;">
                    <span class="badge-categoria"><?= htmlspecialchars(str_replace('_', ' ', $corso['categoria'])) ?></span>
                    <?php if (!empty($corso['bonus_crediti'])): ?>
                        <span class="badge-bonus">+<?= $corso['bonus_crediti'] ?> CR. BONUS (<?= htmlspecialchars($corso['nome_promo']) ?>)</span>
                    <?php endif; ?>
                </div>

                <h1 style="color: #1a1a2e; margin-bottom: 15px; font-size: 26px;"><?= htmlspecialchars($corso['titolo']) ?></h1>
                <p style="color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 20px;"><?= htmlspecialchars($corso['descrizione']) ?></p>

                <div style="background: #fdfdfd; border: 1px solid #eee; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                    <h3 style="font-size: 16px; margin-bottom: 10px; color: #333;">Specifiche Tecniche</h3>
                    <p style="margin-bottom: 8px;"><strong>Intensità:</strong> <span style="text-transform: capitalize; color: #e94560; font-weight: bold;"><?= htmlspecialchars($corso['intensita']) ?></span></p>

                    <h4 style="font-size: 14px; margin-top: 15px; margin-bottom: 8px; color: #555;">📅 Turni ed Edizioni Disponibili</h4>
                    <?php if (empty($corso['edizioni'])): ?>
                        <p style="font-size: 13px; color: #888;">Orari in fase di definizione con la segreteria.</p>
                    <?php else: ?>
                        <ul style="list-style: none; padding-left: 0;">
                            <?php foreach ($corso['edizioni'] as $ed): ?>
                                <li style="font-size: 13px; margin-bottom: 6px; padding: 6px 10px; background: #f8f9fa; border-radius: 4px; display: inline-block; margin-right: 8px;">
                                    👤 <strong><?= htmlspecialchars($ed['trainer']) ?></strong> &mdash; 🗓️ <?= htmlspecialchars($ed['giorni']) ?> ⏰ <?= htmlspecialchars($ed['orario']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Box Prezzo e Azione -->
                <div style="background: #f4f6f8; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                    <div style="margin-bottom: 10px;">
                        <span style="font-size: 13px; color: #666; text-transform: uppercase; font-weight: bold;">Riepilogo Costi</span><br>
                        <?php if ($corso['sconto'] > 0): ?>
                            <span class="prezzo-vecchio"><?= $corso['prezzo_base'] ?> crediti</span>
                            <span class="prezzo-scontato" style="color: #e94560; font-weight: bold; font-size: 24px;"><?= $corso['prezzo_finale'] ?> crediti</span>
                            <span class="badge-promo">-<?= $corso['sconto'] ?>% (<?= htmlspecialchars($corso['nome_promo']) ?>)</span>
                        <?php else: ?>
                            <span class="prezzo-normale" style="font-size: 24px; font-weight: bold; color: #1a1a2e;"><?= $corso['prezzo_base'] ?> crediti</span>
                        <?php endif; ?>
                    </div>

                    <?php if (getRuolo() === 'cliente'): ?>
                        <?php
                        $saldoAttuale = 0;
                        $xmlTransazioni = __DIR__ . '/data/transazioni.xml';
                        if (file_exists($xmlTransazioni) && $idAutoreConnesso) {
                            $domT = new DOMDocument();
                            if ($domT->load($xmlTransazioni)) {
                                $xpT = new DOMXPath($domT);
                                $nodoConto = $xpT->query("//conto[@id_cliente='$idAutoreConnesso']/saldo_crediti")->item(0);
                                if ($nodoConto) $saldoAttuale = (int)$nodoConto->nodeValue;
                            }
                        }
                        ?>
                        <p style="font-size: 13px; margin-bottom: 12px; color: #28a745; font-weight: bold;">
                            Il tuo saldo disponibile: <?= $saldoAttuale ?> crediti
                        </p>
                        <form action="carrello.php" method="POST">
                            <input type="hidden" name="id_corso_aggiungi" value="<?= htmlspecialchars($corso['id']) ?>">
                            <button type="submit" class="btn" style="width: 100%; border: none; cursor: pointer; text-align: center;">Aggiungi al Carrello 🛒</button>
                        </form>
                    <?php elseif (getRuolo() === 'visitatore'): ?>
                        <a href="login.php" class="btn" style="display: block; text-align: center;">Accedi per Iscriverti 🔑</a>
                    <?php else: ?>
                        <p style="color: #666; font-size: 13px; margin-top: 10px;">Accesso con privilegi di staff (<?= htmlspecialchars(getRuolo()) ?>).</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- SEZIONE COMMUNITY & DOMANDE/RECENSIONI -->
<div class="card" style="margin-top: 25px;">
    <h2>💬 Community, Recensioni &amp; Domande sul Corso</h2>
    <p style="color: #666; margin-bottom: 20px;">Leggi i pareri degli iscritti, consulta le risposte dello staff o poni una domanda tecnica sul corso.</p>

    <?= $messaggioCommunity ?>

    <!-- Form per inviare recensione o domanda (Solo per clienti) -->
    <?php if (isLoggedIn() && getRuolo() === 'cliente'): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #e9ecef;">
            <h3 style="font-size: 16px; margin-bottom: 10px;">✍️ Lascia una recensione o fai una domanda</h3>
            <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>">
                <input type="hidden" name="azione_community" value="nuovo_contributo">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Tipologia:</label>
                    <select name="tipo" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="" disabled selected>-- Seleziona tipologia --</option>
                        <option value="recensione">Recensione (Richiede corso già acquistato)</option>
                        <option value="domanda">Domanda al Personale / Community</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px;">Testo:</label>
                    <textarea name="testo" rows="3" placeholder="Scrivi qui la tua recensione o il tuo dubbio..." required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc; font-family: inherit;"></textarea>
                </div>
                <button type="submit" class="btn" style="border: none; cursor: pointer; padding: 8px 16px; font-size: 14px;">Pubblica Contributo</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Lista Contributi Esistenti -->
    <?php if (empty($contributiCorso)): ?>
        <p style="color: #888;">Non ci sono ancora contributi per questo corso. Sii il primo a scriverne uno!</p>
    <?php else: ?>
        <div class="lista-contributi">
            <?php foreach ($contributiCorso as $c): ?>
                <div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 18px; margin-bottom: 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">

                    <!-- Media dei due criteri -->
                    <div style="background: #f1f5f9; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; padding: 2px 8px; border-radius: 3px; background: <?= $c['tipo'] === 'recensione' ? '#28a745' : '#17a2b8' ?>; color: white;">
                                <?= htmlspecialchars($c['tipo']) ?>
                            </span>
                            <?php if ($c['acquisto_verificato']): ?>
                                <span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; margin-left: 6px;">✓ Acquisto Verificato</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 12px; color: #334155; font-weight: 500;">
                            <?php if ($c['num_voti'] > 0): ?>
                                ⭐ <strong>Media Valutazioni:</strong> Supporto <strong><?= $c['media_supporto'] ?> / 3</strong> &bull; Utilità <strong><?= $c['media_utilita'] ?> / 5</strong> <small style="color: #64748b;">(su <?= $c['num_voti'] ?> <?= $c['num_voti'] == 1 ? 'voto' : 'voti' ?>)</small>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">Nessuna valutazione ricevuta finora</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Autore (Nome Cognome) e Data -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div>
                            <strong style="color: #1e293b; font-size: 14px;">👤 <?= $c['autore'] ?></strong>
                        </div>
                        <span style="font-size: 12px; color: #94a3b8;">🕒 <?= date('d/m/Y H:i', strtotime($c['data'])) ?></span>
                    </div>

                    <!-- Testo del Contributo -->
                    <p style="margin: 0 0 15px 0; color: #334155; font-size: 14px; line-height: 1.6;"><?= nl2br(htmlspecialchars($c['testo'])) ?></p>

                    <!-- Media dei due criteri -->
                    <?php if ($c['tipo'] === 'domanda'): ?>
                        <div style="margin-top: 15px; margin-bottom: 12px;">
                            <?php if (!empty($c['risposte'])): ?>
                                <?php foreach ($c['risposte'] as $risp): ?>
                                    <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 14px; border-radius: 0 6px 6px 0; margin-top: 8px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <strong style="color: #1d4ed8; font-size: 13px;">💬 Risposta dello Staff &mdash; <?= $risp['autore'] ?></strong>
                                            <span style="font-size: 11px; color: #64748b;">🕒 <?= date('d/m/Y H:i', strtotime($risp['data'])) ?></span>
                                        </div>
                                        <p style="margin: 0; color: #1e293b; font-size: 13px; line-height: 1.5;"><?= nl2br(htmlspecialchars($risp['testo'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="background: #f8fafc; border-left: 4px solid #cbd5e1; padding: 8px 12px; border-radius: 0 4px 4px 0; font-size: 12px; color: #64748b; font-style: italic;">
                                    In attesa di risposta da parte dello staff.
                                </div>
                            <?php endif; ?>

                            <!-- Admin e gestore possono rispondere alle domande, ma solo l'admin può elevare a FAQ -->
                            <?php if (isLoggedIn() && in_array(getRuolo(), ['admin', 'gestore'])): ?>
                                <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #e2e8f0; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                                    <?php if (empty($c['risposte'])): ?>
                                        <!-- Form rapido per rispondere -->
                                        <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>" style="width: 100%;">
                                            <input type="hidden" name="azione_community" value="rispondi_domanda">
                                            <input type="hidden" name="id_post" value="<?= htmlspecialchars($c['id']) ?>">
                                            <div style="display: flex; gap: 8px; margin-top: 6px;">
                                                <input type="text" name="testo_risposta" placeholder="Scrivi una risposta ufficiale a questa domanda..." required style="flex: 1; padding: 6px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px;">
                                                <button type="submit" style="background: #2563eb; color: white; border: none; padding: 6px 14px; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer;">Rispondi (Staff)</button>
                                            </div>
                                        </form>
                                    <?php elseif (getRuolo() === 'admin' && !empty($c['risposte'])): ?>
                                        <!-- Domanda già risposta: bottone per ELEVARE A FAQ -->
                                        <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>">
                                            <input type="hidden" name="azione_community" value="eleva_a_faq">
                                            <input type="hidden" name="id_post" value="<?= htmlspecialchars($c['id']) ?>">
                                            <input type="hidden" name="titolo_corso" value="<?= htmlspecialchars($corso['titolo'] ?? 'Generale') ?>">
                                            <button type="submit" style="background: #f59e0b; color: #000; border: none; padding: 6px 14px; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer;">
                                                ⭐ Eleva a FAQ Ufficiale
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Sezione di votazione -->
                    <?php if (isLoggedIn() && in_array(getRuolo(), ['cliente', 'gestore'])): ?>
                        <?php if ((string)$idAutoreConnesso !== (string)$c['id_autore']): ?>
                            <div style="background: #fafafa; border-top: 1px solid #f1f5f9; padding: 10px 12px; margin-top: 12px; border-radius: 4px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">

                                <?php if ($c['ha_votato']): ?>
                                    <!-- Utente ha già votato: mostra il voto dato nelle select (disabilitate) -->
                                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                        <span style="font-size: 12px; color: #166534; font-weight: bold;">
                                            ✓ Il tuo voto registrato:
                                        </span>
                                        <label style="font-size: 12px; color: #555;">Supporto:
                                            <select disabled style="padding: 2px 6px; border-radius: 3px; border: 1px solid #cbd5e1; font-size: 12px; background: #e2e8f0; color: #333;">
                                                <option <?= $c['mio_supporto'] == 1 ? 'selected' : '' ?>>1 (Basso)</option>
                                                <option <?= $c['mio_supporto'] == 2 ? 'selected' : '' ?>>2 (Medio)</option>
                                                <option <?= $c['mio_supporto'] == 3 ? 'selected' : '' ?>>3 (Alto)</option>
                                            </select>
                                        </label>
                                        <label style="font-size: 12px; color: #555;">Utilità:
                                            <select disabled style="padding: 2px 6px; border-radius: 3px; border: 1px solid #cbd5e1; font-size: 12px; background: #e2e8f0; color: #333;">
                                                <option <?= $c['mio_utilita'] == 1 ? 'selected' : '' ?>>1 (Minima)</option>
                                                <option <?= $c['mio_utilita'] == 2 ? 'selected' : '' ?>>2</option>
                                                <option <?= $c['mio_utilita'] == 3 ? 'selected' : '' ?>>3</option>
                                                <option <?= $c['mio_utilita'] == 4 ? 'selected' : '' ?>>4</option>
                                                <option <?= $c['mio_utilita'] == 5 ? 'selected' : '' ?>>5 (Massima)</option>
                                            </select>
                                        </label>
                                        <span style="font-size: 11px; color: #64748b; font-style: italic;">(Voto già memorizzato, non ripetibile)</span>
                                    </div>

                                <?php else: ?>
                                    <!-- l'utente non ha ancora votato: select abilitate e bottone invio -->
                                    <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                        <input type="hidden" name="azione_community" value="vota_contributo">
                                        <input type="hidden" name="id_post" value="<?= htmlspecialchars($c['id']) ?>">
                                        <span style="font-size: 12px; font-weight: bold; color: #475569;">Esprimi valutazione:</span>

                                        <label style="font-size: 12px; color: #555;">Supporto:
                                            <select name="supporto" style="padding: 3px 6px; border-radius: 3px; border: 1px solid #cbd5e1; font-size: 12px;">
                                                <option value="1">1 (Basso)</option>
                                                <option value="2" selected>2 (Medio)</option>
                                                <option value="3">3 (Alto)</option>
                                            </select>
                                        </label>

                                        <label style="font-size: 12px; color: #555;">Utilità:
                                            <select name="utilita" style="padding: 3px 6px; border-radius: 3px; border: 1px solid #cbd5e1; font-size: 12px;">
                                                <option value="1">1 (Minima)</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5" selected>5 (Massima)</option>
                                            </select>
                                        </label>

                                        <button type="submit" style="background: #475569; color: white; border: none; padding: 4px 10px; border-radius: 3px; font-size: 12px; cursor: pointer; font-weight: bold;">
                                            Invia Voto
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
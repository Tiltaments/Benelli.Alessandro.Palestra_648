<?php
// admin_domande.php - Pannello Gestione Domande dei Corsi per Admin e gestori
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';
require_once __DIR__ . '/config/db.php';

// SICUREZZA: Accessibile solo da Admin e gestori
richiediRuolo(['admin', 'gestore']);

$messaggio = '';
$xmlCommunity = __DIR__ . '/data/community.xml';
$xmlFaq = __DIR__ . '/data/faq.xml';
$xmlCatalogo = __DIR__ . '/data/catalogo.xml';

// Mappa corsi id_corso => Titolo
$offerteCatalogo = caricaOfferteCatalogo('default');
$mappaCorsi = [];
foreach ($offerteCatalogo as $o) {
    if (isset($o['id'])) {
        $mappaCorsi[$o['id']] = $o['titolo'];
    }
}
$mappaCorsi['generale'] = 'Forum Generale / Domande Comuni';

// Dizionario Utenti: ID -> "Nome Cognome (Ruolo)"
$dizionarioUtenti = [];
try {
    $stmtU = $pdo->query("SELECT id, username, nome, cognome, ruolo FROM utenti");
    while ($rU = $stmtU->fetch(PDO::FETCH_ASSOC)) {
        $dizionarioUtenti[$rU['id']] = htmlspecialchars($rU['nome'] . ' ' . $rU['cognome']) . " (" . ucfirst($rU['ruolo']) . ")";
    }
} catch (Exception $e) {
}

// GESTIONE AZIONI POST (RISPOSTA STAFF & ELEVAZIONE FAQ)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    // 1. PUBBLICAZIONE RISPOSTA DELLO STAFF ALLA DOMANDA
    if ($azione === 'invia_risposta') {
        $idPost = trim($_POST['id_post'] ?? '');
        $testoRisposta = trim($_POST['testo_risposta'] ?? '');

        if (!empty($idPost) && !empty($testoRisposta) && file_exists($xmlCommunity)) {
            $domComm = new DOMDocument();
            $domComm->preserveWhiteSpace = false;
            $domComm->formatOutput = true;

            if ($domComm->load($xmlCommunity)) {
                $xp = new DOMXPath($domComm);
                $nodoPost = $xp->query("//contributo[@id_post='$idPost']")->item(0);

                if ($nodoPost instanceof DOMElement) {
                    $nodoRisposte = $nodoPost->getElementsByTagName('risposte')->item(0);
                    if (!$nodoRisposte) {
                        $nodoRisposte = $domComm->createElement('risposte');
                        $nodoPost->appendChild($nodoRisposte);
                    }

                    $nuovaRisposta = $domComm->createElement('risposta');
                    $nuovaRisposta->setAttribute('id_risposta', 'R' . time() . '_' . mt_rand(10, 99));
                    $nuovaRisposta->appendChild($domComm->createElement('id_autore', (string)$_SESSION['user_id']));
                    $nuovaRisposta->appendChild($domComm->createElement('data_creazione', date('Y-m-d\TH:i:s')));
                    $nuovaRisposta->appendChild($domComm->createElement('testo', htmlspecialchars($testoRisposta)));

                    $nodoRisposte->appendChild($nuovaRisposta);
                    $domComm->save($xmlCommunity);
                    $messaggio = "<div class='alert alert-success' style='background:#d4edda; color:#155724; padding:12px; border-radius:4px; margin-bottom:15px;'>Risposta registrata e pubblicata nel corso!</div>";
                }
            }
        }
    }

    // 2. ELEVAZIONE DOMANDA + RISPOSTA A FAQ UFFICIALE
    elseif ($azione === 'conferma_elevazione_faq') {
        $idPost = trim($_POST['id_post'] ?? '');
        $domandaFaq = trim($_POST['domanda_faq'] ?? '');
        $rispostaFaq = trim($_POST['risposta_faq'] ?? '');
        $etichettaCorso = trim($_POST['etichetta_corso'] ?? 'Generale');

        if (!empty($idPost) && !empty($domandaFaq) && !empty($rispostaFaq) && file_exists($xmlFaq)) {
            $domFaq = new DOMDocument();
            $domFaq->preserveWhiteSpace = false;
            $domFaq->formatOutput = true;

            if ($domFaq->load($xmlFaq)) {
                $rootFaq = $domFaq->getElementsByTagName('archivio_faq')->item(0);

                // Controllo duplicato per evitare elevazioni multiple dello stesso post
                $xpF = new DOMXPath($domFaq);
                $giaPresente = $xpF->query("//faq[id_post_origine='$idPost']")->length > 0;

                if ($giaPresente) {
                    $messaggio = "<div class='alert alert-wip' style='background:#fff3cd; color:#856404; padding:12px; border-radius:4px; margin-bottom:15px;'>Questa domanda è già stata inserita nelle FAQ ufficiali.</div>";
                } else {
                    $nuovaFaq = $domFaq->createElement('faq');
                    $nuovaFaq->setAttribute('id', 'FAQ' . time());
                    $nuovaFaq->setAttribute('provenienza', 'elevata');

                    $nuovaFaq->appendChild($domFaq->createElement('domanda', htmlspecialchars($domandaFaq)));
                    if (!empty($etichettaCorso)) {
                        $nuovaFaq->appendChild($domFaq->createElement('etichetta_corso', htmlspecialchars($etichettaCorso)));
                    }
                    $nuovaFaq->appendChild($domFaq->createElement('risposta', htmlspecialchars($rispostaFaq)));
                    $nuovaFaq->appendChild($domFaq->createElement('id_post_origine', htmlspecialchars($idPost)));
                    $nuovaFaq->appendChild($domFaq->createElement('autore_inserimento', getRuolo()));
                    $nuovaFaq->appendChild($domFaq->createElement('data_pubblicazione', date('Y-m-d')));

                    $rootFaq->appendChild($nuovaFaq);
                    $domFaq->save($xmlFaq);
                    $messaggio = "<div class='alert alert-success' style='background:#d4edda; color:#155724; padding:12px; border-radius:4px; margin-bottom:15px;'>★ <strong>Elevazione completata!</strong> La FAQ è ora visibile pubblicamente nella pagina FAQ.</div>";
                }
            }
        }
    }
}

// LETTURA DELLE DOMANDE GIÀ ELEVATE A FAQ
$idPostElevati = [];
if (file_exists($xmlFaq)) {
    $domF = new DOMDocument();
    if ($domF->load($xmlFaq)) {
        $xpF = new DOMXPath($domF);
        $nodiOrigine = $xpF->query("//faq[id_post_origine]/id_post_origine");
        if ($nodiOrigine !== false) {
            foreach ($nodiOrigine as $no) {
                $idVal = trim($no->nodeValue);
                if (!empty($idVal)) {
                    $idPostElevati[] = $idVal;
                }
            }
        }
    }
}


// LETTURA DI TUTTE LE DOMANDE DELLA COMMUNITY
$elencoDomande = [];
$corsiConDomande = [];

if (file_exists($xmlCommunity)) {
    $domComm = new DOMDocument();
    if ($domComm->load($xmlCommunity)) {
        $xp = new DOMXPath($domComm);
        $nodiDomande = $xp->query("//contributo[@tipo='domanda']");

        if ($nodiDomande !== false) {
            foreach ($nodiDomande as $nd) {
                if ($nd instanceof DOMElement) {
                    $idPost = $nd->getAttribute('id_post');
                    $idCorso = $nd->getAttribute('id_corso');
                    $idAutore = $nd->getElementsByTagName('id_autore')->item(0)->nodeValue ?? '';
                    $data = $nd->getElementsByTagName('data_creazione')->item(0)->nodeValue ?? '';
                    $testo = $nd->getElementsByTagName('testo')->item(0)->nodeValue ?? '';

                    // Risposte associate
                    $risposte = [];
                    $nodiR = $nd->getElementsByTagName('risposta');
                    foreach ($nodiR as $nr) {
                        if ($nr instanceof DOMElement) {
                            $idAutoreR = $nr->getElementsByTagName('id_autore')->item(0)->nodeValue ?? '';
                            $risposte[] = [
                                'id_risposta' => $nr->getAttribute('id_risposta'),
                                'autore'      => $dizionarioUtenti[$idAutoreR] ?? "Staff",
                                'data'        => $nr->getElementsByTagName('data_creazione')->item(0)->nodeValue ?? '',
                                'testo'       => $nr->getElementsByTagName('testo')->item(0)->nodeValue ?? ''
                            ];
                        }
                    }

                    if (!in_array($idCorso, $corsiConDomande)) {
                        $corsiConDomande[] = $idCorso;
                    }

                    $giaElevata = in_array($idPost, $idPostElevati);

                    $elencoDomande[] = [
                        'id_post'     => $idPost,
                        'id_corso'    => $idCorso,
                        'nome_corso'  => $mappaCorsi[$idCorso] ?? "Corso ($idCorso)",
                        'id_autore'   => $idAutore,
                        'autore'      => $dizionarioUtenti[$idAutore] ?? "Utente #$idAutore",
                        'data'        => $data,
                        'testo'       => $testo,
                        'risposte'    => $risposte,
                        'ha_risposta' => !empty($risposte),
                        'gia_elevata' => $giaElevata
                    ];
                }
            }
        }
    }
}

// Filtri GET
$filtroCorso = $_GET['corso'] ?? 'tutti';
$filtroStato = $_GET['stato'] ?? 'da_gestire'; // 'da_gestire', 'in_attesa', 'da_elevare', 'gia_elevate', 'tutti'

// Conteggi per i badge
$numInAttesa = count(array_filter($elencoDomande, fn($d) => !$d['ha_risposta']));
$numDaElevare = count(array_filter($elencoDomande, fn($d) => $d['ha_risposta'] && !$d['gia_elevata']));
$numGiaElevate = count(array_filter($elencoDomande, fn($d) => $d['gia_elevata']));

// Applicazione filtri
$domandeFiltrate = array_filter($elencoDomande, function ($d) use ($filtroCorso, $filtroStato) {
    if ($filtroCorso !== 'tutti' && $d['id_corso'] !== $filtroCorso) {
        return false;
    }
    if ($filtroStato === 'da_gestire' && $d['gia_elevata']) {
        return false; // Esclude quelle già completate
    }
    if ($filtroStato === 'in_attesa' && $d['ha_risposta']) {
        return false;
    }
    if ($filtroStato === 'da_elevare' && (!$d['ha_risposta'] || $d['gia_elevata'])) {
        return false;
    }
    if ($filtroStato === 'gia_elevate' && !$d['gia_elevata']) {
        return false;
    }
    return true;
});
?>

<div class="card" style="border-bottom: 4px solid #17a2b8;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1>💬 Gestione Domande &amp; Risposte Corsi</h1>
            <p style="color: #666; margin-top: 5px;">
                Rispondi alle domande dei clienti ed eleva le risposte più rilevanti all'archivio FAQ ufficiale.
            </p>
        </div>
        <a href="admin_faq.php" class="btn" style="background-color: #64748b; font-size: 13px; padding: 8px 14px;">
            📚 Consulta FAQ Ufficiali &rarr;
        </a>
    </div>
</div>

<?= $messaggio ?>

<!-- SEZIONE FILTRI E SELETTORE CORSI -->
<div class="card" style="margin-bottom: 20px; padding: 18px;">
    <form method="GET" action="admin_domande.php" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">

        <!-- Corsi in cui ci sono domande -->
        <div>
            <label for="corso" style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 4px;">Filtra per Corso:</label>
            <select name="corso" id="corso" onchange="this.form.submit()" style="padding: 7px 12px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px; min-width: 250px;">
                <option value="tutti">Tutti i Corsi (<?= count($elencoDomande) ?>)</option>
                <?php foreach ($corsiConDomande as $cid): ?>
                    <option value="<?= htmlspecialchars($cid) ?>" <?= $filtroCorso === $cid ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mappaCorsi[$cid] ?? $cid) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="stato" style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 4px;">Stato Gestione:</label>
            <select name="stato" id="stato" onchange="this.form.submit()" style="padding: 7px 12px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;">
                <option value="da_gestire" <?= $filtroStato === 'da_gestire' ? 'selected' : '' ?>>🎯 Da Gestire (In attesa o pronte) [<?= $numInAttesa + $numDaElevare ?>]</option>
                <option value="in_attesa" <?= $filtroStato === 'in_attesa' ? 'selected' : '' ?>>⏳ Solo in attesa di risposta [<?= $numInAttesa ?>]</option>
                <option value="da_elevare" <?= $filtroStato === 'da_elevare' ? 'selected' : '' ?>>⭐ Con risposta (Pronte da elevare) [<?= $numDaElevare ?>]</option>
                <option value="gia_elevate" <?= $filtroStato === 'gia_elevate' ? 'selected' : '' ?>>✓ Già elevate a FAQ [<?= $numGiaElevate ?>]</option>
                <option value="tutti" <?= $filtroStato === 'tutti' ? 'selected' : '' ?>>Tutte (Storico completo) [<?= count($elencoDomande) ?>]</option>
            </select>
        </div>

        <?php if ($filtroCorso !== 'tutti' || $filtroStato !== 'da_gestire'): ?>
            <div style="margin-top: 18px;">
                <a href="admin_domande.php" style="font-size: 13px; color: #dc3545; text-decoration: none; font-weight: bold;">&times; Reimposta filtri</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- ELENCO DELLE DOMANDE -->
<div class="card">
    <h2 style="margin-bottom: 15px;">Domande (<?= count($domandeFiltrate) ?>)</h2>

    <?php if (empty($domandeFiltrate)): ?>
        <p style="color: #888; font-style: italic;">Nessuna domanda trovata con i filtri attivi.</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($domandeFiltrate as $d): ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

                    <!-- Intestazione Domanda: Corso, Autore, Data -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span style="background: #eef2f7; color: #334e68; font-size: 12px; font-weight: bold; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">
                                🏷️ <?= htmlspecialchars($d['nome_corso']) ?>
                            </span>
                            <span style="margin-left: 8px; font-weight: bold; color: #1e293b; font-size: 14px;">
                                👤 <?= $d['autore'] ?>
                            </span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if ($d['gia_elevata']): ?>
                                <span style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                    ✓ Elevata a FAQ
                                </span>
                            <?php elseif ($d['ha_risposta']): ?>
                                <span style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                    ⭐ Pronta per FAQ
                                </span>
                            <?php else: ?>
                                <span style="background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                    ⏳ In Attesa di Risposta
                                </span>
                            <?php endif; ?>
                            <span style="font-size: 12px; color: #94a3b8;">🕒 <?= date('d/m/Y H:i', strtotime($d['data'])) ?></span>
                        </div>
                    </div>

                    <!-- Testo della Domanda -->
                    <div style="background: #f8fafc; border-left: 4px solid #64748b; padding: 12px 15px; border-radius: 0 6px 6px 0; margin-bottom: 15px;">
                        <strong style="color: #0f172a; font-size: 15px;">Q: <?= nl2br(htmlspecialchars($d['testo'])) ?></strong>
                    </div>

                    <!-- CASO 1: Domanda con risposta già presente -->
                    <?php if ($d['ha_risposta']): ?>
                        <?php foreach ($d['risposte'] as $r): ?>
                            <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 15px; border-radius: 0 6px 6px 0; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <strong style="color: #1d4ed8; font-size: 13px;">💬 Risposta dello Staff &mdash; <?= $r['autore'] ?>:</strong>
                                    <span style="font-size: 11px; color: #64748b;">🕒 <?= date('d/m/Y H:i', strtotime($r['data'])) ?></span>
                                </div>
                                <p style="margin: 0; color: #1e293b; font-size: 14px; line-height: 1.5;"><?= nl2br(htmlspecialchars($r['testo'])) ?></p>
                            </div>
                        <?php endforeach; ?>

                        <!-- Se è già stata elevata, non mostriamo il form duplicato -->
                        <?php if ($d['gia_elevata']): ?>
                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 14px; border-radius: 6px; font-size: 13px; color: #166534; font-weight: 500;">
                                ✓ Questa domanda è già stata formalizzata e pubblicata nella sezione <strong>FAQ Ufficiali</strong>.
                            </div>
                        <?php else: ?>
                            <!-- Form di Elevazione a FAQ Ufficiale -->
                            <div style="background: #fffbeb; border: 1px solid #fef3c7; padding: 15px; border-radius: 6px; margin-top: 15px;">
                                <h4 style="margin-bottom: 6px; color: #92400e; font-size: 14px;">⭐ Eleva a FAQ Ufficiale del Corso</h4>
                                <p style="font-size: 12px; color: #78350f; margin-bottom: 12px;">
                                    Puoi trasferire questa domanda e risposta direttamente tra le FAQ pubbliche della palestra:
                                </p>
                                <form method="POST" action="admin_domande.php">
                                    <input type="hidden" name="azione" value="conferma_elevazione_faq">
                                    <input type="hidden" name="id_post" value="<?= htmlspecialchars($d['id_post']) ?>">
                                    <input type="hidden" name="etichetta_corso" value="<?= htmlspecialchars($d['nome_corso']) ?>">

                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Titolo Domanda per la FAQ:</label>
                                        <input type="text" name="domanda_faq" value="<?= htmlspecialchars($d['testo']) ?>" required style="width: 100%; padding: 6px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px; box-sizing: border-box;">
                                    </div>

                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-size: 12px; font-weight: bold; margin-bottom: 4px;">Risposta Ufficiale da pubblicare:</label>
                                        <textarea name="risposta_faq" rows="2" required style="width: 100%; padding: 6px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px; font-family: inherit; box-sizing: border-box;"><?= htmlspecialchars($d['risposte'][0]['testo'] ?? '') ?></textarea>
                                    </div>

                                    <button type="submit" style="background: #d97706; color: white; border: none; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer;">
                                        ✓ Salva come FAQ Ufficiale
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- CASO 2: Domanda senza risposta (in attesa dello staff) -->
                    <?php else: ?>
                        <div style="background: #f1f5f9; border-radius: 6px; padding: 15px; margin-top: 10px;">
                            <h4 style="margin-bottom: 6px; color: #334155; font-size: 13px;">✍️ Rispondi a questa domanda:</h4>
                            <form method="POST" action="admin_domande.php">
                                <input type="hidden" name="azione" value="invia_risposta">
                                <input type="hidden" name="id_post" value="<?= htmlspecialchars($d['id_post']) ?>">
                                <textarea name="testo_risposta" rows="3" placeholder="Scrivi la risposta dello staff che sarà visibile nel riquadro del corso..." required style="width: 100%; padding: 8px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px; font-family: inherit; margin-bottom: 8px; box-sizing: border-box;"></textarea>
                                <button type="submit" style="background: #2563eb; color: white; border: none; padding: 7px 16px; border-radius: 4px; font-size: 13px; font-weight: bold; cursor: pointer;">
                                    Invia Risposta
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
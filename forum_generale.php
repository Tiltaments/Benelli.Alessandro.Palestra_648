<?php
// forum_generale.php - Forum Generale della Palestra 648 per discussioni aperte della community
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/db.php';

$messaggioCommunity = '';
$xmlCommunity = __DIR__ . '/data/community.xml';

// Mappa Utenti: ID -> "Nome Cognome (Ruolo)"
$dizionarioUtenti = [];
try {
    $stmtU = $pdo->query("SELECT id, username, nome, cognome, ruolo FROM utenti");
    while ($rU = $stmtU->fetch(PDO::FETCH_ASSOC)) {
        $dizionarioUtenti[$rU['id']] = htmlspecialchars($rU['nome'] . ' ' . $rU['cognome']) . " <small style='color:#666;'>(" . ucfirst($rU['ruolo']) . ")</small>";
    }
} catch (Exception $e) {
}

$idUtenteLoggato = $_SESSION['user_id'] ?? null;


// GESTIONE AZIONI POST (nuovo topic, risposta a topic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $azione = $_POST['azione_community'] ?? '';

    // 1. NUOVO TOPIC NEL FORUM GENERALE
    if ($azione === 'nuovo_contributo_generale' && in_array(getRuolo(), ['cliente', 'gestore', 'admin'])) {
        $testo = trim($_POST['testo'] ?? '');
        if (!empty($testo)) {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if (file_exists($xmlCommunity) && $dom->load($xmlCommunity)) {
                $contributo = $dom->createElement('contributo');
                $contributo->setAttribute('id_post', 'POST' . time() . '_' . mt_rand(10, 99));
                $contributo->setAttribute('id_corso', 'generale');
                $contributo->setAttribute('tipo', 'domanda');
                $contributo->setAttribute('acquisto_verificato', 'false');

                // Sequenza community.dtd: (id_autore, data_creazione, testo)
                $contributo->appendChild($dom->createElement('id_autore', (string)$idUtenteLoggato));
                $contributo->appendChild($dom->createElement('data_creazione', date('Y-m-d\TH:i:s')));
                $contributo->appendChild($dom->createElement('testo', htmlspecialchars($testo)));

                $dom->documentElement->appendChild($contributo);
                $dom->save($xmlCommunity);
                $messaggioCommunity = "<div class='alert-success' style='padding:12px; border-radius:4px; margin-bottom:15px;'>Discussione pubblicata con successo!</div>";
            }
        }
    }

    // 2. RISPOSTA AD UNA DISCUSSIONE NEL FORUM
    elseif ($azione === 'rispondi_forum') {
        $idPost = trim($_POST['id_post'] ?? '');
        $testoRisp = trim($_POST['testo_risposta'] ?? '');

        if (!empty($idPost) && !empty($testoRisp) && file_exists($xmlCommunity)) {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if ($dom->load($xmlCommunity)) {
                $xp = new DOMXPath($dom);
                $nodo = $xp->query("//contributo[@id_post='$idPost']")->item(0);

                if ($nodo instanceof DOMElement) {
                    $nodoRisposte = $nodo->getElementsByTagName('risposte')->item(0);
                    if (!$nodoRisposte) {
                        $nodoRisposte = $dom->createElement('risposte');
                        $nodo->appendChild($nodoRisposte);
                    }

                    $nuovaR = $dom->createElement('risposta');
                    $nuovaR->setAttribute('id_risposta', 'R' . time() . '_' . mt_rand(10, 99));
                    $nuovaR->appendChild($dom->createElement('id_autore', (string)$idUtenteLoggato));
                    $nuovaR->appendChild($dom->createElement('data_creazione', date('Y-m-d\TH:i:s')));
                    $nuovaR->appendChild($dom->createElement('testo', htmlspecialchars($testoRisp)));

                    $nodoRisposte->appendChild($nuovaR);
                    $dom->save($xmlCommunity);
                    $messaggioCommunity = "<div class='alert-success' style='padding:12px; border-radius:4px; margin-bottom:15px;'>La tua risposta è stata aggiunta alla discussione!</div>";
                }
            }
        }
    }
}


// LETTURA DISCUSSIONI GENERALI DAL FILE XML
$postGenerali = [];

if (file_exists($xmlCommunity)) {
    $domComm = new DOMDocument();
    if ($domComm->load($xmlCommunity)) {
        $xpathComm = new DOMXPath($domComm);
        $nodi = $xpathComm->query("//contributo[@id_corso='generale']");

        foreach ($nodi as $nodo) {
            if ($nodo instanceof DOMElement) {
                $idAutore = $nodo->getElementsByTagName('id_autore')->item(0)->nodeValue ?? '0';

                $risposte = [];
                $nodiR = $nodo->getElementsByTagName('risposta');
                foreach ($nodiR as $nr) {
                    if ($nr instanceof DOMElement) {
                        $idR = $nr->getElementsByTagName('id_autore')->item(0)->nodeValue ?? '';
                        $risposte[] = [
                            'autore' => $dizionarioUtenti[$idR] ?? "Utente #$idR",
                            'data'   => $nr->getElementsByTagName('data_creazione')->item(0)->nodeValue ?? '',
                            'testo'  => $nr->getElementsByTagName('testo')->item(0)->nodeValue ?? ''
                        ];
                    }
                }

                $postGenerali[] = [
                    'id_post'  => $nodo->getAttribute('id_post'),
                    'tipo'     => $nodo->getAttribute('tipo'),
                    'testo'    => $nodo->getElementsByTagName('testo')->item(0)->nodeValue ?? '',
                    'data'     => $nodo->getElementsByTagName('data_creazione')->item(0)->nodeValue ?? '',
                    'id_autore' => $idAutore,
                    'autore'   => $dizionarioUtenti[$idAutore] ?? "Utente #$idAutore",
                    'risposte' => $risposte
                ];
            }
        }
    }
}
?>

<div class="card" style="border-bottom: 4px solid #17a2b8;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1>🌐 Forum Generale Palestra 648</h1>
            <p style="color: #666; margin-top: 5px;">Piazza aperta per scambiarsi consigli su allenamento, alimentazione e vita in palestra.</p>
        </div>
        <div>
            <a href="faq.php" class="btn" style="background-color: #64748b; font-size: 13px; padding: 8px 14px;">Consulta le FAQ Ufficiali &rarr;</a>
        </div>
    </div>
</div>

<?= $messaggioCommunity ?>

<!-- Form per aprire una nuova discussione -->
<?php if (isLoggedIn()): ?>
    <div class="card" style="margin-bottom: 25px; background: #f8fafc; border: 1px solid #e2e8f0;">
        <h3 style="font-size: 16px; margin-bottom: 12px; color: #1e293b;">✍️ Apri una nuova discussione nel Forum</h3>
        <form method="POST" action="forum_generale.php">
            <input type="hidden" name="azione_community" value="nuovo_contributo_generale">
            <div style="margin-bottom: 12px;">
                <textarea name="testo" rows="3" placeholder="Scrivi qui la tua domanda o il tuo argomento di discussione..." required style="width: 100%; padding: 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-family: inherit; font-size: 14px; box-sizing: border-box;"></textarea>
            </div>
            <button type="submit" class="btn" style="border: none; cursor: pointer; padding: 8px 18px; font-size: 14px;">Pubblica Discussione</button>
        </form>
    </div>
<?php else: ?>
    <div class="card" style="margin-bottom: 20px; background: #fff3cd; color: #856404;">
        Vuoi partecipare alla discussione? <a href="login.php" style="font-weight: bold; color: #533f03;">Accedi</a> o <a href="register.php" style="font-weight: bold; color: #533f03;">Registrati</a> per pubblicare.
    </div>
<?php endif; ?>

<!-- Elenco Discussioni -->
<div class="card">
    <h2 style="margin-bottom: 20px;">Discussioni della Community (<?= count($postGenerali) ?>)</h2>

    <?php if (empty($postGenerali)): ?>
        <p style="color: #888; font-style: italic;">Non ci sono ancora discussioni aperte nel forum. Sii il primo ad avviarne una!</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($postGenerali as $post): ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div>
                            <span style="font-size: 11px; font-weight: bold; text-transform: uppercase; padding: 2px 8px; border-radius: 3px; background: #17a2b8; color: white;">
                                Forum Generale
                            </span>
                            <span style="margin-left: 8px; font-weight: bold; color: #1e293b; font-size: 14px;">
                                👤 <?= $post['autore'] ?>
                            </span>
                        </div>
                        <span style="font-size: 12px; color: #94a3b8;">🕒 <?= date('d/m/Y H:i', strtotime($post['data'])) ?></span>
                    </div>

                    <p style="color: #334155; font-size: 15px; line-height: 1.6; margin-bottom: 15px;"><?= nl2br(htmlspecialchars($post['testo'])) ?></p>

                    <!-- Risposte al topic -->
                    <?php if (!empty($post['risposte'])): ?>
                        <div style="margin-top: 15px; padding-left: 15px; border-left: 3px solid #cbd5e1; display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($post['risposte'] as $r): ?>
                                <div style="background: #f8fafc; padding: 10px 14px; border-radius: 6px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                        <strong style="color: #1e293b; font-size: 13px;">💬 <?= $r['autore'] ?></strong>
                                        <span style="font-size: 11px; color: #94a3b8;">🕒 <?= date('d/m/Y H:i', strtotime($r['data'])) ?></span>
                                    </div>
                                    <p style="margin: 0; color: #475569; font-size: 13px; line-height: 1.5;"><?= nl2br(htmlspecialchars($r['testo'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Form per rispondere se loggati -->
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" action="forum_generale.php" style="margin-top: 15px; display: flex; gap: 8px;">
                            <input type="hidden" name="azione_community" value="rispondi_forum">
                            <input type="hidden" name="id_post" value="<?= htmlspecialchars($post['id_post']) ?>">
                            <input type="text" name="testo_risposta" placeholder="Scrivi una risposta a questa discussione..." required style="flex: 1; padding: 7px 12px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px;">
                            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 7px 14px; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer;">Rispondi</button>
                        </form>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
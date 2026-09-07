<?php
// admin_faq.php - Gestione FAQ ed Elevazione (CU10)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

// SICUREZZA: Solo l'amministratore o il gestore possono accedere
richiediRuolo(['admin', 'gestore']);

$messaggio = '';
$xmlFaq = __DIR__ . '/data/faq.xml';
$xmlCommunity = __DIR__ . '/data/community.xml';

// --- 1. GESTIONE SALVATAGGIO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    // A) INSERIMENTO MANUALE
    if ($azione === 'inserisci_manuale') {
        $testoDomanda = trim($_POST['domanda'] ?? '');
        $testoRisposta = trim($_POST['risposta'] ?? '');

        if (!empty($testoDomanda) && !empty($testoRisposta)) {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if ($dom->load($xmlFaq)) {
                $root = $dom->getElementsByTagName('archivio_faq')->item(0);
                if ($root) {
                    $nuovaFaq = $dom->createElement('faq');
                    $nuovaFaq->setAttribute('id', 'FAQ' . time());
                    $nuovaFaq->setAttribute('provenienza', 'manuale');

                    $nuovaFaq->appendChild($dom->createElement('domanda', $testoDomanda));
                    $nuovaFaq->appendChild($dom->createElement('risposta', $testoRisposta));
                    $nuovaFaq->appendChild($dom->createElement('autore_inserimento', getRuolo()));
                    $nuovaFaq->appendChild($dom->createElement('data_pubblicazione', date('Y-m-d')));

                    $root->appendChild($nuovaFaq);
                    $dom->save($xmlFaq);
                    $messaggio = "<div class='alert-success'>FAQ manuale inserita con successo!</div>";
                }
            }
        } else {
            $messaggio = "<div class='alert-danger'>Compila sia la domanda che la risposta.</div>";
        }
    }

    // B) ELEVAZIONE DA COMMUNITY
    if ($azione === 'eleva_domanda') {
        $idPostOrigine = $_POST['id_post_origine'] ?? '';
        $testoRisposta = trim($_POST['risposta_elevata'] ?? '');

        if (!empty($idPostOrigine) && !empty($testoRisposta)) {
            // Step B1: Recuperiamo il testo della domanda originale da community.xml
            $testoDomanda = '';
            if (file_exists($xmlCommunity)) {
                $domComm = new DOMDocument();
                $domComm->preserveWhiteSpace = false;
                if ($domComm->load($xmlCommunity)) {
                    $xpathComm = new DOMXPath($domComm);
                    // Cerchiamo la domanda esatta tramite il suo id_post
                    $nodoTesto = $xpathComm->query("//contributo[@id_post='$idPostOrigine']/testo")->item(0);
                    if ($nodoTesto) {
                        $testoDomanda = $nodoTesto->nodeValue;
                    }
                }
            }

            // Step B2: Se abbiamo trovato il testo, salviamo in faq.xml
            if (!empty($testoDomanda)) {
                $domFaq = new DOMDocument();
                $domFaq->preserveWhiteSpace = false;
                $domFaq->formatOutput = true;

                if ($domFaq->load($xmlFaq)) {
                    $root = $domFaq->getElementsByTagName('archivio_faq')->item(0);
                    if ($root) {
                        $nuovaFaq = $domFaq->createElement('faq');
                        $nuovaFaq->setAttribute('id', 'FAQ' . time());
                        $nuovaFaq->setAttribute('provenienza', 'elevata');

                        $nuovaFaq->appendChild($domFaq->createElement('domanda', $testoDomanda));
                        $nuovaFaq->appendChild($domFaq->createElement('risposta', $testoRisposta));
                        $nuovaFaq->appendChild($domFaq->createElement('id_post_origine', $idPostOrigine));
                        $nuovaFaq->appendChild($domFaq->createElement('autore_inserimento', getRuolo()));
                        $nuovaFaq->appendChild($domFaq->createElement('data_pubblicazione', date('Y-m-d')));

                        $root->appendChild($nuovaFaq);
                        $domFaq->save($xmlFaq);
                        $messaggio = "<div class='alert-success'>Domanda elevata a FAQ ufficiale con successo!</div>";
                    }
                }
            } else {
                $messaggio = "<div class='alert-danger'>Errore: impossibile recuperare la domanda originale.</div>";
            }
        } else {
            $messaggio = "<div class='alert-danger'>Seleziona una domanda dal menù e scrivi la risposta ufficiale.</div>";
        }
    }
}

// --- 2. LETTURA DEI DATI PER L'INTERFACCIA ---

// A) Leggiamo le FAQ esistenti
$elencoFaq = [];
if (file_exists($xmlFaq)) {
    $domFaq = new DOMDocument();
    $domFaq->preserveWhiteSpace = false;
    if ($domFaq->load($xmlFaq)) {
        $nodiFaq = $domFaq->getElementsByTagName('faq');
        foreach ($nodiFaq as $nodo) {
            if ($nodo instanceof DOMElement) {
                $elencoFaq[] = [
                    'id' => $nodo->getAttribute('id'),
                    'provenienza' => $nodo->getAttribute('provenienza'),
                    'domanda' => $nodo->getElementsByTagName('domanda')->item(0)->nodeValue ?? '',
                    'risposta' => $nodo->getElementsByTagName('risposta')->item(0)->nodeValue ?? '',
                    'autore' => $nodo->getElementsByTagName('autore_inserimento')->item(0)->nodeValue ?? '',
                    'data' => $nodo->getElementsByTagName('data_pubblicazione')->item(0)->nodeValue ?? '-'
                ];
            }
        }
    }
}

// B) Leggiamo le domande dalla community (da elevare) tramite la tua query XPath!
$domandeCommunity = [];
if (file_exists($xmlCommunity)) {
    $domComm = new DOMDocument();
    $domComm->preserveWhiteSpace = false;
    if ($domComm->load($xmlCommunity)) {
        $xpathComm = new DOMXPath($domComm);
        $nodiDomande = $xpathComm->query("//contributo[@tipo='domanda']");

        if ($nodiDomande !== false) {
            foreach ($nodiDomande as $nodo) {
                if ($nodo instanceof DOMElement) {
                    $domandeCommunity[] = [
                        'id_post' => $nodo->getAttribute('id_post'),
                        'testo' => $nodo->getElementsByTagName('testo')->item(0)->nodeValue ?? 'Domanda senza testo'
                    ];
                }
            }
        }
    }
}
?>



<div class="card">
    <h1>📚 Gestione FAQ Ufficiali</h1>
    <p style="color: #666;">Inserisci nuove FAQ manualmente o "eleva" le domande più interessanti poste dai clienti nella community.</p>
</div>

<?= $messaggio ?>

<div class="grid-faq">
    <!-- Blocco: INSERIMENTO MANUALE -->
    <div class="card">
        <h2 style="margin-bottom: 15px; font-size: 18px;">✍️ Inserimento Manuale</h2>
        <form method="POST" action="admin_faq.php">
            <input type="hidden" name="azione" value="inserisci_manuale">
            <div class="form-group">
                <label>Testo della Domanda:</label>
                <input type="text" name="domanda" placeholder="Es. Serve il certificato medico?" required>
            </div>
            <div class="form-group">
                <label>Testo della Risposta:</label>
                <textarea name="risposta" rows="4" placeholder="Es. Sì, è obbligatorio..." required></textarea>
            </div>
            <button type="submit" class="btn-submit" style="background-color: #28a745;">Salva FAQ Manuale</button>
        </form>
    </div>

    <!-- Blocco: ELEVAZIONE -->
    <div class="card">
        <h2 style="margin-bottom: 15px; font-size: 18px;">🚀 Eleva dalla Community</h2>
        <form method="POST" action="admin_faq.php">
            <input type="hidden" name="azione" value="eleva_domanda">
            <div class="form-group">
                <label>Scegli una Domanda Utente:</label>
                <select name="id_post_origine" required>
                    <option value="">-- Seleziona una domanda --</option>
                    <?php foreach ($domandeCommunity as $dc): ?>
                        <option value="<?= htmlspecialchars($dc['id_post']) ?>">
                            <?= htmlspecialchars(mb_substr($dc['testo'], 0, 50)) ?>... (<?= $dc['id_post'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Risposta Ufficiale (Nuova o copiata dagli utenti):</label>
                <textarea name="risposta_elevata" rows="4" placeholder="Scrivi la risposta definitiva per questa domanda..." required></textarea>
            </div>
            <button type="submit" class="btn-submit" style="background-color: #ffc107; color: black;">Eleva a FAQ</button>
        </form>
    </div>
</div>

<!-- Tabella Riassuntiva FAQ Esistenti -->
<div class="card">
    <h2>Archivio FAQ Pubblicate</h2>
    <?php if (empty($elencoFaq)): ?>
        <p style="color: #888;">Non ci sono FAQ nel database.</p>
    <?php else: ?>
        <table class="tabella-faq">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Provenienza</th>
                    <th>Domanda</th>
                    <th>Autore</th>
                    <th>Data Pubblicazione</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($elencoFaq as $f): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($f['id']) ?></code></td>
                        <td>
                            <span class="badge-prov <?= 'badge-' . htmlspecialchars($f['provenienza']) ?>">
                                <?= htmlspecialchars($f['provenienza']) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($f['domanda']) ?></strong><br>
                            <span style="font-size: 13px; color: #555;"><?= htmlspecialchars($f['risposta']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($f['autore']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($f['data']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</div>
</body>

</html>
<?php
// admin_faq.php - Pannello di Gestione FAQ Ufficiali per Admin e Gestori con possibilità di inserimento manuale o elevazione da domande della community
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';
$offerte = caricaOfferteCatalogo();

// Mappatura comoda id_corso => titolo del corso dal catalogo
$mappaCorsi = [];
foreach ($offerte as $o) {
    if (isset($o['id'])) {
        $mappaCorsi[$o['id']] = $o['titolo'];
    }
}

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
                    if (!empty($_POST['etichetta_corso'])) {
                        $nuovaFaq->appendChild($dom->createElement('etichetta_corso', $_POST['etichetta_corso']));
                    }
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
    elseif ($azione === 'eleva_domanda') {
        $idPostOrigine = $_POST['id_post_origine'] ?? '';
        $testoRisposta = trim($_POST['risposta_elevata'] ?? '');

        if (!empty($idPostOrigine) && !empty($testoRisposta)) {
            // Step B1: Recuperiamo testo e id_corso originale da community.xml
            $testoDomanda = '';
            $idCorsoOrigine = '';

            if (file_exists($xmlCommunity)) {
                $domComm = new DOMDocument();
                $domComm->preserveWhiteSpace = false;
                if ($domComm->load($xmlCommunity)) {
                    $xpathComm = new DOMXPath($domComm);
                    // Cerchiamo il contributo esatto tramite id_post
                    $nodoPost = $xpathComm->query("//contributo[@id_post='$idPostOrigine']")->item(0);
                    if ($nodoPost instanceof DOMElement) {
                        $idCorsoOrigine = $nodoPost->getAttribute('id_corso');
                        $nodoTesto = $nodoPost->getElementsByTagName('testo')->item(0);
                        if ($nodoTesto) {
                            $testoDomanda = $nodoTesto->nodeValue;
                        }
                    }
                }
            }

            // Step B2: Determiniamo l'etichetta del corso corretta in base al catalogo
            $etichettaCorso = 'Generale';
            if (!empty($idCorsoOrigine) && isset($mappaCorsi[$idCorsoOrigine])) {
                $etichettaCorso = $mappaCorsi[$idCorsoOrigine];
            }

            // Step B3: Salviamo in faq.xml
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
                        $nuovaFaq->appendChild($domFaq->createElement('etichetta_corso', $etichettaCorso));
                        $nuovaFaq->appendChild($domFaq->createElement('risposta', $testoRisposta));
                        $nuovaFaq->appendChild($domFaq->createElement('id_post_origine', $idPostOrigine));
                        $nuovaFaq->appendChild($domFaq->createElement('autore_inserimento', getRuolo()));
                        $nuovaFaq->appendChild($domFaq->createElement('data_pubblicazione', date('Y-m-d')));

                        $root->appendChild($nuovaFaq);
                        $domFaq->save($xmlFaq);
                        $messaggio = "<div class='alert-success'>Domanda elevata a FAQ ufficiale per il corso \"" . htmlspecialchars($etichettaCorso) . "\" con successo!</div>";
                    }
                }
            } else {
                $messaggio = "<div class='alert-danger'>Errore: impossibile recuperare la domanda originale.</div>";
            }
        } else {
            $messaggio = "<div class='alert-danger'>Seleziona una domanda dal menù e scrivi la risposta ufficiale.</div>";
        }
    }

    // C) ELIMINAZIONE FAQ
    elseif ($azione === 'elimina_faq') {
        $idFaq = $_POST['id_faq'] ?? '';
        if (!empty($idFaq) && file_exists($xmlFaq)) {
            $domFaq = new DOMDocument();
            $domFaq->preserveWhiteSpace = false;
            $domFaq->formatOutput = true;
            $domFaq->load($xmlFaq);
            $xpathFaq = new DOMXPath($domFaq); // <--- Istanza XPath

            $nodoFaq = $xpathFaq->query("//faq[@id='$idFaq']")->item(0);
            if ($nodoFaq) {
                $nodoFaq->parentNode->removeChild($nodoFaq);
                $domFaq->save($xmlFaq);
                $messaggio = "<div class='alert-success'>FAQ eliminata con successo!</div>";
            } else {
                $messaggio = "<div class='alert-danger'>FAQ non trovata.</div>";
            }
        } else {
            $messaggio = "<div class='alert-danger'>Errore: ID FAQ non valido o file XML mancante.</div>";
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
                    'etichetta_corso' => $nodo->getElementsByTagName('etichetta_corso')->item(0)->nodeValue ?? 'Generale',
                    'autore' => $nodo->getElementsByTagName('autore_inserimento')->item(0)->nodeValue ?? '',
                    'data' => $nodo->getElementsByTagName('data_pubblicazione')->item(0)->nodeValue ?? '-'
                ];
            }
        }
    }
}

// B) Leggiamo le domande dalla community (da elevare) tramite XPath
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
                    $idCorso = $nodo->getAttribute('id_corso');
                    $nomeCorso = $mappaCorsi[$idCorso] ?? 'Generale';

                    $domandeCommunity[] = [
                        'id_post' => $nodo->getAttribute('id_post'),
                        'id_corso' => $idCorso,
                        'nome_corso' => $nomeCorso,
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
                <label>Etichetta Corso (Opzionale):</label>
                <select name="etichetta_corso">
                    <option value="Generale">Interesse Generale</option>
                    <?php foreach ($offerte as $o): ?>
                        <option value="<?= htmlspecialchars($o['titolo']) ?>"><?= htmlspecialchars($o['titolo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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

    <!-- Blocco: ELEVAZIONE DA COMMUNITY -->
    <div class="card">
        <h2 style="margin-bottom: 15px; font-size: 18px;">🚀 Eleva dalla Community</h2>
        <form method="POST" action="admin_faq.php">
            <input type="hidden" name="azione" value="eleva_domanda">
            <div class="form-group">
                <label>Scegli una Domanda Utente (il corso verrà associato automaticamente):</label>
                <select name="id_post_origine" required>
                    <option value="">-- Seleziona una domanda --</option>
                    <?php foreach ($domandeCommunity as $dc): ?>
                        <option value="<?= htmlspecialchars($dc['id_post']) ?>">
                            [<?= htmlspecialchars($dc['nome_corso']) ?>] <?= htmlspecialchars(mb_substr($dc['testo'], 0, 45)) ?>... (<?= $dc['id_post'] ?>)
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
                    <th>Corso / Ambito</th>
                    <th>Provenienza</th>
                    <th>Domanda e Risposta</th>
                    <th>Autore</th>
                    <th>Data Pubblicazione</th>
                    <th>Azione</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($elencoFaq as $f): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($f['id']) ?></code></td>
                        <td><strong><?= htmlspecialchars($f['etichetta_corso']) ?></strong></td>
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

                        <td>
                            <form method="POST" action="admin_faq.php">
                                <input type="hidden" name="azione" value="elimina_faq">
                                <input type="hidden" name="id_faq" value="<?= htmlspecialchars($f['id']) ?>">
                                <button type="submit" onclick="return confirm('Sei sicuro di voler eliminare questa FAQ?');" class="btn-elimina">Elimina</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</div>
</body>

</html>
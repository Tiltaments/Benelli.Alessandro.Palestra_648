<?php
// admin_faq.php - Pannello di Gestione FAQ Ufficiali per Admin e Gestori
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/catalogo_service.php';

// SICUREZZA: Solo Admin e Gestori possono accedere a questa pagina
richiediRuolo(['admin', 'gestore']);

$messaggio = '';
$xmlFaq = __DIR__ . '/data/faq.xml';
$offerte = caricaOfferteCatalogo('default');

// Mappatura id_corso => Titolo
$mappaCorsi = [];
foreach ($offerte as $o) {
    if (isset($o['id'])) {
        $mappaCorsi[$o['id']] = $o['titolo'];
    }
}

// GESTIONE AZIONI POST (INSERIMENTO MANUALE ED ELIMINAZIONE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    // A) INSERIMENTO MANUALE FAQ
    if ($azione === 'inserisci_manuale') {
        $testoDomanda   = trim($_POST['domanda'] ?? '');
        $testoRisposta  = trim($_POST['risposta'] ?? '');
        $etichettaCorso = trim($_POST['etichetta_corso'] ?? 'Generale');

        if (!empty($testoDomanda) && !empty($testoRisposta) && file_exists($xmlFaq)) {
            $domFaq = new DOMDocument();
            $domFaq->preserveWhiteSpace = false;
            $domFaq->formatOutput = true;

            if ($domFaq->load($xmlFaq)) {
                $root = $domFaq->getElementsByTagName('archivio_faq')->item(0);
                if ($root) {
                    $nuovaFaq = $domFaq->createElement('faq');
                    $nuovaFaq->setAttribute('id', 'FAQ' . time());
                    $nuovaFaq->setAttribute('provenienza', 'manuale');


                    $nuovaFaq->appendChild($domFaq->createElement('domanda', htmlspecialchars($testoDomanda)));
                    if (!empty($etichettaCorso)) {
                        $nuovaFaq->appendChild($domFaq->createElement('etichetta_corso', htmlspecialchars($etichettaCorso)));
                    }
                    $nuovaFaq->appendChild($domFaq->createElement('risposta', htmlspecialchars($testoRisposta)));
                    $nuovaFaq->appendChild($domFaq->createElement('autore_inserimento', getRuolo()));
                    $nuovaFaq->appendChild($domFaq->createElement('data_pubblicazione', date('Y-m-d')));

                    $root->appendChild($nuovaFaq);


                    if (@$domFaq->validate()) {
                        $domFaq->save($xmlFaq);
                        $messaggio = "<div class='alert alert-success' style='background:#d4edda; color:#155724; padding:12px; border-radius:4px; margin-bottom:15px;'>FAQ manuale inserita e validata con successo rispetto a faq.dtd!</div>";
                    } else {
                        $messaggio = "<div class='alert alert-danger' style='background:#f8d7da; color:#721c24; padding:12px; border-radius:4px; margin-bottom:15px;'>Errore di validazione DTD durante l'inserimento della FAQ.</div>";
                    }
                }
            }
        } else {
            $messaggio = "<div class='alert alert-danger' style='background:#f8d7da; color:#721c24; padding:12px; border-radius:4px; margin-bottom:15px;'>Compila sia la domanda che la risposta.</div>";
        }
    }

    // B) ELIMINAZIONE FAQ
    elseif ($azione === 'elimina_faq') {
        $idFaq = $_POST['id_faq'] ?? '';
        if (!empty($idFaq) && file_exists($xmlFaq)) {
            $domFaq = new DOMDocument();
            $domFaq->preserveWhiteSpace = false;
            $domFaq->formatOutput = true;

            if ($domFaq->load($xmlFaq)) {
                $xpathFaq = new DOMXPath($domFaq);
                $nodoFaq = $xpathFaq->query("//faq[@id='$idFaq']")->item(0);

                if ($nodoFaq instanceof DOMElement && $nodoFaq->parentNode) {
                    $nodoFaq->parentNode->removeChild($nodoFaq);
                    $domFaq->save($xmlFaq);
                    $messaggio = "<div class='alert alert-success' style='background:#d4edda; color:#155724; padding:12px; border-radius:4px; margin-bottom:15px;'>FAQ eliminata con successo!</div>";
                } else {
                    $messaggio = "<div class='alert alert-danger' style='background:#f8d7da; color:#721c24; padding:12px; border-radius:4px; margin-bottom:15px;'>FAQ non trovata.</div>";
                }
            }
        }
    }
}


// LETTURA DELLE FAQ ESISTENTI 
$elencoFaq = [];
if (file_exists($xmlFaq)) {
    $domFaq = new DOMDocument();
    $domFaq->preserveWhiteSpace = false;
    if ($domFaq->load($xmlFaq)) {
        $nodiFaq = $domFaq->getElementsByTagName('faq');
        foreach ($nodiFaq as $nodo) {
            if ($nodo instanceof DOMElement) {
                $elencoFaq[] = [
                    'id'              => $nodo->getAttribute('id'),
                    'provenienza'     => $nodo->getAttribute('provenienza'),
                    'domanda'         => $nodo->getElementsByTagName('domanda')->item(0)->nodeValue ?? '',
                    'risposta'        => $nodo->getElementsByTagName('risposta')->item(0)->nodeValue ?? '',
                    'etichetta_corso' => $nodo->getElementsByTagName('etichetta_corso')->item(0)->nodeValue ?? 'Generale',
                    'autore'          => $nodo->getElementsByTagName('autore_inserimento')->item(0)->nodeValue ?? '',
                    'data'            => $nodo->getElementsByTagName('data_pubblicazione')->item(0)->nodeValue ?? '-'
                ];
            }
        }
    }
}
?>

<div class="card" style="border-bottom: 4px solid #f59e0b;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1>📚 Gestione Archivio FAQ Ufficiali</h1>
            <p style="color: #666; margin-top: 5px;">
                Archivio delle risposte ufficiali del centro fitness. Puoi creare FAQ manuali o gestire le domande degli iscritti.
            </p>
        </div>
        <a href="admin_domande.php" class="btn" style="background-color: #2563eb; font-size: 13px; padding: 10px 16px;">
            💬 Vai a Gestione Domande &amp; Risposte Corsi &rarr;
        </a>
    </div>
</div>

<?= $messaggio ?>

<!-- SEZIONE INSERIMENTO NUOVA FAQ MANUALE -->
<div class="card" style="margin-bottom: 25px;">
    <h2>➕ Inserimento Nuova FAQ Ufficiale (Manuale)</h2>
    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">
        Compila i campi sottostanti per pubblicare una nuova risposta ufficiale visibile nella pagina pubblica delle FAQ.
    </p>

    <form method="POST" action="admin_faq.php">
        <input type="hidden" name="azione" value="inserisci_manuale">

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Ambito / Corso di Riferimento:</label>
            <select name="etichetta_corso" style="width: 100%; padding: 8px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px;">
                <option value="Generale">Informazione Generale (Non legata a un singolo corso)</option>
                <?php foreach ($mappaCorsi as $idC => $titC): ?>
                    <option value="<?= htmlspecialchars($titC) ?>">Corso: <?= htmlspecialchars($titC) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Domanda Ufficiale:</label>
            <input type="text" name="domanda" placeholder="Es. Quali documenti sono necessari per l'iscrizione?" required style="width: 100%; padding: 8px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px; box-sizing: border-box;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; font-size: 13px; margin-bottom: 4px;">Risposta Ufficiale:</label>
            <textarea name="risposta" rows="3" placeholder="Scrivi la risposta accurata e definitiva fornita dalla palestra..." required style="width: 100%; padding: 8px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px; font-family: inherit; box-sizing: border-box;"></textarea>
        </div>

        <button type="submit" class="btn" style="border: none; cursor: pointer; padding: 10px 20px; font-size: 14px; background-color: #10b981;">
            Pubblica FAQ Ufficiale
        </button>
    </form>
</div>

<!-- TABELLA DELLE FAQ PUBBLICATE -->
<div class="card">
    <h2 style="margin-bottom: 15px;">Elenco FAQ Attualmente Pubblicate (<?= count($elencoFaq) ?>)</h2>

    <?php if (empty($elencoFaq)): ?>
        <p style="color: #888; font-style: italic;">Non ci sono FAQ nel database.</p>
    <?php else: ?>
        <table class="user-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background: #f1f5f9; text-align: left;">
                    <th style="padding: 10px; border: 1px solid #cbd5e1;">Ambito / Corso</th>
                    <th style="padding: 10px; border: 1px solid #cbd5e1;">Provenienza</th>
                    <th style="padding: 10px; border: 1px solid #cbd5e1;">Domanda &amp; Risposta</th>
                    <th style="padding: 10px; border: 1px solid #cbd5e1;">Autore</th>
                    <th style="padding: 10px; border: 1px solid #cbd5e1;">Data</th>
                    <th style="padding: 10px; border: 1px solid #cbd5e1; text-align: center;">Azione</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($elencoFaq as $f): ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: bold; color: #1e293b;">
                            <?= htmlspecialchars($f['etichetta_corso']) ?>
                        </td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0;">
                            <?php if ($f['provenienza'] === 'elevata'): ?>
                                <span style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                    ⭐ Elevata da Domanda
                                </span>
                            <?php else: ?>
                                <span style="background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                    Manuale
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0;">
                            <div style="font-weight: bold; color: #0284c7; margin-bottom: 4px;">
                                Q: <?= htmlspecialchars($f['domanda']) ?>
                            </div>
                            <div style="color: #334155; line-height: 1.4;">
                                A: <?= htmlspecialchars($f['risposta']) ?>
                            </div>
                        </td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; text-transform: capitalize; color: #64748b;">
                            <?= htmlspecialchars($f['autore']) ?>
                        </td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; color: #64748b; font-size: 12px;">
                            <?= htmlspecialchars($f['data']) ?>
                        </td>
                        <td style="padding: 10px; border: 1px solid #e2e8f0; text-align: center;">
                            <form method="POST" action="admin_faq.php" style="margin: 0;">
                                <input type="hidden" name="azione" value="elimina_faq">
                                <input type="hidden" name="id_faq" value="<?= htmlspecialchars($f['id']) ?>">
                                <button type="submit" onclick="return confirm('Vuoi davvero eliminare questa FAQ?');" style="background: #ef4444; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 12px; cursor: pointer;">
                                    Elimina
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
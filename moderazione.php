<?php
// moderazione.php - Pannello di Moderazione della Community per Gestori e Admin con gestione XML dei contributi
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

// SICUREZZA: Solo Gestore e Admin possono moderare la community
richiediRuolo(['gestore', 'admin']);

$messaggio = '';
$xmlCommunity = __DIR__ . '/data/community.xml';

// --- 1. GESTIONE AZIONI DI MODERAZIONE (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['azione'])) {
    $azione = $_POST['azione'];

    if ($azione === 'elimina_post' && isset($_POST['id_post'])) {
        $idPost = $_POST['id_post'];

        if (file_exists($xmlCommunity)) {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if ($dom->load($xmlCommunity)) {
                $xpath = new DOMXPath($dom);
                $nodiTrovati = $xpath->query("//contributo[@id_post='$idPost']");

                if ($nodiTrovati !== false && $nodiTrovati->length > 0) {
                    $nodoDaEliminare = $nodiTrovati->item(0);

                    if ($nodoDaEliminare instanceof DOMElement && $nodoDaEliminare->parentNode) {
                        // Rimuoviamo il nodo figlio dal genitore (community)
                        $nodoDaEliminare->parentNode->removeChild($nodoDaEliminare);
                        $dom->save($xmlCommunity);
                        $messaggio = "<div class='alert-success'>Contributo moderato ed eliminato con successo.</div>";
                    }
                }
            }
        }
    }
}

// --- 2. LETTURA DI TUTTI I CONTRIBUTI DELLA COMMUNITY ---
$tuttiIContributi = [];
if (file_exists($xmlCommunity)) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    if ($dom->load($xmlCommunity)) {
        $xpath = new DOMXPath($dom);
        $nodiPost = $xpath->query("//contributo");

        if ($nodiPost !== false) {
            foreach ($nodiPost as $p) {
                if ($p instanceof DOMElement) {
                    $tuttiIContributi[] = [
                        'id_post' => $p->getAttribute('id_post'),
                        'id_corso' => $p->getAttribute('id_corso'),
                        'tipo' => $p->getAttribute('tipo'),
                        'acquisto_verificato' => $p->getAttribute('acquisto_verificato') === 'true',
                        'id_autore' => $p->getElementsByTagName('id_autore')->item(0)->nodeValue ?? 'N/D',
                        'testo' => $p->getElementsByTagName('testo')->item(0)->nodeValue ?? '',
                        'data' => $p->getElementsByTagName('data_creazione')->item(0)->nodeValue ?? ''
                    ];
                }
            }
        }
    }
}
?>



<div class="card">
    <h1>🛡️ Pannello Gestore: Moderazione Community</h1>
    <p style="color: #666;">Ispeziona le recensioni e le domande pubblicate dagli utenti ed elimina i contenuti non conformi.</p>
</div>

<?= $messaggio ?>

<div class="card">
    <h2>Elenco Contributi Utenti</h2>
    <?php if (empty($tuttiIContributi)): ?>
        <p style="color: #888; margin-top: 15px;">Nessun contributo pubblicato al momento nella community.</p>
    <?php else: ?>
        <table class="tabella-moderazione">
            <thead>
                <tr>
                    <th>ID Post</th>
                    <th>Corso (ID)</th>
                    <th>Autore (ID)</th>
                    <th>Tipologia & Stato</th>
                    <th>Testo del Contributo</th>
                    <th>Data</th>
                    <th>Azione</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tuttiIContributi as $c): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($c['id_post']) ?></code></td>
                        <td><strong><?= htmlspecialchars($c['id_corso']) ?></strong></td>
                        <td>Utente #<?= htmlspecialchars($c['id_autore']) ?></td>
                        <td>
                            <span class="badge-tipo"><?= htmlspecialchars($c['tipo']) ?></span><br><br>
                            <?php if ($c['acquisto_verificato']): ?>
                                <span class="badge-verificato">✔️ Verificato</span>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #888;">Non verificato</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width: 300px; word-break: break-word;"><?= htmlspecialchars($c['testo']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['data']))) ?></td>
                        <td>
                            <form method="POST" action="moderazione.php" onsubmit="return confirm('Vuoi davvero ELIMINARE questo contributo?');">
                                <input type="hidden" name="azione" value="elimina_post">
                                <input type="hidden" name="id_post" value="<?= htmlspecialchars($c['id_post']) ?>">
                                <button type="submit" class="btn-elimina">🗑️ Elimina</button>
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
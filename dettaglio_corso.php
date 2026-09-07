<?php
// dettaglio_corso.php 
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

$idOfferta = $_GET['id'] ?? '';
$xmlCatalogo = __DIR__ . '/data/catalogo.xml';

$corso = null;
$errore = '';
$messaggioCommunity = '';

// BLOCCATO: Non salviamo i post né i voti
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && getRuolo() === 'cliente') {
    $messaggioCommunity = "<div class='alert-danger'><strong>🚧 Lavori in Corso:</strong> L'algoritmo di salvataggio recensioni e calcolo reputazione sarà rilasciato nel prossimo step.</div>";
}

// LETTURA DEL CORSO 
if (!empty($idOfferta) && file_exists($xmlCatalogo)) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    if ($dom->load($xmlCatalogo)) {
        $xpath = new DOMXPath($dom);
        $nodi = $xpath->query("//offerta[@id='$idOfferta']");
        if ($nodi !== false && $nodi->length > 0) {
            $nodo = $nodi->item(0);
            if ($nodo instanceof DOMElement) {
                $prezzoBase = (int)($nodo->getElementsByTagName('prezzo_crediti')->item(0)->nodeValue ?? 0);
                $corso = [
                    'id' => $idOfferta,
                    'categoria' => $nodo->getAttribute('categoria'),
                    'titolo' => $nodo->getElementsByTagName('titolo')->item(0)->nodeValue ?? 'Senza Titolo',
                    'descrizione' => $nodo->getElementsByTagName('descrizione')->item(0)->nodeValue ?? '',
                    'trainer' => $nodo->getElementsByTagName('trainer')->item(0)->nodeValue ?? 'N/D',
                    'intensita' => $nodo->getElementsByTagName('intensita')->item(0)->nodeValue ?? 'N/D',
                    'prezzo_base' => $prezzoBase,
                    'prezzo_finale' => $prezzoBase,
                    'sconto' => 0
                ];
            }
        } else {
            $errore = "Il corso richiesto non è presente a catalogo.";
        }
    }
}
$contributiCorso = []; // Disattiviamo la lettura dei commenti per ora
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
                    <span class="badge badge-sconto">-<?= $corso['sconto'] ?>% PROMO ATTIVA</span>
                <?php endif; ?>
                <h1 style="font-size: 36px; margin-bottom: 15px;"><?= htmlspecialchars($corso['titolo']) ?></h1>
                <p style="font-size: 16px; line-height: 1.8; color: #444; margin-bottom: 25px;">
                    <?= htmlspecialchars($corso['descrizione']) ?>
                </p>
                <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
                <h2>Specifiche Tecniche</h2>
                <ul style="list-style-type: none; padding: 0; margin-top: 10px;">
                    <li style="margin-bottom: 8px;"><strong>Trainer:</strong> <?= htmlspecialchars($corso['trainer']) ?></li>
                    <li style="margin-bottom: 8px;"><strong>Intensità:</strong> <span style="text-transform: capitalize;"><?= htmlspecialchars($corso['intensita']) ?></span></li>
                </ul>
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
                    <form action="carrello.php" method="POST">
                        <input type="hidden" name="id_offerta" value="<?= htmlspecialchars($corso['id']) ?>">
                        <button type="submit" class="btn-azione btn-acquista">Aggiungi al Carrello 🛒</button>
                    </form>
                <?php elseif (getRuolo() === 'visitatore'): ?>
                    <a href="login.php" class="btn-azione btn-login">Accedi per Acquistare 🔑</a>
                <?php else: ?>
                    <p style="color: #666; font-size: 14px; margin-top: 15px;">Solo i clienti possono acquistare questo corso.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- SEZIONE COMMUNITY & FEEDBACK -->
<div class="community-box">
    <h2>💬 Community & Recensioni sul Corso</h2>
    <p style="color: #666; margin-bottom: 20px;">Leggi le recensioni degli altri iscritti o poni una domanda tecnica.</p>

    <?= $messaggioCommunity ?>

    <!-- Form per scrivere un post (Solo per clienti) -->
    <?php if (isLoggedIn() && getRuolo() === 'cliente'): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px;">
            <h3 style="font-size: 16px; margin-bottom: 10px;">✍️ Lascia una recensione o fai una domanda</h3>
            <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>">
                <input type="hidden" name="azione_community" value="nuovo_contributo">
                <div class="form-group">
                    <label>Tipologia Contributo:</label>
                    <select name="tipo" required>
                        <option value="" disabled selected>-- Seleziona tipologia --</option>
                        <option value="recensione">Recensione</option>
                        <option value="domanda">Domanda</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Testo:</label>
                    <textarea name="testo" rows="3" placeholder="Scrivi qui il tuo pensiero o il tuo dubbio..." required></textarea>
                </div>
                <button type="submit" style="background: #007bff; color: white; border: none; padding: 10px 15px; border-radius: 4px; font-weight: bold; cursor: pointer;">Pubblica Contributo</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Lista Post Esistenti -->
    <?php if (empty($contributiCorso)): ?>
        <p style="color: #888;">Non ci sono ancora contributi per questo corso. Sii il primo a scriverne uno!</p>
    <?php else: ?>
        <?php foreach ($contributiCorso as $post): ?>
            <div class="post-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <div>
                        <span style="text-transform: uppercase; font-size: 11px; font-weight: bold; background: #eef2f7; padding: 2px 6px; border-radius: 3px;"><?= htmlspecialchars($post['tipo']) ?></span>
                        <?php if ($post['acquisto_verificato']): ?>
                            <span class="badge-verificato">✔️ Acquisto Verificato</span>
                        <?php endif; ?>
                    </div>
                    <span style="font-size: 12px; color: #888;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($post['data']))) ?></span>
                </div>
                <p style="color: #333; margin-bottom: 10px;"><?= htmlspecialchars($post['testo']) ?></p>

                <div style="font-size: 12px; color: #555; margin-bottom: 10px;">
                    ⭐ Media Supporto: <strong><?= $post['media_supporto'] ?> / 3</strong> |
                    💡 Media Utilità: <strong><?= $post['media_utilita'] ?> / 5</strong>
                    <span style="color: #888;">(<?= $post['tot_voti'] ?> voti)</span>
                </div>

                <!-- Form per votare il post (Solo clienti loggati) -->
                <?php if (isLoggedIn() && getRuolo() === 'cliente'): ?>
                    <form method="POST" action="dettaglio_corso.php?id=<?= htmlspecialchars($idOfferta) ?>" style="display: flex; gap: 10px; align-items: center; background: #f1f3f5; padding: 8px; border-radius: 4px;">
                        <input type="hidden" name="azione_community" value="vota_post">
                        <input type="hidden" name="id_post" value="<?= htmlspecialchars($post['id_post']) ?>">
                        <span style="font-size: 12px; font-weight: bold;">Valuta:</span>
                        <label style="font-size: 12px;">Supporto (1-3):</label>
                        <select name="supporto" style="padding: 3px; font-size: 12px;" required>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                        <label style="font-size: 12px;">Utilità (1-5):</label>
                        <select name="utilita" style="padding: 3px; font-size: 12px;" required>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                        <button type="submit" style="background: #343a40; color: white; border: none; padding: 4px 10px; border-radius: 3px; font-size: 12px; cursor: pointer;">Invia Voto</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</div>
</body>

</html>
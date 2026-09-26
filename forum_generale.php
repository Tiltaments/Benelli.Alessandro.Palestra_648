<?php
// forum_generale.php - Pagina del Forum Generale della Palestra 648 con gestione dei post generali e interazione con la community
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

$messaggioCommunity = '';

// BLOCCATO: Intercettiamo il POST per l'inserimento di nuovi contributi generali
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messaggioCommunity = "<div class='alert-wip'><strong>🚧 Lavori in Corso:</strong> L'inserimento di nuovi post nel Forum Generale sarà sbloccato nel prossimo step.</div>";
}

// Simuliamo la lettura dei post generali da community.xml
$postGenerali = [];
$xmlCommunity = __DIR__ . '/data/community.xml';

if (file_exists($xmlCommunity)) {
    $domComm = new DOMDocument();
    $domComm->preserveWhiteSpace = false;
    if ($domComm->load($xmlCommunity)) {
        $xpathComm = new DOMXPath($domComm);
        // Peschiamo solo i contributi che non sono legati a un corso specifico
        $nodi = $xpathComm->query("//contributo[@id_corso='generale']");

        foreach ($nodi as $nodo) {
            if ($nodo instanceof DOMElement) {
                $postGenerali[] = [
                    'id_post' => $nodo->getAttribute('id_post'),
                    'tipo' => $nodo->getAttribute('tipo'),
                    'testo' => $nodo->getElementsByTagName('testo')->item(0)->nodeValue ?? '',
                    'data' => $nodo->getElementsByTagName('data_creazione')->item(0)->nodeValue ?? '',
                    'autore' => $nodo->getElementsByTagName('id_autore')->item(0)->nodeValue ?? 'Utente'
                ];
            }
        }
    }
}
?>

<div class="card">
    <h1>🌍 Forum Generale Palestra 648</h1>
    <p style="color: #666;">Hai dubbi sugli orari, le policy della palestra o domande non legate a un corso specifico? Questo è il posto giusto!</p>

    <div style="margin-top: 15px;">
        <a href="faq.php" class="btn-azione" style="background-color: #17a2b8; display: inline-block; width: auto; padding: 8px 15px;">Torna alle FAQ Ufficiali</a>
    </div>
</div>

<div class="community-box">
    <?= $messaggioCommunity ?>

    <!-- Form per scrivere un post (Solo per clienti loggati) -->
    <?php if (isLoggedIn() && getRuolo() === 'cliente'): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #ccc;">
            <h3 style="font-size: 16px; margin-bottom: 10px;">✍️ Fai una domanda alla community o allo staff</h3>
            <form method="POST" action="forum_generale.php">
                <input type="hidden" name="azione_community" value="nuovo_contributo_generale">
                <div class="form-group">
                    <label>Testo della Domanda:</label>
                    <textarea name="testo" rows="4" placeholder="Scrivi qui la tua domanda generale..." required></textarea>
                </div>
                <button type="submit" class="btn-submit" style="background-color: #007bff;">Pubblica nel Forum</button>
            </form>
        </div>
    <?php elseif (getRuolo() === 'visitatore'): ?>
        <div class="alert alert-danger">Devi <a href="login.php">accedere come cliente</a> per poter pubblicare nel forum.</div>
    <?php endif; ?>

    <!-- Lista Post Generali -->
    <h2>Discussioni Recenti</h2>
    <?php if (empty($postGenerali)): ?>
        <p style="color: #888; padding: 15px 0;">Non ci sono ancora discussioni generali. Sii il primo a scriverne una!</p>
    <?php else: ?>
        <?php foreach ($postGenerali as $post): ?>
            <div class="post-card">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span class="badge-tipo"><?= htmlspecialchars($post['tipo']) ?></span>
                    <span style="font-size: 12px; color: #888;"><?= htmlspecialchars(date('d/m/Y', strtotime($post['data']))) ?></span>
                </div>
                <p style="color: #333; font-size: 15px;"><?= htmlspecialchars($post['testo']) ?></p>
                <div style="margin-top: 10px; font-size: 12px; color: #666;">
                    Autore: <strong>Utente #<?= htmlspecialchars($post['autore']) ?></strong>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
</body>

</html>
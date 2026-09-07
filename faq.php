<?php
// faq.php - Pagina pubblica delle FAQ
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

$elencoFaq = [];
$xmlFaq = __DIR__ . '/data/faq.xml';

// Lettura dal file XML
if (file_exists($xmlFaq)) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    if ($dom->load($xmlFaq)) {
        $nodiFaq = $dom->getElementsByTagName('faq');
        foreach ($nodiFaq as $nodo) {
            if ($nodo instanceof DOMElement) {
                $elencoFaq[] = [
                    'domanda' => $nodo->getElementsByTagName('domanda')->item(0)->nodeValue ?? '',
                    'risposta' => $nodo->getElementsByTagName('risposta')->item(0)->nodeValue ?? '',
                    'provenienza' => $nodo->getAttribute('provenienza')
                ];
            }
        }
    }
}
?>

<div class="card">
    <h1>💬 Domande Frequenti (FAQ)</h1>
    <p style="color: #666;">Trova le risposte alle domande più comuni poste dai nostri iscritti.</p>
</div>

<div class="card">
    <?php if (empty($elencoFaq)): ?>
        <p style="color: #888;">Nessuna FAQ disponibile al momento.</p>
    <?php else: ?>
        <?php foreach ($elencoFaq as $faq): ?>
            <div style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px;">
                <h2 style="color: #007bff; margin-bottom: 5px;">Q: <?= htmlspecialchars($faq['domanda']) ?></h2>
                <p style="color: #444; line-height: 1.6; margin-bottom: 10px;"><strong>A:</strong> <?= htmlspecialchars($faq['risposta']) ?></p>

                <?php if ($faq['provenienza'] === 'elevata'): ?>
                    <span style="font-size: 11px; background: #ffc107; padding: 3px 6px; border-radius: 4px; font-weight: bold;">🌟 Domanda dalla Community</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
</body>

</html>
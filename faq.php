<?php
// faq.php - Pagina delle Domande Frequenti (FAQ) con gestione XML e visualizzazione dinamica
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
            $elencoFaq[] = [
                'domanda' => $nodo->getElementsByTagName('domanda')->item(0)->nodeValue ?? '',
                'risposta' => $nodo->getElementsByTagName('risposta')->item(0)->nodeValue ?? '',
                'provenienza' => $nodo->getAttribute('provenienza'),
                // Pesca l'etichetta del corso se esiste (per FAQ elevate)
                'etichetta_corso' => $nodo->getElementsByTagName('etichetta_corso')->item(0)->nodeValue ?? 'Generale'
            ];
        }
    }
}
?>

<div class="card" style="border-bottom: 4px solid #ffc107;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1>📚 Domande Frequenti (FAQ)</h1>
            <p style="color: #666;">Trova le risposte ufficiali dello staff alle domande più comuni.</p>
        </div>
        <!-- Link al Forum Generale -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6; text-align: center;">
            <strong style="display: block; margin-bottom: 5px; font-size: 14px;">Non trovi la risposta?</strong>
            <a href="forum_generale.php" class="btn-submit" style="background-color: #28a745; text-decoration: none; display: inline-block; width: auto; font-size: 14px;">Vai al Forum Generale 💬</a>
        </div>
    </div>
</div>

<div class="card">
    <?php if (empty($elencoFaq)): ?>
        <p style="color: #888;">Nessuna FAQ disponibile al momento.</p>
    <?php else: ?>
        <?php foreach ($elencoFaq as $faq): ?>
            <div style="border-bottom: 1px solid #eee; padding-bottom: 20px; margin-bottom: 20px;">
                <!-- Etichetta del Corso -->
                <div style="margin-bottom: 10px;">
                    <?php if ($faq['etichetta_corso'] !== 'Generale'): ?>
                        <span style="background: #17a2b8; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                            Corso: <?= htmlspecialchars($faq['etichetta_corso']) ?>
                        </span>
                    <?php else: ?>
                        <span style="background: #6c757d; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                            Interesse Generale
                        </span>
                    <?php endif; ?>

                    <?php if ($faq['provenienza'] === 'elevata'): ?>
                        <span style="font-size: 11px; background: #ffc107; color: black; padding: 3px 8px; border-radius: 4px; font-weight: bold; margin-left: 5px;">
                            ⭐ Elevata dalla Community
                        </span>
                    <?php endif; ?>
                </div>

                <h2 style="color: #007bff; margin-bottom: 8px; font-size: 18px;">Q: <?= htmlspecialchars($faq['domanda']) ?></h2>
                <p style="color: #444; line-height: 1.6; margin-bottom: 0;"><strong>A:</strong> <?= htmlspecialchars($faq['risposta']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</div>
</body>

</html>
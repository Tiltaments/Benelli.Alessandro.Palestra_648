<?php
// gestione_catalogo.php - Pannello di Gestione Catalogo Corsi per Gestori e Admin
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

richiediRuolo(['gestore', 'admin']);
$messaggio = '';

// BLOCCATO: Intercettiamo il POST e blocchiamo la scrittura XML 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messaggio = "<div class='alert-wip'><strong>🚧 WORK IN PROGRESS:</strong> Salvataggio XML e funzioni di Disattivazione (Soft Delete) bloccate per la demo.</div>";
}

$datiModifica = null;

// Lettura dei corsi attivi e archiviati (Soft Delete)
$xmlCatalogo = __DIR__ . '/data/catalogo.xml';
$corsiAttivi = [];
$corsiArchiviati = [];

if (file_exists($xmlCatalogo)) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    if ($dom->load($xmlCatalogo)) {
        // Cerchiamo il tag 'corso'
        $nodi = $dom->getElementsByTagName('corso');
        foreach ($nodi as $nodo) {
            $id = $nodo->getAttribute('id');
            $titolo = $nodo->getElementsByTagName('titolo')->item(0)->nodeValue ?? 'Senza Titolo';
            $prezzo = $nodo->getElementsByTagName('prezzo_crediti')->item(0)->nodeValue ?? '0';
            $categoria = $nodo->getAttribute('categoria');
            $disponibile = $nodo->getElementsByTagName('disponibile')->item(0)->nodeValue ?? 'false';

            $corso = [
                'id' => $id,
                'titolo' => $titolo,
                'prezzo_base' => $prezzo,
                'categoria' => $categoria
            ];

            if ($disponibile === 'true') {
                $corsiAttivi[] = $corso;
            } else {
                $corsiArchiviati[] = $corso;
            }
        }
    }
}
?>

<div class="card">
    <h1>📋 Gestione Catalogo Corsi</h1>
    <p style="color: #666;">Aggiungi, modifica o disattiva i corsi. I corsi disattivati finiranno nell'archivio senza essere eliminati fisicamente.</p>
</div>

<?= $messaggio ?>

<div class="grid-catalogo">
    <!-- Colonna Sinistra: Form Inserimento/Modifica -->
    <div class="card" <?= $datiModifica ? 'style="border: 2px solid #ffc107;"' : '' ?>>
        <h2 style="margin-bottom: 15px; font-size: 18px;">
            <?= $datiModifica ? '✏️ Modifica Corso' : '➕ Nuovo Corso' ?>
        </h2>

        <form method="POST" action="gestione_catalogo.php">
            <input type="hidden" name="azione" value="<?= $datiModifica ? 'salva_modifica' : 'nuova_offerta' ?>">

            <div class="form-group">
                <label>Categoria</label>
                <select name="categoria" id="categoria" required>
                    <option value="" disabled selected>-- Seleziona una categoria --</option>
                    <option value="corso">Corso</option>
                    <option value="abbonamento">Abbonamento</option>
                    <option value="lezione_privata">Lezione Privata</option>
                </select>
            </div>

            <div class="form-group">
                <label>Titolo Corso/Servizio</label>
                <input type="text" name="titolo" required>
            </div>

            <div class="form-group">
                <label>Descrizione</label>
                <textarea name="descrizione" rows="3" required></textarea>
            </div>

            <div class="form-group">
                <label>Immagine Copertina (Percorso)</label>
                <input type="text" name="immagine" placeholder="Es. img/foto.jpg" required>
            </div>

            <!-- NUOVO: Inserimento Edizioni/Trainer Multipli -->
            <div class="form-group" style="background: #f8f9fa; padding: 10px; border-left: 4px solid #007bff; border-radius: 4px;">
                <label style="color: #007bff;">Trainer delle Edizioni</label>
                <input type="text" name="trainers" placeholder="Es. Marco Rossi, Sara Bianchi" required>
                <small style="color: #666; font-size: 11px; display: block; margin-top: 4px;">
                    Inserisci i nomi separati da virgola per creare automaticamente più edizioni (turni) dello stesso corso.
                </small>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Intensità</label>
                    <select name="intensita" id="intensita" required>
                        <option value="" disabled selected>-- Seleziona --</option>
                        <option value="bassa">Bassa</option>
                        <option value="media">Media</option>
                        <option value="alta">Alta</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Prezzo (Crediti)</label>
                    <input type="number" name="prezzo_crediti" min="1" required>
                </div>
            </div>

            <button type="submit" class="btn-submit">Salva nel Catalogo</button>
        </form>
    </div>

    <!-- Colonna Destra: Elenchi -->
    <div>
        <!-- Tabella Corsi Attivi -->
        <div class="card" style="margin-bottom: 25px;">
            <h2 style="margin-bottom: 15px; font-size: 18px; color: #28a745;">🟢 Corsi Disponibili</h2>
            <?php if (empty($corsiAttivi)): ?>
                <p style="color:#888; font-size:14px;">Nessun corso attivo presente nel catalogo.</p>
            <?php else: ?>
                <table class="tabella-offerte">
                    <thead>
                        <tr>
                            <th>Categoria & Titolo</th>
                            <th>Prezzo</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($corsiAttivi as $corso): ?>
                            <tr>
                                <td>
                                    <span class="badge-cat"><?= htmlspecialchars(str_replace('_', ' ', $corso['categoria'])) ?></span><br>
                                    <strong><?= htmlspecialchars($corso['titolo']) ?></strong>
                                </td>
                                <td style="font-weight: bold;"><?= htmlspecialchars($corso['prezzo_base']) ?> cr.</td>
                                <td>
                                    <form method="POST" action="gestione_catalogo.php" class="form-inline" onsubmit="return confirm('Disattivare questo corso? Finirà in archivio.');">
                                        <input type="hidden" name="azione" value="disattiva">
                                        <input type="hidden" name="id_offerta" value="<?= htmlspecialchars($corso['id']) ?>">
                                        <!-- Il tasto elimina ora fa Soft Delete -->
                                        <button type="submit" class="btn-azione btn-elimina" style="background-color: #dc3545;">Disattiva</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Tabella Corsi Archiviati (Soft Delete) -->
        <div class="card" style="background-color: #fcfcfc; border: 1px dashed #ccc;">
            <h2 style="margin-bottom: 15px; font-size: 18px; color: #6c757d;">📁 Archivio (Non Disponibili)</h2>
            <?php if (empty($corsiArchiviati)): ?>
                <p style="color:#888; font-size:14px;">L'archivio è vuoto.</p>
            <?php else: ?>
                <table class="tabella-offerte">
                    <thead>
                        <tr>
                            <th style="color:#666;">Titolo Archiviato</th>
                            <th style="color:#666;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($corsiArchiviati as $corso): ?>
                            <tr style="opacity: 0.7;">
                                <td><strong><?= htmlspecialchars($corso['titolo']) ?></strong></td>
                                <td>
                                    <form method="POST" action="gestione_catalogo.php" class="form-inline">
                                        <input type="hidden" name="azione" value="ripristina">
                                        <input type="hidden" name="id_offerta" value="<?= htmlspecialchars($corso['id']) ?>">
                                        <button type="submit" class="btn-azione" style="background-color: #28a745;">Ripristina</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</body>

</html>